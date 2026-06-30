/**
 * Tacobout Infinite Scroll + Scroll-to-Top
 * 
 * - IntersectionObserver-based infinite scroll using the WP REST API
 * - Floating scroll-to-top button
 * - Progressive enhancement: pagination remains functional without JS
 */
(function () {
	'use strict';

	/* ============================================
	   CONFIG
	   ============================================ */
	const config = window.tacoboutScroll;
	if (!config) return;

	let currentPage = 1;
	const totalPages = parseInt(config.totalPages, 10);
	const perPage = parseInt(config.perPage, 10);
	let isLoading = false;
	let allLoaded = currentPage >= totalPages;

	/* ============================================
	   DOM REFS
	   ============================================ */
	const grid = document.querySelector('.tacobout-magazine-grid');
	if (!grid) return;

	// Mark body so CSS can hide pagination
	document.body.classList.add('tacobout-infinite-scroll-active');

	/* ============================================
	   SENTINEL + SPINNER
	   ============================================ */
	const sentinel = document.createElement('div');
	sentinel.className = 'tacobout-scroll-sentinel';
	sentinel.setAttribute('aria-hidden', 'true');

	const spinner = document.createElement('div');
	spinner.className = 'tacobout-infinite-scroll-spinner';
	spinner.setAttribute('aria-label', 'Loading more posts');
	spinner.innerHTML = `
		<div class="tacobout-spinner-dots">
			<span></span><span></span><span></span>
		</div>
	`;
	spinner.style.display = 'none';

	// Insert sentinel and spinner after the grid's parent query block
	const queryBlock = grid.closest('.wp-block-query');
	if (queryBlock) {
		queryBlock.parentNode.insertBefore(spinner, queryBlock.nextSibling);
		queryBlock.parentNode.insertBefore(sentinel, spinner);
	} else {
		grid.parentNode.insertBefore(spinner, grid.nextSibling);
		grid.parentNode.insertBefore(sentinel, spinner);
	}

	/* ============================================
	   CARD BUILDER
	   Replicates the template structure from home.html
	   ============================================ */
	function buildCard(post) {
		const format = post.post_format || 'standard';
		const formatClass = 'tacobout-format-' + format;
		const interactionCount = post.interaction_count || 0;

		// Determine what to show/hide based on format
		const showFeaturedImage = format === 'standard';
		const showExcerpt = format === 'standard';
		const showContent = format !== 'standard';

		// Build featured image
		let featuredImageHtml = '';
		if (showFeaturedImage && post._embedded && post._embedded['wp:featuredmedia'] && post._embedded['wp:featuredmedia'][0]) {
			const media = post._embedded['wp:featuredmedia'][0];
			const imgSrc = media.media_details?.sizes?.medium_large?.source_url || media.source_url;
			const imgAlt = media.alt_text || '';
			featuredImageHtml = `
				<figure class="wp-block-post-featured-image">
					<a href="${escHtml(post.link)}">
						<img src="${escHtml(imgSrc)}" alt="${escHtml(imgAlt)}" loading="lazy"
							style="border-radius:12px;aspect-ratio:16/9;object-fit:cover;width:100%" />
					</a>
				</figure>
			`;
		}

		// Build post meta (categories + date)
		let categoriesHtml = '';
		if (post._embedded && post._embedded['wp:term'] && post._embedded['wp:term'][0]) {
			const cats = post._embedded['wp:term'][0];
			categoriesHtml = `
				<div class="wp-block-post-terms taxonomy-category">
					${cats.map(c => `<a href="${escHtml(c.link)}" rel="tag">${escHtml(c.name)}</a>`).join(' ')}
				</div>
			`;
		}

		const dateObj = new Date(post.date);
		const dateFormatted = dateObj.toLocaleDateString('en-US', {
			year: 'numeric', month: 'long', day: 'numeric'
		});

		const postMetaHtml = `
			<div class="wp-block-template-part">
				<div class="wp-block-group tacobout-post-meta" style="font-size:0.8125rem">
					${categoriesHtml}
					<p class="has-muted-color has-text-color" style="font-size:0.8125rem">·</p>
					<time datetime="${escHtml(post.date)}" class="wp-block-post-date">
						<a href="${escHtml(post.link)}">${escHtml(dateFormatted)}</a>
					</time>
				</div>
			</div>
		`;

		// Build title
		const titleHtml = `
			<h2 class="wp-block-post-title" style="font-size:var(--wp--preset--font-size--x-large);line-height:1.2;margin-top:0;margin-bottom:0">
				<a href="${escHtml(post.link)}">${post.title.rendered}</a>
			</h2>
		`;

		// Build excerpt (for standard format)
		let excerptHtml = '';
		if (showExcerpt && post.excerpt && post.excerpt.rendered) {
			excerptHtml = `
				<div class="wp-block-post-excerpt">
					<p class="wp-block-post-excerpt__excerpt">${post.excerpt.rendered}</p>
				</div>
			`;
		}

		// Build content (for non-standard formats)
		let contentHtml = '';
		if (showContent && post.content && post.content.rendered) {
			contentHtml = `
				<div class="wp-block-post-content entry-content">
					${post.content.rendered}
				</div>
			`;
		}

		// Build interaction badge
		let badgeHtml = '';
		if (interactionCount > 0) {
			const label = interactionCount === 1
				? '1 interaction'
				: interactionCount + ' interactions';
			badgeHtml = `<a href="${escHtml(post.link)}" class="tacobout-interaction-badge" aria-label="${escHtml(label)}" title="${escHtml(label)}">💬 ${interactionCount}</a>`;
		}

		// Assemble card
		const li = document.createElement('li');
		li.className = `wp-block-post post-${post.id} post type-post status-publish wp-post-${post.id} ${formatClass}`;

		li.innerHTML = `
			${badgeHtml}
			<div class="wp-block-group tacobout-card-inner">
				${featuredImageHtml}
				${postMetaHtml}
				${titleHtml}
				${excerptHtml}
				${contentHtml}
			</div>
		`;

		return li;
	}

	function escHtml(str) {
		if (!str) return '';
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	/* ============================================
	   FETCH + APPEND
	   ============================================ */
	async function loadMorePosts() {
		if (isLoading || allLoaded) return;
		isLoading = true;
		spinner.style.display = 'flex';

		try {
			const nextPage = currentPage + 1;
			const url = new URL(config.restUrl);
			url.searchParams.set('page', nextPage);
			url.searchParams.set('per_page', perPage);
			url.searchParams.set('orderby', 'date');
			url.searchParams.set('order', 'desc');
			url.searchParams.set('_embed', 'wp:featuredmedia,wp:term');

			// ⚡ Bolt Optimization: Limit JSON payload size and avoid expensive server-side
			// computations for unused fields. We must include _links and _embedded for _embed to work.
			url.searchParams.set('_fields', 'id,date,link,title,excerpt,content,post_format,interaction_count,_links,_embedded');

			const resp = await fetch(url.toString(), {
				headers: { 'X-WP-Nonce': config.nonce }
			});

			if (!resp.ok) {
				if (resp.status === 400) {
					// No more pages
					allLoaded = true;
					observer.disconnect();
					return;
				}
				throw new Error('HTTP ' + resp.status);
			}

			const posts = await resp.json();
			const totalPagesHeader = resp.headers.get('X-WP-TotalPages');
			if (totalPagesHeader) {
				const serverTotalPages = parseInt(totalPagesHeader, 10);
				if (nextPage >= serverTotalPages) {
					allLoaded = true;
					observer.disconnect();
				}
			}

			// Append cards with staggered animation
			const fragment = document.createDocumentFragment();
			posts.forEach((post, i) => {
				const card = buildCard(post);
				card.style.animationDelay = (i * 0.05) + 's';
				fragment.appendChild(card);
			});
			grid.appendChild(fragment);

			currentPage = nextPage;

		} catch (err) {
			console.error('[tacobout] Failed to load posts:', err);
			allLoaded = true;
			observer.disconnect();
		} finally {
			isLoading = false;
			spinner.style.display = 'none';
		}
	}

	/* ============================================
	   INTERSECTION OBSERVER
	   ============================================ */
	const observer = new IntersectionObserver(
		(entries) => {
			if (entries[0].isIntersecting && !isLoading && !allLoaded) {
				loadMorePosts();
			}
		},
		{ rootMargin: '400px' } // Trigger 400px before reaching sentinel
	);

	if (!allLoaded) {
		observer.observe(sentinel);
	}

	/* ============================================
	   SCROLL-TO-TOP FAB
	   ============================================ */
	const fab = document.createElement('button');
	fab.className = 'tacobout-scroll-top';
	fab.setAttribute('aria-label', 'Scroll to top');
	fab.setAttribute('title', 'Scroll to top');
	fab.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>`;
	document.body.appendChild(fab);

	fab.addEventListener('click', () => {
		window.scrollTo({ top: 0, behavior: 'smooth' });
	});

	let fabVisible = false;
	let ticking = false;

	function updateFab() {
		const shouldShow = window.scrollY > 400;
		if (shouldShow !== fabVisible) {
			fabVisible = shouldShow;
			fab.classList.toggle('is-visible', shouldShow);
		}
		ticking = false;
	}

	window.addEventListener('scroll', () => {
		if (!ticking) {
			requestAnimationFrame(updateFab);
			ticking = true;
		}
	}, { passive: true });

	// Initial check
	updateFab();

})();
