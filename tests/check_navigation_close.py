"""Rendered regression: pip install playwright; playwright install chromium webkit.
Run against a preview: python tests/check_navigation_close.py --url URL --output DIR.
"""
import argparse
import json
from pathlib import Path
from playwright.sync_api import sync_playwright, expect

parser = argparse.ArgumentParser()
parser.add_argument('--url', required=True)
parser.add_argument('--output', required=True)
parser.add_argument('--browsers', nargs='+', default=['chromium', 'webkit'])
parser.add_argument('--webkit-executable', help='Optional path to an existing Playwright WebKit launcher')
args = parser.parse_args()
out = Path(args.output)
out.mkdir(parents=True, exist_ok=True)
results = []
with sync_playwright() as pw:
    for engine in args.browsers:
        options = {'executable_path': args.webkit_executable} if engine == 'webkit' and args.webkit_executable else {}
        browser = getattr(pw, engine).launch(**options)
        for width, height in [(390, 844), (320, 844), (820, 1180), (1024, 768), (844, 390)]:
            context = browser.new_context(viewport={'width': width, 'height': height}, reduced_motion='reduce', color_scheme='dark')
            page = context.new_page()
            page.goto(args.url, wait_until='domcontentloaded')
            opener = page.get_by_role('button', name='Open menu', exact=True)
            opener.click()
            close = page.get_by_role('button', name='Close menu', exact=True)
            close.wait_for(state='visible')
            geometry = close.evaluate('''n => {
              const b=n.getBoundingClientRect(),i=n.querySelector('svg').getBoundingClientRect();
              return {width:b.width,height:b.height,iconWidth:i.width,iconHeight:i.height,
                dx:Math.abs((b.left+b.right-i.left-i.right)/2),dy:Math.abs((b.top+b.bottom-i.top-i.bottom)/2),
                appearance:getComputedStyle(n).appearance,name:n.getAttribute('aria-label')};
            }''')
            assert geometry['width'] >= 44 and geometry['height'] >= 44, geometry
            assert abs(geometry['width'] - geometry['height']) <= 1, geometry
            assert geometry['dx'] <= 1 and geometry['dy'] <= 1, geometry
            assert geometry['iconWidth'] == geometry['iconHeight'] == 24, geometry
            assert geometry['appearance'] == 'none' and geometry['name'] == 'Close menu', geometry
            modal = page.locator('.wp-block-navigation__responsive-container.is-menu-open')
            page.screenshot(path=str(out / f'{engine}-{width}x{height}.png'))
            visited = []
            for _ in range(30):
                page.keyboard.press('Tab')
                visited.append(page.evaluate('document.activeElement.getAttribute("aria-label")||document.activeElement.textContent.trim()'))
                assert modal.evaluate('n => n.contains(document.activeElement)'), page.evaluate('document.activeElement.outerHTML.slice(0,500)')
            assert 'Appearance' in visited and 'Search the blog' in visited, visited
            close.click()
            expect(modal).to_have_count(0)
            expect(opener).to_be_focused()
            opener.click()
            modal.wait_for(state='visible')
            page.keyboard.press('Escape')
            expect(modal).to_have_count(0)
            results.append({'engine': engine, 'width': width, 'height': height, **geometry, 'clickClose': True, 'escape': True, 'focusReturn': True})
            context.close()
        browser.close()
(out / 'close-regression-results.json').write_text(json.dumps(results, indent=2) + '\n')
print(f'{len(results)} rendered close-button regressions passed')
