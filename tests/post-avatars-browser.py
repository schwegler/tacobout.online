"""Avatar sizing, accessibility and REST-loaded cards. Optional POST_AVATAR_BASE_URL
checks real loopback WordPress index routes and single bylines, never production.
"""
import json,os,re
from pathlib import Path
from urllib.parse import urlparse,parse_qs
from playwright.sync_api import sync_playwright,expect
ROOT=Path(__file__).resolve().parents[1]
BASE=os.environ.get('POST_AVATAR_BASE_URL','')
if BASE and urlparse(BASE).hostname not in ['localhost','127.0.0.1']:raise RuntimeError('Loopback only')
OUT=Path(os.environ.get('POST_AVATAR_PROOFS','/tmp/post-avatar-proof'));OUT.mkdir(parents=True,exist_ok=True)
AVATAR='<div class="wp-block-avatar tacobout-card-author-avatar"><a href="/author/editor/" aria-label="Posts by Editor"><img src="/assets/brand/schweg-mascot-420.png" alt="" width="24" height="24"></a></div>'
HTML='''<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/editorial.css"><style>body{margin:0}main{padding:24px;max-width:1000px;margin:auto}</style></head><body class="home"><main><div class="wp-block-query"><ul class="tacobout-magazine-grid"><li class="wp-block-post post-1 tacobout-format-standard"><div class="tacobout-card-inner"><div class="tacobout-post-meta">'''+AVATAR+'''<a href="/category/news">News</a><time>October 20, 2026</time></div><h2 class="wp-block-post-title">Existing first card</h2><p>Native first-page layout.</p></div></li></ul></div></main><script>window.tacoboutScroll={restUrl:'http://localhost/wp-json/wp/v2/posts',enableLoading:true,perPage:1,totalPages:2,showAuthorAvatars:true,authorPostsLabel:'Posts by %s'};</script><script src="/dompurify.min.js"></script><script src="/tacobout-infinite-scroll.js"></script></body></html>'''
POST={'id':2,'author':9,'date':'2026-10-19T12:00:00','link':'http://localhost/read/post-2','title':{'rendered':'Later card'},'excerpt':{'rendered':'<p>A later post.</p>'},'content':{'rendered':''},'post_format':'standard','_embedded':{'author':[{'id':9,'name':'Facet & Friends','link':'http://localhost/author/facet/','avatar_urls':{'48':'http://localhost/assets/brand/author-facet.png'}}]}}
with sync_playwright() as p:
 browser=p.chromium.launch(**({'executable_path':os.environ['CHROMIUM_EXECUTABLE']} if os.environ.get('CHROMIUM_EXECUTABLE') else {}))
 if not BASE:
  for width in [1440,768,390,320]:
   for theme in ['light','dark']:
    page=browser.new_page(viewport={'width':width,'height':1000});calls=[]
    def route(r):
     path=urlparse(r.request.url).path
     if path=='/':r.fulfill(body=HTML,content_type='text/html')
     elif path=='/wp-json/wp/v2/posts':
      q=parse_qs(urlparse(r.request.url).query);calls.append(q)
      r.fulfill(body=json.dumps([POST]),content_type='application/json',headers={'X-WP-TotalPages':'2'})
     else:
      f=ROOT/path.lstrip('/')
      if f.is_file():r.fulfill(path=str(f))
      else:r.fulfill(status=404,body='Fixture resource missing')
    page.route('http://localhost/**',route);page.route('https://**/*',lambda r:r.abort())
    page.goto('http://localhost/');page.evaluate('(t)=>document.documentElement.dataset.theme=t',theme)
    expect(page.locator('.post-2 .tacobout-card-author-avatar')).to_have_count(1)
    assert any('author' in q.get('_embed',[''])[0] and 'author' in q.get('_fields',[''])[0] for q in calls)
    link=page.locator('.post-2 .tacobout-card-author-avatar a');assert link.get_attribute('aria-label')=='Posts by Facet & Friends'
    image=link.locator('img');assert image.get_attribute('alt')=='' and image.get_attribute('width')=='24' and image.get_attribute('height')=='24'
    assert image.get_attribute('src').endswith('/assets/brand/author-facet.png')
    for box in page.locator('.tacobout-card-author-avatar img').evaluate_all('(ns)=>ns.map(n=>n.getBoundingClientRect().toJSON())'):assert box['width']==24 and box['height']==24
    assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
    page.screenshot(path=str(OUT/f'loaded-card-{width}-{theme}.png'));page.close()
 else:
  # Fixture setup only adds the avatar-fixture tag to already-disposable fixture posts.
  routes=[('home','/'),('category','/category/uncategorized/'),('tag','/tag/avatar-fixture/'),('search','/?s=Profile+fixture'),('date','/2026/10/20/')]
  for width in [1440,390]:
   for theme in ['light','dark']:
    for label,path in routes:
     page=browser.new_page(viewport={'width':width,'height':1100});page.route('https://**/*',lambda r:r.abort());page.add_init_script("localStorage.setItem('seasonal-theme:v1','off')")
     response=page.goto(BASE+path,wait_until='networkidle');assert response.status==200,(label,response.status)
     page.evaluate('(t)=>document.documentElement.dataset.theme=t',theme)
     cards=page.locator('.tacobout-magazine-grid > .wp-block-post');assert cards.count()>0,label
     for card in cards.all():
      expect(card.locator('.tacobout-post-meta .tacobout-card-author-avatar')).to_have_count(1)
      img=card.locator('.tacobout-card-author-avatar img');assert img.get_attribute('alt')==''
      box=img.bounding_box();assert box['width']==24 and box['height']==24,(label,width,box)
      assert card.locator('.tacobout-card-author-avatar a').get_attribute('aria-label').startswith('Posts by ')
     assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'),(label,width)
     if label in ['home','search']:page.screenshot(path=str(OUT/f'{label}-{width}-{theme}.png'))
     if label=='home':
      destination=cards.first.locator('.wp-block-post-title a').get_attribute('href');page.goto(destination,wait_until='networkidle')
      expect(page.locator('.tacobout-article-header .tacobout-byline-avatar')).to_have_count(1)
      expect(page.locator('.tacobout-author-byline .wp-block-post-author-name')).to_have_count(1)
      img=page.locator('.tacobout-byline-avatar img');assert img.get_attribute('alt')=='' and img.get_attribute('aria-hidden')=='true'
      assert page.locator('.tacobout-byline-avatar a').count()==0
      box=img.bounding_box();assert box['width']==24 and box['height']==24
      assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
      page.evaluate('(t)=>document.documentElement.dataset.theme=t',theme);page.screenshot(path=str(OUT/f'single-{width}-{theme}.png'))
     page.close()
  page=browser.new_page();page.route('https://**/*',lambda r:r.abort());page.goto(BASE+'/author/schwegler/',wait_until='networkidle')
  expect(page.locator('.tacobout-card-author-avatar')).to_have_count(0);expect(page.locator('.activitypub-profile__avatar')).to_have_count(1);page.close()
 browser.close()
print('Card avatars: 24px intrinsic/rendered sizes, accessible link names, decorative alt, REST appends and responsive light/dark passed.' if not BASE else 'Real home/category/tag/search/date cards, single bylines and profile exclusion passed at desktop/mobile in light/dark.')
