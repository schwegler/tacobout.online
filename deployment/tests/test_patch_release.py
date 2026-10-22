import copy
import json
import shutil
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import patch_release as release
from theme_release import Refused, build, load
from update_metadata import generate

REPO = 'schwegler/tacobout.online'
SOURCE = 'a' * 40


def facts(commit=SOURCE):
    runs, jobs = [], {}
    for number, (path, names) in enumerate(release.REQUIRED.items(), 1):
        runs.append(dict(id=number, path=path, event='push', head_sha=commit, head_branch='main',
                         head_repository={'full_name': REPO}, status='completed', conclusion='success', run_attempt=1,
                         html_url=f'https://github.com/{REPO}/actions/runs/{number}'))
        jobs[number] = [dict(name=name, status='completed', conclusion='success') for name in names]
    return runs, jobs


class PolicyTests(unittest.TestCase):
    def test_read_visibility_retries_are_bounded_and_do_not_repeat_a_write(self):
        values = iter([None, None, {'id': 7}])
        with patch.object(release.time, 'sleep') as sleep:
            self.assertEqual({'id': 7}, release.observed(lambda: next(values), lambda item: item is not None, 'visibility'))
            self.assertEqual(2, sleep.call_count)
        with patch.object(release.time, 'sleep') as sleep, self.assertRaises(Refused):
            release.observed(lambda: None, bool, 'visibility', attempts=3)
        self.assertEqual(2, sleep.call_count)

    def test_draft_creation_retains_the_authoritative_api_response(self):
        record = {'id': 77, 'tag_name': 'v3.9.1', 'draft': True, 'assets': []}
        with tempfile.TemporaryDirectory() as temporary:
            notes = Path(temporary) / 'notes.md'; notes.write_text('Verified source')
            with patch.object(release, 'command', return_value=json.dumps(record)) as request:
                self.assertEqual(record, release.GitHub(REPO).create_draft('v3.9.1', notes))
                request.assert_called_once()
                args = request.call_args.args[0]
                self.assertEqual(['gh', 'api', '--method', 'POST'], args[:4])
                payload = json.loads(Path(args[-1]).read_bytes())
                self.assertTrue(payload['draft'])
                self.assertEqual('v3.9.1', payload['tag_name'])

    def test_older_cli_paginated_json_streams_are_all_read(self):
        api = release.GitHub(REPO)
        with patch.object(release, 'command', return_value='[{"id":1}]\n [{"id":2}]'):
            self.assertEqual([{'id': 1}, {'id': 2}], api.pages('releases'))
        with patch.object(release, 'command', return_value='{"jobs":[{"id":1}]}\n{"jobs":[{"id":2}]}'):
            self.assertEqual([{'id': 1}, {'id': 2}], api.pages('actions/jobs', 'jobs'))

    def test_every_required_workflow_and_job_must_succeed_at_exact_merge(self):
        runs, jobs = facts()
        self.assertEqual('ready', release.gate(runs, REPO, 'main', SOURCE, jobs.get)[0])
        self.assertEqual('pending', release.gate(runs, REPO, 'main', 'b' * 40, jobs.get)[0])
        self.assertEqual('pending', release.gate(runs[:-1], REPO, 'main', SOURCE, jobs.get)[0])
        jobs[1][0]['conclusion'] = 'skipped'
        self.assertEqual('failed', release.gate(runs, REPO, 'main', SOURCE, jobs.get)[0])

    def test_in_progress_newest_attempt_and_failed_reruns_cannot_use_old_success(self):
        runs, jobs = facts()
        newer = dict(runs[0], id=9, status='in_progress', conclusion=None, run_attempt=2)
        self.assertEqual('pending', release.gate(runs + [newer], REPO, 'main', SOURCE, jobs.get)[0])
        newer.update(status='completed', conclusion='failure')
        self.assertEqual('failed', release.gate(runs + [newer], REPO, 'main', SOURCE, jobs.get)[0])

    def test_rerunning_an_older_run_id_cannot_hide_behind_a_newer_success(self):
        runs, jobs = facts()
        newer = dict(runs[0], id=9, run_started_at='2026-10-22T12:01:00Z')
        runs[0].update(run_attempt=2, run_started_at='2026-10-22T12:03:00Z', conclusion='failure')
        self.assertEqual('failed', release.gate(runs + [newer], REPO, 'main', SOURCE, jobs.get)[0])
        runs[0].update(status='in_progress', conclusion=None, run_started_at='2026-10-22T12:00:00Z')
        self.assertEqual('pending', release.gate(runs + [newer], REPO, 'main', SOURCE, jobs.get)[0])

    def test_fork_pr_wrong_branch_and_spoofed_workflow_do_not_authorize_publication(self):
        for change in [dict(event='pull_request'), dict(head_branch='feature'),
                       dict(head_repository={'full_name': 'fork/theme'}), dict(path='.github/workflows/spoof.yml')]:
            runs, jobs = facts()
            runs[0].update(change)
            self.assertEqual('pending', release.gate(runs, REPO, 'main', SOURCE, jobs.get)[0])

    def test_job_completeness_and_workflow_failure_are_both_enforced(self):
        runs, jobs = facts()
        jobs[1].pop()
        self.assertEqual('pending', release.gate(runs, REPO, 'main', SOURCE, jobs.get)[0])
        runs[2]['conclusion'] = 'failure'
        self.assertEqual('failed', release.gate(runs, REPO, 'main', SOURCE, jobs.get)[0])

    def test_eventual_consistency_with_null_results_is_pending_not_a_failed_source(self):
        runs, jobs = facts()
        runs[0]['conclusion'] = None
        self.assertEqual('pending', release.gate(runs, REPO, 'main', SOURCE, jobs.get)[0])
        runs[0]['conclusion'] = 'success'
        jobs[1][0]['conclusion'] = None
        self.assertEqual('pending', release.gate(runs, REPO, 'main', SOURCE, jobs.get)[0])

    def test_patch_reservations_include_unpublished_tags_and_respect_minor_seed(self):
        self.assertEqual('v3.9.1', release.next_patch((3, 9, 0), (3, 9, 0), ['v3.8.31']))
        self.assertEqual('v3.9.11', release.next_patch((3, 9, 2), (3, 9, 0), ['v3.9.10', 'v4.0.0']))
        for base, source in [((3, 8, 31), (3, 8, 31)), ((3, 9, 2), (3, 10, 0))]:
            with self.assertRaises(Refused): release.next_patch(base, source, [])
        for tag in ['v03.9.1', 'v3.9.1-beta', '../v3.9.1']:
            with self.assertRaises(Refused): release.numeric(tag)

    def test_normal_and_squash_merges_require_actual_merged_pr_identity(self):
        pr = {'merged_at': '2026-10-22T00:00:00Z', 'merge_commit_sha': SOURCE,
              'base': {'ref': 'main', 'repo': {'full_name': REPO}}}
        self.assertTrue(release.merged_pr([pr], REPO, 'main', SOURCE))
        self.assertFalse(release.merged_pr([dict(pr, merged_at=None)], REPO, 'main', SOURCE))
        self.assertFalse(release.merged_pr([pr], REPO, 'master', SOURCE))
        self.assertFalse(release.merged_pr([pr], 'fork/theme', 'main', SOURCE))

    def test_only_trusted_default_branch_events_can_write(self):
        event = {'repository': {'full_name': REPO, 'default_branch': 'main'}, 'workflow_run': {
            'event': 'push', 'head_branch': 'main', 'head_repository': {'full_name': REPO}}}
        release.trusted_event('workflow_run', event, REPO, 'refs/heads/main')
        release.trusted_event('workflow_dispatch', event, REPO, 'refs/heads/main')
        for modified in [dict(event, workflow_run=dict(event['workflow_run'], event='pull_request')),
                         dict(event, workflow_run=dict(event['workflow_run'], head_repository={'full_name': 'fork/theme'}))]:
            with self.assertRaises(Refused): release.trusted_event('workflow_run', modified, REPO, 'refs/heads/main')
        with self.assertRaises(Refused): release.trusted_event('workflow_dispatch', event, REPO, 'refs/heads/feature')


class MemoryGitHub:
    repo = REPO

    def __init__(self):
        self.items = []
        self.blobs = {}
        self.uploads = []
        self.promotions = []
        self.latest = {'id': 1, 'tag_name': 'v3.9.0'}

    def releases(self): return copy.deepcopy(self.items)

    def create_draft(self, tag, notes):
        self.items.append(dict(id=max([1] + [r['id'] for r in self.items]) + 1, tag_name=tag, draft=True, prerelease=False, assets=[], html_url='https://example.test/' + tag))
        return copy.deepcopy(self.items[-1])

    def publish_draft(self, tag):
        item = next(r for r in self.items if r['tag_name'] == tag)
        item['draft'] = False
        self.latest = item
        self.promotions.append(tag)

    def get(self, path):
        if path == 'releases/latest': return copy.deepcopy(self.latest)
        if path.startswith('releases/tags/'):
            return copy.deepcopy(next(r for r in self.items if r['tag_name'] == path.rsplit('/', 1)[1]))
        raise AssertionError('Unexpected API path ' + path)

    def download(self, tag, name, directory):
        directory.mkdir(parents=True, exist_ok=True)
        data = self.blobs[tag, name]
        (directory / name).write_bytes(data)
        return data

    def upload(self, tag, file):
        if (tag, file.name) in self.blobs: raise AssertionError('Attempted asset overwrite')
        self.blobs[tag, file.name] = file.read_bytes()
        self.uploads.append(file.name)
        next(r for r in self.items if r['tag_name'] == tag)['assets'].append({'name': file.name})


class CandidateFixture(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.repo = self.root / 'repo'; self.repo.mkdir()
        script = Path(__file__).resolve().parents[2] / 'scripts/build-assets.cjs'
        sources = json.loads(subprocess.check_output(['node', '-e', 'process.stdout.write(JSON.stringify(require(process.argv[1]).sources))', str(script)]))
        for source in sources:
            file = self.repo / source; file.parent.mkdir(parents=True, exist_ok=True)
            file.write_text('/* fixture */\n' if source.endswith('.css') else '// fixture\n')
        (self.repo / 'style.css').write_text('/*\nVersion: 3.9.0\nUpdate URI: https://github.com/schwegler/tacobout.online\nRequires at least: 6.4\nRequires PHP: 8.0\n*/\nbody { color: red; }\n\n')
        for name, data in [('functions.php', '<?php'), ('theme.json', '{}'), ('templates/author.html', 'native author')]:
            file = self.repo / name; file.parent.mkdir(parents=True, exist_ok=True); file.write_text(data)
        (self.repo / 'scripts').mkdir(); shutil.copy(script, self.repo / 'scripts/build-assets.cjs')
        release.command(['node', 'scripts/build-assets.cjs'], self.repo)
        release.git(self.repo, 'init', '-q', '-b', 'main')
        release.git(self.repo, 'add', '.')
        release.git(self.repo, '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', 'commit', '-qm', 'source')
        self.source = release.git(self.repo, 'rev-parse', 'HEAD')
        self.tag = 'v3.9.1'
        self.work = self.root / 'candidate'
        self.candidate = release.prepare_candidate(self.repo, self.work, self.source, self.tag)
        self.artifacts = self.root / 'artifacts'
        manifest = build(self.work, self.artifacts); generate(self.artifacts, self.tag)
        (self.artifacts / 'release-source.json').write_bytes(release.canonical(dict(schema=1, source_merge_commit=self.source,
            release_commit=self.candidate, version='3.9.1', zip_sha256=manifest['zip_sha256'])))
        release.git(self.repo, 'tag', self.tag, self.candidate)
        self.api = MemoryGitHub()

    def tearDown(self): self.temp.cleanup()



class CandidateTests(CandidateFixture):
    def test_candidate_is_deterministic_version_only_and_never_moves_main(self):
        again = release.prepare_candidate(self.repo, self.root / 'candidate-again', self.source, self.tag)
        self.assertEqual(self.candidate, again)
        self.assertEqual(self.source, release.git(self.repo, 'rev-parse', 'main'))
        self.assertEqual(self.source, release.git(self.repo, 'rev-parse', self.candidate + '^'))
        self.assertEqual((self.tag, self.candidate), release.existing_candidate(self.repo, self.source, [self.tag]))
        manifest = json.loads((self.artifacts / 'release.json').read_bytes())
        _, files = load(self.artifacts, self.candidate, manifest['zip_sha256'])
        self.assertIn(b'Version: 3.9.1\n', files['style.css'][0])
        self.assertTrue(files['style.css'][0].endswith(b'\n\n'))

    def test_unrelated_runtime_change_is_refused_before_any_publication(self):
        (self.work / 'functions.php').write_text('<?php changed();')
        release.git(self.work, 'add', '.')
        release.git(self.work, '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', 'commit', '--amend', '--no-edit')
        with self.assertRaises(Refused): release.validate_candidate(self.repo, self.source, release.git(self.work, 'rev-parse', 'HEAD'), self.tag)

    def test_partial_upload_resume_and_published_rerun_are_idempotent(self):
        self.api.create_draft(self.tag, None)
        self.api.upload(self.tag, self.artifacts / 'theme.zip')
        ci = {r['path']: r for r in facts()[0]}
        anchor = copy.deepcopy(self.api.latest)
        release.publish(self.repo, self.api, self.source, self.tag, self.candidate, self.artifacts, anchor, ci, self.root / 'first', verify_gate=lambda: None)
        self.assertEqual(list(release.ASSETS), self.api.uploads)
        self.assertEqual([self.tag], self.api.promotions)
        release.publish(self.repo, self.api, self.source, self.tag, self.candidate, self.artifacts, self.api.latest, ci, self.root / 'again', verify_gate=lambda: None)
        self.assertEqual(4, len(self.api.uploads))
        self.assertEqual(1, len(self.api.promotions))

    def test_conflicting_asset_is_never_overwritten_or_published(self):
        self.api.create_draft(self.tag, None)
        self.api.upload(self.tag, self.artifacts / 'theme.zip')
        self.api.blobs[self.tag, 'theme.zip'] = b'conflicting bytes'
        with self.assertRaises(Refused): release.ensure_assets(self.api, self.api.items[0], self.tag, self.artifacts, self.root / 'download')
        self.assertEqual(['theme.zip'], self.api.uploads)
        self.assertFalse(self.api.promotions)

    def test_concurrent_manual_release_leaves_validated_draft_unpublished(self):
        anchor = copy.deepcopy(self.api.latest)
        self.api.latest = {'id': 99, 'tag_name': 'v3.9.2'}
        with self.assertRaises(Refused): release.publish(self.repo, self.api, self.source, self.tag, self.candidate,
            self.artifacts, anchor, {}, self.root / 'publish', verify_gate=lambda: None)
        self.assertTrue(self.api.items[0]['draft'])
        self.assertFalse(self.api.promotions)

    def test_stale_draft_listing_does_not_recreate_the_confirmed_release(self):
        original = self.api.releases
        remaining = 2
        def listing():
            nonlocal remaining
            if self.api.items and self.api.items[0]['draft'] and remaining:
                remaining -= 1
                return []
            return original()
        with patch.object(self.api, 'releases', side_effect=listing), patch.object(release.time, 'sleep') as sleep, \
             patch.object(self.api, 'create_draft', wraps=self.api.create_draft) as create:
            release.publish(self.repo, self.api, self.source, self.tag, self.candidate, self.artifacts,
                self.api.latest, {}, self.root / 'draft-lag', verify_gate=lambda: None)
            create.assert_called_once()
            self.assertEqual(2, sleep.call_count)
        self.assertEqual([self.tag], self.api.promotions)
        self.assertEqual(4, len(self.api.uploads))

    def test_visibility_timeout_preserves_draft_and_retry_reuses_it(self):
        with patch.object(self.api, 'create_draft', wraps=self.api.create_draft) as create:
            with patch.object(self.api, 'releases', return_value=[]), patch.object(release.time, 'sleep'), self.assertRaises(Refused):
                release.publish(self.repo, self.api, self.source, self.tag, self.candidate, self.artifacts,
                    self.api.latest, {}, self.root / 'not-visible', verify_gate=lambda: None)
            self.assertEqual(1, len(self.api.items))
            self.assertTrue(self.api.items[0]['draft'])
            self.assertFalse(self.api.uploads)
            release.publish(self.repo, self.api, self.source, self.tag, self.candidate, self.artifacts,
                self.api.latest, {}, self.root / 'visible-retry', verify_gate=lambda: None)
            create.assert_called_once()
        self.assertEqual([self.tag], self.api.promotions)

    def test_publication_and_latest_pointer_can_become_visible_later(self):
        original_listing, original_get = self.api.releases, self.api.get
        old_latest = copy.deepcopy(self.api.latest)
        remaining = {'list': 2, 'latest': 2}
        def listing():
            result = original_listing()
            if self.api.promotions and remaining['list']:
                remaining['list'] -= 1
                for item in result: item['draft'] = True
            return result
        def get(path):
            if path == 'releases/latest' and self.api.promotions and remaining['latest']:
                remaining['latest'] -= 1
                return old_latest
            return original_get(path)
        with patch.object(self.api, 'releases', side_effect=listing), patch.object(self.api, 'get', side_effect=get), \
             patch.object(release.time, 'sleep') as sleep:
            release.publish(self.repo, self.api, self.source, self.tag, self.candidate, self.artifacts,
                old_latest, {}, self.root / 'stable-lag', verify_gate=lambda: None)
            self.assertEqual(4, sleep.call_count)
        self.assertEqual([self.tag], self.api.promotions)
        self.assertEqual(4, len(self.api.uploads))

    def test_ci_rerun_during_upload_prevents_stable_promotion(self):
        def changed_gate(): raise Refused('source CI restarted')
        with self.assertRaises(Refused): release.publish(self.repo, self.api, self.source, self.tag, self.candidate,
            self.artifacts, self.api.latest, {}, self.root / 'publish-gate', verify_gate=changed_gate)
        self.assertTrue(self.api.items[0]['draft'])
        self.assertFalse(self.api.promotions)

    def test_duplicate_source_tags_fail_closed(self):
        release.git(self.repo, 'tag', 'v3.9.2', self.candidate)
        with self.assertRaises(Refused): release.existing_candidate(self.repo, self.source, [self.tag, 'v3.9.2'])


class QueueGitHub(MemoryGitHub):
    def __init__(self, source, seed):
        super().__init__()
        self.states = {}
        self.latest = dict(id=1, tag_name='v3.9.0', draft=False, prerelease=False,
                           assets=[{'name': n} for n in release.ASSETS[:3]])
        self.items = [self.latest]
        self.blobs.update({('v3.9.0', n): (seed / n).read_bytes() for n in release.ASSETS[:3]})

    def get(self, path):
        return {'default_branch': 'main'} if path == '' else super().get(path)

    def pages(self, path, key=None):
        from urllib.parse import parse_qs
        if path.startswith('commits/'):
            sha = path.split('/')[1]
            return [{'merged_at': '2026-10-22T00:00:00Z', 'merge_commit_sha': sha,
                     'base': {'ref': 'main', 'repo': {'full_name': REPO}}}]
        if path.startswith('actions/runs?'):
            sha = parse_qs(path.split('?')[1])['head_sha'][0]
            runs, _ = facts(sha)
            state = self.states.get(sha, 'ready')
            if state == 'pending': runs[0].update(status='in_progress', conclusion=None)
            if state == 'failed': runs[0]['conclusion'] = 'failure'
            return runs
        if path.startswith('actions/runs/'):
            return facts()[1][int(path.split('/')[2])]
        raise AssertionError(path)


class QueueTests(CandidateFixture):
    def setUp(self):
        super().setUp()
        release.git(self.repo, 'tag', '-d', self.tag)  # Ephemeral test fixture only.
        release.git(self.repo, 'tag', 'v3.9.0', self.source)
        seed = self.root / 'seed'; build(self.repo, seed); generate(seed, 'v3.9.0')
        self.api = QueueGitHub(self.source, seed)
        self.sources = []
        for number in [1, 2]:
            release.git(self.repo, 'checkout', '-b', 'feature-' + str(number), 'main')
            (self.repo / ('notes-' + str(number) + '.txt')).write_text('fixture merge')
            release.git(self.repo, 'add', '.')
            release.git(self.repo, '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', 'commit', '-qm', 'feature')
            release.git(self.repo, 'checkout', 'main')
            release.git(self.repo, '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', 'merge', '--no-ff', '-m', 'Merge pull request fixture', 'feature-' + str(number))
            self.sources.append(release.git(self.repo, 'rev-parse', 'HEAD'))
        self.remote = self.root / 'remote.git'
        release.command(['git', 'init', '--bare', '-q', str(self.remote)])
        release.git(self.repo, 'remote', 'add', 'origin', str(self.remote))
        release.git(self.repo, 'push', 'origin', 'main', '--tags')

    def test_coalesced_event_processes_all_green_merges_in_order_then_noops(self):
        original_main = release.git(self.repo, 'rev-parse', 'main')
        with patch.object(release, 'verify_candidate') as verify:
            release.run(self.repo, self.api, execute=True)
            self.assertEqual(2, verify.call_count)
            release.run(self.repo, self.api, execute=True)
            self.assertEqual(2, verify.call_count)
        self.assertEqual(['v3.9.1', 'v3.9.2'], self.api.promotions)
        self.assertEqual(original_main, release.git(self.repo, 'rev-parse', 'main'))
        for tag, source in zip(self.api.promotions, self.sources):
            record = json.loads(self.api.blobs[tag, 'release-source.json'])
            self.assertEqual(source, record['source_merge_commit'])
            self.assertEqual(source, release.git(self.repo, 'rev-parse', tag + '^'))

    def test_pending_older_merge_blocks_newer_ready_merge_until_retry(self):
        self.api.states[self.sources[0]] = 'pending'
        with patch.object(release, 'verify_candidate') as verify:
            release.run(self.repo, self.api, execute=True)
            verify.assert_not_called()
            self.assertFalse(self.api.promotions)
            self.api.states[self.sources[0]] = 'ready'
            release.run(self.repo, self.api, execute=True)
        self.assertEqual(['v3.9.1', 'v3.9.2'], self.api.promotions)

    def test_failed_merge_never_releases_and_a_green_fix_does(self):
        self.api.states[self.sources[0]] = 'failed'
        with patch.object(release, 'verify_candidate'):
            release.run(self.repo, self.api, execute=True)
        self.assertEqual(['v3.9.1'], self.api.promotions)
        record = json.loads(self.api.blobs['v3.9.1', 'release-source.json'])
        self.assertEqual(self.sources[1], record['source_merge_commit'])

    def test_reserved_tag_without_release_resumes_without_moving_it(self):
        work = self.root / 'interrupted'
        candidate = release.prepare_candidate(self.repo, work, self.sources[0], 'v3.9.1')
        release.git(self.repo, 'tag', 'v3.9.1', candidate)
        release.git(self.repo, 'push', 'origin', 'refs/tags/v3.9.1')
        with patch.object(release, 'verify_candidate'):
            release.run(self.repo, self.api, execute=True)
        self.assertEqual(candidate, release.git(self.repo, 'rev-parse', 'v3.9.1'))
        self.assertEqual(['v3.9.1', 'v3.9.2'], self.api.promotions)

    def test_gate_change_after_validation_creates_no_public_tag(self):
        def restarted(directory): self.api.states[self.sources[0]] = 'pending'
        with patch.object(release, 'verify_candidate', side_effect=restarted), self.assertRaises(Refused):
            release.run(self.repo, self.api, execute=True)
        self.assertEqual('v3.9.0', release.git(self.repo, 'tag', '--list'))
        self.assertFalse(self.api.uploads)
        self.assertFalse(self.api.promotions)

    def test_dry_run_creates_no_commit_tag_upload_or_release(self):
        tags = release.git(self.repo, 'tag', '--list')
        with patch.object(release, 'verify_candidate') as verify:
            release.run(self.repo, self.api, execute=False)
            verify.assert_not_called()
        self.assertEqual(tags, release.git(self.repo, 'tag', '--list'))
        self.assertFalse(self.api.uploads)
        self.assertFalse(self.api.promotions)


if __name__ == '__main__': unittest.main()
