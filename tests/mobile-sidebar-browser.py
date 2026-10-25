"""Exercise real theme CSS, masonry and REST appends with isolated public markup.
No production requests. Browser plugin not available; uses installed Playwright.
"""
import json, os
from pathlib import Path
from urllib.parse import urlparse, parse_qs
from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[1]
OUT = Path(os.environ.get('SIDEBAR_PROOFS', '/tmp/mobile-sidebar-proof'))
OUT.mkdir(parents=True, exist_ok=True)
CARDS = ''.join(f'<li class="wp-block-post post-{i} tacobout-format-standard"><h2 class="wp-block-post-title"><a href="/article">Post {i}</a></h2><p>First batch</p></li>' for i in range(1, 10))
SIDEBAR = '<li class="tacobout-grid-sidebar"><aside class="tacobout-discovery-sidebar" aria-label="Explore more"><section><h3>Reviews</h3><a id="review" href="/article">Review</a></section><section><h3>Trending</h3><details id="disclosure"><summary>Support</summary><a href="/article">Support link</a></details><input id="widget" aria-label="Widget value"><iframe title="Ad fixture" src="/ad"></iframe></section></aside></li>'
HTML = '''<!doctype html><html><head><title>Sidebar regression fixture</title><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/editorial.css"><style>body{margin:0}main{padding:24px;max-width:1240px;margin:auto}.wp-block-post{min-height:400px}iframe{width:120px;height:40px}</style></head><body class="home"><main><div class="wp-block-query"><ul class="tacobout-magazine-grid tacobout-grid-with-sidebar">''' + CARDS + SIDEBAR + '''</ul><nav class="wp-block-query-pagination"><a href="/page/2/">Next page</a></nav></div></main><script>window.tacoboutScroll={restUrl:'http://localhost/wp-json/wp/v2/posts',enableLoading:true,perPage:9,totalPages:50,siteUrl:'http://localhost'};</script><script src="/dompurify.min.js"></script><script src="/tacobout-masonry.js"></script><script src="/tacobout-infinite-scroll.js"></script></body></html>'''

def post(i):
    return {'id':i,'date':'2026-10-20T12:00:00','link':'http://localhost/article','title':{'rendered':f'Post {i}'},'excerpt':{'rendered':'<p>Later batch</p>'},'content':{'rendered':''},'post_format':'standard'}

def check(page, width):
    page.wait_for_timeout(250)
    assert page.locator('.tacobout-grid-sidebar').count() == 1
    assert page.locator('#review').count() == 1
    assert page.locator('#widget').input_value() == 'retained'
    assert page.locator('#disclosure').get_attribute('open') is not None
    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
    if width < 1100:
        positions = page.evaluate(r'''() => {
          const rect = s => document.querySelector(s).getBoundingClientRect();
          return {first:Math.max(...Array.from(document.querySelectorAll('.wp-block-post')).filter(n=>Number(n.className.match(/post-(\d+)/)[1])<=9).map(n=>n.getBoundingClientRect().bottom)),side:rect('.tacobout-grid-sidebar').toJSON(),next:document.querySelector('.post-10')?.getBoundingClientRect().toJSON()};
        }''')
        assert positions['side']['top'] >= positions['first'] - 1, positions
        if positions['next']:
            if positions['next']['top'] < positions['side']['bottom'] - 1:
                page.screenshot(path=str(OUT/'regression.png'), full_page=True)
                raise AssertionError(positions)
    else:
        assert page.locator('.tacobout-grid-sidebar').evaluate('n=>getComputedStyle(n).gridColumnStart') == '3'

with sync_playwright() as p:
    browser = p.chromium.launch(**({'executable_path':os.environ['CHROMIUM_EXECUTABLE']} if os.environ.get('CHROMIUM_EXECUTABLE') else {}))
    for initial in [390, 844, 1099, 1100, 1440, 2100]:
        context = browser.new_context(viewport={'width':initial,'height':844 if initial==390 else 390 if initial==844 else 900}, reduced_motion='reduce')
        calls, errors = [], []
        state = {'fail':False,'ads':0}
        def route(r):
            path = urlparse(r.request.url).path
            if path in ['/', '/page/2/']: r.fulfill(body=HTML, content_type='text/html')
            elif path == '/article': r.fulfill(body='<title>Article</title><h1>Article</h1>', content_type='text/html')
            elif path == '/ad':
                state['ads'] += 1
                r.fulfill(body='Ad fixture', content_type='text/html')
            elif path == '/wp-json/wp/v2/posts':
                batch = int(parse_qs(urlparse(r.request.url).query)['page'][0]); calls.append(batch)
                if state['fail']: r.fulfill(status=503, body='Unavailable')
                else: r.fulfill(body=json.dumps([post(i) for i in range((batch-1)*9+1,batch*9+1)]), content_type='application/json', headers={'X-WP-TotalPages':'50'})
            else:
                f = ROOT / path.lstrip('/')
                if f.is_file(): r.fulfill(path=str(f))
                else: r.fulfill(status=404, body='Missing fixture')
        context.route('http://localhost/**', route)
        context.route('https://**/*', lambda r:r.abort())
        page = context.new_page(); page.on('pageerror',lambda e:errors.append(str(e)))
        page.goto('http://localhost/')
        assert page.title() == 'Sidebar regression fixture'
        page.locator('#widget').fill('retained'); page.locator('#disclosure summary').click()
        check(page,initial)
        for batch in [2,3,4]:
            page.locator('.tacobout-scroll-sentinel').scroll_into_view_if_needed()
            expect(page.locator(f'.post-{batch*9}')).to_have_count(1)
            check(page,initial)
        page.screenshot(path=str(OUT/f'batches-{initial}.png'), full_page=True)
        for width,height in [(1440,900),(390,844),(844,390),(759,900),(760,900),(1099,900),(1100,900),(390,844)]:
            page.set_viewport_size({'width':width,'height':height}); check(page,width)
        assert state['ads'] == 1
        # Reading and keyboard focus order follow the same single DOM instance.
        page.locator('#review').focus(); page.keyboard.press('Tab')
        assert page.locator('#disclosure summary').evaluate('n=>n===document.activeElement')
        state['fail'] = True
        page.evaluate('scrollTo(0,0)'); page.wait_for_timeout(300)
        page.locator('.tacobout-scroll-sentinel').scroll_into_view_if_needed()
        expect(page.locator('.tacobout-end-headline')).to_have_text('Failed to load more posts.')
        assert page.locator('.wp-block-query-pagination').is_visible()
        check(page,390)
        # Existing retry is native pagination/reload, not a separate JS retry button.
        state['fail'] = False
        page.reload(); page.locator('#widget').fill('retained'); page.locator('#disclosure summary').click()
        page.locator('.tacobout-scroll-sentinel').scroll_into_view_if_needed()
        expect(page.locator('.post-18')).to_have_count(1); check(page,390)
        page.locator('#review').click(); expect(page.locator('h1')).to_have_text('Article')
        page.go_back(); expect(page.locator('.tacobout-grid-sidebar')).to_have_count(1)
        page.locator('#widget').fill('retained')
        if page.locator('#disclosure').get_attribute('open') is None: page.locator('#disclosure summary').click()
        check(page,390)
        assert not errors, errors
        assert calls[:3] == [2,3,4], calls
        context.close()
    context = browser.new_context(java_script_enabled=False,viewport={'width':390,'height':844})
    context.route('http://localhost/**',route)
    page=context.new_page();page.goto('http://localhost/')
    assert page.locator('.wp-block-query-pagination').is_visible()
    assert page.locator('.tacobout-grid-sidebar').bounding_box()['y'] >= page.locator('.post-9').bounding_box()['y']
    page.locator('.wp-block-query-pagination a').click(); assert page.url.endswith('/page/2/')
    context.close();browser.close()
print('PASS: three append cycles; portrait/landscape; both breakpoint directions; desktop rail; widget state, focus and single ad; failure/native retry; back navigation; no-JS pagination.')
