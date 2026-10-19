// Isolated browser regression: no WordPress account or live content writes.
const { chromium, webkit } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.join(__dirname, '..');
const css = fs.readFileSync(path.join(root, 'trove.css'), 'utf8');
const cover = (w, h) => 'data:image/svg+xml,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}"><path fill="#35243e" d="M0 0h${w}v${h}H0z"/><path fill="#f2a157" d="M0 0h${w}v20H0zM0 ${h - 20}h${w}v20H0z"/></svg>`);
const card = (image, title) => `<a class="tacobout-trove-card" href="https://trove.schweg.xyz/books/201-you-weren-t-meant-to-be-human-2025">${image ? `<img class="tacobout-trove-card__cover" alt="" width="96" height="128" src="${image}">` : ''}<span class="tacobout-trove-card__body"><span class="tacobout-trove-card__label">From my media collection</span><span class="tacobout-trove-card__title">${title}</span><span class="tacobout-trove-card__description">Author: Andrew Joseph White · Publisher: Titan Books Limited · Year: 2025</span><span class="tacobout-trove-card__note">A note with a verylongunbrokentextthatmustwrapwithoutmovingthecover.</span><span class="tacobout-trove-card__cta">View on Trove ↗</span></span></a>`;
(async () => {
  let cases = 0;
  for (const [name, engine] of Object.entries({ chromium, webkit })) {
    const executablePath = process.env[name.toUpperCase() + '_EXECUTABLE'];
    const browser = await engine.launch({ headless: true, ...(executablePath ? { executablePath } : {}) });
    const page = await browser.newPage({ reducedMotion: 'reduce' });
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    for (const width of [320, 375, 390, 768]) {
      await page.setViewportSize({ width, height: 900 });
      for (const dark of [false, true]) {
        // Structural inline CSS must work even if external theme CSS is stale/missing.
        await page.setContent(`<style>body{margin:16px;font:18px/1.7 sans-serif;background:${dark ? '#101015' : '#f8f6fb'}}.wp-block-post-content a{color:purple;text-decoration:underline}.wp-block-post-content img{height:auto;max-width:100%}${dark ? ':root{--taco-fg:#f4f2f7;--taco-surface:#191820;--taco-border:#45414d;--taco-muted:#bab5c3;--taco-accent:#c3a4ff}' : ''}${css}</style><main class="wp-block-post-content">${card(cover(200, 310), "You Weren’t Meant to Be Human (2025)")}${card(cover(250, 250), 'Square album artwork')}${card('', 'No image — a longer title with punctuation, accents, and an accessible destination')}</main>`);
        await page.locator('img').evaluateAll(images => Promise.all(images.map(image => image.decode())));
        const metrics = await page.locator('.tacobout-trove-card').evaluateAll(cards => cards.map(a => {
          const image = a.querySelector('img'), r = a.getBoundingClientRect(), body = a.querySelector('.tacobout-trove-card__body').getBoundingClientRect();
          return { underline: getComputedStyle(a).textDecorationLine, display: getComputedStyle(a).display, overflow: a.scrollWidth > a.clientWidth, bodyFits: body.right <= r.right && body.bottom <= r.bottom, imageRatio: image ? image.width / image.height : null, sourceRatio: image ? image.naturalWidth / image.naturalHeight : null };
        }));
        for (const m of metrics) {
          assert.equal(m.display, 'flex'); assert.equal(m.underline, 'none'); assert.equal(m.overflow, false); assert(m.bodyFits);
          if (m.imageRatio) assert(Math.abs(m.imageRatio - m.sourceRatio) < .02, 'natural cover ratio');
        }
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
        await page.locator('.tacobout-trove-card').first().focus();
        await page.keyboard.press('Shift');
        assert(await page.locator('.tacobout-trove-card').first().evaluate(a => a === document.activeElement && getComputedStyle(a).outlineStyle !== 'none'));
        if (process.env.EMBED_QA_DIR && !dark) { fs.mkdirSync(process.env.EMBED_QA_DIR, { recursive: true }); await page.screenshot({ path: path.join(process.env.EMBED_QA_DIR, `fixture-${name}-${width}.png`) }); }
        cases++;
      }
    }
    await page.route('https://trove.schweg.xyz/**', route => route.fulfill({ contentType: 'text/html', body: '<h1>Linked Trove item</h1>' }));
    await page.keyboard.press('Enter');
    await page.waitForURL('https://trove.schweg.xyz/books/201-you-weren-t-meant-to-be-human-2025');
    assert.equal(await page.locator('h1').textContent(), 'Linked Trove item');
    assert.deepEqual(errors, []);
    await browser.close();
  }
  console.log(`${cases} responsive Chromium/WebKit cases passed (portrait, square, missing art, long text, keyboard, light/dark, stale external CSS).`);
})().catch(error => { console.error(error); process.exit(1); });
