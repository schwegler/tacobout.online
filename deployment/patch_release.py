"""Serialize green default-branch merges into immutable patch releases. Never install WordPress."""
import argparse
import json
import os
import re
import subprocess
import tempfile
import time
from pathlib import Path

from theme_release import Refused, SLUG, build, canonical, hex_value, load, require, sha, version
from update_metadata import generate

REQUIRED = {
    '.github/workflows/tests.yml': {'test', 'seasonal-placement', 'release-tooling', 'theme-artifact'},
    '.github/workflows/trove-cards.yml': {'browsers'},
    '.github/workflows/public-email.yml': {'contact'},
}
ASSETS = ('theme.zip', 'release.json', 'tacobout-update.json', 'release-source.json')
MINIMUM = (3, 9, 0)


def command(args, cwd=None, env=None, quiet=True):
    result = subprocess.run(args, cwd=cwd, env=env, check=False, text=True,
                            stdout=subprocess.PIPE if quiet else None, stderr=subprocess.PIPE if quiet else None)
    require(result.returncode == 0, 'Command failed: ' + args[0] + ' ' + args[1])
    return result.stdout.strip() if quiet else ''


def git(repo, *args, env=None):
    return command(['git', *args], repo, env)


def observed(read, ready, label, attempts=24, delay=3):
    """Retry only reads after a confirmed write; never repeat a mutation."""
    for attempt in range(attempts):
        value = read()
        if ready(value):
            return value
        if attempt + 1 < attempts:
            time.sleep(delay)
    raise Refused(label + '; confirmed writes are preserved for a later retry')


def numeric(tag):
    require(isinstance(tag, str) and re.fullmatch(r'v(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)', tag), 'Invalid stable release tag')
    return tuple(map(int, tag[1:].split('.')))


def next_patch(base, source_version, tags):
    require(base >= MINIMUM and base[:2] == source_version[:2], 'Publish the intended minor-version seed manually first')
    reserved = [numeric(tag)[2] for tag in tags if re.fullmatch(r'v(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)', tag)
                and numeric(tag)[:2] == base[:2]]
    return 'v%d.%d.%d' % (*base[:2], max([base[2], source_version[2], *reserved]) + 1)


def gate(runs, repo, branch, commit, jobs):
    """Require the newest push run for every configured workflow and every required job."""
    latest = {}
    pending = False
    for run in runs:
        path = run.get('path')
        if path not in REQUIRED or run.get('event') != 'push' or run.get('head_sha') != commit or run.get('head_branch') != branch:
            continue
        if run.get('head_repository', {}).get('full_name') != repo:
            continue
        pending = pending or run.get('status') != 'completed' or run.get('conclusion') is None
        def started(item):
            return (item.get('run_started_at') or item.get('created_at') or '', item['id'], item.get('run_attempt', 1))
        if path not in latest or started(run) > started(latest[path]):
            latest[path] = run
    if pending or set(latest) != set(REQUIRED):
        return 'pending', latest
    if any(r.get('conclusion') != 'success' for r in latest.values()):
        return 'failed', latest
    for path, run in latest.items():
        actual = {j['name']: j for j in jobs(run['id'])}
        if any(name not in actual or actual[name].get('status') != 'completed' or actual[name].get('conclusion') is None
               for name in REQUIRED[path]):
            return 'pending', latest
        if any(actual[name]['conclusion'] != 'success' for name in REQUIRED[path]):
            return 'failed', latest
    return 'ready', latest


def source_gate(api, branch, source):
    runs = api.pages('actions/runs?head_sha=' + source + '&event=push&branch=' + branch + '&per_page=100', 'workflow_runs')
    return gate(runs, api.repo, branch, source,
                lambda run_id: api.pages('actions/runs/' + str(run_id) + '/jobs?per_page=100', 'jobs'))


def merged_pr(pulls, repo, branch, commit):
    return any(p.get('merged_at') and p.get('merge_commit_sha') == commit and p.get('base', {}).get('ref') == branch
               and p.get('base', {}).get('repo', {}).get('full_name') == repo for p in pulls)


class GitHub:
    def __init__(self, repo):
        require(re.fullmatch(r'[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+', repo) is not None, 'Invalid repository identity')
        self.repo = repo

    def get(self, path):
        return json.loads(command(['gh', 'api', '-H', 'Cache-Control: no-cache', ('repos/' + self.repo + '/' + path).rstrip('/')]))

    def pages(self, path, key=None):
        stream = command(['gh', 'api', '--paginate', '-H', 'Cache-Control: no-cache', 'repos/' + self.repo + '/' + path])
        pages = []
        decoder = json.JSONDecoder()
        while stream.strip():
            stream = stream.lstrip()
            page, end = decoder.raw_decode(stream)
            pages.append(page)
            stream = stream[end:]
        return [item for page in pages for item in (page[key] if key else page)]

    def releases(self):
        return self.pages('releases?per_page=100')

    def download(self, tag, name, destination):
        numeric(tag)
        require(name in ASSETS, 'Unexpected release asset')
        destination.mkdir(parents=True, exist_ok=True)
        command(['gh', 'release', 'download', tag, '--repo', self.repo, '--pattern', name, '--dir', str(destination)])
        return (destination / name).read_bytes()

    def upload(self, tag, file):
        # Deliberately no --clobber: an existing asset is immutable.
        command(['gh', 'release', 'upload', tag, str(file), '--repo', self.repo])


    def verify_tag(self, tag, expected_commit):
        numeric(tag)
        hex_value(expected_commit, 40)
        actual = self.get('git/ref/tags/' + tag).get('object', {})
        require(actual.get('type') == 'commit' and actual.get('sha') == expected_commit,
                'Remote release tag is missing or changed; never create or move it implicitly')

    def create_draft(self, tag, notes):
        request = notes.parent / 'draft-request.json'
        request.write_bytes(canonical({'tag_name': tag, 'name': 'Tacobout Social ' + tag[1:],
                                       'draft': True, 'prerelease': False, 'body': notes.read_text()}))
        # Keep the authoritative write response; an immediate list can be stale.
        return json.loads(command(['gh', 'api', '--method', 'POST', 'repos/' + self.repo + '/releases',
                                   '--input', str(request)]))

    def publish_draft(self, tag):
        command(['gh', 'release', 'edit', tag, '--repo', self.repo, '--draft=false', '--latest'])


def source_anchor(repo, api, release, directory):
    tag = release['tag_name']
    require(not release['draft'] and not release['prerelease'], 'Seed must be a published stable release')
    numeric(tag)
    manifest = json.loads(api.download(tag, 'release.json', directory))
    require(manifest.get('schema') == 1 and manifest.get('theme_slug') == SLUG, 'Invalid seed manifest identity')
    hex_value(manifest.get('zip_sha256'), 64)
    commit = hex_value(manifest['source_commit'], 40)
    require(git(repo, 'rev-parse', tag + '^{commit}') == commit, 'Published tag/manifest commit mismatch')
    require(version(git(repo, 'show', tag + ':style.css').encode()) == numeric(tag), 'Published tag/version mismatch')
    if any(a['name'] == 'release-source.json' for a in release['assets']):
        record = json.loads(api.download(tag, 'release-source.json', directory))
        require(record.get('schema') == 1 and record.get('release_commit') == commit and record.get('version') == tag[1:]
                and record.get('zip_sha256') == manifest['zip_sha256'], 'Invalid published release provenance')
        source = hex_value(record['source_merge_commit'], 40)
        require(git(repo, 'rev-parse', commit + '^') == source, 'Release commit is not a child of its merge')
        return source
    return commit  # Existing manually verified 3.9.0 is the initial seed.


def validate_candidate(repo, source, candidate, tag):
    """The release child may change version metadata/fingerprints only, never product code."""
    numeric(tag)
    require(git(repo, 'rev-parse', candidate + '^') == source, 'Unexpected release parent')
    old_css = subprocess.check_output(['git', 'show', source + ':style.css'], cwd=repo)
    new_css = subprocess.check_output(['git', 'show', candidate + ':style.css'], cwd=repo)
    expected = re.sub(rb'(?m)^Version:[^\r\n]*', b'Version: ' + tag[1:].encode(), old_css, count=1)
    # Preserve the original newline; never normalize or rewrite other CSS.
    require(new_css == expected, 'Release child changes more than its version header')
    old_assets = json.loads(git(repo, 'show', source + ':asset-manifest.json'))
    new_assets = json.loads(git(repo, 'show', candidate + ':asset-manifest.json'))
    expected_assets = dict(old_assets)
    expected_assets['style.css'] = 'style.' + sha(new_css)[:16] + '.css'
    require(new_assets == expected_assets, 'Release child changes unrelated asset identities')
    require(subprocess.check_output(['git', 'show', candidate + ':' + new_assets['style.css']], cwd=repo) == new_css, 'Version fingerprint bytes mismatch')
    allowed = {'style.css', 'asset-manifest.json', old_assets['style.css'], new_assets['style.css']}
    changed = set(git(repo, 'diff-tree', '--no-commit-id', '--name-only', '-r', source, candidate).splitlines())
    require(changed <= allowed and {'style.css', 'asset-manifest.json'} <= changed, 'Unexpected files in release child')


def prepare_candidate(repo, directory, source, tag):
    git(repo, 'worktree', 'add', '--detach', str(directory), source)
    css = directory / 'style.css'
    content = css.read_bytes()
    replacement, count = re.subn(rb'(?m)^Version:[^\r\n]*', b'Version: ' + tag[1:].encode(), content)
    require(count == 1, 'Ambiguous version header')
    css.write_bytes(replacement)
    command(['node', 'scripts/build-assets.cjs'], directory)
    git(directory, 'add', '-A')
    epoch = git(repo, 'show', '-s', '--format=%ct', source)
    env = dict(os.environ, GIT_AUTHOR_DATE='@' + epoch + ' +0000', GIT_COMMITTER_DATE='@' + epoch + ' +0000')
    git(directory, '-c', 'user.name=github-actions[bot]', '-c', 'user.email=41898282+github-actions[bot]@users.noreply.github.com',
        'commit', '-m', 'Release Tacobout Social ' + tag[1:] + '\n\nSource-Merge: ' + source, env=env)
    candidate = git(directory, 'rev-parse', 'HEAD')
    validate_candidate(repo, source, candidate, tag)
    return candidate


def existing_candidate(repo, source, tags):
    found = []
    for tag in tags:
        if not re.fullmatch(r'v\d+\.\d+\.\d+', tag):
            continue
        commit = git(repo, 'rev-parse', tag + '^{commit}')
        message = git(repo, 'show', '-s', '--format=%B', commit)
        if re.search(r'^Source-Merge: ' + source + r'$', message, re.M):
            validate_candidate(repo, source, commit, tag)
            found.append((tag, commit))
    require(len(found) <= 1, 'Multiple release tags claim the same merge; review without overwriting')
    return found[0] if found else None


def verify_candidate(directory):
    # Browser/native-core CI is already required for the exact source SHA. The
    # candidate's only permitted runtime differences are version/fingerprint bytes.
    commands = [
        ['composer', 'install', '--prefer-dist', '--no-progress'],
        ['php', 'vendor/bin/phpunit'],
        ['node', 'scripts/build-assets.cjs', '--check'],
        ['node', '--test', *[str(p) for p in sorted(directory.glob('tests/*.test.cjs'))]],
        ['python3', '-m', 'unittest', 'discover', '-s', 'deployment/tests', '-q'],
        ['python3', 'scripts/check_public_email.py'],
    ]
    for args in commands:
        command(args, directory, quiet=False)


def ensure_assets(api, release, tag, artifacts, downloaded):
    """Resume a partial draft by adding only absent assets; compare every existing byte."""
    names = [a['name'] for a in release['assets']]
    require(len(names) == len(set(names)) and set(names) <= set(ASSETS), 'Unexpected or duplicate assets; do not overwrite')
    for name in ASSETS:
        if name in names:
            require(api.download(tag, name, downloaded / name) == (artifacts / name).read_bytes(), 'Existing asset differs: ' + name)
        else:
            require(release['draft'], 'Published release is missing an immutable asset')
            api.upload(tag, artifacts / name)
    # Download and validate actual hosted bytes before making a draft discoverable.
    hosted = downloaded / 'hosted'
    hosted.mkdir(parents=True)
    for name in ASSETS:
        actual = api.download(tag, name, downloaded / ('verified-' + name))
        (hosted / name).write_bytes(actual)
        require(actual == (artifacts / name).read_bytes(), 'Hosted asset differs: ' + name)
    manifest = json.loads((artifacts / 'release.json').read_bytes())
    load(hosted, manifest['source_commit'], manifest['zip_sha256'])
    require(json.loads((hosted / 'tacobout-update.json').read_bytes()) == generate(artifacts, tag), 'Hosted updater metadata mismatch')


def publish(repo, api, source, tag, candidate, artifacts, anchor, ci, root, verify_gate):
    root.mkdir(parents=True, exist_ok=True)
    api.verify_tag(tag, candidate)
    releases = api.releases()
    matches = [r for r in releases if r['tag_name'] == tag]
    require(len(matches) <= 1, 'Duplicate release identity')
    require(git(repo, 'rev-parse', tag + '^{commit}') == candidate, 'Tag changed; never move it')
    if not matches:
        notes = root / 'notes.md'
        links = '\n'.join('- ' + run['html_url'] for run in ci.values())
        notes.write_text('Automatic patch from merged commit `' + source + '`.\n\nExact-commit CI:\n' + links
                         + '\n\nOnly version/fingerprint metadata differs from the merged source. WordPress installation remains manual.\n')
        created = api.create_draft(tag, notes)
        require(created.get('tag_name') == tag and created.get('draft') is True and isinstance(created.get('id'), int), 'Unexpected draft creation response; preserve and inspect')
        def visible_draft():
            found = [r for r in api.releases() if r['tag_name'] == tag]
            require(len(found) <= 1, 'Duplicate draft identity; preserve and inspect')
            return found
        matches = observed(visible_draft, lambda found: len(found) == 1 and found[0]['id'] == created['id'],
                           'Confirmed draft ' + tag + ' (id ' + str(created['id']) + ') is not visible yet')
    release = matches[0]
    require(not release['prerelease'], 'Existing tag is a prerelease; review manually')
    ensure_assets(api, release, tag, artifacts, root / 'downloaded')
    if release['draft']:
        verify_gate()
        api.verify_tag(tag, candidate)
        require(api.get('releases/latest')['id'] == anchor['id'], 'Another stable release appeared; preserve draft and review ordering')
        api.publish_draft(tag)
    def visible_stable():
        found = [r for r in api.releases() if r['tag_name'] == tag]
        require(len(found) <= 1, 'Duplicate published identity')
        return found[0] if found else None
    actual = observed(visible_stable, lambda item: item is not None and not item['draft'] and not item['prerelease'],
                      'Stable publication is not visible yet')
    latest = observed(lambda: api.get('releases/latest'), lambda item: item['id'] == actual['id'],
                      'Latest pointer has not reached the confirmed release or another publisher changed it')
    print('Verified published patch:', actual['html_url'], flush=True)
    return latest


def run(repo, api, execute=False):
    identity = api.get('')
    branch = identity['default_branch']
    require(branch == 'main', 'Default branch changed; review trigger before publishing')
    git(repo, 'fetch', 'origin', branch, '--tags')
    head = git(repo, 'rev-parse', 'refs/remotes/origin/' + branch)
    anchor = api.get('releases/latest')
    with tempfile.TemporaryDirectory(prefix='patch-queue-') as temporary:
        root = Path(temporary)
        base = source_anchor(repo, api, anchor, root / 'seed')
        git(repo, 'merge-base', '--is-ancestor', base, head)
        require(base in git(repo, 'rev-list', '--first-parent', head).splitlines(), 'Release source is not on the default first-parent history')
        commits = git(repo, 'rev-list', '--first-parent', '--reverse', base + '..' + head).splitlines()
        planned_tags = []
        for index, source in enumerate(commits):
            hex_value(source, 40)
            pulls = api.pages('commits/' + source + '/pulls?per_page=100')
            if not merged_pr(pulls, api.repo, branch, source):
                print('No merged PR at source; skipping direct push:', source, flush=True)
                continue
            state, ci = source_gate(api, branch, source)
            print('Merge gate:', source, state, flush=True)
            if state == 'pending':
                break  # Never release a newer merge and later roll back to an older pending one.
            if state == 'failed':
                continue  # A later green fix may ship; failed sources are never released.
            tags = git(repo, 'tag', '--list').splitlines() + planned_tags
            previous = existing_candidate(repo, source, git(repo, 'tag', '--list').splitlines())
            tag = previous[0] if previous else next_patch(numeric(anchor['tag_name']), version(git(repo, 'show', source + ':style.css').encode()), tags)
            if not execute:
                print('Would prepare patch:', tag, source, flush=True)
                planned_tags.append(tag)
                continue
            work = root / ('candidate-' + str(index))
            if previous:
                candidate = previous[1]
                git(repo, 'worktree', 'add', '--detach', str(work), candidate)
            else:
                candidate = prepare_candidate(repo, work, source, tag)
            try:
                verify_candidate(work)
                artifacts = root / ('artifacts-' + str(index))
                manifest = build(work, artifacts)
                generate(artifacts, tag)
                (artifacts / 'release-source.json').write_bytes(canonical({'schema': 1, 'source_merge_commit': source,
                    'release_commit': candidate, 'version': tag[1:], 'zip_sha256': manifest['zip_sha256']}))
                state, ci = source_gate(api, branch, source)
                require(state == 'ready', 'Source CI changed during candidate validation; retry when green')
                if not previous:
                    git(repo, 'tag', tag, candidate)
                    git(repo, 'push', 'origin', 'refs/tags/' + tag)  # No force, no default-branch write.
                anchor = publish(repo, api, source, tag, candidate, artifacts, anchor, ci, root / ('publish-' + str(index)),
                                 verify_gate=lambda: require(source_gate(api, branch, source)[0] == 'ready', 'Source CI changed before stable publication'))
            finally:
                git(repo, 'worktree', 'remove', '--force', str(work))


def trusted_event(event_name, event, repo, ref):
    require(event.get('repository', {}).get('full_name') == repo, 'Unexpected event repository')
    branch = event.get('repository', {}).get('default_branch')
    require(branch == 'main', 'Unexpected default branch')
    if event_name == 'workflow_dispatch':
        require(ref == 'refs/heads/main', 'Manual publication must use the default branch')
    else:
        run_event = event.get('workflow_run', {})
        require(event_name == 'workflow_run' and run_event.get('event') == 'push'
                and run_event.get('head_branch') == branch and run_event.get('head_repository', {}).get('full_name') == repo,
                'Untrusted workflow trigger')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--repo', required=True)
    parser.add_argument('--execute', action='store_true')
    args = parser.parse_args()
    if args.execute:
        require(os.environ.get('GITHUB_ACTIONS') == 'true' and os.environ.get('GITHUB_REPOSITORY') == args.repo, 'Publication requires the trusted patch workflow')
        trusted_event(os.environ.get('GITHUB_EVENT_NAME'), json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_bytes()), args.repo, os.environ.get('GITHUB_REF'))
    run(Path.cwd(), GitHub(args.repo), args.execute)


if __name__ == '__main__':
    main()
