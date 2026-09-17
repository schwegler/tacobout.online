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
	let headerHeight = header.offsetHeight;

	// Cache header height and update with ResizeObserver to avoid layout thrashing in scroll handler
	if ( typeof ResizeObserver !== 'undefined' ) {
		const headerObserver = new ResizeObserver( entries => {
			for ( let entry of entries ) {
				headerHeight = entry.contentRect.height;
			}
		} );
		headerObserver.observe( header );
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
