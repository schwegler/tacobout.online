## 2024-05-30 - Fix XSS in `escHtml` function
**Vulnerability:** DOM-based escaping function used for HTML generation.
**Learning:** `div.innerHTML` escapes `<`, `>`, and `&`, but leaves quotes (`"`, `'`) unescaped, enabling attribute injection.
**Prevention:** Use explicit Regex string replacements `(str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'))` to escape content destined for HTML string interpolation.

## 2024-07-10 - Fix Open Redirect/XSS in OAuth Flow
**Vulnerability:** Unvalidated redirect to a custom URI scheme during OAuth login redirection.
**Learning:** Returning unvalidated input directly to a redirect filter (like `login_redirect`) can allow an attacker to inject dangerous schemes (e.g. `javascript:`, `data:`, `vbscript:`) that bypass domain matching. Standard `wp_validate_redirect()` doesn't work for custom URI schemes (like `ivory://`) as it restricts redirects to the local domain.
**Prevention:** Always sanitize the redirect URI with `wp_sanitize_redirect()` first, then use `wp_parse_url()` to extract and explicitly block dangerous schemes before allowing the redirect.
