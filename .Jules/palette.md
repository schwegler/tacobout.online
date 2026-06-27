## 2023-10-27 - WP Block Theme Empty States
**Learning:** WordPress block themes require an explicit `wp:query-no-results` block inside the `wp:query` loop. Without it, queries that return no results (like empty searches or categories) will display a broken or entirely blank state to the user, creating a confusing dead-end experience.
**Action:** When designing or modifying WP block theme query loops, always verify that a `wp:query-no-results` block is present and contains helpful fallback content (like a clear message and a search bar).
