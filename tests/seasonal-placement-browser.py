"""Run with Python Playwright installed; set CHROMIUM_EXECUTABLE for a system browser.
Uses isolated theme fixtures, without WordPress or production writes.
"""
import os
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
HTML = '''<!doctype html><html><head><meta name="viewport" content="width=device-width">
<style>body{margin:0}header{height:80px;position:sticky;top:0;z-index:2}main{max-width:1000px;margin:auto}p{line-height:2}footer{height:100px}</style>
<link rel="stylesheet" href="/seasonal/seasonal.css"></head><body>
<header><button class="tacobout-theme-control">Theme</button></header>
<main><h1>Floating over article content</h1>''' + ('<p>Plain article text without links. ' + 'Reading space. ' * 60 + '</p>') * 20 + '''</main>
<footer><button>Footer control</button></footer>
<script src="/seasonal/season-config.js"></script><script src="/seasonal/controller.js"></script></body></html>'''

with sync_playwright() as p:
    executable = os.environ.get('CHROMIUM_EXECUTABLE')
    browser = p.chromium.launch(**({'executable_path': executable} if executable else {}))
    # The shipped pack must stay dormant, even for returning Halloween visitors.
    for width in [390, 1440]:
        page = browser.new_page(viewport={'width': width, 'height': 900})
        page.add_init_script("localStorage.setItem('seasonal-theme:v1', 'halloween')")
        def ordinary_route(request):
            path = request.request.url.split('http://localhost')[1]
            if path == '/':
                request.fulfill(body=HTML, content_type='text/html')
            else:
                request.fulfill(path=str(ROOT / path.lstrip('/')))
        page.route('http://localhost/**', ordinary_route)
        page.clock.set_fixed_time('2026-10-20T12:00:00Z')
        page.goto('http://localhost/')
        page.wait_for_timeout(1200)
        assert page.locator('[data-seasonal-controls], .seasonal-overlay, .seasonal-atmosphere').count() == 0
        assert page.locator('html').get_attribute('data-season') is None
        evidence = ROOT / 'docs/screenshots/seasonal-retired'
        evidence.mkdir(parents=True, exist_ok=True)
        page.screenshot(path=str(evidence / f'ordinary-{width}.png'))
        page.close()
        print(f'PASS {width}px: shipped Halloween pack dormant with saved preference')
    for width in [320, 390, 768, 1440, 1920]:
        for reduced in ['reduce', 'no-preference']:
            page = browser.new_page(viewport={'width': width, 'height': 900}, reduced_motion=reduced)
            errors = []
            page.on('pageerror', lambda error: errors.append(str(error)))
            def route(request):
                path = request.request.url.split('http://localhost')[1]
                if path == '/':
                    request.fulfill(body=HTML, content_type='text/html')
                elif path == '/seasonal/season-config.js':
                    # Explicit test-only activation preserves coverage of the reusable pack.
                    config = (ROOT / path.lstrip('/')).read_text().replace('"enabled": false', '"enabled": true')
                    request.fulfill(body=config, content_type='application/javascript')
                else:
                    request.fulfill(path=str(ROOT / path.lstrip('/')))
            page.route('http://localhost/**', route)
            # Keep the seasonal boundary deterministic, including outside October.
            page.clock.set_fixed_time('2026-10-20T12:00:00Z')
            page.goto('http://localhost/')
            main_top = page.locator('main').evaluate('n => n.getBoundingClientRect().top')
            page.wait_for_timeout(1200)
            overlay = page.locator('.seasonal-overlay')
            assert page.locator('main').evaluate('n => n.getBoundingClientRect().top') == main_top
            assert page.locator('.seasonal-invitation-slot').count() == 0
            assert page.locator('.seasonal-character img').get_attribute('alt') == ''
            assert overlay.is_visible()
            assert page.locator('.seasonal-character img').bounding_box()['width'] >= 44
            if reduced == 'reduce':
                assert page.locator('.seasonal-character img').evaluate("n => getComputedStyle(n).animationName") == 'none'
            for scroll in [0, 500, 1500]:
                page.evaluate('(y) => scrollTo(0,y)', scroll)
                page.wait_for_timeout(80)
                box = page.locator('.seasonal-character img').bounding_box()
                assert box and box['y'] >= 0 and box['y'] + box['height'] <= 900, (width, scroll, box)
                assert overlay.evaluate("n => getComputedStyle(n).position") == 'fixed'
                assert overlay.get_attribute('data-docked') is None
                dismiss_box = page.get_by_role('button', name='Dismiss Halloween character', exact=True).bounding_box()
                assert dismiss_box and dismiss_box['x'] >= 0 and dismiss_box['x'] + dismiss_box['width'] <= width
                assert dismiss_box['y'] >= 0 and dismiss_box['y'] + dismiss_box['height'] <= 900
                assert page.get_by_role('button', name='Dismiss Halloween character', exact=True).evaluate("n => { const r=n.getBoundingClientRect();return n===document.elementFromPoint(r.x+r.width/2,r.y+r.height/2); }")
            page.evaluate('scrollTo(0,0)')
            page.wait_for_timeout(80)
            page.get_by_role('button', name='Turn on Halloween mode', exact=True).click()
            assert page.locator('html').get_attribute('data-season') == 'halloween'
            dismiss = page.get_by_role('button', name='Dismiss Halloween character', exact=True)
            dismiss.click()
            assert overlay.is_hidden()
            if page.get_by_role('button', name='Appearance', exact=True).get_attribute('aria-expanded') != 'true':
                page.get_by_role('button', name='Appearance', exact=True).click()
            page.get_by_role('button', name='Show Halloween character', exact=True).click()
            assert overlay.is_visible()
            page.get_by_role('button', name='Appearance', exact=True).click()
            page.get_by_role('button', name='Restore original appearance', exact=True).click()
            assert page.locator('html').get_attribute('data-season') is None
            if width == 1440:
                page.get_by_role('button', name='Appearance', exact=True).click()
                page.get_by_role('button', name='Show Halloween character', exact=True).click()
                session = page.context.new_cdp_session(page)
                session.send('Emulation.setPageScaleFactor', {'pageScaleFactor': 2})
                page.wait_for_timeout(100)
                box = page.locator('.seasonal-character img').bounding_box()
                assert box and box['y'] >= 0 and box['y'] + box['height'] <= 900, (width, scroll, box)
                assert page.evaluate('visualViewport.width') == 720
                if overlay.is_visible():
                    box = overlay.bounding_box()
                    assert box['x'] >= 0 and box['x'] + box['width'] <= 720
                session.send('Emulation.setPageScaleFactor', {'pageScaleFactor': 1})
                page.set_viewport_size({'width': 720, 'height': 900})
                page.wait_for_timeout(100)
                assert overlay.is_visible()
            assert not errors, errors
            page.close()
            print(f'PASS {width}px motion={reduced}: visible floating, reachable X, no layout gap, activation, restore, undo')
    browser.close()
