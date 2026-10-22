"""Generate public updater metadata only from a verified, version-matched archive."""
import json, re, sys
from pathlib import Path
from theme_release import load, require, version, SLUG

URI = 'https://github.com/schwegler/tacobout.online'

def generate(directory, tag):
    directory = Path(directory)
    manifest = json.loads((directory / 'release.json').read_bytes())
    _, files = load(directory, manifest['source_commit'], manifest['zip_sha256'])
    css = files['style.css'][0]
    number = '.'.join(map(str, version(css)))
    require(tag == 'v' + number, 'Release tag must match theme version')
    def header(name):
        match = re.search(rb'^' + name.encode() + rb':\s*([^\r\n]+)', css, re.M)
        require(match is not None, 'Missing release header')
        return match[1].decode().strip()
    require(header('Update URI') == URI, 'Unexpected update identity')
    metadata = dict(schema=1, id=URI, theme=SLUG, version=number,
                    url=URI + '/releases/tag/' + tag,
                    package=URI + '/releases/download/' + tag + '/theme.zip',
                    requires=header('Requires at least'), requires_php=header('Requires PHP'))
    (directory / 'tacobout-update.json').write_text(json.dumps(metadata, sort_keys=True) + '\n')
    return metadata

if __name__ == '__main__':
    generate(*sys.argv[1:])
