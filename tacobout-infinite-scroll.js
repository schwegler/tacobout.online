/**
 * Tacobout Infinite Scroll + Scroll-to-Top + Theme Toggle
 *
 * - IntersectionObserver-based infinite scroll using the WP REST API
 * - Two-phase fetch on taxonomy pages: filtered posts → separator → global feed
 * - Floating scroll-to-top button
 * - Theme toggle (dark/light) with localStorage persistence
 * - Progressive enhancement: pagination remains functional without JS
 */
(function () {
	"use strict";

	// Defense-in-depth: don't run inside the Site Editor's preview iframe
	if (window.location.search.indexOf('wp_theme_preview') !== -1) {
		return;
	}

	/* ============================================
		 THEME TOGGLE — run before any layout to avoid flash
		 ============================================ */
	(function applyThemePreference() {
		const stored = localStorage.getItem('tacobout-theme');
		const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
		const theme = stored || (prefersDark ? 'dark' : 'light');
		document.documentElement.setAttribute('data-theme', theme);
	})();

	/* ============================================
		 CONFIG
		 ============================================ */
	const config = window.tacoboutScroll;
	if (!config) return;

	// Two-phase fetch state for taxonomy archives
	const hasTerm = !!(config.termId && config.termType);
	let termPhase = hasTerm; // true = still fetching filtered term posts
	let termCurrentPage = 1;
	const termTotalPages = hasTerm ? parseInt(config.termTotalPages, 10) || 1 : 0;
	let separatorInserted = false;

	let currentPage = hasTerm ? 0 : 1;
	const totalPages = parseInt(config.totalPages, 10);
	const perPage = parseInt(config.perPage, 10);
	let isLoading = false;
	let allLoaded = hasTerm ? false : (currentPage >= totalPages);



	/* ============================================
		 DOM REFS
		 ============================================ */
	const grid = document.querySelector('.tacobout-magazine-grid, body.author .wp-block-post-template');
	if (!grid) return;

	// Keep track of posts we've already displayed to prevent duplicates
	// (especially when transitioning from a term-filtered fetch to a global fetch)
	const seenPostIds = new Set();
	grid.querySelectorAll('.wp-block-post').forEach(el => {
		const classMatch = el.className.match(/\bpost-(\d+)\b/);
		if (classMatch) seenPostIds.add(parseInt(classMatch[1], 10));
	});

	// Four-pixel tracks give each card its own height without equal-height rows.
	// Keep DOM order intact and place cards against the shortest column.
	let layoutFrame = 0;
	const preparedCards = new WeakSet();
	const resizeObserver = typeof ResizeObserver !== 'undefined'
		? new ResizeObserver(layoutMasonryGrid) : null;

	function layoutMasonryGrid() {
		if (layoutFrame) return;
		layoutFrame = requestAnimationFrame(() => {
			layoutFrame = 0;
			grid.classList.add('tacobout-bento-ready');
			const columns = parseInt(getComputedStyle(grid).getPropertyValue('--tacobout-columns'), 10) || 1;
			const skyline = Array(columns).fill(0);
			const sidebar = grid.querySelector('.tacobout-grid-sidebar');
			const wideSidebar = sidebar && window.matchMedia('(min-width: 2000px)').matches;
			grid.style.minHeight = wideSidebar ? sidebar.getBoundingClientRect().height + 'px' : '';

			if (grid.dataset.bentoColumns !== String(columns)) {
				Array.from(grid.children).forEach(card => { card.style.gridColumn = ''; card.style.gridRow = ''; });
				grid.dataset.bentoColumns = String(columns);
			}
			// Reserve the upper-right slot before placing posts in the shortest column.
			if (sidebar && !wideSidebar && columns > 1) {
				sidebar.style.gridColumn = String(columns);
				const rows = Math.ceil((sidebar.getBoundingClientRect().height + 20) / 4);
				sidebar.style.gridRow = '1 / span ' + rows;
				skyline[columns - 1] = rows;
			}
			Array.from(grid.children).forEach(card => {
				if (card === sidebar && (wideSidebar || columns > 1)) {
					if (wideSidebar) { card.style.gridColumn = ''; card.style.gridRow = ''; }
					return;
				}
				const separator = card.classList.contains('tacobout-overflow-separator');
				let span = separator ? columns : 1;
				let column = skyline.indexOf(Math.min(...skyline));
				let top = skyline[column];
				if (separator) {
					column = 0;
					top = Math.max(...skyline);
				} else if (columns > 1 && card.classList.contains('tacobout-card-wide')) {
					// Only bridge columns when their bottoms nearly line up. Otherwise
					// a wide card would seal a large, unfillable hole beneath itself.
					let candidate = -1;
					let lowest = Infinity;
					for (let i = 0; i < columns - 1; i++) {
						const edge = Math.max(skyline[i], skyline[i + 1]);
						if (Math.abs(skyline[i] - skyline[i + 1]) <= 8 && edge < lowest) {
							candidate = i; lowest = edge;
						}
					}
					if (candidate !== -1 && lowest <= top + 8) { span = 2; column = candidate; top = lowest; }
				}
				card.classList.toggle('tacobout-card-spanning', span > 1 && !separator);
				const placement = (column + 1) + ' / span ' + span;
				if (card.style.gridColumn !== placement) card.style.gridColumn = placement;
				const rows = Math.ceil((card.getBoundingClientRect().height + 20) / 4);
				const row = (top + 1) + ' / span ' + rows;
				if (card.style.gridRow !== row) card.style.gridRow = row;
				for (let i = column; i < column + span; i++) skyline[i] = top + rows;
			});
		});
	}

	function prepareCards(cards) {
		cards.forEach(card => {
			if (preparedCards.has(card)) return;
			preparedCards.add(card);
			const content = card.querySelector('.wp-block-post-content');
			const featured = card.querySelector('.wp-block-post-featured-image');
			const media = !card.classList.contains('tacobout-format-standard') && content?.querySelector('img, video, iframe, audio, .wp-block-gallery');
			if (featured && media && !card.classList.contains('tacobout-format-standard')) featured.remove();
			// Explicit intrinsic ratios also avoid the 1500px containment placeholder
			// browsers can give WordPress lazy images with sizes="auto".
			card.querySelectorAll('img').forEach(img => {
				const sizeImage = () => {
					const width = img.naturalWidth || Number(img.getAttribute('width'));
					const height = img.naturalHeight || Number(img.getAttribute('height'));
					if (width && height) img.style.setProperty('aspect-ratio', width + ' / ' + height, 'important');
					layoutMasonryGrid();
				};
				sizeImage();
				img.addEventListener('load', sizeImage);
			});
			const index = Array.from(grid.querySelectorAll('.wp-block-post')).indexOf(card);
			const gallery = !card.classList.contains('tacobout-format-standard') && content?.querySelector('.wp-block-gallery, .gallery');
			card.classList.toggle('tacobout-card-wide', !!gallery ||
				(!!featured && (index === 0 || index % 7 === 4)));
			card.classList.toggle('tacobout-card-horizontal', !!featured && !media);

			// Honor provider ratios, including portrait video, without squashing audio embeds.
			content?.querySelectorAll('iframe').forEach(frame => {
				const src = frame.getAttribute('src') || '';
				if (!/youtube(?:-nocookie)?\.com|youtu\.be|vimeo\.com|videopress\.com/.test(src)) return;
				const block = frame.closest('.wp-block-embed');
				const ratioClass = block?.className.match(/wp-embed-aspect-(\d+)-(\d+)/);
				const w = Number(frame.getAttribute('width'));
				const h = Number(frame.getAttribute('height'));
				const ratio = ratioClass ? Number(ratioClass[1]) / Number(ratioClass[2]) :
					(/\/shorts\//.test(src) ? 9 / 16 : (w && h ? w / h : 16 / 9));
				frame.classList.add('tacobout-video-frame');
				frame.style.aspectRatio = String(ratio);
				frame.loading = 'lazy';
				if (!frame.title) frame.title = 'Video player';
			});

			// Collapse long prose at block boundaries, never halfway through a player.
			if (content && !card.classList.contains('tacobout-format-standard') && content.textContent.trim().length > 650 && !content.querySelector('details')) {
				const blocks = Array.from(content.children);
				let length = 0;
				let cutoff = blocks.length;
				for (let i = 0; i < blocks.length; i++) {
					length += blocks[i].textContent.length;
					if (length > 300) { cutoff = i + 1; break; }
				}
				const firstMedia = blocks.findIndex(block => block.matches('figure, video, audio, iframe') || block.querySelector('img, video, audio, iframe'));
				cutoff = Math.max(cutoff, firstMedia + 1);
				if (cutoff < blocks.length) {
					const details = document.createElement('details');
					details.className = 'tacobout-card-more';
					const summary = document.createElement('summary');
					summary.textContent = 'Keep reading';
					details.append(summary);
					blocks.slice(cutoff).forEach(block => details.append(block));
					content.append(details);
				}
			}
			if (card.classList.contains('tacobout-format-link') && content) {
				const source = Array.from(content.querySelectorAll('a[href]')).find(link => {
					const url = new URL(link.href, location.href);
					return /^https?:$/.test(url.protocol) && url.hostname !== location.hostname;
				});
				if (source) {
					const link = document.createElement('a');
					link.className = 'tacobout-link-source';
					link.href = source.href;
					link.textContent = new URL(source.href).hostname.replace(/^www\./, '') + ' ↗';
					content.append(link);
				}
			}
			if (content && card.matches('.tacobout-format-gallery, .tacobout-format-image')) {
				const images = Array.from(card.querySelectorAll('.wp-block-post-content img, .wp-block-post-featured-image img'));
				if (images.length) {
					const view = document.createElement('button');
					view.type = 'button';
					view.className = 'tacobout-view-media';
					view.textContent = images.length > 1 ? 'View ' + images.length + ' photos ↗' : 'View image ↗';
					view.addEventListener('click', () => openMedia(images));
					content.append(view);
				}
			}
			resizeObserver?.observe(card);
		});
		layoutMasonryGrid();
	}

	function openMedia(images) {
		const dialog = document.createElement('dialog');
		dialog.className = 'tacobout-media-dialog';
		dialog.setAttribute('aria-label', 'Image viewer');
		dialog.innerHTML = '<div class="tacobout-media-toolbar"><button type="button" data-close autofocus>Close</button><button type="button" data-prev aria-label="Previous image">←</button><span aria-live="polite"></span><button type="button" data-next aria-label="Next image">→</button></div><figure><img alt=""><figcaption></figcaption></figure>';
		let index = 0;
		const render = () => {
			const source = images[index];
			const full = source.closest('a')?.href;
			const img = dialog.querySelector('img');
			img.src = source.dataset.fullUrl || source.dataset.origFile || (full && /\.(?:png|jpe?g|webp|gif|avif)(?:[?#]|$)/i.test(full) ? full : source.currentSrc || source.src);
			img.alt = source.alt;
			dialog.querySelector('figcaption').textContent = source.closest('figure')?.querySelector('figcaption')?.textContent || source.alt;
			dialog.querySelector('[aria-live]').textContent = (index + 1) + ' / ' + images.length;
			dialog.querySelector('[data-prev]').disabled = index === 0;
			dialog.querySelector('[data-next]').disabled = index === images.length - 1;
		};
		dialog.querySelector('[data-close]').onclick = () => dialog.close();
		dialog.querySelector('[data-prev]').onclick = () => { index--; render(); };
		dialog.querySelector('[data-next]').onclick = () => { index++; render(); };
		dialog.addEventListener('click', event => {
			const rect = dialog.getBoundingClientRect();
			const isInDialog = rect.top <= event.clientY && event.clientY <= rect.bottom && rect.left <= event.clientX && event.clientX <= rect.right;
			if (!isInDialog) dialog.close();
		});
		dialog.addEventListener('keydown', event => {
			if (event.key === 'ArrowRight' && index < images.length - 1) { index++; render(); event.preventDefault(); }
			if (event.key === 'ArrowLeft' && index > 0) { index--; render(); event.preventDefault(); }
		});
		dialog.addEventListener('close', () => dialog.remove(), { once: true });
		document.body.append(dialog);
		render();
		dialog.showModal();
	}

	function layoutNewItems(newItems) {
		prepareCards(newItems);
		setTimeout(() => {
			const sentinel = document.querySelector('.tacobout-scroll-sentinel');
			if (sentinel && sentinel.getBoundingClientRect().top < innerHeight + 400 && !isLoading && !allLoaded) loadMorePosts();
		}, 100);
	}

	window.layoutMasonryGrid = layoutMasonryGrid;
	window._tacoboutObserveCards = prepareCards;
	prepareCards(Array.from(grid.querySelectorAll('.wp-block-post')));
	resizeObserver?.observe(grid);
	const discovery = grid.querySelector('.tacobout-discovery-sidebar');
	if (discovery) resizeObserver?.observe(discovery);
	grid.addEventListener('load', layoutMasonryGrid, true);
	grid.addEventListener('loadedmetadata', layoutMasonryGrid, true);
	grid.addEventListener('toggle', layoutMasonryGrid, true);
	window.addEventListener('resize', layoutMasonryGrid);
	document.fonts?.ready.then(layoutMasonryGrid);
	document.body.classList.add('tacobout-infinite-scroll-active');


	/* ============================================
	   SENTINEL + SPINNER
	   ============================================ */
	const sentinel = document.createElement("div");
	sentinel.className = "tacobout-scroll-sentinel";
	sentinel.setAttribute("aria-hidden", "true");

	const spinner = document.createElement("div");
	spinner.className = "tacobout-infinite-scroll-spinner";
	spinner.setAttribute("aria-label", "Loading more posts");
	spinner.setAttribute("role", "status");
	spinner.setAttribute("aria-live", "polite");
	spinner.innerHTML = `
		<div class="tacobout-spinner-dots">
			<span></span><span></span><span></span>
		</div>
	`;
	spinner.style.display = "none";

	const endMessage = document.createElement("p");
	endMessage.className = "has-muted-color has-text-color";
	endMessage.style.cssText =
		"text-align: center; padding: 2rem 0; font-size: 0.875rem; display: none;";
	endMessage.textContent = "You have reached the end of the feed.";
	endMessage.setAttribute("aria-live", "polite");

	// Insert sentinel, spinner, and end message after the grid's parent query block
	const queryBlock = grid.closest(".wp-block-query");
	if (queryBlock) {
		queryBlock.parentNode.insertBefore(endMessage, queryBlock.nextSibling);
		queryBlock.parentNode.insertBefore(spinner, endMessage);
		queryBlock.parentNode.insertBefore(sentinel, spinner);
	} else {
		grid.parentNode.insertBefore(endMessage, grid.nextSibling);
		grid.parentNode.insertBefore(spinner, endMessage);
		grid.parentNode.insertBefore(sentinel, spinner);
	}

	if (allLoaded) {
		endMessage.style.display = 'block';
	}

	/* ============================================
		 CARD BUILDER
		 Replicates the template structure from home.html
		 ============================================ */

	// Performance optimization: Instantiate Intl.DateTimeFormat once outside the loop/render cycle
	// Calling toLocaleDateString() repeatedly is expensive.
	const dateFormatter = new Intl.DateTimeFormat("en-US", {
		year: "numeric",
		month: "long",
		day: "numeric",
	});

	function buildCard(post) {
		const format = post.post_format || "standard";
		const formatClass = "tacobout-format-" + format;
		const interactionCount = post.interaction_count || 0;

		// Determine what to show/hide based on format
		const showFeaturedImage = format === "standard" || !/<(?:img|video|iframe|audio)\b|wp-block-gallery/i.test(post.content?.rendered || "");
		const showExcerpt = format === "standard";
		const showContent = format !== "standard";

		// Build featured image
		let featuredImageHtml = "";
		if (
			showFeaturedImage &&
			post._embedded &&
			post._embedded["wp:featuredmedia"] &&
			post._embedded["wp:featuredmedia"][0]
		) {
			const media = post._embedded["wp:featuredmedia"][0];
			const imgSrc =
				media.media_details?.sizes?.medium_large?.source_url ||
				media.source_url;
			const imgAlt = media.alt_text || "";
			featuredImageHtml = `
				<figure class="wp-block-post-featured-image">
					<a href="${escHtml(post.link)}">
						<img src="${escHtml(imgSrc)}" alt="${escHtml(imgAlt)}" loading="lazy"
							width="${Number(media.media_details?.sizes?.medium_large?.width || media.media_details?.width) || 800}" height="${Number(media.media_details?.sizes?.medium_large?.height || media.media_details?.height) || 600}"
							style="border-radius:12px;width:100%;height:auto" />
					</a>
				</figure>
			`;
		}

		// Build post meta (categories + date)
		let categoriesHtml = "";
		if (
			post._embedded &&
			post._embedded["wp:term"] &&
			post._embedded["wp:term"][0]
		) {
			const cats = post._embedded["wp:term"][0];
			categoriesHtml = `
				<div class="wp-block-post-terms taxonomy-category">
					${cats.map((c) => `<a href="${escHtml(c.link)}" rel="tag">${escHtml(c.name)}</a>`).join(" ")}
				</div>
			`;
		}

		const dateObj = new Date(post.date);
		const dateFormatted = dateFormatter.format(dateObj);

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
				<a href="${escHtml(post.link)}">${escHtml(post.title.rendered) || "Untitled post"}</a>
			</h2>
		`;

		// Build excerpt (for standard format)
		let excerptHtml = "";
		if (showExcerpt && post.excerpt && post.excerpt.rendered) {
			excerptHtml = `
				<div class="wp-block-post-excerpt">
					${post.excerpt.rendered}
				</div>
			`;
		}

		// Build content (for non-standard formats)
		let contentHtml = "";
		if (showContent && post.content && post.content.rendered) {
			contentHtml = `
				<div class="wp-block-post-content entry-content">
					${post.content.rendered}
				</div>
			`;
		}

		// Build interaction badge
		let badgeHtml = "";
		if (interactionCount > 0) {
			const label =
				interactionCount === 1
					? "1 locally recorded interaction"
					: interactionCount + " locally recorded interactions";
			badgeHtml = `<a href="${escHtml(post.link)}" class="tacobout-interaction-badge" aria-label="${escHtml(label)}" title="${escHtml(label)}"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg> ${interactionCount}</a>`;
		}

		// Assemble card
		const li = document.createElement("li");
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

		// REST excerpts do not inherit the Query block's moreText setting.
		// Match the server-rendered cards without duplicating an existing link.
		const excerpt = li.querySelector('.wp-block-post-excerpt');
		if (excerpt && !excerpt.querySelector('.wp-block-post-excerpt__more-link')) {
			const more = document.createElement('a');
			more.className = 'wp-block-post-excerpt__more-link';
			more.href = post.link;
			more.textContent = 'Read more →';
			const target = excerpt.querySelector('p:last-child') || excerpt;
			target.append(document.createTextNode(' '), more);
		}

		return li;
	}

	function escHtml(str) {
		if (!str) return "";
		return String(str)
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;")
			.replace(/'/g, "&#39;");
	}

	/* ============================================
		 FETCH + APPEND
		 ============================================ */

	/**
	 * Build and insert the overflow separator between taxonomy posts and the
	 * global "everything else" feed.
	 */
	function insertOverflowSeparator() {
		if (separatorInserted) return;
		separatorInserted = true;

		const sep = document.createElement('li');
		sep.className = 'tacobout-overflow-separator';
		sep.innerHTML = `
			<span class="tacobout-overflow-separator-label">
				<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
				You've seen all <strong>${escHtml(config.termName)}</strong> posts &mdash; here's everything else
			</span>
		`;
		grid.appendChild(sep);
		resizeObserver?.observe(sep);
		// Separator spans both columns — trigger a layout pass
		setTimeout(layoutMasonryGrid, 50);
	}

	async function loadMorePosts() {
		if (isLoading || allLoaded) return;
		isLoading = true;
		spinner.style.display = 'flex';

		try {
			while (!allLoaded) {
				const url = new URL(config.restUrl);
				url.searchParams.set('per_page', perPage);
				url.searchParams.set('orderby', 'date');
				url.searchParams.set('order', 'desc');
				url.searchParams.set('_embed', 'wp:featuredmedia,wp:term');
				url.searchParams.set('_fields', 'id,date,link,title,excerpt,content,post_format,interaction_count,_links,_embedded');

				let nextPage;
				if (termPhase) {
					nextPage = termCurrentPage + 1;
					url.searchParams.set('page', nextPage);
					url.searchParams.set(config.termType, config.termId);
				} else {
					nextPage = currentPage + 1;
					url.searchParams.set('page', nextPage);
				}

				const resp = await fetch(url.toString(), {
					headers: { 'X-WP-Nonce': config.nonce }
				});

				if (!resp.ok) {
					if (resp.status === 400) {
						if (termPhase) {
							// Term posts exhausted — switch to global feed
							termPhase = false;
							insertOverflowSeparator();
							// Loop around to fetch global feed immediately
							continue;
						}
						// Global feed exhausted
						allLoaded = true;
						endMessage.style.display = 'block';
						observer.disconnect();
						break;
					}
					throw new Error('HTTP ' + resp.status);
				}

				let posts = await resp.json();
				const totalPagesHeader = resp.headers.get('X-WP-TotalPages');

				// Filter out already seen posts to prevent duplicates
				const newPosts = posts.filter(p => !seenPostIds.has(p.id));
				newPosts.forEach(p => seenPostIds.add(p.id));

				if (termPhase) {
					termCurrentPage = nextPage;
					if (newPosts.length > 0) {
						const newCards = appendCards(newPosts);
						if (window._tacoboutObserveCards) window._tacoboutObserveCards(newCards);
					}

					// Check if this is the last term page
					const serverTermPages = totalPagesHeader ? parseInt(totalPagesHeader, 10) : termTotalPages;
					if (nextPage >= serverTermPages) {
						termPhase = false;
						insertOverflowSeparator();
					}
				} else {
					currentPage = nextPage;
					if (totalPagesHeader) {
						const serverTotalPages = parseInt(totalPagesHeader, 10);
						if (nextPage >= serverTotalPages) {
							allLoaded = true;
							endMessage.style.display = 'block';
							observer.disconnect();
						}
					}

					if (newPosts.length > 0) {
						const newCards = appendCards(newPosts);
						if (window._tacoboutObserveCards) window._tacoboutObserveCards(newCards);
					}
				}

				// If we successfully appended new posts, we can break and wait for the user to scroll.
				// If we appended 0 posts (they were all duplicates), the loop will seamlessly fetch the next page.
				if (newPosts.length > 0) {
					break;
				}
			}
		} catch (err) {
			console.error('[tacobout] Failed to load posts:', err);
			// Restore server pagination if the network or REST endpoint fails.
			document.body.classList.remove('tacobout-infinite-scroll-active');
			allLoaded = true;
			endMessage.textContent = 'Failed to load more posts.';
			endMessage.style.display = 'block';
			observer.disconnect();
		} finally {
			isLoading = false;
			spinner.style.display = 'none';
		}
	}

	/**
	 * Append an array of post objects to the grid and return the new card elements.
	 */
	function appendCards(posts) {
		const newCards = [];
		const fragment = document.createDocumentFragment();
		posts.forEach((post, i) => {
			const card = buildCard(post);
			card.style.animationDelay = (i * 0.05) + 's';
			fragment.appendChild(card);
			newCards.push(card);
		});
		grid.appendChild(fragment);
		// Re-check sentinel position in case viewport needs more content
		setTimeout(() => layoutNewItems(newCards), 100);
		return newCards;
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
		{ rootMargin: "400px" }, // Trigger 400px before reaching sentinel
	);

	if (!allLoaded) {
		observer.observe(sentinel);
	}

	/* ============================================
		 SCROLL-TO-TOP FAB
		 ============================================ */
	const fab = document.createElement("button");
	fab.setAttribute("type", "button");
	fab.className = "tacobout-scroll-top";
	fab.setAttribute("aria-label", "Scroll to top");
	fab.setAttribute("title", "Scroll to top");
	fab.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 15 12 9 6 15"></polyline></svg>`;
	document.body.appendChild(fab);

	fab.addEventListener("click", () => {
		window.scrollTo({ top: 0, behavior: "smooth" });
		// Reset focus to top of document so keyboard users aren't trapped at the bottom
		document.body.setAttribute("tabindex", "-1");
		document.body.focus({ preventScroll: true });
		// Remove tabindex after focus so it doesn't stay focusable unnecessarily
		document.body.addEventListener("blur", function onBlur() {
			document.body.removeAttribute("tabindex");
			document.body.removeEventListener("blur", onBlur);
		});
	});

	let fabVisible = false;
	let ticking = false;

	function updateFab() {
		const shouldShow = window.scrollY > 400;
		if (shouldShow !== fabVisible) {
			fabVisible = shouldShow;
			fab.classList.toggle("is-visible", shouldShow);
			const themeFabEl = document.getElementById("tacobout-theme-toggle");
			if (themeFabEl) {
				themeFabEl.classList.toggle("is-stacked", shouldShow);
			}
		}
		ticking = false;
	}

	window.addEventListener(
		"scroll",
		() => {
			if (!ticking) {
				requestAnimationFrame(updateFab);
				ticking = true;
			}
		},
		{ passive: true },
	);


	/* ============================================
		 THEME TOGGLE FAB
		 ============================================ */
	const THEME_KEY = 'tacobout-theme';
	const SUN_ICON = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>`;
	const MOON_ICON = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>`;

	const themeFab = document.createElement('button');
	themeFab.setAttribute('type', 'button');
	themeFab.className = 'tacobout-theme-toggle';
	themeFab.id = 'tacobout-theme-toggle';
	document.body.appendChild(themeFab);

	function getCurrentTheme() {
		return document.documentElement.getAttribute('data-theme') || 'light';
	}

	function applyTheme(theme) {
		document.documentElement.setAttribute('data-theme', theme);
		localStorage.setItem(THEME_KEY, theme);
		const isDark = theme === 'dark';
		themeFab.innerHTML = isDark ? SUN_ICON : MOON_ICON;
		themeFab.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
		themeFab.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
	}

	// Set initial state based on what was already applied by the early script
	applyTheme(getCurrentTheme());

	themeFab.addEventListener('click', () => {
		const newTheme = getCurrentTheme() === 'light' ? 'dark' : 'light';
		applyTheme(newTheme);
	});

	// Keep in sync if system preference changes and user hasn't set a manual override
	window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
		if (!localStorage.getItem(THEME_KEY)) {
			applyTheme(e.matches ? 'dark' : 'light');
		}
	});

	// Initial check (must happen after theme toggle is in the DOM)
	updateFab();
})();
