# Masonry gap-packing regression

## Cause and fix

The deployed feed and Safari screenshot showed an ordinary row grid: a shorter card could have approximately 216px of space underneath it while the adjacent taller card determined the next row. The source's masonry function only cleared placement, ResizeObserver was disabled, and editorial CSS forced automatic rows.

The enhancement now measures card border boxes and assigns standard CSS Grid row spans to the earliest available post column, breaking ties left first. Posts are never moved in the DOM; their top edges remain chronological. The homepage lead and editorial section separators form explicit boundaries. The desktop discovery rail has its own lane; tablet and mobile retain their responsive placement. This is not experimental native CSS masonry.

Updates are coalesced into requestAnimationFrame. A ResizeObserver watches card dimensions and grid width; it ignores changes to the grid height caused by its own row placements. Child additions/removals, image load, media metadata, details changes, font loading and viewport changes request another pass. Existing placements are written only when they change. Images keep the feed's existing intrinsic-ratio preparation. Placement itself is not animated, including with reduced motion. Four-pixel row tracks limit rounding to less than 4px and avoid excessive implicit-track counts.

Mobile and JavaScript-disabled pages retain the native ordered row grid. A 3.8.18 asset version refreshes the changed CSS and JS.

## Actual rendered checks

Environment: disposable local WordPress at 127.0.0.1:8769, real theme files, public artwork and synthetic posts; no external server-side requests/cron. Codex in-app browser was available. No production/admin tab was edited. Safari's supplied screenshot was inspected; Safari runtime testing remains unverified because its native desktop-control connection had failed earlier.

| Check | Result |
|---|---|
| Live public source/layout inspection | Row alignment reproduced; public feed contained editorial CSS and support footer. |
| WordPress desktop 1440px | Zero overlaps/overflow; same-column card gutters 32.52–35.47px. |
| WordPress tablet 900px | Zero overlaps/overflow; gutters 32.64–34.81px; rail moves below feed. |
| WordPress mobile 390px | Enhancement removed; zero overlaps/overflow; native single-column 26px gutters. |
| 1440 → 900 → 390 → 1440 | Old lanes cleared; desktop packing restored with identical post IDs/order. |
| Real infinite loading | 9 → 12 posts, including gallery and chat; chronological IDs retained, zero overlaps/overflow, gutters 32.17–35.47px. |
| Actual script-disabled WordPress load | Nine author cards rendered without enhancement, no overlaps, native Next Page URL remained available. Script execution was re-enabled after loading for read-only inspection. |
| Portrait image load | Synthetic card height 147.5 → 779.5px; repacked without overlap. |
| Embed resize | Synthetic card height 279.5 → 599.5px; repacked without overlap. |
| Disclosure/text metrics/append | All repacked without overlaps; appending preserved every existing card's x/y positions; DOM post order stayed unchanged. |
| Full-width section separator | Starts beneath both previous columns; subsequent posts start below it. Larger measured gaps across this deliberate boundary are expected. |
| Keyboard/reduced motion | Tab from first card link focused second card link with solid outline; reduced-motion scenario retained packing. No DOM node relocation or tabindex changes. |

Evidence: masonry-browser-measurements.json, masonry-infinite-measurements.json, screenshots/masonry/. The screenshots use local fixtures only. The dynamic harness at tests/fixtures/masonry.html uses the actual shipping controller and exposes image, embed, expansion, text and append actions through buttons.

A single unattributed “MutationObserver parameter is not of type Node” console entry appeared during the multi-navigation/script-disabled test sequence. A clean WordPress reload with Runtime exception monitoring produced no exception and packing remained active. Its origin was not established; this is not claimed as an entirely clean console history. The unavailable video source and disabled external services are fixture limitations, not evidence of remote playback success.

## Automated validation and limits

Five Node tests cover the reported shorter-column regression, wide boundaries, stable appends, fractional-height/non-overlap properties over 150 varied cards in 1/2/3 columns, and resized-embed repacking. They do not replace rendered measurements. CI runs these alongside syntax checks and the existing PHPUnit suite (83 tests / 342 assertions).

Not verified: Safari engine execution, screen-reader traversal, arbitrary third-party embed internals that do not resize their outer frame, real font-network failure, exhaustive performance/CLS measurements, or unbounded thousands-of-post feeds. No coverage percentage or complete accessibility certification is claimed. Native fallback remains available if JavaScript is absent; real publishing, settings and content were unchanged. No merge/deployment performed.
