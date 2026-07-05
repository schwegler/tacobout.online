## 2024-05-30 - Fix XSS in `escHtml` function
**Vulnerability:** DOM-based escaping function used for HTML generation.
**Learning:** `div.innerHTML` escapes `<`, `>`, and `&`, but leaves quotes (`"`, `'`) unescaped, enabling attribute injection.
**Prevention:** Use explicit Regex string replacements `(str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'))` to escape content destined for HTML string interpolation.
## 2024-05-31 - Fix Open Redirect / XSS in `login_redirect` filter
**Vulnerability:** A custom `login_redirect` filter returned `$_REQUEST['redirect_to']` blindly to support Mastodon oauth callbacks.
**Learning:** `wp_validate_redirect()` only permits local URLs or URLs in a strict allowlist. When external custom app URI schemes are required (like `ivory://`), they cannot use standard validation. Unvalidated redirects can cause Open Redirects, and more severely, XSS if the scheme is `javascript:`.
**Prevention:** When validating external custom URI callbacks, sanitize the input FIRST with `wp_sanitize_redirect()`, then manually parse the scheme with `wp_parse_url($url, PHP_URL_SCHEME)` and explicitly block dangerous schemes (`javascript`, `vbscript`, `data`).
