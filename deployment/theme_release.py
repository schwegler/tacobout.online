"""Deterministic theme-only artifacts. Never execute code from downloaded artifacts."""
import hashlib, io, json, re, stat, subprocess, zipfile
from pathlib import Path, PurePosixPath

SLUG = 'tacobout.online-main'
MAX_FILE = 32 * 1024 * 1024
MAX_TOTAL = 128 * 1024 * 1024
MAX_FILES = 5000
FORBIDDEN = {'wp-config.php', 'uploads', 'plugins', '.git', '.env'}
ROOT_FILES = {'functions.php', 'index.php', 'theme.json', 'asset-manifest.json', 'LICENSE', 'README.md', 'screenshot.png'}
DIRECTORIES = {'assets', 'inc', 'parts', 'patterns', 'seasonal', 'templates'}

class Refused(ValueError):
    """Fail closed without dumping remote paths, credentials or artifact content."""


def require(ok, message):
    if not ok:
        raise Refused(message)


def sha(data):
    return hashlib.sha256(data).hexdigest()


def canonical(value):
    return json.dumps(value, sort_keys=True, separators=(',', ':'), ensure_ascii=True).encode()


def hex_value(value, length=64):
    require(isinstance(value, str) and re.fullmatch('[0-9a-f]{%d}' % length, value), 'Invalid immutable digest/commit')
    return value


def relative(name, managed=False):
    require(isinstance(name, str) and 0 < len(name) <= 512 and '\\' not in name, 'Invalid relative path')
    p = PurePosixPath(name)
    require(not p.is_absolute() and str(p) == name and all(x not in {'.', '..', ''} for x in name.split('/')), 'Unsafe relative path')
    require(not any(ord(x) < 32 or ord(x) == 127 for x in name), 'Control character in path')
    require(not any(x in FORBIDDEN or x.startswith('.tacobout-deploy-') for x in p.parts), 'Forbidden theme path')
    require(len(p.parts) <= 12, 'Theme path too deep')
    if managed:
        require(re.fullmatch(r'[A-Za-z0-9_.\-/]+', name), 'Unsupported managed path characters')
        require(p.parts[0] in DIRECTORIES or (len(p.parts) == 1 and (name in ROOT_FILES or p.suffix in {'.css', '.js'})), 'Not a runtime theme path')
    return name


def describe(files):
    return {p: {'sha256': sha(data), 'size': len(data), 'mode': mode} for p, (data, mode) in sorted(files.items())}


def digest(files):
    return sha(canonical(files))


def version(data):
    match = re.search(rb'^Version:\s*(\d+)\.(\d+)\.(\d+)\s*$', data, re.M)
    require(match is not None, 'Theme must have a numeric three-part Version header')
    return tuple(map(int, match.groups()))


def build(repo, output):
    repo, output = Path(repo), Path(output)
    commit = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=repo, text=True).strip()
    hex_value(commit, 40)
    files = {}
    tree = subprocess.check_output(['git', 'ls-tree', '-rz', 'HEAD'], cwd=repo)
    for entry in tree.split(b'\0'):
        if not entry:
            continue
        meta, raw_name = entry.split(b'\t', 1)
        name = raw_name.decode()
        p = PurePosixPath(name)
        if not (p.parts[0] in DIRECTORIES or (len(p.parts) == 1 and (name in ROOT_FILES or p.suffix in {'.css', '.js'}))):
            continue
        relative(name, True)
        mode, kind, _ = meta.decode().split()
        require(kind == 'blob' and mode == '100644', 'Non-regular/executable runtime source rejected')
        data = subprocess.check_output(['git', 'show', commit + ':' + name], cwd=repo)
        files[name] = (data, 0o644)
    require('style.css' in files and 'functions.php' in files and 'templates/author.html' in files, 'Incomplete theme')
    output.mkdir(parents=True, exist_ok=True)
    payload = io.BytesIO()
    with zipfile.ZipFile(payload, 'w', zipfile.ZIP_DEFLATED) as z:
        for name, (data, _) in sorted(files.items()):
            info = zipfile.ZipInfo(SLUG + '/' + name, (1980, 1, 1, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            z.writestr(info, data)
    archive = payload.getvalue()
    manifest = {'schema': 1, 'theme_slug': SLUG, 'source_commit': commit, 'zip_sha256': sha(archive), 'files': describe(files)}
    (output / 'theme.zip').write_bytes(archive)
    (output / 'release.json').write_bytes(canonical(manifest))
    load(output, commit, manifest['zip_sha256'])
    return manifest


def load(directory, expected_commit, expected_zip_sha):
    hex_value(expected_commit, 40); hex_value(expected_zip_sha)
    directory = Path(directory)
    require(not (directory / 'theme.zip').is_symlink() and not (directory / 'release.json').is_symlink(), 'Artifact links rejected')
    require((directory / 'theme.zip').stat().st_size <= MAX_TOTAL and (directory / 'release.json').stat().st_size <= 8 * 1024 * 1024, 'Artifact file size limit')
    archive = (directory / 'theme.zip').read_bytes()
    require(len(archive) <= MAX_TOTAL and sha(archive) == expected_zip_sha, 'Release ZIP checksum mismatch')
    manifest = json.loads((directory / 'release.json').read_bytes())
    require(manifest.get('schema') == 1 and manifest.get('theme_slug') == SLUG and manifest.get('source_commit') == expected_commit and manifest.get('zip_sha256') == expected_zip_sha, 'Release identity mismatch')
    files, total = {}, 0
    with zipfile.ZipFile(io.BytesIO(archive)) as z:
        require(0 < len(z.infolist()) <= MAX_FILES, 'Invalid archive count')
        for entry in z.infolist():
            require(entry.filename.startswith(SLUG + '/'), 'Archive root mismatch')
            name = relative(entry.filename[len(SLUG) + 1:], True)
            require(name not in files and not entry.is_dir() and entry.file_size <= MAX_FILE, 'Duplicate, directory or oversized artifact entry')
            require(stat.S_IFMT(entry.external_attr >> 16) in {0, stat.S_IFREG}, 'Archive links/devices rejected')
            total += entry.file_size
            require(total <= MAX_TOTAL, 'Artifact uncompressed size limit')
            files[name] = (z.read(entry), 0o644)
    require(describe(files) == manifest['files'], 'Release file manifest mismatch')
    require('style.css' in files and 'functions.php' in files and 'theme.json' in files, 'Incomplete runtime theme')
    version(files['style.css'][0])
    assets = json.loads(files['asset-manifest.json'][0])
    require(isinstance(assets, dict) and assets, 'Asset manifest missing')
    for source, target in assets.items():
        require(PurePosixPath(source).suffix in {'.css', '.js'}, 'Only frontend CSS/JS may be HTTP-verified')
        relative(source, True); relative(target, True)
        require(source in files and target in files and files[source][0] == files[target][0], 'Generated asset mismatch')
        base, ext = source.rsplit('.', 1)
        require(target == base + '.' + sha(files[source][0])[:16] + '.' + ext, 'Generated filename digest mismatch')
    return manifest, files


if __name__ == '__main__':
    import argparse
    p = argparse.ArgumentParser(); p.add_argument('repo'); p.add_argument('output')
    args = p.parse_args()
    result = build(args.repo, args.output)
    print(json.dumps({k: result[k] for k in ['source_commit', 'zip_sha256']}))
