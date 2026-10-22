"""Network-free Link spacing regression with WordPress's native 24px flow margins."""
import json
import os
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
# These native flow rules are intentionally present: bare HTML missed the real gap bug.
CORE_FLOW = '''
:root :where(.is-layout-flow)>*{margin-block-start:24px;margin-block-end:0}
:root :where(.is-layout-flow)>:first-child{margin-block-start:0}
:root :where(.is-layout-flow)>:last-child{margin-block-end:0}
.wp-block-image img{box-sizing:border-box;height:auto;max-width:100%;vertical-align:bottom}
.wp-block-image{margin:0 0 1em}.wp-block-image figcaption{margin-top:.5em;margin-bottom:1em}
body{margin:0;padding:24px}main{max-width:1240px;margin:auto}
'''
IMAGE = '<figure class="wp-block-image is-resized {align}" style="width:320px"><img src="/body.svg" width="480" height="320" style="width:320px;height:auto" alt="Friends gathered around a sofa">{caption}</figure>'
SOURCE = 'https://publication.test/story'
SUMMARY = 'A short introduction to the review, with the full commentary after More.'

def card(number, *, caption=False, align='', server=False, image=True, host='publication.test'):
    media = IMAGE.format(align=align, caption='<figcaption>Existing image credit</figcaption>' if caption else '') if image else ''
    if server:
        content = (f'<div class="tacobout-link-media">{media}</div>' if media else '') + f'<p>{SUMMARY}</p><p class="tacobout-card-source"><a class="tacobout-link-source" href="{SOURCE}">Open source <span>{host} ↗</span></a></p><p class="tacobout-card-jump"><a class="wp-block-post-excerpt__more-link" href="/article-{number}">Read more</a></p>'
    else:
        content = f'<p><a href="{SOURCE}">{SUMMARY}</a></p>{media}<a class="more-link" href="/article-{number}#more">Continue</a><p>Hidden commentary</p>'
    return f'<li class="wp-block-post post-{number} tacobout-format-link"><div class="wp-block-group tacobout-card-inner is-layout-flow"><div class="wp-block-template-part"><div class="tacobout-post-meta">Links · October 22, 2026</div></div><h2 class="wp-block-post-title"><a href="/article-{number}">A review worth reading</a></h2><div class="entry-content wp-block-post-content is-layout-flow" {"data-feed-preview=link" if server else ""}>{content}</div></div></li>'

CASES = card(101) + card(102, caption=True, align='alignright') + card(103, image=False) + card(104, server=True, align='alignleft') + card(105, server=True, image=False, host='a-very-long-publication-hostname-that-must-wrap.example.test')
HTML = f'''<!doctype html><html><head><meta charset="utf-8"><title>Native Link spacing QA</title><meta name="viewport" content="width=device-width"><style>{CORE_FLOW}</style><link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/editorial.css"></head><body class="home"><main><ul class="tacobout-magazine-grid">{CASES}</ul><article id="single-control"><div class="wp-block-post-content is-layout-flow"><p>Single article paragraph</p><p>Another authored paragraph</p></div></article></main><script>window.tacoboutScroll={{enableLoading:false,totalPages:1,perPage:5}};</script><script src="/dompurify.min.js"></script><script src="/tacobout-infinite-scroll.js"></script><script src="/tacobout-alt-badge.js"></script></body></html>'''
PHOTO = '<svg xmlns="http://www.w3.org/2000/svg" width="480" height="320"><rect width="480" height="320" fill="#b78968"/></svg>'

def bottom(box):
    return box['y'] + box['height']

with sync_playwright() as p:
    browser = p.chromium.launch(**({'executable_path': os.environ['CHROMIUM_PATH']} if os.environ.get('CHROMIUM_PATH') else {}))
    for width in [723, 320, 390, 768, 1440]:
        for theme in ['light', 'dark']:
            page = browser.new_page(viewport={'width': width, 'height': 1000})
            errors = []
            page.on('pageerror', lambda error: errors.append(str(error)))
            page.on('console', lambda message: errors.append(message.text) if message.type == 'error' else None)
            def route(r):
                path = r.request.url.split('http://localhost')[-1].split('?')[0]
                if path == '/': r.fulfill(body=HTML, content_type='text/html')
                elif path == '/body.svg': r.fulfill(body=PHOTO, content_type='image/svg+xml')
                elif path in ['/article-101', '/article-102', '/article-103', '/article-104', '/article-105']: r.fulfill(body='<title>Full article</title><p>Full commentary</p>', content_type='text/html')
                else: r.fulfill(path=str(ROOT / path.lstrip('/')))
            page.route('http://localhost/**', route)
            response = page.goto('http://localhost/', wait_until='networkidle')
            assert response.status == 200 and page.title() == 'Native Link spacing QA'
            page.locator('html').evaluate('(node,theme)=>node.dataset.theme=theme', theme)
            assert page.locator('.tacobout-format-link').count() == 5
            capture = os.environ.get('CAPTURE_DIR')
            if capture and width in [320, 723]:
                Path(capture).mkdir(parents=True, exist_ok=True)
                page.locator('.post-101').screenshot(path=str(Path(capture) / f'link-{width}-{theme}.png'))
            for number in range(101, 106):
                item = page.locator(f'.post-{number}')
                content = item.locator('.wp-block-post-content')
                summary = content.locator(':scope > p:not(.tacobout-card-source):not(.tacobout-card-jump)')
                source = item.locator('.tacobout-link-source')
                jump = item.locator('.tacobout-card-jump a')
                source_box, jump_box, summary_box = source.bounding_box(), jump.bounding_box(), summary.bounding_box()
                if number == 101:
                    print(json.dumps({'width':width,'theme':theme,'summary_to_source':round(source_box['y']-bottom(summary_box),2),'actions_share_row':abs(source_box['y']-jump_box['y'])<=1}), flush=True)
                assert 0 <= source_box['y'] - bottom(summary_box) <= 16, 'source drifts away from summary'
                assert source_box['height'] >= 44 and jump_box['height'] >= 44, 'touch target shrank'
                assert source.get_attribute('href') == SOURCE
                assert 'Hidden commentary' not in item.inner_text()
                assert item.locator('.wp-block-post-excerpt__more-link').count() == 1
                if number in [101, 102, 104]:
                    media_box = item.locator('.tacobout-link-media').bounding_box()
                    assert 0 <= summary_box['y'] - bottom(media_box) <= 16, 'image drifts away from summary'
                    assert item.locator('img').get_attribute('alt') == 'Friends gathered around a sofa'
                    assert item.locator('img').bounding_box()['height'] <= 320
                if number == 101: assert item.locator('figcaption').count() == 0
                if number == 102: assert item.locator('figcaption').inner_text() == 'Existing image credit'
                # Actions share a row when they fit; otherwise wrap with a compact gap.
                if number != 105 and width in [723, 1440]:
                    assert abs(source_box['y'] - jump_box['y']) <= 1, 'actions waste separate rows'
                elif jump_box['y'] > source_box['y'] + 1:
                    assert 0 <= jump_box['y'] - bottom(source_box) <= 12
            assert page.locator('#single-control p').nth(1).evaluate('n=>getComputedStyle(n).marginTop') == '24px'
            assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'), 'horizontal overflow'
            badge = page.locator('.post-101 .tacobout-alt-badge')
            badge.focus(); page.keyboard.press('Enter')
            assert badge.get_attribute('aria-expanded') == 'true'
            assert page.locator('.post-101 .tacobout-alt-tooltip').is_visible()
            page.keyboard.press('Escape')
            assert badge.get_attribute('aria-expanded') == 'false'
            assert not errors, errors
            page.locator('.post-101 .tacobout-card-jump a').click()
            assert page.title() == 'Full article'
            print(f'PASS {width}px {theme}: native flow, compact actions, captions/alt, keyboard and navigation', flush=True)
            page.close()
    browser.close()
