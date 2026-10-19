/**
 * Sticky Header Scroll Observer
 * Hides the header on scroll down, shows it on scroll up.
 */
document.addEventListener( 'DOMContentLoaded', () => {
	const header = document.querySelector( '.tacobout-header' );

	if ( ! header ) {
		return;
	}

	let lastScroll = window.scrollY;
	let ticking    = false;

	// ⚡ Bolt: Cache header offsetHeight to prevent layout thrashing during high-frequency scroll events.
	// We use a ResizeObserver to keep the cache accurate if the header changes size.
	let headerHeight = header.offsetHeight;
	const resizeObserver = new ResizeObserver(() => {
		headerHeight = header.offsetHeight;
	});
	resizeObserver.observe(header);

	const handleScroll = () => {
		const currentScroll = window.scrollY;
		// A transformed sticky ancestor traps the fixed navigation dialog. Keep
		// the header visible for the entire dialog lifetime and keyboard use.
		const menuOpen = header.querySelector( '.is-menu-open' );
		const focused = header.contains( document.activeElement );

		if ( menuOpen || focused || currentScroll <= 0 ) {
			header.classList.remove( 'is-hidden' );
		} else if ( currentScroll > lastScroll && currentScroll > headerHeight ) {
			// Scrolling down past the header height.
			header.classList.add( 'is-hidden' );
		} else if ( currentScroll < lastScroll ) {
			// Scrolling up.
			header.classList.remove( 'is-hidden' );
		}

		lastScroll = currentScroll;
		ticking    = false;
	};

	window.addEventListener( 'scroll', () => {
		if ( ! ticking ) {
			window.requestAnimationFrame( handleScroll );
			ticking = true;
		}
	}, { passive: true } );

	// Ensure header reveals itself when keyboard focus enters it
	header.addEventListener( 'focusin', () => {
		header.classList.remove( 'is-hidden' );
	} );
} );

/** Keep core WordPress dialog focus/scroll behavior, with the site's utilities inside it. */
document.addEventListener('DOMContentLoaded', () => {
 const nav=document.querySelector('.tacobout-primary-navigation');
 const modal=nav?.querySelector('.wp-block-navigation__responsive-container');
 const content=modal?.querySelector('.wp-block-navigation__responsive-container-content');
 if(!modal||!content)return;
 const heading=document.createElement('div');heading.className='tacobout-menu-heading';
 const home=document.createElement('a');home.href=document.querySelector('.wp-block-site-title a')?.href||'/';home.textContent='taco ’bout it';
 const label=document.createElement('span');label.textContent='Channel guide';heading.append(home,label);
 const extras=document.createElement('div');extras.className='tacobout-menu-extras';
 let utilities=null,anchor=null;
 const sync=()=>{
  if(modal.classList.contains('is-menu-open')){
   if(!heading.isConnected){content.prepend(heading);content.append(extras);}
   utilities=document.querySelector('.tacobout-utilities');
   if(utilities&&!extras.contains(utilities)){anchor=document.createComment('Header utilities');utilities.before(anchor);extras.append(utilities);}
   document.querySelector('.tacobout-header')?.classList.remove('is-hidden');
  }else{
   if(anchor&&utilities){anchor.replaceWith(utilities);anchor=null;}
   heading.remove();extras.remove();
   const appearance=document.querySelector('.seasonal-appearance-toggle[aria-expanded="true"]');if(appearance)appearance.click();
  }
 };
 // Core snapshots endpoints before utilities move. Include the current visible
 // controls on every Tab, including an expanded Appearance panel in WebKit.
 modal.addEventListener('keydown',event=>{
  if(event.key!=='Tab'||!modal.classList.contains('is-menu-open'))return;
  const nodes=[...modal.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),summary,[tabindex]:not([tabindex^="-"])')].filter(node=>node.getClientRects().length&&!node.closest('[hidden],[inert]')&&getComputedStyle(node).visibility!=='hidden');
  if(!nodes.length)return;
  const current=nodes.indexOf(document.activeElement);
  const next=current<0?(event.shiftKey?nodes.length-1:0):(current+(event.shiftKey?-1:1)+nodes.length)%nodes.length;
  event.preventDefault();event.stopImmediatePropagation();nodes[next].focus();
 },true);
 new MutationObserver(sync).observe(modal,{attributes:true,attributeFilter:['class']});sync();
});
