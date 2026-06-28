## 2024-03-24 - WordPress Block Theme Empty States
**Learning:** WordPress block themes don't automatically provide fallback content when a `wp:query` loop returns no results. This can lead to a completely blank or broken-feeling page for users when searching or viewing empty categories.
**Action:** Always ensure an explicit `wp:query-no-results` block is included inside `wp:query` loops to provide a helpful empty state message.
