"""Style regression fixture by default; PROFILE_BASE_URL runs real local WP/AP integration.
External requests are blocked. Never point at production; local mode only accepts loopback.
"""
import os
from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright, expect
ROOT = Path(__file__).resolve().parents[1]
BASE = os.environ.get('PROFILE_BASE_URL', '')
if BASE and urlparse(BASE).hostname not in ['localhost', '127.0.0.1']:
    raise RuntimeError('Integration tests must use loopback WordPress, never production.')
ARTIFACTS = Path(os.environ.get('PROFILE_SCREENSHOTS', '/tmp/author-profile-proof'))
ARTIFACTS.mkdir(parents=True, exist_ok=True)
with sync_playwright() as p:
    browser = p.chromium.launch(**({'executable_path': os.environ['CHROMIUM_EXECUTABLE']} if os.environ.get('CHROMIUM_EXECUTABLE') else {}))
    for width in [1440, 1024, 768, 390, 320]:
        for theme in ['light', 'dark']:
            page = browser.new_page(viewport={'width': width, 'height': 1100}, reduced_motion='reduce')
            errors = []
            page.on('pageerror', lambda e: errors.append(str(e)))
            if not BASE:
                def route(req):
                    path = urlparse(req.request.url).path
                    if path.startswith('/author/'):
                        req.fulfill(path=str(ROOT / 'tests/fixtures/author-profile.html'), content_type='text/html')
                    else:
                        target = ROOT / path.lstrip('/')
                        if target.is_file(): req.fulfill(path=str(target))
                        else: req.fulfill(status=404, body='Not available in the static layout fixture')
                page.route('http://localhost/**', route)
            page.route('https://**/*', lambda r: r.abort())
            page.add_init_script("localStorage.setItem('seasonal-theme:v1','off')")
            page.goto((BASE or 'http://localhost') + '/author/schwegler/', wait_until='networkidle')
            page.evaluate('(theme)=>document.documentElement.dataset.theme=theme', theme)
            page.wait_for_timeout(100)
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), (width, theme, 'overflow')
            rail = page.locator('.tacobout-profile-rail').bounding_box()
            feed = page.locator('.tacobout-profile-timeline').bounding_box()
            if width > 800:
                assert rail['x'] + rail['width'] < feed['x']
                assert feed['width'] <= 713 and rail['width'] <= 249
            else:
                assert rail['y'] + rail['height'] <= feed['y'] + 1
            entries = page.locator('.tacobout-author-feed > .wp-block-post')
            boxes = [entries.nth(i).bounding_box() for i in range(entries.count())]
            assert all(a['y'] + a['height'] <= b['y'] + 2 for a,b in zip(boxes, boxes[1:])), 'chronology overlaps'
            assert page.locator('.tacobout-author-feed').evaluate('(n)=>getComputedStyle(n).display') == 'block'
            expect(page.locator('.ap-tabs__tab[aria-current="page"]')).to_have_text('Posts & Replies')
            assert page.locator('video[controls]').count() > 0
            assert all(n['height'] >= 44 for n in page.locator('.ap-tabs__tab, .activitypub-profile .wp-block-button__link').evaluate_all('(ns)=>ns.map(n=>n.getBoundingClientRect().toJSON())'))
            assert page.locator('.activitypub-profile__avatar').evaluate('(n)=>getComputedStyle(n).objectFit') == 'contain'
            assert page.locator('h1').count() == 1
            if BASE:
                follow = page.locator('.activitypub-profile .wp-block-button__link')
                follow.focus();page.keyboard.press('Enter')
                expect(page.locator('.activitypub-modal__overlay')).to_be_visible()
                page.wait_for_timeout(150)  # Native plugin installs focus trap/document listeners asynchronously.
                page.keyboard.press('Escape')
                expect(page.locator('.activitypub-modal__overlay')).to_be_hidden()
                video = page.locator('video').first
                video.evaluate('(v)=>{v.muted=true;v.load();return v.play()}')
                page.wait_for_timeout(150)
                assert video.evaluate('(v)=>v.currentTime') > 0
                video.evaluate('(v)=>v.pause()')
            assert not errors, errors
            page.locator('body').click(position={'x':2,'y':2})
            page.screenshot(path=str(ARTIFACTS / f'author-{width}-{theme}.png'))
            page.close()
    if BASE:
        page = browser.new_page(viewport={'width':1440,'height':1100})
        page.route('https://**/*', lambda r: r.abort())
        page.goto(BASE + '/author/schwegler/?filter=posts', wait_until='networkidle')
        expect(page.locator('.ap-tabs__tab[aria-current="page"]')).to_have_text('Posts')
        assert 'This local reply must disappear' not in page.locator('.tacobout-author-feed').inner_text()
        nextlink = page.locator('.wp-block-query-pagination-next')
        assert 'filter=posts' in nextlink.get_attribute('href')
        nextlink.click();page.wait_for_load_state('networkidle')
        assert '/page/2/' in page.url and 'filter=posts' in page.url
        expect(page.locator('.wp-block-query-pagination-previous')).to_be_visible()
        assert 'This local reply must disappear' not in page.locator('.tacobout-author-feed').inner_text()
        page.close()
    browser.close()
print('Author layout passed at 1440/1024/768/390/320 in light/dark; source order, controls, touch sizing and overflow checked.' + (' Native follow, playback, Posts filter and page 2 passed.' if BASE else ' Static native-markup fixture; federation actions require local integration mode.'))
