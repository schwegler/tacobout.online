## 2024-06-25 - [WordPress Preconnect Hints]
**Learning:** In WordPress themes, preconnect hints for external resources (like Google Fonts) can be cleanly implemented using the `wp_resource_hints` filter, checking for the specific queued stylesheet using `wp_style_is( 'handle', 'queue' )` to ensure they are only added when needed.
**Action:** Use `wp_resource_hints` filter with `'crossorigin'` to add preconnect hints for critical external assets.

## 2024-07-25 - [CSS Animation Performance]
**Learning:** Full-screen background mesh animations using `background-position` and `background-size` cause continuous CPU layout repaints, which is a major performance bottleneck and drains battery.
**Action:** Create an oversized pseudo-element (e.g., `200vw`, `200vh`) and animate it using `transform: translate()` to offload the animation to the GPU (compositor thread). Add `will-change: transform` to optimize it further.

## 2024-11-20 - [WordPress Block Pre-Rendering Optimization]
**Learning:** WordPress Full Site Editing (FSE) block templates in Query Loops will render every single block on the backend (like full content, featured images, and excerpts) even if the theme hides them using CSS (`display: none`). For blocks like `core/post-content`, this means WordPress might process shortcodes, do oEmbed lookups, or construct expensive HTML for posts where the content is never seen by the user.
**Action:** Use the `pre_render_block` filter to intercept block rendering. Check if inside a Query Loop (`$block_instance->context['queryId']`) and conditionally return an empty string `''` based on post criteria (like post format) to short-circuit the backend processing of visually hidden blocks. This saves CPU time and reduces the transmitted HTML payload size significantly.
