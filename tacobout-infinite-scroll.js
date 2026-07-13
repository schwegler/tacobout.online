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

  // Mark body so CSS can hide pagination
  document.body.classList.add("tacobout-infinite-scroll-active");


	// Optional JS-based masonry fallback if CSS grid-template-rows: masonry isn't supported yet
	let initialLayoutDone = false;

	function layoutMasonryGrid() {
		// Only run fallback if CSS grid masonry isn't supported natively
		if (CSS.supports && CSS.supports('grid-template-rows', 'masonry')) return;

		const rowHeight = 10; // Use 10px instead of 1px to avoid the 10000 limit
		grid.style.gridAutoRows = rowHeight + 'px';
		grid.style.rowGap = '0px'; // Disable CSS grid row gaps, we use margin instead

		const items = grid.querySelectorAll('.wp-block-post, .tacobout-overflow-separator');

		// Anchor scroll position to a visible element so the reset below doesn't
		// cause the browser to jump the viewport. Pick the last item that is
		// currently above (or at) the top of the viewport as the anchor.
		let anchorEl = null;
		let anchorOffsetBefore = 0;
		const viewportTop = window.scrollY;
		Array.from(items).forEach(item => {
			const rect = item.getBoundingClientRect();
			if (rect.top <= 0) {
				anchorEl = item;
				anchorOffsetBefore = rect.top; // negative when scrolled past
			}
		});

		// Phase 1: Reset styles (writes)
		items.forEach(item => {
			item.style.gridRowEnd = 'auto';
		});

		// Force reflow once
		grid.offsetHeight;

		// Phase 2: Measure elements (reads)
		const measurements = Array.from(items).map(item => {
			const style = window.getComputedStyle(item);
			const marginTop = parseFloat(style.marginTop) || 0;
			const marginBottom = parseFloat(style.marginBottom) || 0;
			const height = item.getBoundingClientRect().height;
			return {
				item,
				span: Math.ceil((height + marginTop + marginBottom) / rowHeight)
			};
		});

		// Phase 3: Apply spans (writes)
		measurements.forEach(({ item, span }) => {
			item.style.gridRowEnd = 'span ' + span;
		});

		// Restore scroll position relative to the anchor element so the page
		// doesn't jump after spans are re-applied.
		if (anchorEl) {
			const anchorOffsetAfter = anchorEl.getBoundingClientRect().top;
			const drift = anchorOffsetAfter - anchorOffsetBefore;
			if (Math.abs(drift) > 1) {
				window.scrollBy({ top: drift, behavior: 'instant' });
			}
		}

		initialLayoutDone = true;
	}

	/**
	 * Lightweight variant used after infinite-scroll appends new cards.
	 * Only measures and sets spans for the provided new items — existing
	 * cards are never reset, so the viewport never jumps.
	 */
	function layoutNewItems(newItems) {
		if (CSS.supports && CSS.supports('grid-template-rows', 'masonry')) return;
		if (!newItems || newItems.length === 0) return;

		const rowHeight = 10;

		// Ensure the grid row settings are always correct before measuring.
		// This guards against being called before layoutMasonryGrid has run
		// (e.g. an image fires its load event before the initial layout).
		grid.style.gridAutoRows = rowHeight + 'px';
		grid.style.rowGap = '0px';

		// Phase 1: Reset only the new items (writes)
		newItems.forEach(item => {
			item.style.gridRowEnd = 'auto';
		});

		// Force reflow once
		grid.offsetHeight;

		// Phase 2: Measure new items (reads)
		const measurements = newItems.map(item => {
			const style = window.getComputedStyle(item);
			const marginTop = parseFloat(style.marginTop) || 0;
			const marginBottom = parseFloat(style.marginBottom) || 0;
			const height = item.getBoundingClientRect().height;
			return {
				item,
				span: Math.ceil((height + marginTop + marginBottom) / rowHeight)
			};
		});

		// Phase 3: Apply spans (writes)
		measurements.forEach(({ item, span }) => {
			item.style.gridRowEnd = 'span ' + span;
		});

		// If the sentinel is still in view after layout, trigger another fetch
		// so the infinite scroll doesn't stall on large screens.
		setTimeout(() => {
			const sentinel = document.querySelector('.tacobout-scroll-sentinel');
			if (!sentinel) return;
			const rect = sentinel.getBoundingClientRect();
			if (rect.top < window.innerHeight + 400 && !isLoading && !allLoaded) {
				loadMorePosts();
			}
		}, 50);
	}

	// Make it global so author page can use it
	window.layoutMasonryGrid = layoutMasonryGrid;

	function debounce(func, wait) {
		let timeout;
		return function executedFunction(...args) {
			const later = () => {
				clearTimeout(timeout);
				func(...args);
			};
			clearTimeout(timeout);
			timeout = setTimeout(later, wait);
		};
	}

	// Single debounced full layout — used for resize and initial load triggers
	const debouncedLayout = debounce(layoutMasonryGrid, 150);

	window.addEventListener('resize', debouncedLayout);

	// Run layout once after fonts + window are ready (a single pass is enough)
	if (document.fonts) {
		document.fonts.ready.then(() => setTimeout(layoutMasonryGrid, 50));
	} else {
		window.addEventListener('load', () => setTimeout(layoutMasonryGrid, 50));
	}

	// ResizeObserver — watches every card for height changes caused by anything:
	// lazy images finishing, gallery images loading, iframes expanding, etc.
	//
	// IMPORTANT: only re-spanning the changed card is not enough. CSS grid shares
	// row tracks across both columns, so when a card in column 1 grows it shifts
	// the row grid and can visually overlap cards in column 2 that have stale spans.
	// We must do a full re-layout whenever any card changes height.
	// The anchor-based scroll-position logic inside layoutMasonryGrid prevents jumps.
	if (typeof ResizeObserver !== 'undefined') {
		// Separate debounce from the resize-event one so they don't interfere
		const debouncedFullLayout = debounce(layoutMasonryGrid, 150);

		const cardResizeObserver = new ResizeObserver(() => {
			if (!initialLayoutDone) return; // full layout will handle everything
			debouncedFullLayout();
		});

		// Observe every existing card's inner content wrapper so ResizeObserver
		// picks up height changes from child elements (iframes, images, etc.)
		function observeCards(cards) {
			cards.forEach(card => {
				// Observe the inner wrapper so iframe/image expansion is detected
				const inner = card.querySelector('.tacobout-card-inner') || card;
				cardResizeObserver.observe(inner);
			});
		}

		// Observe initial cards once the DOM is ready
		observeCards(Array.from(grid.querySelectorAll('.wp-block-post')));

		// Expose so loadMorePosts can observe newly appended cards too
		window._tacoboutObserveCards = observeCards;
	}


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
  function buildCard(post) {
    const format = post.post_format || "standard";
    const formatClass = "tacobout-format-" + format;
    const interactionCount = post.interaction_count || 0;

    // Determine what to show/hide based on format
    const showFeaturedImage = format === "standard";
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
							style="border-radius:12px;aspect-ratio:16/9;object-fit:cover;width:100%" />
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
    const dateFormatted = dateObj.toLocaleDateString("en-US", {
      year: "numeric",
      month: "long",
      day: "numeric",
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
    let excerptHtml = "";
    if (showExcerpt && post.excerpt && post.excerpt.rendered) {
      excerptHtml = `
				<div class="wp-block-post-excerpt">
					<p class="wp-block-post-excerpt__excerpt">${post.excerpt.rendered}</p>
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
          ? "1 interaction"
          : interactionCount + " interactions";
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
		sep.setAttribute('aria-hidden', 'true');
		sep.innerHTML = `
			<span class="tacobout-overflow-separator-label">
				<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
				You've seen all <strong>${escHtml(config.termName)}</strong> posts &mdash; here's everything else
			</span>
		`;
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
			// Give each new card a large provisional span immediately so it
			// stacks below existing content and doesn't overlap while we wait
			// for the measured span to be applied.
			card.style.gridRowEnd = 'span 200';
			fragment.appendChild(card);
			newCards.push(card);
		});
		grid.appendChild(fragment);
		// Re-layout only the newly added cards so existing cards (and the
		// scroll position) are never disturbed.
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
  fab.className = "tacobout-scroll-top";
  fab.setAttribute("aria-label", "Scroll to top");
  fab.setAttribute("title", "Scroll to top");
  fab.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>`;
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
