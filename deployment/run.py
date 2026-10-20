"""Fixed-path workflow entrypoint; no artifact executable or arbitrary command/path inputs."""
import json, os, sys
from pathlib import Path
from easywp import (config_file, connection, ThemeRemote, describe, digest, canonical, sha,
                    require, hex_value, load, deploy_plan, rollback_plan, key_bytes,
                    seal, unseal, apply, smoke, confirm_backup, http_read)


def directories():
    root = Path(os.environ['RUNNER_TEMP']) / 'easywp'
    root.mkdir(mode=0o700, parents=True, exist_ok=True)
    return root, Path(os.environ['RUNNER_TEMP']) / 'easywp-source'


def open_remote(c):
    return connection(os.environ['EASYWP_SFTP_HOST'], 22, os.environ['EASYWP_SFTP_USERNAME'], os.environ['EASYWP_SFTP_PASSWORD'], os.environ['EASYWP_KNOWN_HOSTS'])


def desired_plan(c, before, source, operation, original_cipher=None):
    if operation == 'inspect':
        return {'schema': 1, 'operation': 'inspect', 'theme_root': c['theme_root'], 'source_commit': None, 'before': describe(before), 'changes': {}, 'remove': []}, {}
    confirm = confirm_backup(os.environ.get('HOST_BACKUP_REFERENCE'), os.environ.get('HOST_BACKUP_CREATED_UTC'), os.environ.get('BACKUP_CONFIRMATION'))
    if operation == 'deploy':
        release, desired = load(source, os.environ['EXPECTED_COMMIT'], os.environ['ARTIFACT_SHA256'])
        require(c.get('baseline') == '.github/deployment/production-baseline.json', 'Baseline path must be fixed/reviewed')
        baseline = json.loads(Path(c['baseline']).read_bytes())
        plan = deploy_plan(c, baseline, before, release, desired, os.environ['EXPECTED_LIVE_DIGEST'])
    else:
        require(sha(original_cipher) == hex_value(os.environ['ARTIFACT_SHA256']), 'Rollback encrypted artifact checksum mismatch')
        original, previous = unseal(original_cipher, key_bytes(os.environ['THEME_BACKUP_KEY']))
        plan, desired = rollback_plan(c, original, previous, before, os.environ['EXPECTED_LIVE_DIGEST'], os.environ['EXPECTED_COMMIT'])
    plan['host_backup'] = confirm
    return plan, desired


def preflight():
    operation = os.environ['OPERATION']; root, source = directories()
    c = config_file('.github/deployment/production.json', writing=operation != 'inspect')
    key = key_bytes(os.environ['THEME_BACKUP_KEY'])
    require(c['public_theme_url'].encode() in http_read(c['site_url']), 'Current homepage does not link the verified theme URL')
    original = (source / 'backup.enc').read_bytes() if operation == 'rollback' else None
    with open_remote(c) as sftp:
        before = ThemeRemote(sftp, c['theme_root']).snapshot()
    plan, _ = desired_plan(c, before, source, operation, original)
    bundle = root / 'backup'; bundle.mkdir(mode=0o700, exist_ok=True)
    cipher = seal(before, plan, key)
    (bundle / 'backup.enc').write_bytes(cipher)
    receipt = {'schema': 1, 'operation': operation, 'source_commit': plan['source_commit'], 'before_digest': digest(plan['before']), 'cipher_sha256': sha(cipher), 'plan_sha256': sha(canonical(plan)), 'run_id': os.environ['GITHUB_RUN_ID'], 'run_attempt': os.environ['GITHUB_RUN_ATTEMPT']}
    (bundle / 'receipt.json').write_bytes(canonical(receipt))
    (root / 'plan.json').write_bytes(canonical(plan))
    with open(os.environ['GITHUB_STEP_SUMMARY'], 'a') as f:
        f.write('## Theme-only preflight\n\nOperation: `' + operation + '`\n\nLive file digest: `' + receipt['before_digest'] + '`\n\nEncrypted backup SHA-256: `' + receipt['cipher_sha256'] + '`\n\nNo managed theme files have been replaced. Backup upload/readback must succeed before writes.\n')
    print('Read-only preflight and encrypted local backup complete.')


def publish():
    root, source = directories(); operation = os.environ['OPERATION']
    require(operation in {'deploy', 'rollback'}, 'Inspect never publishes')
    c = config_file('.github/deployment/production.json', writing=True)
    saved = json.loads((root / 'plan.json').read_bytes())
    downloaded = Path(os.environ['RUNNER_TEMP']) / 'easywp-durable-backup'
    cipher = (downloaded / 'backup.enc').read_bytes()
    receipt = json.loads((downloaded / 'receipt.json').read_bytes())
    require(receipt['run_id'] == os.environ['GITHUB_RUN_ID'] and receipt['run_attempt'] == os.environ['GITHUB_RUN_ATTEMPT'], 'Backup artifact belongs to another attempt')
    original = (source / 'backup.enc').read_bytes() if operation == 'rollback' else None
    with open_remote(c) as sftp:
        remote = ThemeRemote(sftp, c['theme_root']); before = remote.snapshot()
        plan, desired = desired_plan(c, before, source, operation, original)
        require(plan == saved, 'Preflight plan changed; start a new explicitly confirmed run')
        receipt['artifact_id'] = os.environ['BACKUP_ARTIFACT_ID']
        apply(remote, plan, desired, receipt, cipher, root / 'audit.json')
    # No automatic rollback: failures can be caused by caches/DB overrides. Preserve
    # file backup and audit, then use a separately approved explicit rollback run.
    if operation == 'deploy': smoke(c, desired)
    else: smoke(c)
    with open(os.environ['GITHUB_STEP_SUMMARY'], 'a') as f:
        f.write('\nSFTP bytes verified and HTTP smoke passed. Theme files only; database overrides were preserved. This was not an atomic directory swap.\n')
    print('Theme file readback and HTTP smoke passed.')


if __name__ == '__main__':
    os.umask(0o077)
    try:
        require(len(sys.argv) == 2 and sys.argv[1] in {'preflight', 'publish'}, 'Unknown fixed workflow phase')
        (preflight if sys.argv[1] == 'preflight' else publish)()
    except Exception as e:
        from theme_release import Refused
        print('Deployment stopped: ' + (str(e) if isinstance(e, Refused) else type(e).__name__))
        print('No success is implied. If publication began, inspect the saved backup/audit and current state before explicit rollback.')
        raise SystemExit(1)
