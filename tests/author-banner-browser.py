"""Author-only banner sizing regression using the representative native markup.
Optional AUTHOR_BANNER_ASSETS points at actual approved facet/telly/hitch-banner.jpg
files for visual proofs; no accounts or production changes are made.
"""
import os
from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
ASSETS=Path(os.environ['AUTHOR_BANNER_ASSETS']) if os.environ.get('AUTHOR_BANNER_ASSETS') else None
OUT=Path(os.environ.get('AUTHOR_BANNER_PROOFS','/tmp/author-banner-proof'));OUT.mkdir(parents=True,exist_ok=True)
with sync_playwright() as p:
 b=p.chromium.launch(**({'executable_path':os.environ['CHROMIUM_EXECUTABLE']} if os.environ.get('CHROMIUM_EXECUTABLE') else {}))
 for name in (['facet','telly','hitch'] if ASSETS else ['fixture']):
  for width in [1440,1024,768,390]:
   page=b.new_page(viewport={'width':width,'height':1000})
   def route(r):
    path=urlparse(r.request.url).path
    if path=='/proof-banner.jpg':
     r.fulfill(path=str(ASSETS/(name+'-banner.jpg')))
    elif path.startswith('/author/'):
     r.fulfill(path=str(ROOT/'tests/fixtures/author-profile.html'),content_type='text/html')
    else:
     f=ROOT/path.lstrip('/')
     if f.is_file():r.fulfill(path=str(f))
     else:r.fulfill(status=404,body='Unavailable in layout fixture')
   page.route('http://localhost/**',route)
   page.route('https://**/*',lambda r:r.abort())
   page.goto('http://localhost/author/schwegler/')
   page.locator('.tacobout-profile-banner').evaluate('''(n,real)=>{
    n.classList.add('has-user-banner');
    const img=document.createElement('img');img.className='tacobout-profile-banner-image';img.alt='';
    img.src=real?'/proof-banner.jpg':'data:image/svg+xml,'+encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="1500" height="500"><rect width="1500" height="500" fill="purple"/></svg>');
    n.replaceChildren(img);
   }''',bool(ASSETS))
   page.locator('.tacobout-profile-banner-image').evaluate('(n)=>n.decode()')
   rect=page.locator('.has-user-banner').bounding_box();assert abs(rect['width']/rect['height']-3)<.02,(name,width,rect)
   img=page.locator('.tacobout-profile-banner-image')
   assert img.evaluate('(n)=>getComputedStyle(n).objectFit')=='contain'
   r=img.bounding_box();assert abs(r['width']/r['height']-3)<.02
   assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
   if ASSETS:
    assert img.evaluate('(n)=>n.naturalWidth===1500&&n.naturalHeight===500')
    page.screenshot(path=str(OUT/f'{name}-{width}.png'))
   page.close()
 b.close()
print('3:1 author banners retain full composition at 1440/1024/768/390; no overflow or crop.'+(' All three approved source JPEGs checked.' if ASSETS else ' Synthetic dimension fixture only.'))
