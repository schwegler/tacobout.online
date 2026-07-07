## 2024-05-30 - Fix XSS in `escHtml` function
**Vulnerability:** DOM-based escaping function used for HTML generation.
**Learning:** `div.innerHTML` escapes `<`, `>`, and `&`, but leaves quotes (`"`, `'`) unescaped, enabling attribute injection.
**Prevention:** Use explicit Regex string replacements `(str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'))` to escape content destined for HTML string interpolation.

## 2024-05-30 - Fix Open Redirect / XSS in custom OAuth flows
**Vulnerability:** Returning unsanitized user-controlled redirect URL (`$requested_redirect_to`) allows Open Redirect and XSS via custom URI schemes (`javascript:`, `vbscript:`, `data:`).
**Learning:** Functions like `wp_validate_redirect` are too restrictive for OAuth flows that require custom local app URIs (e.g., `ivory://`). However, just returning the requested URL directly is dangerous.
**Prevention:** Always use `wp_sanitize_redirect()` first, then manually extract and inspect the scheme using `wp_parse_url($url, PHP_URL_SCHEME)`. Explicitly block malicious schemes (`javascript`, `vbscript`, `data`) rather than relying on WordPress's default local-only validation.
