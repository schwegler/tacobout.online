# Seasonal appearance · floating overlay contract v2

After about one second, the small costumed PNG itself arrives and hovers above the existing page. There is no hero, explanatory panel or insertion into main content. When no safe outer gutter fits, the visible 44×44 character shares the existing header Appearance footprint with a 44px menu button. Hover tooltip: **make it weird**. Accessible character label: **Turn on Halloween mode**. The discreet X has a 44px hit area and label **Dismiss Halloween character**.

Clicking the character applies dark plum/charcoal colours, orange accents and optional animated atmosphere. A collapsed **Appearance** disclosure in the header contains Halloween on/off, Undo, motion, atmosphere, Show Halloween character and reset. The blog’s existing light/dark control remains inside this menu and retains its original saved preference. The other two sites keep their supported standard appearance. No footer settings or floating action panel remains. Dismissing the character hides the entire overlay and preserves the selected theme; Undo remains available in Appearance. Escape closes the menu and returns focus to its disclosure; clicking outside or tabbing away closes it too.

## Shared configuration and state

`seasonal/season-config.js` defines `window.SchwegSeasonalConfig`: `schemaVersion: 2`, `invitationDelayMs`, `profile: {id, character}`, and `seasons` entries with `id`, `theme`, `label`, `enabled`, required UTC date `window.from` / exclusive `window.until`, and `asset`. Optional `scheme`, `tooltip`, `notice` and `decorations` keep pack-specific copy and scenery out of the controller, so a future Christmas pack does not inherit Halloween jokes or pumpkins.

Halloween 2026 is explicitly configured for October 18 through the exclusive November 1 boundary in UTC. A single deadline timeout restores the original state and removes seasonal UI at that boundary; long future windows are chunked only at the browser’s maximum timeout limit, without polling. Add a reviewed Christmas pack with a November 1 start, its approved PNG and matching theme CSS to use the same controller next. No final Christmas artwork or speculative pack is invented here. No entry in the current date window means no seasonal UI. Date selection chooses an invitation; it does not automatically activate a theme.

- `localStorage['seasonal-theme:v1']`: theme name (currently `halloween`) or `off`. Existing saved choices remain compatible. Unknown past-season values allow the new season’s invitation; explicit `off` suppresses invitations until reset.
- `localStorage['seasonal-motion:v1']`: `paused` / `auto`; device reduced motion takes precedence.
- `localStorage['seasonal-atmosphere:v1']`: optional site extension `hidden` / `auto`; Hide atmosphere removes the scene without turning off the dark theme.
- `sessionStorage['seasonal-prompt:v1:<season-id>']`: `dismissed` for this tab and season.
- Root attributes: `data-season`, `data-season-id`, `data-season-motion`, `data-seasonal-profile`.
- Window event `schweg:seasonal-change`: `{schemaVersion:2, seasonId, theme, active, motion, source}`.

Root attributes are snapshotted with their exact missing/present values and restored on Undo/expiry; the existing non-seasonal theme preference is preserved. Storage denial leaves usable in-page choices. Storage events synchronize appearance and motion across tabs. The same contract was sent to the separate Trove task; Facet’s bat remains owned there.

## Positioning, performance and accessibility

The transparent fixed wrapper has `pointer-events:none`; only character/control buttons receive pointer events. Positioning preserves a currently safe viewport-edge location, or chooses another location clear of entire main/article/sidebar/footer regions, text blocks, headers, navigation, alerts, status/notice areas, links, buttons and form controls. If no safe gutter fits, the character moves into the header beside the compact Appearance button; the header remains visible while this invitation is eligible. The invitation stays available on articles with the older mobile hiding mitigation without editing their content. Mobile safe-area insets and the visual viewport are respected. A visible modal temporarily hides the overlay rather than covering an action. Custom obstacles can use `data-seasonal-avoid`.

One cancellable timeout schedules the invitation. Position reads are coalesced by a single animation frame only after show, scroll, resize, click/focus or dialog events; a ResizeObserver handles content-size changes. There is no polling, continuously running JavaScript animation loop, canvas or pointer-follow handler. Character and background motion use CSS transforms/opacity. Static shadows are not animated. Paused motion stays paused across reloads; reduced motion gives a static character and background. No focus is stolen on arrival. Keyboard Enter/Space activates the button, Escape dismisses when focus is in the overlay, and Undo restores the original appearance.

All artwork is decorative (`alt=""` or hidden from accessibility APIs). Print suppresses overlays and seasonal decoration. Dark mode uses the existing approved light signature PNG where necessary and restores the exact original source attributes on undo. Existing character images remain unchanged; costumes are confined to the overlay. Existing `data-theme` and its saved preference are never changed. No main-content element is inserted or resized by the invitation; no fallback slot is created.

## Placement regression checks

Run `python tests/seasonal-placement-browser.py` with Python Playwright/Chromium installed (or set `CHROMIUM_EXECUTABLE`). CI runs this separately from PHP/Node checks. The isolated fixture contains long unlinked prose: 320/390/768/1440/1920px widths, three scroll positions, both motion preferences, activation, keyboard dismissal, restoration, Undo and a 2× visual-viewport zoom. Desktop gutters remain floating; insufficient gutters retain the visible character in the header without shifting content. No browser artifacts are committed.

## Costume provenance

The costume PNGs are unchanged. Reference: `assets/brand/telly_pose_01_on_the_mic.png`. Built-in imagegen edited Andrew’s existing transparent pencil mascot into a wizard and Telly’s microphone pose into a vampire host. Moots’ paired sheet ghosts were newly generated to echo its overlapping-circle brand. Transparency was verified, transparent margins trimmed, and delivery sizes limited to 480 × 600. No mascot SVG substitute was used.

## Genuine review evidence

- [Short actual animation (GIF)](screenshots/seasonal/overlay-demo.gif) · [Original browser recording (WebM)](screenshots/seasonal/overlay-demo.webm)
- [Floating invitation desktop](screenshots/seasonal/invitation-desktop.png) · [Floating invitation mobile](screenshots/seasonal/invitation-mobile.png)
- [Dark Halloween desktop](screenshots/seasonal/halloween-desktop.png) · [Dark Halloween mobile](screenshots/seasonal/halloween-mobile.png)
- [Appearance menu desktop](screenshots/seasonal/appearance-desktop.png) · [Appearance menu mobile](screenshots/seasonal/appearance-mobile.png)
- [Browser results](screenshots/seasonal/browser-results.json)

Videos show a real browser’s arrival, activation, moving background, pause and undo; the GIF is converted from that recording, not fabricated from design mockups. Stills are 1440 × 1050 and 390 × 844; the harness also checks 320px, zero main-layout movement, actual animated movement, keyboard activation, dismissal, persistence, motion pause, reduced motion, print hiding and protected controls. The Browser plugin was unavailable; installed Playwright/Chromium was used.

Blog previews render captured current WordPress HTML with local theme assets, excluding remote WordPress scripts. Fonts/assets are warmed before recording to avoid measuring unrelated network/font layout shifts. PHP regression tests cover integration; the preview is not a complete local WordPress installation. No production theme was activated.

## Visible compact placement correction (October 21, 2026)

Live 3.8.25 uses `seasonal.20a08dff7539803d.css` and `controller.687610a29e8ea9fb.js`. PR #352 commit `cf9266b` introduced the 96px invitation slot, with a further 24px theme sibling gap in anonymous markup. The first CSS-only correction removed that space by hiding the docked fallback. That was an overcorrection: a fresh live desktop check confirmed eligible=true, data-docked=true and display=none. Zero gap alone was insufficient verification. Do not reuse the hiding-only fix as the final solution.

The reviewed replacement mounts the 44×44 original character beside a 44px Appearance menu button inside the existing header controls whenever a safe gutter is unavailable. Their combined width stays approximately the previous Appearance button width. The menu keeps its accessible Appearance name, activation/Undo and a labeled Dismiss Halloween character action. The compact image is static; device reduced motion remains respected elsewhere. A suitable wide gutter still permits the floating character. No invitation slot, new hero, costume/art edit, logo replacement or text overlay is introduced.

The controller and seasonal CSS must ship together. The old hiding rule cannot implement this behavior alone because it cannot reparent the character into the header. Remove the previously published hiding-only Additional CSS when the reviewed replacement is deployed; all necessary compact behavior is in the new stylesheet.

### Verification and evidence

The regression suite explicitly requires visible artwork and a nonzero image bounding box at 320/390/768/1440/1920, both motion preferences, scrolling and 2× zoom. The compact fallback must remain inside the header without overlapping other header controls; floating positions must clear reading regions. Initial main position must remain unchanged when the invitation arrives. Activation, keyboard dismissal, restoration and Undo remain exercised.

Public homepage and article markup previews additionally verify the 44×44 image, viewport bounds, no horizontal overflow, unchanged main flow and working activation/Undo. These are previews, not a live repair claim. See [visible homepage mobile](screenshots/seasonal-visible/visible-header-390.png), [desktop](screenshots/seasonal-visible/visible-header-1440.png), [article mobile](screenshots/seasonal-visible/visible-article-390.png) and [measurements](screenshots/seasonal-visible/visible-header-results.json).

### Current live blocker and rollback

WordPress Theme File Editor refused to save `seasonal/controller.js`: **You need to make this file writable before you can save your changes.** No file permissions, security settings, package approval evidence or deployment gates were changed. No controller correction has been published. The current production hiding CSS therefore remains an unresolved regression.

The staged editor paste initially contained a malformed duplicate and was not saved. A native Select All/Paste replacement was copied back in full, matched the intended tested source exactly, and passed `node --check`; the subsequent save reached the independent file-writability refusal above. Do not override validation or file permissions. Publish through an explicitly authorized writable theme workflow after current snapshot reconciliation.

Preserve a current installed snapshot and use content-addressed new assets and the generated manifest when deploying the reviewed correction. The old controller has a ten-year cache lifetime, so mutating its existing fingerprinted filename is insufficient for returning visitors. The automated EasyWP workflow remains disabled and its historical package-review check is unresolved; see [deployment prerequisites](easywp-deployment.md). No alternate publication route was used after the writability refusal.

Existing exact local rollback evidence is retained outside this public repository: original empty Additional CSS, the later hiding CSS, and both live controller source/fingerprint backups. To roll back only the hiding regression without deploying the replacement, restoring empty Additional CSS reverts to the earlier visible but oversized row; it does not achieve the intended compact design. Do not report that as the completed fix.

## 3.8.26 replacement candidate readiness

The hiding-only Additional CSS was rolled back to its verified empty original setting. Live desktop/mobile character visibility and activation/Undo were verified restored; the earlier 96px invitation slot returns. Preserve this live state until a separately approved replacement succeeds.

Proposed version: **3.8.26**. Compared with repository main `b96a82d` (3.8.25), runtime scope is limited to seasonal controller/CSS, regenerated controller/seasonal/style fingerprints, the generated asset manifest, and the Version header. No PHP, template, image, costume, author/avatar, editorial or unrelated runtime change is included. Runtime package paths change only for these source/generated files. Tests/docs are review evidence, not installed changes (except the existing runtime README policy).

A read-only anonymous comparison checked 18 live public files: style.css, asset-manifest.json, seasonal/mascot.png and all 15 baseline fingerprinted assets. Every byte matched the 3.8.25 repository baseline. This verifies those files only. Public HTTP cannot reveal installed PHP source, extra files, file permissions, or database-stored template/style overrides. No complete current 3.8.25 installed archive is available in this task. The available Library archive is still tacobout-live-theme-3.8.24-2026-10-20.zip, created October 20 at 21:53 UTC, the same historical archive recorded in the earlier reconciliation; it does not prove today's host state. Hosting-tab inventory timed out, so current host dashboard backup status is **unverified**, not absent or completed.

### Package review failure and required process

The sole deployment-tooling failure is test_reviewed_snapshot_content_cannot_enable_deployment. It rebuilds HEAD and requires its ZIP SHA-256 and complete changes map to equal .github/deployment/release-content-review.json. That review was recorded in commit 3a63a2a for the former 3.8.25 package (331730079a6d1fb4a8f40990729461741bebad2975ac8ac1d57464197b4bd005) against the historical 3.8.24 snapshot. Runtime edits necessarily invalidate that identity. The current candidate has not received an equivalent current snapshot/replacement review. Updating the expected hash alone would manufacture review evidence. No assertion, approval metadata, baseline, workflow or production enabled flag was changed.

Before approval: obtain and retain a completed current EasyWP host backup and exact installed-theme snapshot; reconcile every candidate replacement and any live-only file (including server-side/theme-editor changes) without removing unrelated work; record a new reviewed candidate/delta through the documented review process. PHP/Node and both browser CI jobs passed for the functional repair; Node/generated-asset checks also pass after the version bump. CI must complete for the exact final candidate; the deployment review failure remains expected until legitimate review evidence exists.

Prepare the deterministic candidate with python3 deployment/theme_release.py . OUTPUT. It creates theme.zip and release.json from the exact committed runtime files and validates asset digests. Rebuilding the same commit must produce identical ZIP bytes. This local candidate is not the gated tested-theme-COMMIT-ATTEMPT main artifact, which is only produced after merge and successful required main jobs. Do not merge or replace the installed theme merely because a candidate ZIP exists.

Once backup, reconciliation and exact-artifact review are complete, the separate production approval would identify version 3.8.26, its immutable commit/ZIP checksum, backup reference and the normal WordPress Upload Theme → Replace current theme operation, plus permission to restore that verified previous theme/backup if smoke checks fail. Stop before upload/replacement until that approval. No permissions change, SFTP setup or deployment enablement is part of this candidate preparation.
