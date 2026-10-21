# Seasonal appearance · floating overlay contract v2

After about one second, the small costumed PNG itself arrives and hovers above the existing page. There is no hero, explanatory panel or insertion into main content. When no safe outer gutter fits, the character is concealed and the existing Appearance menu keeps seasonal actions available. Hover tooltip: **make it weird**. Accessible character label: **Turn on Halloween mode**. The discreet X has a 44px hit area and label **Dismiss Halloween character**.

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

The transparent fixed wrapper has `pointer-events:none`; only character/control buttons receive pointer events. Positioning preserves a currently safe viewport-edge location, or chooses another location clear of entire main/article/sidebar/footer regions, text blocks, headers, navigation, alerts, status/notice areas, links, buttons and form controls. If no safe gutter fits, the character is concealed without reserving space or covering content. The invitation stays available on articles with the older mobile hiding mitigation without editing their content. Mobile safe-area insets and the visual viewport are respected. A visible modal temporarily hides the overlay rather than covering an action. Custom obstacles can use `data-seasonal-avoid`.

One cancellable timeout schedules the invitation. Position reads are coalesced by a single animation frame only after show, scroll, resize, click/focus or dialog events; a ResizeObserver handles content-size changes. There is no polling, continuously running JavaScript animation loop, canvas or pointer-follow handler. Character and background motion use CSS transforms/opacity. Static shadows are not animated. Paused motion stays paused across reloads; reduced motion gives a static character and background. No focus is stolen on arrival. Keyboard Enter/Space activates the button, Escape dismisses when focus is in the overlay, and Undo restores the original appearance.

All artwork is decorative (`alt=""` or hidden from accessibility APIs). Print suppresses overlays and seasonal decoration. Dark mode uses the existing approved light signature PNG where necessary and restores the exact original source attributes on undo. Existing character images remain unchanged; costumes are confined to the overlay. Existing `data-theme` and its saved preference are never changed. No main-content element is inserted or resized by the invitation; no fallback slot is created.

## Placement regression checks

Run `python tests/seasonal-placement-browser.py` with Python Playwright/Chromium installed (or set `CHROMIUM_EXECUTABLE`). CI runs this separately from PHP/Node checks. The isolated fixture contains long unlinked prose: 320/390/768/1440/1920px widths, three scroll positions, both motion preferences, activation, keyboard dismissal, restoration, Undo and a 2× visual-viewport zoom. Desktop gutters remain floating; insufficient gutters conceal the character without shifting content. No browser artifacts are committed.

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

## Compact placement correction (October 21, 2026)

Live homepage assets matched main 3.8.25 (`seasonal.20a08dff7539803d.css` and `controller.687610a29e8ea9fb.js`). PR #352 commit `cf9266b` introduced `.seasonal-invitation-slot`; its fallback adds 72px character height plus two 12px margins, a measured 96px above main. Restore the earlier body-mounted fixed overlay and concealed fallback, retaining the reading-region protection added in that PR. No artwork, costume, alt text, motion or theme choice changes. Tacobout's actual invitation is Telly's vampire PNG; Facet's bat is owned by Trove.

Before/after captured public homepage markup at 1440×1000 and 390×844 confirms main moves up 120px (96px slot plus the theme’s 24px sibling gap), no horizontal overflow, no controller errors, and working Appearance → Halloween appearance → Restore original appearance. These are isolated previews, not proof of production deployment. The long-prose fixture additionally checks 320/390/768/1440/1920, reduced motion, scrolling and zoom.

### Narrow live correction and rollback

A CSS-only equivalent for the verified 3.8.25 assets is:

```css
/* Keep the seasonal invitation out of reading flow when no gutter fits. */
.seasonal-invitation-slot { display: contents; }
.seasonal-overlay[data-docked]:not([hidden]) { display: none !important; }
```

Through an already authorized Additional CSS/theme-edit workflow, append these rules after the existing seasonal CSS. It conceals only the docked fallback and removes the slot’s layout box and sibling gap; safe fixed positions and Appearance controls remain available. First save the existing custom CSS or exact edited file and verify the active theme and live asset hashes still match the above. Clear the existing cache and check anonymous desktop/mobile home and article pages, including menu activation and Undo. Roll back by removing exactly these added rules and clearing that cache again. This patch has not been published. Do not deploy other commits or provision SFTP/security permissions for it.

For the repository correction, publish only the reviewed controller/CSS and regenerated manifest/assets through the existing verified release workflow. The automated EasyWP workflow is disabled and its setup gates remain unresolved; see [deployment prerequisites](easywp-deployment.md). A full theme replacement requires a current installed snapshot and reconciliation first.
