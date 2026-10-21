# Seasonal appearance and floating invitation

The seasonal controller uses schema v2 configuration, explicit UTC date windows and an opt-in Appearance menu. Halloween 2026 runs October 18 through the exclusive November 1 boundary. The approved Telly vampire PNG is unchanged and decorative (`alt=""`); it must not gain an ALT badge. Activation, Undo, Show character, session dismissal and reduced-motion preferences remain supported. No artwork is generated or replaced.

## Current correction

PR352 (`cf9266b`) inserted a docked invitation slot before main. On the deployed 3.8.25 theme, its 72px image plus vertical margins reserved 96px, with another 24px sibling gap. The resulting main-content shift was 120px.

The owner explicitly requested the earlier floating character over content, with its adjacent X to dismiss it. The final implementation starts from the pre-top-row controller. The overlay is a fixed body child, never a header child or layout slot. It prefers positions clear of controls; when no clear gutter exists it chooses the corner overlapping the fewest controls, rather than hiding the character. Article text/content overlap is intentional. The original image hover/arrival motion remains, subject to device reduced motion and the existing pause control. An open modal can temporarily obscure it. The original adjacent 44px X remains keyboard/touch reachable. Appearance remains a normal header menu with activation/Undo.

The earlier compact-header proposal was rejected and superseded. Do not deploy its saved ZIP or hiding CSS. The hiding-only live Additional CSS was rolled back to its empty original setting; live still uses the visible original 3.8.25 row until a revised publication is approved and verified.

## Verification

`tests/seasonal-placement-browser.py` checks widths 320, 390, 768, 1440 and 1920 with both motion preferences, scrolling, activation, reachable adjacent X, Show character, Undo and 2x viewport zoom. It requires visible artwork, fixed placement, no docked state, no invitation slot and unchanged main position. Node regression tests and generated-asset checks also pass.

Captured homepage/article markup previews compare the deployed controller/CSS with this revision using local assets. These are isolated previews, not a live deployment claim. See `screenshots/seasonal-floating/` for before/after evidence. Remote WordPress scripts are excluded from these previews.

## Publication scope and status

Candidate version remains 3.8.26. Runtime changes against repository main 3.8.25 are the seasonal controller/CSS, their generated fingerprints, the asset manifest and the style.css version/fingerprint. No PHP, templates, approved artwork, avatars, posts, database or uploads are changed.

A selective update needs seven writes, with no deletions: three new fingerprint files and four existing files (controller source, seasonal CSS source, style.css and asset-manifest.json). Keep old fingerprints for cached pages and rollback. All four overwrite files previously matched the tested 3.8.25 artifact, but must be freshly compared over the actual publication connection. The tested previous artifact has ZIP SHA256 `331730079a6d1fb4a8f40990729461741bebad2975ac8ac1d57464197b4bd005` and is retained locally. Git/artifacts restore tracked theme code, not database settings or uploads. The owner does not require fresh host backups for this change.

WordPress Theme File Editor refused a controller save because the file was non-writable; no controller save occurred and permissions were not changed. The approved temporary SFTP access was revoked before any SSH authentication or upload. A first-use host-key trust decision remains unresolved; no actual key mismatch was observed. The revised file bytes/plan require publication approval; the old compact-header seven-file approval must not be treated as approval of this replacement.

The automated EasyWP workflow remains disabled and unreconciled. Its package-review regression test pins the historical 3.8.25 ZIP and therefore fails for a new runtime package. No historical hash/review evidence, guard or production configuration was weakened or changed. A local deterministic candidate is preparation evidence, not a gated main release. See `easywp-deployment.md` for that workflow's independent review/setup requirements. No merge or deployment is performed by this preparation.
