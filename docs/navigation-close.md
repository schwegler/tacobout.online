# Navigation close control

The core WordPress SVG was left-aligned inside a 44px circular button because it rendered as a block inside a non-flex button. The theme now explicitly centers the 24px SVG, resets native button appearance and padding, and keeps a square 44px tap target, accessible name and focus outline.

The rendered regression also found that WordPress snapshots tab endpoints before the theme moves Search and Appearance into the dialog. The dialog now derives its visible focus targets on every Tab, preserving access to those dynamic controls in WebKit and Chromium. Native close and Escape actions retain their original focus return.

Run `python tests/check_navigation_close.py --url PREVIEW_URL --output OUTPUT_DIRECTORY` after installing Playwright and its Chromium/WebKit browsers. The optional `--webkit-executable` accepts an existing compatible Playwright launcher. Ten actual rendered checks pass at 320×844,390×844,820×1180,1024×768 and844×390 in both engines: centered square icon/button geometry,44px target,accessible name,keyboard access to Search/Appearance,focus containment,click close,Escape and opener focus return. See [results](screenshots/navigation-close/close-regression-results.json) and [WebKit phone](screenshots/navigation-close/webkit-390x844.png).

Version3.8.21 refreshes long-lived cached asset URLs. The previous live3.8.20stylesheet and header script are preserved for rollback. No PHP or saved template customization changes.
