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
