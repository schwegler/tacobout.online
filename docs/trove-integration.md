# Trove and Tacobout integration

The WordPress side supports Trove cards inside posts. It currently reads public
item pages, so there is no new API to coordinate or deploy.

## Current contract

- Canonical HTTPS origin: `https://trove.schweg.xyz`.
- Item routes: `/movies/:id`, `/albums/:id`, `/comics/:id`, `/tv_shows/:id`,
  `/tv_episodes/:id`, `/video_games/:id`, and `/books/:id`, with positive numeric IDs
  and optional lowercase title slugs (for example `/comics/9-uncanny-x-men-2024`).
- Successful public pages include HTML `<meta property="og:title" content="…">`
  and, when available, `og:description` and an absolute HTTPS `og:image` URL.
- Titles may end in ` | Trove`; the blog removes that suffix from the card.
- Descriptions should contain useful item context such as creator, media type,
  and release year. They should not include private user notes or collection state.
- The blog follows no redirects, sends no authentication, limits responses to
  256 KiB, times out after three seconds, and caches successful metadata for six
  hours. Errors produce a working link with a five-minute retry delay.
- Cards link directly to the canonical item URL. The blog never fetches profiles
  or private collection endpoints and does not create or synchronize reviews.

## Discovery sidebar refresh

The sidebar reads only anonymous public activity pages. Hourly WP-Cron scans
follow pagination until three written reviews are found, the feed ends, or the
200-page safety limit is reached. Bulk library imports can bury recent reviews
beyond the previous five-page limit. Each continuation makes one bounded request,
renews the scan lock, and retries a failed page once. Failed or empty scans keep
the last successful snapshot. WP-Cron execution still depends on site traffic or
an external cron runner; an hourly schedule does not guarantee hourly execution.
Relative cover paths resolve against the canonical Trove HTTPS origin.

Discovery layout rules live in `sidebar.css` and are attached inline to the theme
stylesheet handle. This keeps them with the rendered markup when an edge cache
strips version query strings from stylesheet URLs.
