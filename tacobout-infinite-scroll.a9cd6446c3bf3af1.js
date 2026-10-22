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

	// Keep chronological DOM order; the measured grid fills the shortest column.
	const preparedCards = new WeakSet();
	const masonry = window.TacoboutMasonry?.create(grid);
	function layoutMasonryGrid() { masonry?.schedule(); }

	function normalizeFeedPreview(card, content) {
		const permalink = card.querySelector('.wp-block-post-title a, .wp-block-post-date a')?.href;
		if (!permalink) return;
		const title = card.querySelector('.wp-block-post-title')?.textContent.trim() || 'post';
		const format = ['image', 'video', 'link'].find(type => card.classList.contains('tacobout-format-' + type));
		if (format && content && !content.dataset.feedPreview) {
			const source = Array.from(content.querySelectorAll('a[href]')).find(link => {
				try { const url = new URL(link.href, location.href); return /^https?:$/.test(url.protocol) && url.hostname !== location.hostname; } catch { return false; }
			});
			if (format === 'link') {
				const boundary = content.querySelector('[id^="more-"], a.more-link');
				if (boundary) { const range = document.createRange(); range.setStartBefore(boundary); range.setEnd(content, content.childNodes.length); range.deleteContents(); }
			}
			const bodyImage = format === 'link' ? content.querySelector('img:not(.wp-block-post-featured-image img)') : null;
			let media = null;
			if (bodyImage && window.DOMPurify) {
				const figure = bodyImage.closest('figure');
				const original = figure && figure.querySelectorAll('img').length === 1 && !figure.querySelector('video, iframe, object, embed') ? figure : bodyImage;
				media = document.createElement('div'); media.className = 'tacobout-link-media';
				media.innerHTML = window.DOMPurify.sanitize(original.outerHTML, {USE_PROFILES: {html: true}});
			}
			let primary = format === 'link' ? Array.from(content.querySelectorAll('blockquote, p:not(figure p)')).find(node => node.textContent.trim()) : content.querySelector(format === 'image' ? 'img' : 'video, iframe, object, embed');
			if (primary && format !== 'link') primary = primary.closest('figure, .wp-block-embed, .wp-video') || primary;
			if (primary && format === 'link') {
				const quote = document.createElement(primary.tagName === 'BLOCKQUOTE' ? 'blockquote' : 'p');
				const words = primary.textContent.trim().split(/\s+/);
				quote.textContent = words.slice(0, 55).join(' ') + (words.length > 55 ? '…' : ''); primary = quote;
			}
			content.replaceChildren(...(media ? [media] : []), ...(primary ? [primary] : []));
			if (source) {
				const row = document.createElement('p'), link = document.createElement('a');
				row.className = 'tacobout-card-source'; link.className = 'tacobout-link-source'; link.href = source.href;
				link.textContent = 'Open source · ' + new URL(source.href).hostname.replace(/^www\./, '') + ' ↗'; row.append(link); content.append(row);
			}
			const row = document.createElement('p'), jump = document.createElement('a');
			row.className = 'tacobout-card-jump'; jump.href = permalink; jump.className = 'wp-block-post-excerpt__more-link'; row.append(jump); content.append(row);
			content.dataset.feedPreview = format;
		}
		card.querySelectorAll('a.more-link, a.wp-block-post-excerpt__more-link').forEach(link => {
			link.textContent = 'Read more'; link.setAttribute('aria-label', 'Read more: ' + title);
		});
	}

	function prepareCards(cards) {
		// ⚡ Bolt: Cache all posts array outside the loop to prevent O(N²) DOM queries
		const allPosts = Array.from(grid.querySelectorAll('.wp-block-post'));

		cards.forEach(card => {
			if (preparedCards.has(card)) return;
			preparedCards.add(card);
			const content = card.querySelector('.wp-block-post-content');
			normalizeFeedPreview(card, content);
			const featured = card.querySelector('.wp-block-post-featured-image');
			const media = !card.classList.contains('tacobout-format-standard') && content?.querySelector('img, video, iframe, audio, .wp-block-gallery');
			if (featured && media && !card.classList.contains('tacobout-format-standard')) featured.remove();
			// Explicit intrinsic ratios also avoid the 1500px containment placeholder
			// browsers can give WordPress lazy images with sizes="auto".
			card.querySelectorAll('img').forEach(img => {
				const sizes = img.getAttribute('sizes');
				if (sizes?.startsWith('auto,')) img.setAttribute('sizes', sizes.replace(/^auto,\s*/, ''));
				const sizeImage = () => {
					const width = img.naturalWidth || Number(img.getAttribute('width'));
					const height = img.naturalHeight || Number(img.getAttribute('height'));
					if (width && height) img.style.setProperty('aspect-ratio', width + ' / ' + height, 'important');
					layoutMasonryGrid();
				};
				sizeImage();
				img.addEventListener('load', sizeImage);
			});
			const index = allPosts.indexOf(card);
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
		});
		masonry?.refresh();
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

	// Search, author, date and custom-type queries keep native scoped pagination.
	if (!config.enableLoading) return;
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

	const endMessage = document.createElement("div");
	endMessage.className = "tacobout-end-of-feed";
	endMessage.style.cssText = "display: none;";
	endMessage.setAttribute("aria-live", "polite");

	const tacoBreakMascot = (config && config.mascotTacoBreak)
		? config.mascotTacoBreak
		: ((config && config.siteUrl ? config.siteUrl : '') + '/wp-content/themes/tacobout/assets/brand/telly_pose_02_taco_break.png');

	endMessage.innerHTML = `
		<div class="tacobout-end-card">
			<img src="${tacoBreakMascot}" alt="Telly having a taco break" class="mascot-sticker tacobout-end-mascot" />
			<p class="tacobout-end-headline">You’re caught up.</p>
			<p class="tacobout-end-subcopy">There’s more in the archive. The collection continues on Trove.</p>
			<a href="${config.siteUrl}/in/everything/" class="tacobout-end-archive">Browse the archive →</a>
			<a href="https://trove.schweg.xyz" class="tacobout-sister-nav-pill">Explore Trove</a>
		</div>
	`;

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

		// Use native embedded public author data, including the avatar URL/data filters.
		const author = post._embedded?.author?.[0];
		const avatarUrl = author?.avatar_urls?.['48'] || author?.avatar_urls?.['24'];
		let authorAvatarHtml = '';
		if (config.showAuthorAvatars && !document.body.classList.contains('author') && author?.name && author?.link && avatarUrl) {
			const label = (config.authorPostsLabel || 'Posts by %s').replace('%s', author.name);
			authorAvatarHtml = `<div class="wp-block-avatar tacobout-card-author-avatar"><a class="wp-block-avatar__link" href="${escHtml(author.link)}" aria-label="${escHtml(label)}"><img src="${escHtml(avatarUrl)}" alt="" width="24" height="24" loading="lazy" decoding="async" /></a></div>`;
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
					${authorAvatarHtml}
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
		if (showContent && post.tacobout_feed_content) {
			contentHtml = post.tacobout_feed_content;
		} else if (showContent && post.content && post.content.rendered) {
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

		const rawHtml = `
			${badgeHtml}
			<div class="wp-block-group tacobout-card-inner">
				${featuredImageHtml}
				${postMetaHtml}
				${titleHtml}
				${excerptHtml}
				${contentHtml}
			</div>
		`;
		if (!window.DOMPurify) {
			console.error("DOMPurify not loaded. Aborting feed rendering to prevent XSS.");
			return document.createElement("li");
		}
		li.innerHTML = window.DOMPurify.sanitize(rawHtml, { USE_PROFILES: { html: true, svg: true }, ADD_TAGS: ['iframe'], ADD_ATTR: ['target'] });

		// REST excerpts do not inherit the Query block's moreText setting.
		// Match the server-rendered cards without duplicating an existing link.
		const excerpt = li.querySelector('.wp-block-post-excerpt');
		if (excerpt && !excerpt.querySelector('.wp-block-post-excerpt__more-link')) {
			const more = document.createElement('a');
			more.className = 'wp-block-post-excerpt__more-link';
			more.href = post.link;
			more.textContent = 'Read more';
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
		const sepRawHtml = `
			<span class="tacobout-overflow-separator-label">
				<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
				You've seen all <strong>${escHtml(config.termName)}</strong> posts &mdash; here's everything else
			</span>
		`;
		if (!window.DOMPurify) return;
		sep.innerHTML = window.DOMPurify.sanitize(sepRawHtml, { USE_PROFILES: { html: true, svg: true } });
		grid.appendChild(sep);
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
				url.searchParams.set('_embed', 'author,wp:featuredmedia,wp:term');
				url.searchParams.set('_fields', 'id,author,date,link,title,excerpt,content,tacobout_feed_content,post_format,interaction_count,_links,_embedded');

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
			const errorHeadline = endMessage.querySelector('.tacobout-end-headline');
			if (errorHeadline) {
				errorHeadline.textContent = 'Failed to load more posts.';
			} else {
				endMessage.textContent = 'Failed to load more posts.';
			}
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
		// The shared provider loader is already present on feeds with Bluesky
		// embeds. Scan only newly inserted cards, without evaluating it again.
		if (window.bluesky && typeof window.bluesky.scan === 'function') {
			newCards.forEach(card => window.bluesky.scan(card));
		}
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

})();
