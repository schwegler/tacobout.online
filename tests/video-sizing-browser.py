"""Archive video sizing regression; isolated original portrait markup, no network writes.
Run with Python Playwright and optional CHROMIUM_EXECUTABLE / SCREENSHOT_DIR.
"""
import os
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT = Path(__file__).resolve().parents[1]
CARD = '''<li class="wp-block-post tacobout-format-video"><div class="tacobout-card-inner">
<h2 class="wp-block-post-title">candy corn has a history</h2>
<div class="wp-block-post-content"><figure class="wp-block-embed is-type-video wp-embed-aspect-9-16 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper"><span class="embed-youtube" style="display:block;text-align:center"><iframe width="640" height="360" src="https://www.youtube.com/embed/PFZ6Gj4noLA" title="Video player"></iframe></span></div></figure><p>a minute of candy corn history from Candy is Dandy Shorts</p><details><summary>Source details</summary><p>Original portrait embed</p></details></div></div></li>'''
HTML = '''<!doctype html><html><head><meta name="viewport" content="width=device-width"><title>Portrait lead regression</title>
<style>body{margin:0;padding:16px} .wp-block-embed__wrapper{position:relative}.wp-has-aspect-ratio .wp-block-embed__wrapper:before{content:"";display:block;padding-top:177.777%}.wp-has-aspect-ratio iframe{position:absolute;top:0;left:0;width:100%;height:100%}</style>
<link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/editorial.css"></head><body class="home"><main><ul class="tacobout-magazine-grid">''' + CARD + '''<li class="wp-block-post"><div class="tacobout-card-inner"><h2>Next post</h2></div></li></ul></main>
<script>window.tacoboutScroll={totalPages:1,perPage:10};</script><script src="/tacobout-masonry.js"></script><script src="/tacobout-infinite-scroll.js"></script></body></html>'''
with sync_playwright() as p:
    executable = os.environ.get('CHROMIUM_EXECUTABLE')
    browser = p.chromium.launch(**({'executable_path': executable} if executable else {}))
    for width in [320, 390, 768, 1024, 1440]:
        for js in [False, True]:
            page = browser.new_page(viewport={'width': width, 'height': 900}, java_script_enabled=js)
            errors=[]
            page.on('pageerror',lambda error: errors.append(str(error)))
            def route(r):
                url=r.request.url
                if not url.startswith('http://localhost/'):
                    r.fulfill(body='<html><body style="background:black;color:white">Portrait video fixture</body></html>',content_type='text/html')
                elif url=='http://localhost/':
                    r.fulfill(body=HTML,content_type='text/html')
                else:
                    r.fulfill(path=str(ROOT / url.split('http://localhost/')[1]))
            page.route('**/*',route)
            page.goto('http://localhost/')
            page.wait_for_timeout(300)
            assert page.title()=='Portrait lead regression'
            assert page.locator('h2').first.is_visible()
            frame=page.locator('iframe')
            box=frame.bounding_box()
            assert box['height']<=360.5, (width,js,box)
            assert abs(box['width']/box['height']-16/9)<.02, (width,js,box)
            wrapper=page.locator('.wp-block-embed__wrapper').bounding_box()
            assert abs(wrapper['height']-box['height'])<2, (width,js,wrapper,box)
            assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'), (width,js)
            page.get_by_text('Source details',exact=True).click()
            assert page.get_by_text('Original portrait embed',exact=True).is_visible()
            assert not errors,errors
            folder=os.environ.get('SCREENSHOT_DIR')
            if folder and js:
                Path(folder).mkdir(parents=True,exist_ok=True)
                page.screenshot(path=f'{folder}/after-{width}.png')
            print(f'PASS {width}px JS={js}: {box["width"]:.1f} x {box["height"]:.1f}; no spacer or overflow; details expand')
            # Native portrait files must fit without cropping, including after metadata.
            page.locator('.wp-block-post-content').first.evaluate("n => n.innerHTML='<figure class=wp-block-video><video width=360 height=640 controls></video></figure>'")
            native=page.locator('video')
            native_box=native.bounding_box()
            assert native_box['height']<=360.5
            assert native.evaluate('n => getComputedStyle(n).objectFit')=='contain'
            # Archive rules must not flatten the individual article's authored ratio.
            page.locator('body').evaluate("n => n.className='single'")
            page.locator('ul').evaluate("n => n.className=''")
            page.locator('.wp-block-post-content').first.evaluate("n => n.innerHTML='<figure class=wp-block-video><video width=180 height=320 controls></video></figure>'")
            assert page.locator('video').bounding_box()['height'] > page.locator('video').bounding_box()['width']
            page.close()
    browser.close()
