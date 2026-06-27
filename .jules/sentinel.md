## 2024-06-25 - CSS Rules Display
**Vulnerability:** None. Small UI defect.
**Learning:** Understanding CSS specificity and post-format rendering.
**Prevention:** Proper testing of CSS classes.

## 2024-06-27 - Missing Direct Access Protection
**Vulnerability:** functions.php missing `if ( ! defined( 'ABSPATH' ) ) { exit; }` check.
**Learning:** WordPress theme files can sometimes be accessed directly via their URL, potentially exposing the file path and leading to a Full Path Disclosure (FPD) vulnerability.
**Prevention:** Always include the ABSPATH check at the top of PHP files in WordPress themes and plugins to prevent direct access.
