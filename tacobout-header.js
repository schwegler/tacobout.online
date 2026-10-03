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

	// ⚡ Bolt: Cache header offsetHeight and update via ResizeObserver to prevent layout thrashing inside the scroll handler.
	const resizeObserver = new ResizeObserver(entries => {
		for (let entry of entries) {
			headerHeight = entry.target.offsetHeight;
		}
	});
	resizeObserver.observe(header);

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
