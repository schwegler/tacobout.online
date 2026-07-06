## 2024-05-30 - Fix XSS in `escHtml` function
**Vulnerability:** DOM-based escaping function used for HTML generation.
**Learning:** `div.innerHTML` escapes `<`, `>`, and `&`, but leaves quotes (`"`, `'`) unescaped, enabling attribute injection.
**Prevention:** Use explicit Regex string replacements `(str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'))` to escape content destined for HTML string interpolation.
## 2024-06-11 - [Open Redirect via Custom URL Schemes]
**Vulnerability:** XSS Open Redirect vulnerability due to directly returning an unsanitized `$requested_redirect_to` parameter in a `login_redirect` filter.
**Learning:** For custom URL schemes (like `ivory://` for native app OAuth flow), `wp_validate_redirect()` cannot be used as it restricts to the local domain. However, directly passing the URL is a security risk.
**Prevention:** Always sanitize first using `wp_sanitize_redirect()`, then manually parse the scheme using `wp_parse_url()` and explicitly block dangerous schemes (`javascript`, `vbscript`, `data`) before allowing the redirect.
