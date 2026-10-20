// Keep copies beside their sources so relative CSS/JS asset paths still resolve.
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');

const sources = [
  'style.css', 'editorial.css', 'dompurify.min.js', 'tacobout-author-profile.js', 'tacobout-author-profile-editor.js',
  'tacobout-masonry.js', 'tacobout-infinite-scroll.js', 'tacobout-alt-badge.js',
  'tacobout-header.js', 'tacobout-ui.js', 'tacobout-sidebar.js', 'tacobout-reviews.js',
  'seasonal/seasonal.css', 'seasonal/season-config.js', 'seasonal/controller.js',
];

function fingerprint(file, bytes) {
  const extension = path.extname(file);
  const hash = crypto.createHash('sha256').update(bytes).digest('hex').slice(0, 16);
  return `${file.slice(0, -extension.length)}.${hash}${extension}`;
}

function build(root, check = false) {
  const manifest = Object.fromEntries(sources.map(file => [file, fingerprint(file, fs.readFileSync(path.join(root, file)))]));
  const manifestPath = path.join(root, 'asset-manifest.json');
  const serialized = `${JSON.stringify(manifest, null, 2)}\n`;
  const stale = [];
  if (check) {
    if (!fs.existsSync(manifestPath) || fs.readFileSync(manifestPath, 'utf8') !== serialized) stale.push('asset-manifest.json');
    for (const [source, target] of Object.entries(manifest)) {
      if (!fs.existsSync(path.join(root, target)) || !fs.readFileSync(path.join(root, source)).equals(fs.readFileSync(path.join(root, target)))) stale.push(target);
    }
    if (stale.length) throw new Error(`Generated assets are stale: ${stale.join(', ')}. Run node scripts/build-assets.cjs and commit the output.`);
  } else {
    const previous = fs.existsSync(manifestPath) ? JSON.parse(fs.readFileSync(manifestPath, 'utf8')) : {};
    for (const [source, target] of Object.entries(manifest)) fs.copyFileSync(path.join(root, source), path.join(root, target));
    // Prune only names previously generated for our explicit source list.
    for (const source of sources) {
      const old = previous[source];
      if (old && old !== manifest[source] && path.dirname(old) === path.dirname(source) && fs.existsSync(path.join(root, old)) && old === fingerprint(source, fs.readFileSync(path.join(root, old)))) fs.unlinkSync(path.join(root, old));
    }
    fs.writeFileSync(manifestPath, serialized);
  }
  return manifest;
}

if (require.main === module) {
  try {
    const manifest = build(path.resolve(__dirname, '..'), process.argv.includes('--check'));
    console.log(`${Object.keys(manifest).length} fingerprinted frontend assets ${process.argv.includes('--check') ? 'verified' : 'built'}.`);
  } catch (error) { console.error(error.message); process.exitCode = 1; }
}
module.exports = { sources, fingerprint, build };
