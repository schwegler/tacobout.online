## 2024-06-25 - CSS Rules Display
**Vulnerability:** None. Small UI defect.
**Learning:** Understanding CSS specificity and post-format rendering.
**Prevention:** Proper testing of CSS classes.

## 2024-08-16 - Prevent Direct File Access
**Vulnerability:** Full Path Disclosure via direct access to PHP files.
**Learning:** WordPress themes require ABSPATH checks in all PHP files, including block patterns, but for block patterns it must be placed *after* the WordPress header block comment.
**Prevention:** Always add the ABSPATH check to new PHP files.
