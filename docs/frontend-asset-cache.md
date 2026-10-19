# Frontend asset cache invalidation

Anonymous production HTML inspected on October 19, 2026 removed the theme's
`?ver=3.8.21` parameters. Logged-in HTML retained them. Theme CSS and JavaScript
responses advertised `Cache-Control: max-age=315360000` (ten years). The regular
iPhone Chrome profile showed an older menu while its private tab showed the
current menu. This is consistent with a warm browser cache retaining mutable,
unversioned asset URLs. The affected phone's cached response was not captured,
and the layer removing queries has not been identified.

Version 3.8.23 addresses the theme's invalidation defect with actual,
content-fingerprinted CSS and JavaScript files. The `style_loader_src` and
`script_loader_src` filters replace only known parent-theme asset URLs. Handles,
dependencies, localized script data, inline structural styles, child-theme
overrides, and third-party assets retain their existing behavior. No production
cache settings or security protections are changed by this code.

## Updating a theme asset

1. Edit the original source, such as `style.css` or `seasonal/controller.js`.
2. Run `node scripts/build-assets.cjs` and commit the generated copies plus
   `asset-manifest.json` with the source change. CI checks exact byte equality.
3. Run `vendor/bin/phpunit -c phpunit.xml` and `node --test tests/*.test.cjs`.

Generated files stay in the same directory as their sources, preserving relative
image URLs and the seasonal controller's `document.currentScript` base. Unchanged
bytes retain the same filename. `style.css` stays present for WordPress theme
metadata and editor styles. Do not edit generated files or deploy only a changed
source through the Theme File Editor: deploy the complete generated release.

## Deployment review

Deployment is separate from this PR. A release must include the new manifest,
loader, and every generated file. Retain previously deployed fingerprinted files
while old HTML may still reference them; the source builder removes superseded
copies from the new checkout, not from the running server. Refresh cached HTML
after an approved deployment and verify anonymous HTML points to the new paths.
Existing browser caches can retain old HTML until it refreshes, but the new HTML
cannot reuse the old CSS/JS URL. Roll back the complete release together.

This change covers theme frontend CSS/JS. It does not diagnose or repair missing
WordPress core admin CSS. The separate healthy Mac capture loaded Jetpack's
`c0.wp.com` admin-menu stylesheet with HTTP 200 and `text/css`; the reported iPad
failure remains unobserved. Do not infer a security/plugin configuration change
from that screenshot alone.

## Validation

- PHP: 94 tests, 468 assertions; Node: 12 passing tests. Tests cover content
  changes, unchanged cache keys, stale/corrupted generated files, query stripping,
  relative PNG paths, and preserving child/plugin/core URLs.
- Chrome via Computer Use: isolated anonymous homepage HTML, generated theme
  files, and locally copied, already-loaded WordPress navigation CSS/module.
  Desktop 1188 × 913 and phone 390 × 844 render without horizontal overflow.
  Phone menu opens/closes, contains 22 links, has a 44 × 44 close control, and
  includes working Appearance controls. Halloween's decorative PNG loads with
  empty alt text; the choice survives reload. No theme errors were captured.
  Google Sign-In logged one FedCM abort after reloading the isolated local origin.
- The initial local fixture could not apply the cross-origin core navigation
  stylesheet; copying the healthy live page's loaded stylesheet resolved the
  fixture dependency. No production page or cache was modified for this check.
- Physical iPhone/iPad validation and post-deployment warm-cache verification
  remain outstanding. Browser viewport emulation is not an iOS engine test.
