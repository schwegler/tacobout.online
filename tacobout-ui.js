/** Global controls are independent of the optional feed enhancer. */
(() => {
	'use strict';
	const key = 'tacobout-theme';
	const system = window.matchMedia('(prefers-color-scheme: dark)');
	let preference = null;
	try { preference = localStorage.getItem(key); } catch { /* Private storage may be unavailable. */ }
	if (!['light', 'dark'].includes(preference)) preference = null;
	const apply = theme => {
		document.documentElement.dataset.theme = theme;
		document.querySelectorAll('.tacobout-theme-control').forEach(button => {
			button.hidden = false;
			button.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
			button.title = button.getAttribute('aria-label');
			button.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">' + (theme === 'dark' ? '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/>' : '<path d="M20 15.5A8.5 8.5 0 0 1 8.5 4 8.5 8.5 0 1 0 20 15.5Z"/>') + '</svg><span>' + button.getAttribute('aria-label') + '</span>';
		});
	};
	apply(preference || (system.matches ? 'dark' : 'light'));
	system.addEventListener('change', event => { if (!preference) apply(event.matches ? 'dark' : 'light'); });
	document.addEventListener('DOMContentLoaded', () => {
		// Old Site Editor header overrides can retain their markup without losing controls.
		const header = document.querySelector('.tacobout-header-row');
		if (header && !header.querySelector('.tacobout-utilities')) {
			const utilities = document.createElement('div');
			utilities.className = 'tacobout-utilities';
			const search = document.createElement('a');
			search.className = 'tacobout-search-link';
			search.href = new URL('?s=', document.querySelector('link[rel="home"]')?.href || document.querySelector('.wp-block-site-title a')?.href || location.origin).href;
			search.textContent = 'Search';
			const toggle = document.createElement('button');
			toggle.type = 'button'; toggle.className = 'tacobout-theme-control';
			utilities.append(search, toggle); header.append(utilities);
		}
		apply(document.documentElement.dataset.theme);
		document.querySelectorAll('.tacobout-theme-control').forEach(button => button.addEventListener('click', () => {
			preference = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
			try { localStorage.setItem(key, preference); } catch { /* The session still works. */ }
			apply(preference);
		}));
		const fab = document.createElement('button');
		fab.type = 'button'; fab.className = 'tacobout-scroll-top'; fab.textContent = 'Back to top'; fab.hidden = true;
		document.body.append(fab);
		fab.addEventListener('click', () => {
			window.scrollTo({top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'});
			const target = document.querySelector('main');
			if (target) { target.setAttribute('tabindex', '-1'); target.focus({preventScroll: true}); }
		});
		const update = () => { fab.hidden = window.scrollY < 600; fab.classList.toggle('is-visible', !fab.hidden); };
		window.addEventListener('scroll', update, {passive: true}); update();
	});
})();
