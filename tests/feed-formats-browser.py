"""Network-free mixed-format feed/REST/keyboard/native playback checks."""
from pathlib import Path
import os
import re
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
PHOTO='<svg xmlns="http://www.w3.org/2000/svg" width="1920" height="1551"><rect width="1920" height="1551" fill="#b78968"/></svg>'
BODY='''<li class="wp-block-post post-1 tacobout-format-image"><div class="tacobout-card-inner"><h2 class="wp-block-post-title"><a href="/image">Image post</a></h2><figure class="wp-block-post-featured-image"><img src="/photo.svg"></figure><div class="wp-block-post-content"><figure><img src="/photo.svg" width="1920" height="1551" alt="Complete image"><figcaption>Original caption</figcaption></figure><p>Image commentary belongs after jump. <a href="https://source.test/image">Source</a></p><a class="more-link" href="/image#more">more…</a></div></div></li>
<li class="wp-block-post post-2 tacobout-format-link"><div class="tacobout-card-inner"><h2 class="wp-block-post-title"><a href="/link">Link post</a></h2><figure class="wp-block-post-featured-image"><img src="/featured.svg" alt="Social image"></figure><div class="wp-block-post-content"><figure class="wp-block-image alignright is-resized" style="width:160px"><img src="/photo.svg" width="160" height="129" alt="Link body image" onerror="window.imageAttack=true"><figcaption>Link caption</figcaption></figure><blockquote>Quoted source extract.</blockquote><p><a href="https://source.test/story">Original source</a></p><span id="more-2"></span><img src="/below.svg"><p>Personal commentary</p></div></div></li>
<li class="wp-block-post post-3 tacobout-format-video"><div class="tacobout-card-inner"><h2 class="wp-block-post-title"><a href="/video">Video post</a></h2><div class="wp-block-post-content"><figure class="wp-block-embed"><div class="wp-block-embed__wrapper"><iframe width="640" height="360" src="/player" title="Inline playback"></iframe></div><figcaption>Player caption</figcaption></figure><p>Video commentary belongs after jump.</p></div></div></li>'''
HTML='''<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Feed format QA</title><link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/editorial.css"></head><body class="home"><main><ul class="tacobout-magazine-grid">'''+BODY+'''</ul></main><script>window.tacoboutScroll={enableLoading:true,totalPages:2,perPage:3,restUrl:'http://localhost/posts'};</script><script src="/dompurify.min.js"></script><script src="/tacobout-masonry.js"></script><script src="/tacobout-infinite-scroll.js"></script></body></html>'''
parts=re.findall(r'<li.*?</li>',BODY,re.S)
VIDEO_FIRST_HTML=HTML.replace(BODY,parts[2]+parts[0]+parts[1])
with sync_playwright() as p:
 b=p.chromium.launch(**({"executable_path":os.environ["CHROMIUM_PATH"]} if os.environ.get("CHROMIUM_PATH") else {}));context=b.new_context();seed=context.new_page()
 # A tiny generated test clip exercises a real decoder and progressing frames, no download.
 clip=bytes(seed.evaluate('''async()=>{const c=document.createElement('canvas');c.width=160;c.height=90;const g=c.getContext('2d');g.fillStyle='red';g.fillRect(0,0,160,90);const stream=c.captureStream(10),r=new MediaRecorder(stream,{mimeType:'video/webm'}),chunks=[];r.ondataavailable=e=>chunks.push(e.data);const done=new Promise(resolve=>r.onstop=resolve);r.start();const timer=setInterval(()=>{g.fillStyle=Date.now()%2?'red':'blue';g.fillRect(0,0,160,90);},50);await new Promise(resolve=>setTimeout(resolve,250));g.fillStyle='blue';g.fillRect(0,0,160,90);await new Promise(resolve=>setTimeout(resolve,500));clearInterval(timer);r.stop();await done;stream.getTracks().forEach(t=>t.stop());return [...new Uint8Array(await new Blob(chunks).arrayBuffer())];}'''));seed.close();print('Generated tiny test clip',len(clip),flush=True)
 for width in [320,390,768,1440]:
  for theme in ['light','dark']:
   page=context.new_page();page.set_viewport_size({'width':width,'height':900});errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.on('console',lambda m: print('CONSOLE',m.type,m.text,flush=True) if m.type=='error' else None)
   def route(r):
    path=r.request.url.split('http://localhost')[-1].split('?')[0]
    if path in ['/','/video-first']:r.fulfill(body=VIDEO_FIRST_HTML if path=='/video-first' else HTML,content_type='text/html')
    elif path in ['/photo.svg','/below.svg','/featured.svg']:r.fulfill(body=PHOTO,content_type='image/svg+xml')
    elif path=='/clip.webm':r.fulfill(body=clip,content_type='video/webm')
    elif path=='/player':r.fulfill(body='<video controls width="160" height="90" src="/clip.webm"></video><button onclick="document.querySelector(\'video\').play()">Play sample</button>',content_type='text/html')
    elif path.startswith('/wp-content/themes/tacobout/assets/'):r.fulfill(body=PHOTO,content_type='image/svg+xml')
    elif path=='/posts':r.fulfill(json=[{'id':999,'date':'2026-10-21T12:00:00','link':'http://localhost/new','title':{'rendered':'REST image'},'post_format':'image','content':{'rendered':'Extra commentary'},'tacobout_feed_content':'<div class="wp-block-post-content" data-feed-preview="image"><figure><img src="/photo.svg" alt="REST photo"></figure><p class="tacobout-card-jump"><a class="wp-block-post-excerpt__more-link" href="/new">Read more</a></p></div>'}],headers={'X-WP-TotalPages':'2'})
    else:r.fulfill(path=str(ROOT/path.lstrip('/')))
   page.route('http://localhost/**',route);page.goto('http://localhost/',wait_until='domcontentloaded');print('Loaded',width,theme,flush=True);page.locator('html').evaluate('(n,t)=>n.dataset.theme=t',theme)
   image=page.locator('.post-1');assert image.locator('.wp-block-post-featured-image').count()==0
   assert image.locator('.wp-block-post-content img').count()==1
   assert 'Image commentary' not in image.inner_text()
   assert image.locator('.tacobout-link-source').get_attribute('href')=='https://source.test/image'
   assert image.locator('img').evaluate('n=>getComputedStyle(n).objectFit')=='contain'
   image.get_by_role('button',name='View image ↗').focus();page.keyboard.press('Enter');assert page.locator('dialog[open]').is_visible();page.keyboard.press('Escape');assert not page.locator('dialog[open]').count()
   assert 'Personal commentary' not in page.locator('.post-2').inner_text()
   assert page.locator('.post-2 blockquote').inner_text()=='Quoted source extract.'
   link=page.locator('.post-2')
   assert link.locator('.tacobout-link-media img').count()==1
   assert link.locator('.wp-block-post-featured-image').count()==0
   if theme=='dark': link.locator('.tacobout-link-media figure').evaluate('n=>{n.classList.remove("alignright");n.classList.add("alignleft")}')
   assert link.locator('figcaption').inner_text()=='Link caption'
   assert link.locator('img').get_attribute('alt')=='Link body image'
   assert link.locator('img').get_attribute('onerror') is None
   assert link.locator('img').bounding_box()['height']<=320
   assert link.locator('img').bounding_box()['width']<=160
   assert link.locator('figure').evaluate('n=>getComputedStyle(n).float')==('none' if width<=480 else ('left' if theme=='dark' else 'right'))
   assert link.locator('img').bounding_box()['y']+link.locator('img').bounding_box()['height']<=link.locator('blockquote').bounding_box()['y']
   assert link.locator('[src="/below.svg"]').count()==0
   player=page.locator('.post-3 iframe');assert player.count()==1
   player.scroll_into_view_if_needed();page.frame_locator('iframe').get_by_role('button',name='Play sample').click()
   frame=page.frames[-1];frame.wait_for_function('document.querySelector("video").currentTime>0.1');assert frame.locator('video').evaluate('v=>v.videoWidth')==160
   page.evaluate('scrollTo(0,document.body.scrollHeight)');page.locator('.post-999').wait_for(timeout=5000)
   assert 'Extra commentary' not in page.locator('.post-999').inner_text()
   assert page.locator('.post-999 img').count()==1
   assert page.locator('.post-999 .wp-block-post-content').count()==1
   assert page.locator('.post-999 .wp-block-post-excerpt__more-link').count()==1
   # Reading affordance survives REST appends without adding spoken arrow text.
   reading=page.locator('.post-999 .wp-block-post-excerpt__more-link')
   assert reading.evaluate('n=>n.href')=='http://localhost/new'
   assert reading.inner_text()=='Read more'
   assert reading.evaluate('n=>getComputedStyle(n,"::after").content')=='""'
   assert reading.evaluate('n=>getComputedStyle(n,"::after").borderRightWidth')=='2px'
   assert reading.evaluate('n=>getComputedStyle(n,"::after").transform')=='matrix(0.707107, 0.707107, -0.707107, 0.707107, 0, 0)'
   reading.evaluate('n=>n.dir="rtl"')
   assert reading.evaluate('n=>getComputedStyle(n,"::after").transform')=='matrix(-0.707107, -0.707107, 0.707107, -0.707107, 0, 0)'
   reading.evaluate('n=>n.removeAttribute("dir")')
   assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'),(width,theme)
   # A newest video is a normal aligned feed card, never a viewport-wide essay lead.
   page.goto('http://localhost/video-first',wait_until='domcontentloaded')
   page.wait_for_timeout(100)
   first=page.locator('.wp-block-post').first
   title=first.locator('h2');player=first.locator('iframe');jump=first.locator('.wp-block-post-excerpt__more-link')
   assert title.evaluate('n=>parseFloat(getComputedStyle(n).fontSize)')<=28
   assert first.evaluate('n=>parseFloat(getComputedStyle(n).paddingTop)')==0
   assert first.evaluate('n=>parseFloat(getComputedStyle(n).borderLeftWidth)')==0
   assert abs(title.bounding_box()['x']-player.bounding_box()['x'])<2
   assert abs(title.bounding_box()['x']-jump.bounding_box()['x'])<2
   assert player.bounding_box()['width']<=first.bounding_box()['width']+1,(player.bounding_box(),first.bounding_box())
   if width>=760:
    assert first.bounding_box()['width']<page.locator('.tacobout-magazine-grid').bounding_box()['width']*.75
    assert abs(first.bounding_box()['y']-page.locator('.post-1').bounding_box()['y'])<3
   assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
   assert not errors,errors
   print(f'PASS {width} {theme}: inline image/source/video, keyboard, REST, no overflow',flush=True);page.close()
 b.close()
