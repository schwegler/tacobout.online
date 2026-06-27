## 2024-06-25 - [WordPress Preconnect Hints]
**Learning:** In WordPress themes, preconnect hints for external resources (like Google Fonts) can be cleanly implemented using the `wp_resource_hints` filter, checking for the specific queued stylesheet using `wp_style_is( 'handle', 'queue' )` to ensure they are only added when needed.
**Action:** Use `wp_resource_hints` filter with `'crossorigin'` to add preconnect hints for critical external assets.

## 2024-07-25 - [CSS Animation Performance]
**Learning:** Full-screen background mesh animations using `background-position` and `background-size` cause continuous CPU layout repaints, which is a major performance bottleneck and drains battery.
**Action:** Create an oversized pseudo-element (e.g., `200vw`, `200vh`) and animate it using `transform: translate()` to offload the animation to the GPU (compositor thread). Add `will-change: transform` to optimize it further.

## 2024-07-26 - [Async Google Fonts Loading]
**Learning:** In WordPress themes, `wp_enqueue_style` adds blocking `<link rel="stylesheet">` tags. Blocking resources significantly slow down First Contentful Paint (FCP). To asynchronously load enqueued styles like Google Fonts, you can hook into `style_loader_tag` and inject the `media="print" onload="this.media='all'"` pattern with a `<noscript>` fallback.
**Action:** Use the `style_loader_tag` filter to modify specific external stylesheet `<link>` tags to load asynchronously.
