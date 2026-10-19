# Mobile welcome mascot and stylesheet caching

The repository's welcome image is 372 × 450 pixels. With absent or older theme
CSS, existing saved WordPress markup without dimensions can render it at its
intrinsic size. HTML dimensions in the file-based pattern protect newly rendered
patterns, but saved Site Editor content may retain older markup. Inline welcome
rules bound both the image and its nonshrinking flex wrapper and preserve room
for the copy. They cover existing markup as well as the updated pattern.

Read-only production inspection on 2026-10-15 found:

- Homepage HTML links to `/wp-content/themes/tacobout.online-main/style.css`,
  without `?ver=`, although `tacobout_enqueue_styles()` supplies the theme version.
- That exact stylesheet URL returns `Cache-Control: max-age=315360000` (ten years),
  `Expires: Thu, 31 Dec 2037 23:55:55 GMT`, and `X-Cache: HIT`.
- The homepage still contains an undimensioned welcome image and older welcome
  text. Pattern edits alone cannot be assumed to replace this saved/cached markup.

An unversioned URL with this lifetime allows browsers to reuse obsolete CSS across
releases. This is an observed invalidation defect, consistent with cache clearing
helping. It does not identify the optimizer/plugin/hosting layer removing the
version, or prove which cached response caused the reporter's failure. The inspected
CSS response itself contains the current sizing rules; no broken cached response
from the affected phone has been captured.

## Deployment verification (requires separate deployment approval)

1. Version 3.8.23 provides actual content-fingerprinted frontend CSS/JS paths;
   follow [the asset build and deployment instructions](frontend-asset-cache.md).
   A version bump alone cannot fix stripped queries. Deploy the complete release,
   retain old fingerprinted files while cached HTML may reference them, and verify
   the anonymous HTML's new asset paths.
2. Purge old HTML and stylesheet objects at the host/CDN after deployment. Ensure
   the new HTML includes the inline welcome rules, even for saved welcome blocks.
3. Replace the ten-year policy for unversioned mutable CSS with revalidation or a
   short cache lifetime. A CDN purge cannot evict already fresh browser cache entries.
   Retain inline sizing so clients holding old external CSS receive bounded images
   once their HTML refreshes.
4. Verify the generated stylesheet URL, headers, and cache hits after a second
   request, then check the homepage in iOS Safari with both warm and cold caches.

## Validation

- PHPUnit: 79 tests, 328 assertions pass; PHP syntax checks pass.
- Isolated Playwright WebKit 26.5: 20 cases at 320, 393, 640, 768, and 1440 px
  using captured production HTML with PR CSS. Current CSS, blocked theme CSS,
  simulated old CSS, and all external stylesheets blocked retain bounded welcome
  image/text. Mobile image is 53 × 64 px; larger viewports use 69 × 84 px.
- Page identity/content and page JavaScript errors checked; mobile menu opens and
  closes with current CSS. Third-party Google sign-in was blocked in this isolated
  run after its overlay intercepted the first menu attempt.
- At 320 px with missing CSS, other page content still overflows slightly. This
  change protects the welcome strip, not the entire unstyled page.
- Browser plugin unavailable; used a separate Playwright process.
- iPhone 17 Pro / iOS 27 simulator boot was attempted, but Safari openurl timed
  out and the captured simulator screen showed the Apple boot logo. Actual iOS
  and physical iPhone validation remain outstanding.

No production configuration was changed or cache purged during this investigation.
