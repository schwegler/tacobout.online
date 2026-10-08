# Trove and Tacobout integration

The WordPress side supports Trove cards inside posts. It currently reads public
item pages, so there is no new API to coordinate or deploy.

## Current contract

- Canonical HTTPS origin: `https://trove.schweg.xyz`.
- Item routes: `/movies/:id`, `/albums/:id`, `/comics/:id`, `/tv_shows/:id`,
  `/tv_episodes/:id`, `/video_games/:id`, and `/books/:id`, with positive numeric IDs.
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
