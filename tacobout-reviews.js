/* Review bodies never enter the cacheable page HTML. Revalidate on every visit. */
(() => {
	let generation = 0;
	async function loadReviews() {
		const current = ++generation;
		const widgets = Array.from(document.querySelectorAll('[data-tacobout-reviews]'));
		// ⚡ Bolt: Fetch all reviews concurrently instead of sequentially to significantly reduce total load time.
		await Promise.all(widgets.map(async (widget) => {
			widget.hidden = true;
			widget.replaceChildren();
			try {
				const response = await fetch(widget.dataset.tacoboutReviews, {
					cache: 'no-store',
					credentials: 'omit',
					headers: { Accept: 'application/json' },
				});
				if (!response.ok) return;
				const data = await response.json();
				if (current !== generation) return;
				if (typeof data.html !== 'string' || !data.html) return;
				// HTML is built and escaped by the same-origin WordPress endpoint.
				widget.innerHTML = data.html;
				widget.hidden = false;
			} catch {
				// An unavailable endpoint must never reveal a saved review body.
			}
		}));
	}
	window.addEventListener('pageshow', loadReviews);
	// Clear before a page enters the back/forward cache, then revalidate on return.
	window.addEventListener('pagehide', () => {
		generation++;
		for (const widget of document.querySelectorAll('[data-tacobout-reviews]')) {
			widget.hidden = true;
			widget.replaceChildren();
		}
	});
})();
