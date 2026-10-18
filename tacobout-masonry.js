/** Small measured-grid enhancement. Source order and native fallback stay intact. */
(function (root, factory) {
	const api = factory();
	if (typeof module === 'object' && module.exports) module.exports = api;
	else root.TacoboutMasonry = api;
})(typeof window === 'undefined' ? this : window, function () {
	'use strict';
	const UNIT = 4;

	/** Earliest available column, stable left tie-break; wide items form section boundaries. */
	function pack(items, columns, gap) {
		const ends = Array(columns).fill(0);
		const gutter = Math.ceil(gap / UNIT);
		return items.map(item => {
			const span = Math.max(1, Math.ceil(item.height / UNIT));
			const column = item.wide ? 0 : ends.indexOf(Math.min(...ends));
			const row = item.wide ? Math.max(...ends) : ends[column];
			if (item.wide) ends.fill(row + span + gutter);
			else ends[column] = row + span + gutter;
			return { column, row, span, width: item.wide ? columns : 1 };
		});
	}

	function create(grid) {
		let frame = 0;
		let previousTracks = 0;
		const observed = new Set();
		const sizes = new WeakMap();
		const observer = typeof ResizeObserver === 'function' ? new ResizeObserver(entries => {
			let changed = false;
			for (const entry of entries) {
				const box = entry.borderBoxSize?.[0];
				const next = [box?.inlineSize ?? entry.contentRect.width, box?.blockSize ?? entry.contentRect.height];
				const old = sizes.get(entry.target);
				// Our row placements change the grid height: only its width is an input.
				if (!old || Math.abs(next[0] - old[0]) > .25 || (entry.target !== grid && Math.abs(next[1] - old[1]) > .25)) changed = true;
				sizes.set(entry.target, next);
			}
			if (changed) schedule();
		}) : null;

		function set(element, name, value) {
			if (element.style.getPropertyValue(name) !== value) element.style.setProperty(name, value, 'important');
		}
		function reset() {
			grid.classList.remove('tacobout-masonry-active');
			for (const element of grid.children) {
				element.style.removeProperty('grid-row');
				element.style.removeProperty('grid-column');
			}
		}
		function layout() {
			frame = 0;
			if (!grid.isConnected || !grid.getBoundingClientRect().width) return;
			const style = getComputedStyle(grid);
			const tracks = parseInt(style.getPropertyValue('--tacobout-masonry-columns'), 10) || 1;
			if (tracks !== previousTracks) {
				reset(); // Remove old explicit lanes before measuring a new breakpoint.
				previousTracks = tracks;
			}
			if (tracks < 2 || style.display !== 'grid') { reset(); return; }
			const children = Array.from(grid.children).filter(el => getComputedStyle(el).display !== 'none');
			const rail = tracks === 3 ? children.find(el => el.classList.contains('tacobout-grid-sidebar')) : null;
			const columns = rail ? tracks - 1 : tracks;
			const lead = document.body.matches('.home:not(.paged)') ? children.find(el => el.classList.contains('wp-block-post')) : null;
			const gap = parseFloat(style.getPropertyValue('--tacobout-masonry-gap')) || 30;
			// All heights are read before row writes. Fixed widths are supplied by the fallback grid.
			const items = children.filter(el => el !== rail);
			const railSpan = rail ? Math.max(1, Math.ceil(rail.getBoundingClientRect().height / UNIT)) : 0;
			const plan = pack(items.map(el => ({ height: el.getBoundingClientRect().height, wide: el === lead || !el.classList.contains('wp-block-post') })), columns, gap);
			grid.classList.add('tacobout-masonry-active');
			items.forEach((el, i) => {
				const p = plan[i];
				set(el, 'grid-column', el === lead ? '1 / -1' : `${p.column + 1} / span ${p.width}`);
				set(el, 'grid-row', `${p.row + 1} / span ${p.span}`);
			});
			if (rail) {
				const i = items.indexOf(lead);
				const row = i < 0 ? 0 : plan[i].row + plan[i].span + Math.ceil(gap / UNIT);
				set(rail, 'grid-column', `${tracks}`);
				set(rail, 'grid-row', `${row + 1} / span ${railSpan}`);
			}
		}
		function schedule() { if (!frame) frame = requestAnimationFrame(layout); }
		function refresh() {
			for (const el of observed) if (el !== grid && el.parentElement !== grid) { observer?.unobserve(el); observed.delete(el); }
			for (const el of [grid, ...grid.children]) if (!observed.has(el)) { observer?.observe(el); observed.add(el); }
			schedule();
		}
		new MutationObserver(refresh).observe(grid, { childList: true });
		grid.addEventListener('load', schedule, true);
		grid.addEventListener('loadedmetadata', schedule, true);
		grid.addEventListener('toggle', schedule, true);
		window.addEventListener('resize', schedule, { passive: true });
		document.fonts?.ready.then(schedule);
		document.fonts?.addEventListener('loadingdone', schedule);
		refresh();
		return { schedule, refresh };
	}
	return { pack, create };
});
