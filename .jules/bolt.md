## 2024-06-25 - [WordPress Preconnect Hints]
**Learning:** In WordPress themes, preconnect hints for external resources (like Google Fonts) can be cleanly implemented using the `wp_resource_hints` filter, checking for the specific queued stylesheet using `wp_style_is( 'handle', 'queue' )` to ensure they are only added when needed.
**Action:** Use `wp_resource_hints` filter with `'crossorigin'` to add preconnect hints for critical external assets.
