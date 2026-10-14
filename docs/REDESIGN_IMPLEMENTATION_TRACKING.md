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
| **1. Global Header & Sister Nav** | Custom speech-bubble taco app icon in header, violet sister-brand pill (`💎 Media Trove & Library ↗`), sticky header offsets | `parts/header.html`, `style.css`, `tests/HeaderNavigationTest.php` | `DONE` |
| **2. Homepage Hero & Bento Feed** | "On the Air" welcome strip featuring `telly_pose_01_on_the_mic.png`, end-of-feed notice with `telly_pose_02_taco_break.png`, footer tagline | `templates/index.html`, `patterns/query.php`, homepage tests | `TODO` |
| **3. Single Article & Fediverse Prompt** | Post footer discussion card with `telly_pose_02_taco_break.png` and Bluesky CTA, Trove review card with `facet_pose_04_five_star.png`, trending box | `templates/single.html`, `inc/sidebar.php`, sidebar/article tests | `TODO` |
| **4. Channel Banners & 404 Recovery** | Category archive channel cards (`/in/media/tv/`, `/wrestling/`, `/food/`), About page Bento grid, 404 "Dead Air on This Channel" | `templates/archive.html`, `templates/404.html`, archive/404 tests | `TODO` |
| **5. Quality Gates & Release Verification** | Full PHPUnit test suite, responsive checks (320px–2000px), dark/light mode contrast | All tests pass, zero regressions | `TODO` |

---

## 2. Commit Log & Progression

- **Commit 0:** `Establish brand tokens, comic card mechanics, and transparent PNG mascot assets`
  - Added official Telly, Facet, and Duo crossover mascot PNGs and logo assets to `assets/brand/`.
  - Added sister brand variables (`--taco-blue`, `--trove-violet`, `--shared-gold`, `--shadow-comic`) and `.mascot-sticker` utility to `style.css`.
  - Added PHPUnit tests in `tests/RedesignBrandTest.php`.
  - Added `docs/REDESIGN_IMPLEMENTATION_TRACKING.md`.

- **Commit 1:** `Integrate Trove sister-brand navigation pill and header pill styling`
  - Added violet companion navigation link `💎 Media Trove & Library ↗` to `parts/header.html`.
  - Added responsive pill layout and dark-mode styling for `.tacobout-sister-nav-pill` in `style.css`.
  - Added PHPUnit test in `tests/HeaderNavigationTest.php`.

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
