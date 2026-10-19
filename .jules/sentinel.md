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

## 2026-07-21 - Fix DOM-based XSS in infinite scroll
**Vulnerability:** Unescaped insertion of `post.title.rendered` into DOM via `innerHTML` in `tacobout-infinite-scroll.js`.
**Learning:** WordPress REST API `title.rendered` can contain unescaped HTML characters and must be manually escaped when used with `innerHTML` in JS.
**Prevention:** Always use the `escHtml()` helper function when interpolating `title.rendered` into HTML strings.
## 2024-05-30 - Fix DOM XSS in REST API Title Rendering
**Vulnerability:** DOM-based XSS caused by injecting `post.title.rendered` into the DOM.
**Learning:** In WordPress REST API responses, `title.rendered` may contain unescaped HTML, unlike `excerpt.rendered` or `content.rendered` which are intended to contain HTML.
**Prevention:** Always explicitly escape `title.rendered` (e.g., using `escHtml()`) on the client side before DOM insertion.
## 2023-10-25 - DOMPurify Configuration for WordPress REST API HTML
**Vulnerability:** Cross-Site Scripting (XSS) via `innerHTML` assignment of post content fetched from the REST API.
**Learning:** When adding `DOMPurify` to sanitize HTML assembled in JavaScript from the WordPress REST API, the default configuration strips `iframe` elements and `target` attributes, which breaks legitimate WordPress features like embedded YouTube videos and external links.
**Prevention:** To avoid breaking legitimate WordPress content when fixing XSS vulnerabilities with `DOMPurify`, always configure the sanitizer to explicitly allow necessary tags and attributes. For example: `DOMPurify.sanitize(html, { USE_PROFILES: { html: true, svg: true }, ADD_TAGS: ['iframe'], ADD_ATTR: ['target'] })`. Also ensure that `svg` profiles are enabled if the layout contains inline SVG icons.

## 2024-10-19 - Fail Securely with DOMPurify
**Vulnerability:** Fallback to injecting raw, unescaped HTML when `DOMPurify` fails to load (e.g., due to ad blockers or network failures).
**Learning:** Checking for the existence of a security dependency and falling back to the insecure method defeats the purpose of the security dependency. If the dependency is missing, the application must fail securely (e.g., abort the operation).
**Prevention:** When a security library like `DOMPurify` is required to sanitize untrusted input, never fall back to raw assignment (like `innerHTML = rawHtml`) if the library is unavailable.
