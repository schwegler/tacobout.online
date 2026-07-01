🎨 Palette: [UX improvement] Fix mobile admin bar overflow by hiding non-essential elements

### What
Added CSS rules targeting `#wpadminbar` at the mobile breakpoint (max-width: 782px) to hide textual labels for custom plugins (Friends, HackGuardian, Clear Cache) and to completely hide the Command Palette and Jetpack Stats icons.

### Why
The WordPress admin bar was overflowing horizontally on mobile devices because there were far too many custom plugins adding text labels and large elements (like the Jetpack stats chart). This made the admin bar unusable on small screens and broke the page layout.

### Before/After
*   **Before:** Admin bar overflowed horizontally, hiding some items and breaking page layout.
*   **After:** Textual labels and non-essential items (stats, command palette) are hidden on mobile, keeping the admin bar compact and within the viewport.

### Accessibility
By ensuring the admin bar fits on mobile screens, we prevent horizontal scrolling and ensure all essential navigation items (menu toggle, site name, profile) remain reachable and visible for mobile users.
