const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { sources, fingerprint, build } = require('../scripts/build-assets.cjs');

test('release asset manifest and every generated file match the sources', () => {
  assert.equal(Object.keys(build(path.resolve(__dirname, '..'), true)).length, sources.length);
});

test('a changed asset gets a new cache key even when every query is stripped', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'tacobout-assets-'));
  try {
    for (const source of sources) {
      fs.mkdirSync(path.dirname(path.join(root, source)), { recursive: true });
      fs.writeFileSync(path.join(root, source), `original ${source}`);
    }
    const first = build(root);
    const firstCache = new Map(Object.entries(first).map(([source, target]) => [target, fs.readFileSync(path.join(root, source), 'utf8')]));
    fs.appendFileSync(path.join(root, 'style.css'), '\n/* new mobile navigation */');
    assert.throws(() => build(root, true), /Generated assets are stale/);
    const second = build(root);
    assert.notEqual(second['style.css'], first['style.css']);
    assert.equal(firstCache.has(second['style.css']), false, 'A warm browser cannot reuse old CSS at the new filename.');
    assert.equal(second['tacobout-header.js'], first['tacobout-header.js'], 'Unchanged scripts keep stable URLs.');
    assert.equal(fs.existsSync(path.join(root, first['style.css'])), false, 'Only superseded generated copies are pruned.');
    fs.writeFileSync(path.join(root, second['style.css']), 'corrupt');
    assert.throws(() => build(root, true), /Generated assets are stale/);
  } finally { fs.rmSync(root, { recursive: true, force: true }); }
});

test('seasonal controller stays beside its relative PNG and config assets', () => {
  const filename = fingerprint('seasonal/controller.js', Buffer.from('current script'));
  const base = new URL('.', `https://example.com/theme/${filename}`);
  assert.equal(new URL('mascot.png', base).href, 'https://example.com/theme/seasonal/mascot.png');
});
