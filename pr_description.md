Fixes the integration with the Enable Mastodon Apps plugin when using modern native clients (like Mastodon for iOS or Ivory).

**What was changed:**
1. Added a `login_redirect` hook to prevent third-party SSO/login redirection plugins from overriding the OAuth flow's `redirect_to` parameter. This ensures users are properly redirected back to the OAuth authorization screen instead of the WP Admin dashboard.
2. Added mock REST API endpoints for `/api/v2/filters` and `/api/v2/notifications/policy` and their corresponding rewrite rules. Native apps request these v2 endpoints, and if the plugin doesn't handle them, WordPress returns a full HTML 404 page, causing the JSON parser in the app to crash. The mock endpoints return safe, empty JSON.
3. Added a version-gated call to `flush_rewrite_rules()` on `init` to ensure the new `/api/v2/...` URLs are routed correctly.
