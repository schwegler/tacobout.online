## 2024-05-30 - Fix XSS in `escHtml` function
**Vulnerability:** DOM-based escaping function used for HTML generation.
**Learning:** `div.innerHTML` escapes `<`, `>`, and `&`, but leaves quotes (`"`, `'`) unescaped, enabling attribute injection.
**Prevention:** Use explicit Regex string replacements `(str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'))` to escape content destined for HTML string interpolation.
