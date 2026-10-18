/** Stick only when the entire discovery column fits; otherwise use ordinary document scrolling. */
(function (root, factory) {
 const api=factory();
 if(typeof module==='object'&&module.exports) module.exports=api;
 else if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',api.init,{once:true});
 else api.init();
})(typeof window==='undefined'?this:window,function(){
 'use strict';
 function fits({width,height,header=0,admin=0,sidebar}) {
  return width>=1024 && sidebar>0 && sidebar+header+admin+32<=height-1;
 }
 function init(){
  const sidebar=document.querySelector('body.single .tacobout-sidebar-sticky');
  if(!sidebar || typeof ResizeObserver!=='function') return;
  const header=document.querySelector('.tacobout-header');
  const admin=document.getElementById('wpadminbar');
  let frame=0;
  function update(){
   frame=0;
   const headHeight=header?.getBoundingClientRect().height||0;
   const adminHeight=admin?.getBoundingClientRect().height||0;
   const height=Math.min(innerHeight,window.visualViewport?.height||innerHeight);
   const allowed=fits({width:innerWidth,height,header:headHeight,admin:adminHeight,sidebar:sidebar.getBoundingClientRect().height});
   const top=`${Math.ceil(headHeight+adminHeight+16)}px`;
   if(sidebar.style.getPropertyValue('--tacobout-sidebar-top')!==top) sidebar.style.setProperty('--tacobout-sidebar-top',top);
   sidebar.classList.toggle('tacobout-sidebar-fits',allowed);
  }
  function schedule(){if(!frame)frame=requestAnimationFrame(update);}
  const observer=new ResizeObserver(schedule);
  [sidebar,header,admin].filter(Boolean).forEach(el=>observer.observe(el));
  window.addEventListener('resize',schedule,{passive:true});
  window.visualViewport?.addEventListener('resize',schedule,{passive:true});
  sidebar.addEventListener('load',schedule,true);
  document.fonts?.ready.then(schedule);
  schedule();
 }
 return {fits,init};
});
