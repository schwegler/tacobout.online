# taco ’bout it — Redesign Implementation Tracking

**Reference Authority:** `revision_2/REDESIGN_PROPOSAL.md` (Design Authority) & `revision_2/FEATURE_PRESERVATION.csv` (Capability Preservation Register).  
**Repository Scope:** `schwegler/tacobout.online` (WordPress Full-Site Editing Theme & Integration).  
**Sister Site Scope:** `schwegler/media_inventory` (Trove Rails 8 Application).  
**Baseline Verified:** PHPUnit suite passing (56 tests, 162 assertions).

---

## 1. Modular Implementation Plan

This plan organizes the `tacobout.online` redesign into self-contained, reviewable slices. Each modular slice includes code changes, tests, and a dedicated git commit.

| Phase / Module | Description | Key Deliverables & Evidence | Status |
| :--- | :--- | :--- | :--- |
| **0. Tracking & Brand Assets** | Establish redesign tokens, ligne claire mechanics, `.mascot-sticker` system, and Telly/Trove transparent PNGs | `assets/brand/*`, `style.css`, `tests/RedesignBrandTest.php`, `docs/REDESIGN_IMPLEMENTATION_TRACKING.md` | `DONE` |
| **1. Global Header & Sister Nav** | Custom speech-bubble taco app icon in header, custom vector glyph sister-brand pill, sticky header offsets | `parts/header.html`, `style.css`, `tests/HeaderNavigationTest.php` | `DONE` |
| **2. Homepage Hero & Bento Feed** | "On the Air" welcome strip featuring `telly_pose_01_on_the_mic.png`, end-of-feed notice with `telly_pose_02_taco_break.png`, footer tagline | `templates/home.html`, `patterns/on-the-air.php`, `tacobout-infinite-scroll.js`, `tests/HomepageBentoTest.php` | `DONE` |
| **3. Single Article & Fediverse Prompt** | Post footer discussion card with `telly_pose_02_taco_break.png` and Bluesky CTA, Trove review card with `facet_pose_04_five_star.png`, trending box | `templates/single.html`, `inc/sidebar.php`, `patterns/post-discussion.php`, `tests/SingleArticleAndSidebarTest.php` | `DONE` |
| **4. Channel Banners & 404 Recovery** | Category archive channel cards (`/in/media/tv/`, `/wrestling/`, `/food/`), About page Bento grid, 404 "Dead Air on This Channel" | `templates/archive.html`, `templates/404.html`, `patterns/channel-banner.php`, `patterns/error-404.php`, `tests/ChannelBannerAndError404Test.php` | `DONE` |
| **5. Quality Gates & Release Verification** | Full PHPUnit test suite, Companion Vault pattern, About template, responsive checks (320px–2000px), emoji hygiene | All 78 tests pass (323 assertions), zero regressions | `DONE` |

---

## 2. Commit Log & Progression

- **Commit 0:** `Establish brand tokens, comic card mechanics, and transparent PNG mascot assets`
  - Added official Telly, Facet, and Duo crossover mascot PNGs and logo assets to `assets/brand/`.
  - Added sister brand variables (`--taco-blue`, `--trove-violet`, `--shared-gold`, `--shadow-comic`) and `.mascot-sticker` utility to `style.css`.
  - Added PHPUnit tests in `tests/RedesignBrandTest.php`.
  - Added `docs/REDESIGN_IMPLEMENTATION_TRACKING.md`.

- **Commit 1:** `Integrate Trove sister-brand navigation pill and header pill styling`
  - Added violet companion navigation link `Media Trove & Library` to `parts/header.html`.
  - Added responsive pill layout and dark-mode styling for `.tacobout-sister-nav-pill` in `style.css`.
  - Added PHPUnit test in `tests/HeaderNavigationTest.php`.

- **Commit 2:** `Add On The Air welcome strip, Telly end-of-feed mascot card, and Trove sister footer`
  - Added `patterns/on-the-air.php` featuring `telly_pose_01_on_the_mic.png`, custom SVG lightning glyph, and broadcast welcome copy.
  - Added `tacobout-end-of-feed` mascot card in `tacobout-infinite-scroll.js` starring `telly_pose_02_taco_break.png` and Trove cross-link.
  - Updated card excerpts to `Tune in & read →` and featured card badge to `LATEST DROP`.
  - Updated `parts/footer.html` with sister broadcast link to `Trove (trove.schweg.xyz)`.
  - Upgraded custom SVG glyphs in `assets/icons/` to eliminate emojis across navigation and badges.
  - Bumped theme version to `3.8.8` in `style.css`.
  - Added PHPUnit tests in `tests/HomepageBentoTest.php`.

- **Commit 3:** `Add single post Fediverse discussion card and redesign discovery sidebar widgets`
  - Added `patterns/post-discussion.php` featuring `telly_pose_02_taco_break.png`, Bluesky CTA, and Mastodon reply link without system emojis.
  - Updated `templates/single.html` to integrate the post discussion pattern.
  - Updated `inc/sidebar.php` trending widget to `Hot on the Wire` with `telly_pose_04_breaking_hype.png` and custom SVG lightning icon.
  - Updated `inc/sidebar.php` Trove widget to `Freshly Logged in Trove` with `facet_pose_04_five_star.png`, `Explore the shelf`, and custom SVG gem icon.
  - Added styles in `sidebar.css` and `style.css`.
  - Bumped theme version to `3.8.9` in `style.css`.
  - Added PHPUnit tests in `tests/SingleArticleAndSidebarTest.php`.

- **Commit 4:** `Add archive Channel Banner Cards and Dead Air 404 recovery page`
  - Added `patterns/channel-banner.php` with custom curated banner headlines, sub-copy, Telly mascots (`telly_pose_03_side_eye.png`, `telly_pose_02_taco_break.png`), and Trove links for TV, Wrestling, Big Brother, and Food channels.
  - Updated `templates/archive.html` to use the Channel Banner pattern.
  - Added `patterns/error-404.php` featuring `telly_pose_03_side_eye.png`, "404: Dead Air on This Channel" broadcast recovery, and search form.
  - Updated `templates/404.html` to use the error-404 pattern.
  - Added styles in `style.css`.
  - Bumped theme version to `3.8.10` in `style.css`.
  - Added PHPUnit tests in `tests/ChannelBannerAndError404Test.php`.

- **Commit 5:** `Add Companion Vault callout pattern, About template, and finalize release quality gates`
  - Added `patterns/companion-vault.php` featuring `mascot_duo_telly_and_facet_transparent.png`, custom SVG prism glyph, and Trove sister shelf copy.
  - Added `templates/page-about.html` integrating the Companion Vault pattern.
  - Added `.tacobout-companion-vault-card` and mascot responsive styling in `style.css`.
  - Bumped theme version to `3.8.11` in `style.css`.
  - Added comprehensive quality gates and emoji hygiene suite in `tests/ReleaseQualityGatesTest.php`.
  - Verified 100% test pass rate (78 tests, 323 assertions).

---

## 3. How to Pick Up From Where We Left Off

For any agent continuing this work:

1. Check Section 1 table above for the next module marked `TODO` or `IN PROGRESS`.
2. Review the corresponding section in `revision_2/REDESIGN_PROPOSAL.md` and `docs/04_COMPLETE_MASTER_PLAYBOOK.md`.
3. Implement the feature and accompanying PHPUnit tests.
4. Run the suite:
   ```bash
   eval "$(/opt/homebrew/bin/brew shellenv)" && vendor/bin/phpunit
   ```
5. Commit the change with a clear commit message and update this document.
