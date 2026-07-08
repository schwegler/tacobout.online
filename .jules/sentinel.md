## 2024-05-30 - Fix XSS in `escHtml` function
**Vulnerability:** DOM-based escaping function used for HTML generation.
**Learning:** `div.innerHTML` escapes `<`, `>`, and `&`, but leaves quotes (`"`, `'`) unescaped, enabling attribute injection.
**Prevention:** Use explicit Regex string replacements `(str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'))` to escape content destined for HTML string interpolation.

## 2024-06-12 - Fix Open Redirect / XSS in OAuth login flow
**Vulnerability:** OAuth redirect returned `$requested_redirect_to` directly, causing an Open Redirect and potential XSS vulnerability (if `javascript:` scheme was provided).
**Learning:** `wp_validate_redirect()` is strictly for local domains and breaks custom application URI schemes (like `ivory://`) often used in OAuth. Returning user input directly without sanitization is dangerous.
**Prevention:** Always sanitize input first using `wp_sanitize_redirect()`. Then manually parse the scheme from the sanitized result using `wp_parse_url()`, explicitly blocking dangerous schemes (`javascript`, `vbscript`, `data`).
