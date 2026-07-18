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
## 2026-07-13 - Explicit Feedback for Infinite Scroll End
**Learning:** When implementing infinite scroll, users (especially screen reader users) can be left wondering if they've reached the bottom of a page, if there was a network error, or if there is simply no more content. Explicit visual and audible (`aria-live="polite"`) feedback when the feed reaches its conclusion is critical to assure users that they have seen all available content.
**Action:** Always provide an end-of-feed message and ensure it explicitly reveals visually (`display: block`) and audibly (`aria-live`) when content is exhausted.
## 2024-07-14 - Redundant announcement of decorative SVGs
**Learning:** Decorative SVG icons inside buttons that already have an `aria-label` will be announced by screen readers as "unlabelled image/graphic", creating a confusing and redundant experience.
**Action:** Always add `aria-hidden="true"` to decorative SVGs within interactive elements to prevent them from being announced by screen readers.
## 2024-07-14 - Admin bar spacing and Dark Mode override for WP Core Blocks
**Learning:** WordPress core blocks like Navigation often have dynamic responsive elements (like the close icon or container padding) that don't exist in local theme files. When fixing spacing for the admin bar, unconditionally applying padding to these elements causes visual bugs for logged-out users. Likewise, SVGs generated by WP core may need explicit `currentColor` targeting in dark mode to overcome default fills.
**Action:** When adjusting UI elements to accommodate the WordPress admin bar, always scope the layout compensation (e.g., `padding-top`) to the `body.admin-bar` selector. When applying dark mode rules to core block elements (like `.wp-block-navigation__responsive-container-close svg`), ensure both `[data-theme="dark"]` and `html:not([data-theme="light"])` are used to cover the theme's manual and system-level toggles, using `currentColor` for SVG fills.
## 2024-07-28 - Focus Parity on Submit Buttons
**Learning:** While custom interactive components often get detailed `:focus-visible` states, standard form inputs like `input[type="submit"]` or `#submit` are frequently overlooked and left with only default focus rings, creating an inconsistent experience compared to their rich `:hover` styles.
**Action:** When adding `:hover` styles to form submit buttons, always include comma-separated `:focus-visible` selectors to ensure keyboard navigators receive the same visual feedback (like shadow or color changes) as mouse users.

## 2024-07-28 - Explicit Button Types in JS
**Learning:** When dynamically creating `<button>` elements via JavaScript (e.g., `document.createElement('button')`), they default to `type="submit"`. If these generated buttons are ever placed inside or near a `<form>`, pressing them or hitting enter will inadvertently submit the form instead of performing the intended script action (like toggling a tooltip or scrolling to top).
**Action:** Always explicitly set `type="button"` using `setAttribute('type', 'button')` on any dynamically generated button element to prevent accidental form submissions and ensure safe reuse across different contexts.

## 2024-07-17 - Unified Sidebar and User Profile Lists
**Learning:** WordPress Full Site Editing (FSE) heavily relies on Block markup structure and inline styles. When changing FSE markup, removing format-specific padding and background properties programmatically via CSS override is crucial for creating custom context-based layouts (like a simplified sidebar list), since post formats conditionally apply CSS rules that break uniform layout patterns in query blocks.
**Action:** When creating simplified block layouts for specific theme contexts (like widgets or profile pages), always introduce a context-specific wrapper class (e.g., `tacobout-basic-list`) and explicitly override core structural theme CSS using `!important` to strip unintended format layouts. Use `:empty` pseudo-classes to hide conditional elements.

## 2024-07-17 - Mobile Safari Viewport Height Bug
**Learning:** `100vh` on mobile Safari often fails to cover the entire view due to address bars causing unexpected clipping on fixed overlays like mobile menus.
**Action:** Always use `100dvh` (Dynamic Viewport Height) coupled with explicitly setting `top: 0` and `bottom: 0` to accurately span the whole height of the mobile device for modals and full screen overlays.
## 2026-07-17 - Simplified Single Post Meta Info
**Learning:** The default single post template had cluttered meta info (author name, date, categories separated by bullets) which could be simplified to match a cleaner, more visual design.
**Action:** Replaced text-heavy meta information with a simple visual row: author avatar (`wp:avatar`), post date, and category terms as pills, removing extraneous separator bullets for a cleaner aesthetic.
## 2024-07-28 - Exposing Semantic Context Shifts in Infinite Scroll
**Learning:** When dynamically appending content or separators (like an overflow separator between a category feed and a global feed), using `aria-hidden="true"` visually presents the transition to sighted users but completely hides the structural context shift from screen reader users, leading to confusion about why the feed content changed.
**Action:** Do not use `aria-hidden="true"` on elements that convey critical structural or contextual shifts in dynamically loaded content. Allow screen readers to read these separator messages natively to ensure all users understand the context of the new content.
