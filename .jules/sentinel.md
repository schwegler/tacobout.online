## 2024-05-30 - Fix XSS in `escHtml` function
**Vulnerability:** DOM-based escaping function used for HTML generation.
**Learning:** `div.innerHTML` escapes `<`, `>`, and `&`, but leaves quotes (`"`, `'`) unescaped, enabling attribute injection.
**Prevention:** Use explicit Regex string replacements `(str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'))` to escape content destined for HTML string interpolation.

## 2024-05-30 - Fix XSS Open Redirect in custom OAuth flow
**Vulnerability:** XSS Open Redirect via `login_redirect` filter bypassing validation for custom URI schemes (like `ivory://`).
**Learning:** Returning `$requested_redirect_to` directly in a redirect filter allows `javascript:` URIs to execute if the redirect is followed by the browser. `wp_validate_redirect()` blocks custom schemes entirely, breaking the OAuth flow for native apps.
**Prevention:** When allowing custom URI schemes, always explicitly call `wp_sanitize_redirect($url)` first. Then, parse the scheme using `wp_parse_url($url, PHP_URL_SCHEME)` and explicitly block dangerous schemes (`javascript`, `vbscript`, `data`).
