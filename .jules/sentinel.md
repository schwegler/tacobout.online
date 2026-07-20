## 2024-05-30 - Fix XSS in `escHtml` function
**Vulnerability:** DOM-based escaping function used for HTML generation.
**Learning:** `div.innerHTML` escapes `<`, `>`, and `&`, but leaves quotes (`"`, `'`) unescaped, enabling attribute injection.
**Prevention:** Use explicit Regex string replacements `(str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'))` to escape content destined for HTML string interpolation.

## 2024-05-30 - Fix Open Redirect XSS in custom OAuth flow
**Vulnerability:** The `login_redirect` filter blindly returned `$requested_redirect_to` for a custom OAuth flow, allowing potentially malicious URI schemes like `javascript:` to execute script.
**Learning:** `wp_validate_redirect()` strictly enforces redirection to the local domain, which breaks custom OAuth flows requiring redirect to app schemes (e.g., `ivory://`). However, just returning the requested URL without checks introduces an Open Redirect XSS vulnerability.
**Prevention:** When permitting custom external redirect schemes, always sanitize with `wp_sanitize_redirect()`, manually parse the scheme with `wp_parse_url()`, and explicitly block dangerous schemes (`javascript`, `vbscript`, `data`). Cast the scheme to a string to prevent PHP 8.1 deprecation warnings if no scheme is provided.

## 2024-05-30 - Fix Open Redirect XSS in custom OAuth flow (HTTP/HTTPS)
**Vulnerability:** The previous open redirect fix for the `login_redirect` filter only blocked specific XSS schemes (`javascript`, `vbscript`, `data`). It failed to validate standard web protocols (`http`, `https`) or empty schemes, leaving the application vulnerable to classic Open Redirect attacks where an attacker could redirect the user to a malicious web page after login.
**Learning:** Bypassing `wp_validate_redirect()` entirely for custom OAuth flow schemas (like `ivory://`) accidentally bypasses safe domain checking for standard URLs.
**Prevention:** Conditionally apply `wp_validate_redirect()` for web protocols (`http`, `https`) and relative paths (empty scheme) to enforce safe domain policies, while only bypassing validation for non-standard, custom application schemas that the application explicitly needs to support, and explicitly denying malicious ones (`javascript`, `vbscript`, `data`).

## 2024-07-20 - Fix DOM-based XSS in infinite scroll titles
**Vulnerability:** `post.title.rendered` from the WP REST API was directly interpolated into HTML without escaping, leading to DOM-based XSS.
**Learning:** In WordPress REST API responses, `title.rendered` can contain unescaped HTML characters depending on backend filters. When dynamically inserting this into the DOM via JavaScript, it must be explicitly escaped on the client side. Conversely, properties like `excerpt.rendered` and `content.rendered` are intended to contain HTML and generally should not be fully escaped.
**Prevention:** Always escape `title.rendered` using a client-side escaping function (like `escHtml()`) before injecting it into the DOM via `innerHTML` or template literals.
