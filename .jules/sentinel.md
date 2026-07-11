## 2024-05-30 - Fix XSS in `escHtml` function
**Vulnerability:** DOM-based escaping function used for HTML generation.
**Learning:** `div.innerHTML` escapes `<`, `>`, and `&`, but leaves quotes (`"`, `'`) unescaped, enabling attribute injection.
**Prevention:** Use explicit Regex string replacements `(str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'))` to escape content destined for HTML string interpolation.
## 2024-05-31 - Fix XSS Open Redirect in OAuth Login Flow
**Vulnerability:** The `login_redirect` filter returned `$requested_redirect_to` unvalidated when allowing custom URI schemes, exposing an XSS Open Redirect vulnerability.
**Learning:** `wp_validate_redirect()` blocks custom schemes (like `ivory://`) since it restricts redirects to the local domain. Developers sometimes bypass it entirely for OAuth flows without sanitizing the input.
**Prevention:** Always sanitize input first using `wp_sanitize_redirect()`. When validating custom schemes, manually parse the scheme with `wp_parse_url()` and explicitly block dangerous schemes (`javascript`, `vbscript`, `data`) before redirecting.
