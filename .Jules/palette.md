## 2023-10-27 - Enhance Keyboard Focus in Card Components
**Learning:** Keyboard users often miss out on the elevation/highlight effects that mouse users see when hovering over card-like components (like blog posts or author cards). While `a:focus` highlights the specific link, the card itself doesn't react.
**Action:** Use `:focus-within` on the parent card container (e.g., `.wp-block-post`, `.tacobout-author-card`) alongside `:hover` to ensure that when a user tabs into *any* link inside the card, the entire card visually reacts as if it were hovered, providing equal context and delight for keyboard navigation.
## 2024-05-18 - Visual Separators in Block Themes
**Learning:** In WordPress FSE/Block themes, using standard Paragraph blocks for visual separators (like a middle dot `·` between metadata items) causes screen readers to redundantly announce "dot" or "bullet". This breaks the flow of metadata reading.
**Action:** When creating visual separators in Block Themes, use the Custom HTML block (`wp:html`) with an `aria-hidden="true"` span rather than a generic paragraph block to keep the DOM clean and accessible.
