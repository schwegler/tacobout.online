/**
 * Tacobout Infinite Scroll + Scroll-to-Top
 *
 * - IntersectionObserver-based infinite scroll using the WP REST API
 * - Floating scroll-to-top button
 * - Progressive enhancement: pagination remains functional without JS
 */
(function () {
  "use strict";

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
	const grid = document.querySelector('.tacobout-magazine-grid, body.author .wp-block-post-template');
	if (!grid) return;

  // Mark body so CSS can hide pagination
  document.body.classList.add("tacobout-infinite-scroll-active");

	// Optional JS-based masonry fallback if CSS grid-template-rows: masonry isn't supported yet
	function layoutMasonryGrid() {
		// Only run fallback if CSS grid masonry isn't supported natively
		if (CSS.supports && CSS.supports('grid-template-rows', 'masonry')) return;

		const rowHeight = 10; // Use 10px instead of 1px to avoid the 10000 limit
		grid.style.gridAutoRows = rowHeight + 'px';
		grid.style.rowGap = '0px'; // Disable CSS grid row gaps, we use margin instead

		const items = grid.querySelectorAll('.wp-block-post');

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
	}

	// Make it global so author page can use it
	window.layoutMasonryGrid = layoutMasonryGrid;

	window.addEventListener('load', layoutMasonryGrid);

	// Ensure layout is recalculated when fonts load (prevent text wrapping from shifting heights)
	if (document.fonts) {
		document.fonts.ready.then(layoutMasonryGrid);
	}

	// Capture phase image load listener to recalculate layout after specific images finish loading in the grid
	grid.addEventListener('load', (e) => {
		if (e.target.tagName && e.target.tagName.toLowerCase() === 'img') {
			layoutMasonryGrid();
		}
	}, true);

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

	window.addEventListener('resize', debounce(layoutMasonryGrid, 150));
	setTimeout(layoutMasonryGrid, 100);


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

  function showEndMessage() {
    endMessage.style.display = "block";
  }

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
    showEndMessage();
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

			// ⚡ Bolt Optimization: Limit the REST API payload to only required fields.
			// This reduces JSON download size and parsing time. _links and _embedded
			// are required for the _embed parameter to work correctly.
			url.searchParams.set('_fields', 'id,date,link,title,excerpt,content,post_format,interaction_count,_links,_embedded');

			const resp = await fetch(url.toString(), {
				headers: { 'X-WP-Nonce': config.nonce }
			});

			if (!resp.ok) {
				if (resp.status === 400) {
					// No more pages
					allLoaded = true;
					showEndMessage();
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
					showEndMessage();
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
			// Re-layout masonry if needed
			setTimeout(layoutMasonryGrid, 100);

			currentPage = nextPage;

		} catch (err) {
			console.error('[tacobout] Failed to load posts:', err);
			allLoaded = true;
			showEndMessage();
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

  // Initial check
  updateFab();
})();
