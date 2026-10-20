"""Read-only GitHub provenance/environment checks. Inputs are data, never shell."""
import json, os, re
from urllib.request import Request, urlopen
from theme_release import require, hex_value, MAX_TOTAL

REPOSITORY = 'schwegler/tacobout.online'
ENVIRONMENT = 'easywp-production'


def api(path):
    require(path.startswith('/repos/' + REPOSITORY + '/'), 'GitHub API scope mismatch')
    request = Request('https://api.github.com' + path, headers={
        'Authorization': 'Bearer ' + os.environ['GH_TOKEN'], 'Accept': 'application/vnd.github+json',
        'X-GitHub-Api-Version': '2022-11-28'})
    with urlopen(request, timeout=30) as r:
        return json.load(r)


def positive(value):
    require(isinstance(value, str) and re.fullmatch(r'[1-9][0-9]{0,15}', value), 'Invalid run/attempt identifier')
    return value


def verify_environment(policy, get=api):
    env = get('/repos/' + REPOSITORY + '/environments/' + ENVIRONMENT)
    require(env.get('deployment_branch_policy', {}).get('custom_branch_policies') is True, 'Environment must restrict deployment to main')
    branches = get('/repos/' + REPOSITORY + '/environments/' + ENVIRONMENT + '/deployment-branch-policies?per_page=100')
    rules = branches.get('branch_policies', [])
    require(branches.get('total_count') == 1 and len(rules) == 1 and rules[0].get('name') == 'main' and rules[0].get('type', 'branch') == 'branch', 'Environment branch policy must be exactly main, not wildcard/tags')
    reviewers = [r for r in env.get('protection_rules', []) if r.get('type') == 'required_reviewers' and r.get('reviewers')]
    if policy['approval_mode'] == 'environment-reviewers':
        require(reviewers, 'Required environment reviewers are not configured')
    else:
        repo = get('/repos/' + REPOSITORY + '/')
        require(repo.get('visibility') != 'public' and policy.get('manual_reviewers_unavailable_reason'), 'Reviewer fallback requires recorded unsupported-plan evidence; public repos support reviewers')
        # A manual-only fallback is weaker: explicitly require an admin/maintainer.
        actor = os.environ.get('GITHUB_ACTOR', '')
        require(re.fullmatch(r'[A-Za-z0-9-]+', actor), 'Invalid dispatch actor')
        permission = get('/repos/' + REPOSITORY + '/collaborators/' + actor + '/permission')
        require(permission.get('permission') in {'admin', 'maintain'}, 'Manual fallback requires administrator/maintainer')


def verify_source(operation, run_id, attempt, commit, get=api):
    run_id, attempt = positive(run_id), positive(attempt)
    hex_value(commit, 40)
    run = get('/repos/' + REPOSITORY + '/actions/runs/' + run_id)
    execution = get('/repos/' + REPOSITORY + '/actions/runs/' + run_id + '/attempts/' + attempt)
    expected_workflow = '.github/workflows/tests.yml' if operation == 'deploy' else '.github/workflows/easywp-production.yml'
    expected_event = 'push' if operation == 'deploy' else 'workflow_dispatch'
    require(run.get('repository', {}).get('full_name') == REPOSITORY and run.get('path') == expected_workflow and run.get('head_branch') == 'main' and run.get('event') == expected_event, 'Untrusted source workflow/ref/event')
    require(execution.get('status') == 'completed' and (operation != 'deploy' or execution.get('conclusion') == 'success'), 'Source attempt is not a completed successful test/build')
    if operation == 'deploy':
        require(run.get('head_sha') == commit and execution.get('head_sha') == commit, 'Build source commit mismatch')
        name = 'tested-theme-' + commit + '-' + attempt
    else:
        name = 'theme-backup-' + run_id + '-' + attempt
    artifacts = get('/repos/' + REPOSITORY + '/actions/runs/' + run_id + '/artifacts?per_page=100')
    require(artifacts.get('total_count', 0) <= 100, 'Ambiguous artifact inventory')
    selected = [a for a in artifacts.get('artifacts', []) if a.get('name') == name and not a.get('expired')]
    require(len(selected) == 1 and 0 < selected[0].get('size_in_bytes', 0) <= MAX_TOTAL * 2, 'Immutable artifact missing/expired/ambiguous')
    value = selected[0].get('digest', '')
    require(value.startswith('sha256:'), 'GitHub artifact digest missing')
    hex_value(value[7:])
    return {'artifact_id': positive(str(selected[0]['id'])), 'artifact_name': name, 'artifact_digest': value[7:]}


def main():
    from easywp import config_file
    require(os.environ.get('GITHUB_REPOSITORY') == REPOSITORY and os.environ.get('GITHUB_REF') == 'refs/heads/main' and os.environ.get('GITHUB_EVENT_NAME') == 'workflow_dispatch', 'Production workflow is main-only, explicitly dispatched')
    operation = os.environ.get('OPERATION')
    require(operation in {'inspect', 'verify', 'deploy', 'rollback'}, 'Invalid operation')
    policy = config_file('.github/deployment/production.json', writing=operation in {'deploy', 'rollback'})
    verify_environment(policy)
    output = {'artifact_id': '', 'artifact_name': '', 'artifact_digest': ''}
    if operation != 'inspect':
        hex_value(os.environ.get('ARTIFACT_SHA256', ''))
        if operation != 'verify':
            hex_value(os.environ.get('EXPECTED_LIVE_DIGEST', ''))
            from easywp import confirm_backup
            confirm_backup(os.environ.get('HOST_BACKUP_REFERENCE'), os.environ.get('HOST_BACKUP_CREATED_UTC'), os.environ.get('BACKUP_CONFIRMATION'))
        output = verify_source('deploy' if operation == 'verify' else operation, os.environ.get('ARTIFACT_RUN_ID'), os.environ.get('ARTIFACT_ATTEMPT'), os.environ.get('EXPECTED_COMMIT', ''))
    with open(os.environ['GITHUB_OUTPUT'], 'a') as f:
        for k, v in output.items(): f.write(k + '=' + v + '\n')
    print('Manual workflow/ref, environment policy and immutable source identity validated.')


if __name__ == '__main__':
    try:
        main()
    except Exception as e:
        from theme_release import Refused
        print('Gate refused: ' + (str(e) if isinstance(e, Refused) else type(e).__name__))
        raise SystemExit(1)
