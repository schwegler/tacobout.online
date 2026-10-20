"""Run with Python Playwright installed; set CHROMIUM_EXECUTABLE for a system browser.
Uses isolated theme fixtures, without WordPress or production writes.
"""
import os
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
HTML = '''<!doctype html><html><head><meta name="viewport" content="width=device-width">
<style>body{margin:0}header{height:80px}main{max-width:1000px;margin:auto}p{line-height:2}footer{height:100px}</style>
<link rel="stylesheet" href="/seasonal/seasonal.css"></head><body>
<header><button class="tacobout-theme-control">Theme</button></header>
<main><h1>Reading without overlap</h1>''' + ('<p>Plain article text without links. ' + 'Reading space. ' * 60 + '</p>') * 20 + '''</main>
<footer><button>Footer control</button></footer>
<script src="/seasonal/season-config.js"></script><script src="/seasonal/controller.js"></script></body></html>'''

with sync_playwright() as p:
    executable = os.environ.get('CHROMIUM_EXECUTABLE')
    browser = p.chromium.launch(**({'executable_path': executable} if executable else {}))
    for width in [320, 390, 768, 1440, 1920]:
        for reduced in ['reduce', 'no-preference']:
            page = browser.new_page(viewport={'width': width, 'height': 900}, reduced_motion=reduced)
            errors = []
            page.on('pageerror', lambda error: errors.append(str(error)))
            def route(request):
                path = request.request.url.split('http://localhost')[1]
                if path == '/':
                    request.fulfill(body=HTML, content_type='text/html')
                else:
                    request.fulfill(path=str(ROOT / path.lstrip('/')))
            page.route('http://localhost/**', route)
            # Keep the seasonal boundary deterministic, including outside October.
            page.clock.set_fixed_time('2026-10-20T12:00:00Z')
            page.goto('http://localhost/')
            page.wait_for_timeout(1200)
            overlay = page.locator('.seasonal-overlay')
            assert overlay.is_visible()
            if width < 1440:
                assert overlay.get_attribute('data-docked') == 'true'
            else:
                assert overlay.get_attribute('data-docked') is None
            if reduced == 'reduce':
                assert page.locator('.seasonal-character img').evaluate("n => getComputedStyle(n).animationName") == 'none'
            for scroll in [0, 500, 1500]:
                page.evaluate('(y) => scrollTo(0,y)', scroll)
                page.wait_for_timeout(80)
                assert page.evaluate('''() => {
                    const o=document.querySelector('.seasonal-overlay'),a=o.getBoundingClientRect();
                    return ![...document.querySelectorAll('main,header,footer')].some(n=>{
                        const b=n.getBoundingClientRect();
                        return a.left<b.right&&a.right>b.left&&a.top<b.bottom&&a.bottom>b.top;
                    });
                }'''), (width, reduced, scroll)
            page.evaluate('scrollTo(0,0)')
            page.wait_for_timeout(80)
            page.get_by_role('button', name='Turn on Halloween mode', exact=True).click()
            assert page.locator('html').get_attribute('data-season') == 'halloween'
            dismiss = page.get_by_role('button', name='Dismiss Halloween character', exact=True)
            dismiss.focus()
            page.keyboard.press('Enter')
            assert overlay.is_hidden()
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
                assert page.evaluate('visualViewport.width') == 720
                if overlay.get_attribute('data-docked') is None:
                    box = overlay.bounding_box()
                    assert box['x'] >= 0 and box['x'] + box['width'] <= 720
                session.send('Emulation.setPageScaleFactor', {'pageScaleFactor': 1})
                page.set_viewport_size({'width': 720, 'height': 900})
                page.wait_for_timeout(100)
                assert overlay.get_attribute('data-docked') == 'true'
            assert not errors, errors
            page.close()
            print(f'PASS {width}px motion={reduced}: clearance, activation, dismissal, restore, undo')
    browser.close()
