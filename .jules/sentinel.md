## 2024-05-30 - Fix XSS in `escHtml` function
**Vulnerability:** DOM-based escaping function used for HTML generation.
**Learning:** `div.innerHTML` escapes `<`, `>`, and `&`, but leaves quotes (`"`, `'`) unescaped, enabling attribute injection.
**Prevention:** Use explicit Regex string replacements `(str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'))` to escape content destined for HTML string interpolation.

## 2025-02-27 - Open Redirect & XSS in OAuth Flows
**Vulnerability:** The OAuth login redirect hook (`login_redirect`) returned the `requested_redirect_to` parameter directly without validation, creating an Open Redirect and potential XSS vulnerability (e.g., via `javascript:` URIs).
**Learning:** WordPress's default `wp_validate_redirect()` is too strict for OAuth flows because it blocks custom schemes (like `ivory://`) and external domains, leading developers to bypass validation entirely.
**Prevention:** Use `wp_parse_url()` to explicitly block dangerous schemes (`javascript`, `vbscript`, `data`), then use `wp_sanitize_redirect()` which safely allows custom schemes and external domains needed for OAuth.
