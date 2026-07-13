## 2024-05-30 - Fix XSS in `escHtml` function
**Vulnerability:** DOM-based escaping function used for HTML generation.
**Learning:** `div.innerHTML` escapes `<`, `>`, and `&`, but leaves quotes (`"`, `'`) unescaped, enabling attribute injection.
**Prevention:** Use explicit Regex string replacements `(str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'))` to escape content destined for HTML string interpolation.

## 2024-05-30 - Fix Open Redirect XSS in custom OAuth flow
**Vulnerability:** The `login_redirect` filter blindly returned `$requested_redirect_to` for a custom OAuth flow, allowing potentially malicious URI schemes like `javascript:` to execute script.
**Learning:** `wp_validate_redirect()` strictly enforces redirection to the local domain, which breaks custom OAuth flows requiring redirect to app schemes (e.g., `ivory://`). However, just returning the requested URL without checks introduces an Open Redirect XSS vulnerability.
**Prevention:** When permitting custom external redirect schemes, always sanitize with `wp_sanitize_redirect()`, manually parse the scheme with `wp_parse_url()`, and explicitly block dangerous schemes (`javascript`, `vbscript`, `data`). Cast the scheme to a string to prevent PHP 8.1 deprecation warnings if no scheme is provided.
