## 2024-06-25 - [WordPress Preconnect Hints]
**Learning:** In WordPress themes, preconnect hints for external resources (like Google Fonts) can be cleanly implemented using the `wp_resource_hints` filter, checking for the specific queued stylesheet using `wp_style_is( 'handle', 'queue' )` to ensure they are only added when needed.
**Action:** Use `wp_resource_hints` filter with `'crossorigin'` to add preconnect hints for critical external assets.

## 2024-07-25 - [CSS Animation Performance]
**Learning:** Full-screen background mesh animations using `background-position` and `background-size` cause continuous CPU layout repaints, which is a major performance bottleneck and drains battery.
**Action:** Create an oversized pseudo-element (e.g., `200vw`, `200vh`) and animate it using `transform: translate()` to offload the animation to the GPU (compositor thread). Add `will-change: transform` to optimize it further.

## 2024-07-26 - [Async Google Fonts Loading]
**Learning:** In WordPress themes, `wp_enqueue_style` adds blocking `<link rel="stylesheet">` tags. Blocking resources significantly slow down First Contentful Paint (FCP). To asynchronously load enqueued styles like Google Fonts, you can hook into `style_loader_tag` and inject the `media="print" onload="this.media='all'"` pattern with a `<noscript>` fallback.
**Action:** Use the `style_loader_tag` filter to modify specific external stylesheet `<link>` tags to load asynchronously.

## 2024-06-29 - Short-circuit FSE block rendering inside query loops for CSS-hidden blocks
**Learning:** In WordPress FSE themes, components like `core/post-content` or `core/post-featured-image` that are hidden via CSS based on block or post formatting (like `display: none;`) still get fully processed and rendered on the backend server, wasting CPU cycles and padding HTML payload.
**Action:** Use the `pre_render_block` filter to check the block type and format (and check `queryId` in the parent context to ensure it's in a query loop), and short-circuit rendering by returning an empty string `''` for these hidden blocks.

## 2024-07-27 - [WordPress REST API Payload Optimization]
**Learning:** When using `_fields` to limit the JSON payload size in the WordPress REST API, if you are also using `_embed` (e.g., `_embed=wp:featuredmedia,wp:term`), you MUST include `_links` and `_embedded` in the `_fields` list. If you don't include them, the API will successfully filter the fields but the embedding engine will silently fail, leaving you without the related resources you requested.
**Action:** Always include `_links,_embedded` in the `_fields` parameter list whenever using `_embed` simultaneously in WordPress REST API requests to ensure the payload is minimized without breaking embeddings.

## 2024-07-28 - [WordPress FSE Render Block Filter]
**Learning:** In WordPress Full Site Editing (FSE) block themes, the generic `render_block` filter hook fires for every single block on the page, resulting in hundreds of unnecessary callback invocations and wasted CPU cycles. The `render_block_{$block_name}` filter should be used instead to target specific blocks.
**Action:** Use `render_block_{$block_name}` (e.g. `render_block_core/post-template`) instead of `render_block` when modifying specific block output to optimize performance and prevent excessive filter runs.

## 2024-07-01 - Optimize WordPress Bulk Delete Memory Usage
**Learning:** Hydrating full post objects with `get_posts()` when only the ID is needed for bulk operations (like `wp_delete_post()`) creates massive memory overhead and N+1 query inefficiencies.
**Action:** Always use `'fields' => 'ids'` in `get_posts()` or `WP_Query` when only post IDs are required for subsequent hook-based processing.

## 2024-07-01 - Prevent repetitive function calls inside loops
**Learning:** Calling `get_stylesheet()` inside a `foreach` loop results in the same function being called redundantly for each iteration.
**Action:** Extract the function call to a variable before the loop and use the variable inside the loop to avoid unnecessary re-evaluations.

## 2024-07-01 - Avoid regex compilation in loops
**Learning:** Even though PHP PCRE caches regex, running `preg_match` in loops (especially over user input like `$_GET`) is slower than simple string functions.
**Action:** When evaluating strings for prefixes or suffixes inside loops, default to `str_starts_with` and `str_ends_with` first as a fast-path check to avoid or limit regex execution.
## 2024-05-18 - Debouncing MutationObservers
**Learning:** Using `MutationObserver` with `subtree: true` triggers frequently during large DOM updates (like infinite scrolling appending new cards). Running expensive operations like `querySelectorAll` synchronously on every mutation batch blocks the main thread and causes layout thrashing.
**Action:** Always wrap expensive DOM operations inside `MutationObserver` callbacks with a debounce timer (e.g., `setTimeout` for 100-150ms) to batch updates, especially when watching the entire `document.body` for child list changes.

## 2024-07-29 - [WordPress N+1 Queries in Render Callbacks]
**Learning:** Using `get_comments()` with `count => true` inside repeated hooks like `render_block` filters or REST API field callbacks causes severe N+1 query performance issues because WordPress doesn't cache comment counts per post by default for custom interaction types.
**Action:** Wrap expensive iterative DB queries like `get_comments` inside a helper function using `wp_cache_get` and `wp_cache_set` (saving to a specific cache group), and always invalidate it using a corresponding hook like `clean_post_cache`.
