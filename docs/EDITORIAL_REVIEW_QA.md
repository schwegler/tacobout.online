# Editorial refinement: implementation evidence

Review branch for PR #332. Production WordPress settings, posts, scheduled publishing queues, accounts and DNS were not changed. The implementation follows the approved local editorial proposal, with native WordPress/plugin behavior retained rather than simulated controls.

## What changed

- Restrained lavender/charcoal surfaces, Space Grotesk headings and Instrument Sans body text; consistent reading measure, ordered feed, quieter controls and sidebar, reduced-motion rules.
- Original detailed transparent PNG Telly/Facet assets retained. The creative-team artwork appears on About; Andrew's existing detailed PNG appears only on the Schweg author profile. Guest profiles do not inherit it.
- Search and theme controls work across templates. Search, author and date queries keep native scoped pagination; category/tag progressive discovery retains its existing labeled transition to broader posts. CSS no longer reorders cards into shortest columns.
- Query contents are directly nested in the shipped templates. Runtime pattern rendering was dropping the parent query context: an empty search could show the global feed. Actual WordPress rendering now returns zero cards for an unmatched term and one Food article in the Food fixture archive. Saved FSE overrides are preserved; customized templates should be reviewed explicitly before release, never deleted automatically.
- Native ActivityPub reactions expose the plugin's actions. ATmosphere shares that reaction block, so no duplicate reaction widget is added. A separate local snapshot explains provenance and incomplete coverage. Mapped Bluesky links require the plugin's stored, validated post URI. Imported comment provenance appears with approved comments. No remote publishing, polling or synchronization is introduced.
- Shared canonical path-based schweg.xyz wordmark used in the linked creator footer, with a dark surface variant and Contact Andrew link to andrew@schweg.xyz. Publication/guest identity remains distinct.
- Bundled DOMPurify updated from 3.0.6 to official 3.4.17. Composer's three flagged development lint packages updated to patched compatible releases (plus their compatible dependency). Composer reports no known advisories after the update.

## Verification

- PHPUnit: 83 tests, 342 assertions pass on PHP 8.5.7. CI runs the repository's PHP 8.3 workflow separately.
- PHP syntax checks for inc/ and patterns/ pass. Both changed application JS files pass node --check.
- Disposable local WordPress 7.1.3 with SQLite, no external server-side HTTP and no cron, actual ActivityPub 9.3.1 and ATmosphere 2.4.1 activated. No real social credentials copied locally.
- Actual browser checks at 390px and 1440px: home, TV category, tag, matching and empty search, author, About, 404, video single, article single, and Food category. No horizontal document overflow. Machine-readable observations: browser-qa.json. An intentionally invalid second page for a four-result search returns 404. A bare home ?paged=2 is not the theme query's pagination parameter and is not claimed as a successful pagination test.
- Author Next Page link followed through the UI: /author/schwegler/page/2/ shows Gallery, Chat and the remaining standard post, preserving author scope.
- Mobile navigation opens its native dialog and moves focus into the menu; close works. Theme toggle persists on navigation. The mobile brand and controls have separate bounds after the spacing correction.
- Native ActivityPub Like dialog opened locally, displaying its profile input, copy control, close control and public post URL. No profile submitted, reaction sent or remote publish attempted. Imported comment provenance and mapped-record CTA rendered locally.
- Screenshots in docs/screenshots/editorial-* use only disposable fixture data and public artwork. No signed-in admin screenshots are included.

## Limits and release checks

This is not a complete accessibility certification or an end-to-end federation test. Real AP delivery, OAuth, AT publication/import, notification mail, Captcha, Akismet, consent/analytics, remote media-provider playback and hosting cache behavior require a controlled staging/live release check. Plugin settings were read, never saved. The local video fixture intentionally exercises an unavailable source; the audio fixture contains a local tone. Existing aggregate SQL is exercised through SQLite compatibility and unit tests; production MySQL behavior still needs staging confirmation. The local source snapshot is explicitly incomplete, never advertised as a network total.

Local visual checks cover representative widths and native interactions; no Safari, screen reader, automated axe audit or exhaustive WCAG contrast scan was run. Focus outlines, primary theme colors, reduced-motion rules and 44px controls are implemented, but every third-party embed/popover state cannot be certified from these checks. About and author follow/profile content remains owned by the installed ActivityPub plugin and enabled actor configuration.

An existing saved FSE template can override a shipped file. Before release, compare customized templates with this branch and adopt changes deliberately. Keep the current no-store Trove review endpoint behavior: never restore stale review bodies or add polling. All publishing queues and plugin automations remain owned by their existing systems.

Additional final checks: 320px homepage has no horizontal overflow and separated brand/control bounds (brand right 121px, controls left 140px). Native ActivityPub publication Follow dialog opened with both actor modes enabled locally; its copy-handle control received focus and no form was submitted. Browser error log was empty in that final homepage check. Local HTTP cache was disabled for the repeated measurements so stale pre-change markup was not mistaken for current output.
