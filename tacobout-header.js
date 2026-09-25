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
	// ⚡ Bolt: Cache offsetHeight outside the high-frequency scroll event
	// to prevent synchronous layout thrashing (forced reflows).
	let headerHeight = header.offsetHeight;

	if ( typeof ResizeObserver !== 'undefined' ) {
		const observer = new ResizeObserver( ( entries ) => {
			for ( const entry of entries ) {
				// Safe to read offsetHeight here since it runs post-layout
				headerHeight = entry.target.offsetHeight;
			}
		} );
		observer.observe( header );
	} else {
		window.addEventListener( 'resize', () => {
			headerHeight = header.offsetHeight;
		}, { passive: true } );
	}

	const handleScroll = () => {
		const currentScroll = window.scrollY;

		if ( currentScroll <= 0 ) {
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
