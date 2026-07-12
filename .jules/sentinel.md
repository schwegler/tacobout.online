## 2024-05-30 - Fix XSS in `escHtml` function
**Vulnerability:** DOM-based escaping function used for HTML generation.
**Learning:** `div.innerHTML` escapes `<`, `>`, and `&`, but leaves quotes (`"`, `'`) unescaped, enabling attribute injection.
**Prevention:** Use explicit Regex string replacements `(str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'))` to escape content destined for HTML string interpolation.

## 2024-06-03 - Fix Open Redirect/XSS in login_redirect filter
**Vulnerability:** The `login_redirect` filter passed the unfiltered `$requested_redirect_to` URL when allowing the custom `enable-mastodon-apps-authenticate` flow. This allowed Open Redirect attacks and potentially XSS if a `javascript:` scheme was provided.
**Learning:** `wp_validate_redirect()` blocks custom schemes entirely, meaning it cannot be used for custom apps (e.g. `ivory://`). However, allowing unsanitized inputs enables attacks.
**Prevention:** Always use `wp_sanitize_redirect()` first, then manually extract the scheme with `wp_parse_url()` and explicitly block dangerous schemes (`javascript`, `vbscript`, `data`) before returning the redirect URL.
