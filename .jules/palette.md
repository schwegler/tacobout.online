## 2023-10-27 - Enhance Keyboard Focus in Card Components
**Learning:** Keyboard users often miss out on the elevation/highlight effects that mouse users see when hovering over card-like components (like blog posts or author cards). While `a:focus` highlights the specific link, the card itself doesn't react.
**Action:** Use `:focus-within` on the parent card container (e.g., `.wp-block-post`, `.tacobout-author-card`) alongside `:hover` to ensure that when a user tabs into *any* link inside the card, the entire card visually reacts as if it were hovered, providing equal context and delight for keyboard navigation.
## 2024-06-25 - Explicit query-no-results blocks required in WP FSE
**Learning:** In WordPress Full Site Editing (FSE) block themes, explicit `wp:query-no-results` blocks are required inside `wp:query` loops to handle empty states (like empty searches or categories). Without them, WordPress renders broken/blank pages when queries have no results.
**Action:** Always include a `wp:query-no-results` block with a helpful empty state message and potentially a search bar when working with query loops in FSE templates.
## 2024-06-30 - Focus Visibility Parity for Interactive Elements
**Learning:** Many interactive components (like custom FAB buttons, interaction badges, and pagination links) have robust `:hover` states but lack equivalent `:focus-visible` or `:focus-within` states. This creates a disjointed experience where keyboard users navigate past elements without receiving visual cues or delightful micro-interactions (like scaling or shadow changes).
**Action:** Always audit interactive elements with `:hover` states and add comma-separated `:focus-visible` or `:focus-within` selectors to ensure feature parity for keyboard users, adhering to the "good UX is invisible - it just works" philosophy.
## 2024-07-20 - Scroll-to-Top Keyboard Focus Management
**Learning:** Adding a scroll-to-top floating action button (FAB) improves mouse usability, but when clicked via keyboard, focus remains trapped at the bottom of the page (on the FAB). The user has visually scrolled to the top but their keyboard context hasn't moved, meaning their next Tab press will start from the end of the page.
**Action:** When implementing scroll-to-top actions via JS, always imperatively move focus back to the top of the document (e.g., to a skip-to-content link, `document.body` if appropriately tab-indexed, or the main header) to ensure keyboard users' context follows the visual scroll.
## 2024-07-25 - Surfacing Alt Text
**Learning:** Alt text is crucial for screen reader users, but it's often hidden from sighted users unless an image fails to load. This misses an opportunity to provide context, humor, or additional information that the author included in the alt text to all users.
**Action:** Expose alt text via a visual badge or toggle on images, ensuring the implementation is accessible (using `aria-label`, `<button>`, and managing `aria-hidden` on the tooltip).
## 2026-07-01 - Hide admin bar elements on mobile
**Learning:** When hiding elements added by third-party plugins in WordPress, you must hide the parent list item (e.g. `li#wp-admin-bar-plugin-name`) rather than just its child spans, otherwise the list item padding/icons will still take up horizontal space and cause overflow.
**Action:** Always target the uppermost parent container of the UI component to ensure it is completely removed from the layout.
## 2026-07-01 - Fix Mobile Admin Bar Overflow
**Learning:** WordPress admin bar (`#wpadminbar`) can easily overflow horizontally on mobile (screen widths < 782px) when multiple plugins inject custom menu items with large labels or imagery (like Jetpack stats).
**Action:** When designing a theme or troubleshooting WP UI, proactively scope `.admin-bar` media queries (`max-width: 782px`) to target `#wpadminbar` and apply `display: none !important` to non-essential textual labels (`.ab-label`, custom plugin text) or large elements to ensure the critical admin functions (menu, profile, edit) remain accessible without horizontal scrolling.
## 2026-07-02 - Accessible Custom Tooltips and Popovers
**Learning:** Custom tooltips or popovers revealed via a button click require specific ARIA attributes (`aria-expanded` and `aria-controls`) to inform screen readers of their state and relationship. Additionally, they must support keyboard dismissal (Escape key) and focus management (returning focus to the trigger button when dismissed) so keyboard users don't lose context.
**Action:** When implementing custom tooltips or popovers, ensure the trigger button uses `aria-expanded` (toggling between true/false), references the tooltip via `aria-controls` with a unique ID, and includes an Escape key listener that closes the tooltip and calls `.focus()` on the trigger.
## 2024-08-01 - Infinite Scroll End Feedback
**Learning:** Infinite scroll systems often end silently when all items are loaded, leaving users (especially those using screen readers) unsure if the feed is broken or just empty.
**Action:** Always provide explicit visual and audible (via `aria-live="polite"`) feedback when an infinite scroll feed reaches its conclusion to assure users they have seen all available content.
