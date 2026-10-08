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

## Copy-paste prompt for the Trove environment

> The WordPress companion integration is implemented in the Tacobout Social
> theme. A public Trove item URL pasted into a post becomes a card showing its
> Open Graph title, description, and cover, with a direct link back to Trove.
> There is no API dependency or authenticated data synchronization.
>
> Continue the Trove UX work with the blog’s Instrument Sans / Space Grotesk
> typography, and provide a clear “Blog” link to https://tacobout.online. Preserve
> public canonical numeric item routes for movies, albums, comics, TV shows,
> TV episodes, video games, and books. Keep public og:title, og:description, and
> absolute HTTPS og:image metadata present in the server-rendered HTML. Use
> concise item context in the description and never expose private collection
> notes or user activity through this metadata. On a public item detail page,
> consider a “Copy link” action so I can easily include that item in a blog post.
>
> Validate the public book and movie pages without authentication and test the
> copy-link action. If your route or metadata changes require a companion change,
> give me a short integration note to paste into the WordPress environment.

If a future request needs collection shelves, user ratings, or review
synchronization, define that data contract separately; the current post cards
make no assumptions about those features.
