# Article header and height-aware sidebar

PR scope: two separate feature commits, then validation/CI evidence. Based on main after merged masonry PR #334. No edits to email behavior, brand assets, editorial wording/case, production content/settings or publishing queues.

## Evidence and behavior

The supplied Safari screenshots were inspected. The exact public article `/2026/10/09/free-streaming-originals-without-subscription/` was also measured read-only in an isolated browser tab. At 1675px, headline/author started at x=40, while the featured image/article column began at x=231.5. The headline was 950px wide at 58px type. At 1440×600, its 1078px sidebar remained sticky at top:160px, so it could not fit into the view.

The article header now shares the centered 1200px article/rail grid and the reading lane's 2:1 split with a 65px gutter. Headline and metadata line up with the article column. The headline uses a 48px desktop maximum, 32px at the tested tablet width and 30px on mobile, with ordinary wrapping as fallback for text-wrap:pretty. Below the native column-stack breakpoint the header uses the full reading width. Metadata-to-image spacing is 30px rather than compounded margins. The image, content, comments and sidebar remain in their existing columns.

The sidebar defaults to ordinary document flow, including without JavaScript. It sticks only when its entire measured height plus header/admin bar and 16px edge clearances fit. At narrow widths or when zoom reduces the visual viewport, it uses normal flow. ResizeObserver watches dynamic sidebar/header heights; window and visual-viewport resize and image/font loading request one coalesced animation-frame check. There is no added nested scrolling region, max-height clipping, hidden content or polling. Existing review loading/privacy and stats fallback logic remain unchanged.

## Rendered validation

Environment: isolated WordPress on loopback port 8771 with existing public artwork and synthetic fixtures, outbound server requests/cron blocked. Codex in-app browser; user/admin and other workers' tabs untouched.

- **60 article checks:** all ten formats (standard, link, video, status, audio, aside, image, quote, gallery, chat), each at 1675/900/390px, in light and dark mode. Title and metadata align within 1px of the content container in every case; zero horizontal overflow. Both short fixture titles and the long standard headline rendered. Additional 781/782/1024/720px checks confirm column-stack boundary behavior. No editorial text or case was rewritten.
- **Sidebar matrix:** 1440×600/900, 1024×600, 390×667/844 and 720×450. Real WordPress's compact 420–441px fallback sidebar fits in the tested desktop windows and becomes static on narrow screens. A synthetic review sidebar growing from 301px to 1421px switches from sticky to static; all tall-content matrix cases retain overflow:visible and max-height:none. Shrinking it restores sticky eligibility.
- **Dynamic header:** increasing a synthetic header from 90px to 180px in a 1440×500 view disables sticking when the previously fitting 301px sidebar no longer fits.
- **Keyboard:** Tab from the article link reaches the last sidebar link in the tall fixture; that link is fully visible and has a solid focus outline. No DOM reordering/tabindex change.
- **No JavaScript:** actual script-disabled WordPress load leaves the sidebar static, unclipped and visible. Script execution was re-enabled only for read-only measurement.
- **Zoom limitation:** 720×450 is the CSS reflow equivalent of a 1440×900 window at 200%. Actual browser zoom was not changed; this is explicitly a reflow simulation. The controller also uses visual viewport width/height when available.
- Browser error log was empty in the final article check. Screenshots in screenshots/single-layout use disposable fixture content, not signed-in private data.

The requested Safari runtime check remains unavailable: native desktop control previously failed before Safari UI could be read. No new permissions were enabled. Real remote Trove review fetches were blocked in the local environment, so long-widget behavior is demonstrated with the real controller and synthetic late-growing review markup; plugin publication and remote playback are not claimed tested. Screen-reader and exhaustive accessibility certification remain outside these checks.

## Automated checks

83 PHPUnit tests / 342 assertions pass. Four sidebar-fit Node regressions cover short windows, header/admin clearance, narrow/zoomed widths and growth/shrink transitions; all five masonry regressions still pass. CI now runs all Node regression files plus JS syntax checks. PHP lint and git diff checks pass. Machine-readable measurements: single-layout-measurements.json. The reproducible dynamic harness is tests/fixtures/sidebar-height.html.

No merge or deployment performed. Saved FSE overrides remain preserved; a customized single template must adopt the shared header wrapper deliberately.
