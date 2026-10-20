"""SFTP-only, fail-closed theme snapshots and explicit deploy/rollback plans.
No remote command execution, database operations, mirror deletes or directory swap.
"""
import base64, datetime as dt, errno, io, json, os, re, secrets, stat, zipfile
from contextlib import contextmanager
from pathlib import Path, PurePosixPath
from urllib.parse import urlparse, urljoin, quote
from urllib.request import Request, build_opener, HTTPRedirectHandler
import paramiko
from cryptography.hazmat.primitives.ciphers.aead import AESGCM
from theme_release import (SLUG, MAX_FILE, MAX_TOTAL, MAX_FILES, Refused, require,
                           sha, canonical, relative, describe, digest, hex_value, load, version)

LOCK = '.tacobout-deploy-lock'
AAD = b'tacobout-theme-backup-v1'


def config_file(path, writing=False):
    c = json.loads(Path(path).read_bytes())
    require(c.get('schema') == 1 and c.get('theme_slug') == SLUG, 'Invalid site policy')
    root = c.get('theme_root')
    require(isinstance(root, str) and root.startswith('/') and str(PurePosixPath(root)) == root and not root.endswith('/'), 'Verified SFTP theme root is not configured')
    require(all(x not in {'.', '..', ''} for x in root[1:].split('/')) and re.fullmatch(r'/[A-Za-z0-9_./-]+', root), 'Invalid remote root')
    require(root.endswith('/wp-content/themes/' + SLUG), 'Root must be the verified active theme directory')
    require(c.get('active_theme_verified_at') and c.get('site_url') == 'https://tacobout.online' and c.get('public_theme_url') == 'https://tacobout.online/wp-content/themes/' + SLUG, 'Active site/theme identity unverified')
    require(c.get('approval_mode') in {'environment-reviewers', 'manual-dispatch'}, 'Invalid approval policy')
    require(isinstance(c.get('smoke_paths'), list) and c['smoke_paths'], 'Smoke routes required')
    for p in c['smoke_paths']:
        require(isinstance(p, str) and p.startswith('/') and not p.startswith('//') and not any(x in p for x in ['..', '?', '#', '\\']) and not any(ord(x)<32 for x in p), 'Invalid smoke path')
    if writing:
        require(c.get('enabled') is True, 'Production writes are disabled')
    return c


def confirm_backup(reference, timestamp, confirmation, now=None):
    require(confirmation == 'CONFIRM CURRENT EASYWP BACKUP AND FROZEN THEME EDITS', 'Explicit host-backup/frozen-edits confirmation missing')
    require(isinstance(reference, str) and 3 <= len(reference) <= 200 and all(32 <= ord(x) < 127 for x in reference), 'Host backup reference required')
    require(re.fullmatch(r'\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ', timestamp or ''), 'Host backup must have an exact UTC timestamp')
    when = dt.datetime.strptime(timestamp, '%Y-%m-%dT%H:%M:%SZ').replace(tzinfo=dt.timezone.utc)
    now = now or dt.datetime.now(dt.timezone.utc)
    require(dt.timedelta(0) <= now - when <= dt.timedelta(hours=24), 'Host backup is future-dated or older than 24 hours')
    return {'reference': reference, 'created_at': timestamp, 'confirmed_by': os.environ.get('GITHUB_ACTOR', 'local-fixture')}


@contextmanager
def connection(host, port, username, password, known_hosts):
    require(re.fullmatch(r'[A-Za-z0-9.-]+', host or '') and isinstance(port, int) and 1 <= port <= 65535, 'Invalid SFTP endpoint')
    require(username and password and known_hosts, 'Approved SFTP credentials and known_hosts required')
    # Only explicitly supplied, independently verified host keys. No agent, local
    # keys, system key trust, TOFU, AutoAddPolicy, shell, exec or port forwarding.
    client = paramiko.SSHClient(); client.set_missing_host_key_policy(paramiko.RejectPolicy())
    import tempfile
    with tempfile.NamedTemporaryFile(mode='w', prefix='theme-host-key-', delete=True) as f:
        os.chmod(f.name, 0o600); f.write(known_hosts); f.flush(); client.load_host_keys(f.name)
    try:
        client.connect(host, port=port, username=username, password=password, look_for_keys=False,
                       allow_agent=False, timeout=20, banner_timeout=20, auth_timeout=20, channel_timeout=30)
        with client.open_sftp() as sftp:
            sftp.get_channel().settimeout(30)
            yield sftp
    finally:
        client.close()


class ThemeRemote:
    def __init__(self, sftp, root):
        self.sftp, self.root = sftp, root
        self.check_directory(root)
        require(sftp.normalize(root) == root, 'Configured SFTP root is not canonical')

    def check_directory(self, path):
        require(path == self.root or path.startswith(self.root + '/') or self.root.startswith(path.rstrip('/') + '/'), 'Directory outside target ancestry')
        current = ''
        for part in PurePosixPath(path).parts[1:]:
            current += '/' + part
            require(stat.S_ISDIR(self.sftp.lstat(current).st_mode), 'Symlink or non-directory in target ancestry')

    def path(self, name):
        return self.root + '/' + relative(name)

    def read(self, name):
        path = self.path(name); self.check_directory(str(PurePosixPath(path).parent))
        attr = self.sftp.lstat(path)
        require(stat.S_ISREG(attr.st_mode) and attr.st_size <= MAX_FILE, 'Non-regular or oversized theme file')
        with self.sftp.open(path, 'rb') as f:
            data = f.read(MAX_FILE + 1)
        require(len(data) <= MAX_FILE and len(data) == attr.st_size, 'Theme file changed during read')
        return data, stat.S_IMODE(attr.st_mode)

    def snapshot(self, ignore=()):
        files = {}; total = 0
        def walk(prefix=''):
            nonlocal total
            self.check_directory(self.root + ('/' + prefix if prefix else ''))
            for attr in self.sftp.listdir_attr(self.root + ('/' + prefix if prefix else '')):
                name = prefix + '/' + attr.filename if prefix else attr.filename
                require('/' not in attr.filename and attr.filename not in {'.', '..'}, 'Invalid SFTP directory entry')
                if name in ignore:
                    continue
                relative(name)
                if stat.S_ISDIR(attr.st_mode):
                    walk(name)
                else:
                    require(stat.S_ISREG(attr.st_mode), 'Theme contains symlink/device; reconcile before deployment')
                    files[name] = self.read(name); total += len(files[name][0])
                    require(len(files) <= MAX_FILES and total <= MAX_TOTAL, 'Theme backup limit exceeded')
        walk()
        require('style.css' in files and 'functions.php' in files, 'Target does not look like an installed theme')
        return files

    def ensure_parent(self, name):
        parts = PurePosixPath(relative(name, True)).parts[:-1]; prefix = self.root
        for part in parts:
            prefix += '/' + part
            try:
                attr = self.sftp.lstat(prefix)
                require(stat.S_ISDIR(attr.st_mode), 'Destination parent is a link/file')
            except OSError as e:
                if e.errno != errno.ENOENT:
                    raise
                self.sftp.mkdir(prefix, 0o755)
            self.check_directory(prefix)

    def replace(self, name, data, mode, token):
        relative(name, True)
        require(mode in {0o600, 0o640, 0o644}, 'Refusing unsafe file permissions')
        self.ensure_parent(name)
        p = PurePosixPath(name)
        temporary = str(p.parent / ('.tacobout-deploy-' + token + '-' + p.name)) if str(p.parent) != '.' else '.tacobout-deploy-' + token + '-' + p.name
        path = self.root + '/' + temporary
        # Preserve extension, especially .php: never stage PHP as a public .txt backup.
        try:
            with self.sftp.open(path, 'wx') as f:
                f.chmod(0o600); f.write(data); f.flush()
            with self.sftp.open(path, 'rb') as f:
                require(sha(f.read(MAX_FILE + 1)) == sha(data), 'Staged bytes failed verification')
            self.sftp.chmod(path, mode)
            self.sftp.posix_rename(path, self.path(name))
            require(self.read(name) == (data, mode), 'Published bytes/mode failed SFTP readback')
        finally:
            try:
                self.sftp.remove(path)
            except OSError as e:
                if e.errno != errno.ENOENT:
                    raise

    def probe_replace(self, token):
        a = self.root + '/.tacobout-deploy-' + token + '-probe-a.txt'
        b = self.root + '/.tacobout-deploy-' + token + '-probe-b.txt'
        created = []
        try:
            for path, content in [(a, b'a'), (b, b'b')]:
                with self.sftp.open(path, 'wx') as f:
                    f.chmod(0o600); f.write(content)
                created.append(path)
            self.sftp.posix_rename(a, b)
            with self.sftp.open(b, 'rb') as f:
                require(f.read() == b'a', 'SFTP overwrite probe failed')
        finally:
            for path in created:
                try:
                    self.sftp.remove(path)
                except OSError as e:
                    if e.errno != errno.ENOENT:
                        raise


def changes(before, desired):
    return {p: {'from': before.get(p, {}).get('sha256'), 'to': meta['sha256']} for p, meta in sorted(desired.items()) if before.get(p, {}).get('sha256') != meta['sha256']}


def deploy_plan(c, baseline, before, release, desired, expected_live):
    actual = describe(before); hex_value(expected_live)
    require(baseline.get('schema') == 1 and baseline.get('status') == 'reconciled' and baseline.get('snapshot_reference'), 'Live drift is unresolved')
    require(actual == baseline.get('files') and digest(actual) == baseline.get('digest') == expected_live, 'Live theme drift detected')
    require(release['source_commit'] == baseline.get('approved_commit') and release['zip_sha256'] == baseline.get('approved_zip_sha256'), 'Release not approved by reviewed live baseline')
    delta = changes(actual, describe(desired))
    require(delta and delta == baseline.get('approved_replacements'), 'Unreviewed file replacements')
    require(version(desired['style.css'][0]) > version(before['style.css'][0]), 'Changed deployment requires a higher theme version')
    for name in delta:
        relative(name, True)
        require(name not in before or before[name][1] in {0o600, 0o640, 0o644}, 'Existing managed file has unsafe permissions')
    return {'schema': 1, 'operation': 'deploy', 'theme_root': c['theme_root'], 'source_commit': release['source_commit'], 'zip_sha256': release['zip_sha256'], 'before': actual, 'changes': delta, 'remove': []}


def key_bytes(value):
    try:
        key = base64.b64decode(value, validate=True)
    except Exception as e:
        raise Refused('Backup encryption key must be base64') from e
    require(len(key) == 32, 'Backup encryption key must be 32 bytes')
    return key


def seal(before, plan, key):
    payload = io.BytesIO()
    with zipfile.ZipFile(payload, 'w', zipfile.ZIP_DEFLATED) as z:
        z.writestr('plan.json', canonical(plan))
        for name, (data, _) in sorted(before.items()):
            relative(name); z.writestr('files/' + name, data)
    nonce = secrets.token_bytes(12)
    return b'TBKP1' + nonce + AESGCM(key).encrypt(nonce, payload.getvalue(), AAD)


def unseal(cipher, key):
    require(cipher.startswith(b'TBKP1') and len(cipher) <= MAX_TOTAL + MAX_TOTAL // 10, 'Invalid encrypted backup')
    plain = AESGCM(key).decrypt(cipher[5:17], cipher[17:], AAD)
    files = {}; total = 0
    with zipfile.ZipFile(io.BytesIO(plain)) as z:
        require(len(z.infolist()) <= MAX_FILES + 1 and len(set(z.namelist())) == len(z.namelist()), 'Invalid backup archive')
        plan = json.loads(z.read('plan.json'))
        for entry in z.infolist():
            if entry.filename == 'plan.json':
                continue
            require(entry.filename.startswith('files/') and entry.file_size <= MAX_FILE, 'Invalid backup entry')
            name = relative(entry.filename[6:]); total += entry.file_size
            require(total <= MAX_TOTAL and name in plan['before'], 'Backup size/manifest mismatch')
            files[name] = (z.read(entry), plan['before'][name]['mode'])
    require(describe(files) == plan['before'], 'Backup bytes do not match its authenticated plan')
    return plan, files


def rollback_plan(c, original, original_before, current, expected_live, expected_commit):
    actual = describe(current)
    require(original.get('schema') == 1 and original.get('operation') == 'deploy' and original['theme_root'] == c['theme_root'] and original['source_commit'] == expected_commit, 'Not a backup for this release/target')
    require(digest(actual) == hex_value(expected_live), 'Rollback current-state digest mismatch; inspect first')
    desired = {}; remove = []; delta = {}
    for name, action in original['changes'].items():
        relative(name, True)
        current_hash = actual.get(name, {}).get('sha256')
        require(current_hash in {action['from'], action['to']}, 'Managed file changed after deployment; reconcile manually')
        if name in original_before:
            desired[name] = original_before[name]
            if current_hash != action['from']:
                delta[name] = {'from': current_hash, 'to': action['from']}
        elif current_hash is not None:
            # Explicit rollback only: remove a release-created file iff its bytes
            # still match this exact release. Never remove unrelated/unknown files.
            remove.append(name)
    return {'schema': 1, 'operation': 'rollback', 'theme_root': c['theme_root'], 'source_commit': expected_commit, 'before': actual, 'changes': delta, 'remove': remove}, desired


def apply(remote, plan, desired, backup_receipt, durable_backup, audit_path):
    require(backup_receipt.get('plan_sha256') == sha(canonical(plan)), 'Plan changed after backup')
    require(sha(durable_backup) == backup_receipt.get('cipher_sha256'), 'Durable encrypted backup readback mismatch')
    require(backup_receipt.get('artifact_id', '').isdigit(), 'Remote backup artifact confirmation missing')
    require(describe(remote.snapshot()) == plan['before'], 'Theme changed after backup; no deployment')
    token = secrets.token_hex(12); lock = remote.root + '/' + LOCK
    acquired = False; done = []
    try:
        with remote.sftp.open(lock, 'wx') as f:
            acquired = True; f.chmod(0o600); f.write(token.encode())
        require(describe(remote.snapshot(ignore=(LOCK,))) == plan['before'], 'Theme changed while acquiring lock')
        # Probe is after durable backup; failure does not replace a managed file.
        remote.probe_replace(token)
        def order(name):
            if name == 'functions.php': return (3, name)
            if name == 'asset-manifest.json': return (4, name)
            if name.startswith(('templates/', 'parts/', 'patterns/')): return (5, name)
            return (1 if name.startswith('inc/') else 0, name)
        for name in sorted(plan['changes'], key=order):
            before = plan['before'].get(name)
            try:
                current = remote.read(name)
            except OSError as e:
                if e.errno != errno.ENOENT: raise
                current = None
            require((current is None and before is None) or (current is not None and before is not None and sha(current[0]) == before['sha256'] and current[1] == before['mode']), 'Managed file changed during deployment')
            data, mode = desired[name]
            require(sha(data) == plan['changes'][name]['to'], 'Desired file changed after plan')
            remote.replace(name, data, before['mode'] if before else mode, token)
            done.append(name)
            Path(audit_path).write_bytes(canonical({'state': 'partial', 'completed': done, 'plan_sha256': sha(canonical(plan))}))
        for name in plan['remove']:
            current = remote.read(name)
            require(sha(current[0]) == plan['before'][name]['sha256'], 'Rollback-created file changed; will not delete')
            remote.sftp.remove(remote.path(name)); done.append(name)
        expected = dict(plan['before'])
        for name in plan['changes']:
            data, mode = desired[name]; expected[name] = {'sha256': sha(data), 'size': len(data), 'mode': expected.get(name, {}).get('mode', mode)}
        for name in plan['remove']: expected.pop(name)
        final = describe(remote.snapshot(ignore=(LOCK,)))
        require(final == expected, 'Final theme readback differs; use recorded backup/rollback')
        Path(audit_path).write_bytes(canonical({'state': 'sftp-verified', 'completed': done, 'final_digest': digest(final), 'plan_sha256': sha(canonical(plan))}))
    finally:
        if acquired:
            with remote.sftp.open(lock, 'rb') as f:
                require(f.read() == token.encode(), 'Lock ownership changed')
            remote.sftp.remove(lock)


class SameOriginRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        require(urlparse(newurl).netloc == urlparse(req.full_url).netloc and urlparse(newurl).scheme == 'https', 'Cross-origin/insecure HTTP redirect refused')
        return super().redirect_request(req, fp, code, msg, headers, newurl)


def http_read(url):
    with build_opener(SameOriginRedirect()).open(Request(url, headers={'Cache-Control': 'no-cache', 'User-Agent': 'TacoBout-theme-deploy/1'}), timeout=30) as r:
        require(r.status == 200, 'HTTP smoke did not return 200')
        data = r.read(MAX_FILE + 1); require(len(data) <= MAX_FILE, 'HTTP smoke response too large')
        return data


def smoke(c, desired=None):
    for path in c['smoke_paths']:
        body = http_read(urljoin(c['site_url'], path)).lower()
        require(not any(x in body for x in [b'fatal error:', b'there has been a critical error on this website']), 'WordPress error page detected')
    if desired:
        assets = json.loads(desired['asset-manifest.json'][0])
        for target in assets.values():
            relative(target, True)
            url = c['public_theme_url'] + '/' + quote(target, safe='/') + '?deploy=' + sha(desired[target][0])[:16]
            require(sha(http_read(url)) == sha(desired[target][0]), 'Published HTTP asset/cache mismatch')
