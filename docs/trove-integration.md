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

## Discovery sidebar reviews API

The sidebar uses `GET https://trove.schweg.xyz/api/reviews?limit=3`, with no
cookies or authentication. It sends `Accept: application/json` and stores the
last valid normalized body and ETag in `tacobout_reviews_api_snapshot`.
Every widget request contacts Trove: a `304` reuses the matching saved body;
a valid `200` replaces both body and ETag, including an empty reviews array.
Timeouts, HTTP errors, and invalid JSON hide the widget. Legacy HTML snapshots
and background pagination jobs are retired on upgrade.

Cacheable page HTML contains only a hidden placeholder. `tacobout-reviews.js`
fetches the same-origin `/wp-json/tacobout/v1/reviews` endpoint with `no-store`
and no credentials. The WordPress endpoint returns `Cache-Control: no-store,
private`; its review HTML is escaped server-side. The widget clears before
entering the browser back/forward cache and revalidates on return. Reviews do
not display without JavaScript. The existing masonry ResizeObserver accounts
for the widget's changed height.

Deploy the Trove endpoint before expecting reviews to appear. Purge old page
HTML on theme deployment to remove embedded legacy snapshots; configure any
cache that overrides origin headers to bypass the reviews REST route. HTTP
revalidation cannot retract already downloaded content or purge independent
caches.

Discovery layout rules live in `sidebar.css` and are attached inline to the theme
stylesheet handle. This keeps them with the rendered markup when an edge cache
strips version query strings from stylesheet URLs.
