# Tacobout Social — WordPress Theme

A personal magazine theme for **schwegler** at [tacobout.online](https://tacobout.online). A format-aware tumblog compatible with optional Bluesky/ATProto and ActivityPub plugins.

## Features

- **Full Site Editing (FSE)** — Customize headers, footers, and templates in the Site Editor
- **Post Format-Aware Feed** — Video posts show embeds, audio shows players, statuses show inline text, standard posts show excerpts with featured images. Fully automatic.
- **Magazine Grid** — Responsive three-column desktop grid, two columns on tablets, and one on phones, with a two-column lead card on the first home page and side-by-side hero media/text on desktop
- **Dark Mode** — Automatic via `prefers-color-scheme`, no toggle needed
- **Glassmorphic Header** — Sticky, blurred header that stays visible while scrolling
- **Bluesky + Mastodon Integration** — Works with ActivityPub, Nodeinfo, and WebFinger plugins. Footer links to your Bluesky and Mastodon profiles.
- **Modern Typography** — Space Grotesk for headings, Instrument Sans for body (loaded from Google Fonts)
- **Micro-Animations** — Staggered fade-in for feed items (respects `prefers-reduced-motion`)

## Installation

1. Copy the `tacobout.online` folder to `wp-content/themes/`
2. Go to **Appearance → Themes** and activate **Tacobout Social**
3. Go to **Appearance → Editor** to customize

## Post Formats

This theme uses WordPress **Post Formats** (not categories) to control how posts appear in feeds:

| Format | How to Set | Feed Behavior |
|--------|-----------|---------------|
| **Standard** | Default | Featured image + excerpt + "Read more" |
| **Video** | Post sidebar → Format → Video | YouTube/Vimeo embed shown inline, no featured image |
| **Audio** | Post sidebar → Format → Audio | Audio player shown inline |
| **Status** | Post sidebar → Format → Status | Full text shown, no title or image (microblog style) |
| **Image** | Post sidebar → Format → Image | Featured image prominent, no excerpt |
| **Quote** | Post sidebar → Format → Quote | Full content shown with accent border |
| **Link** | Post sidebar → Format → Link | Full content shown in a card |

### How to Set the Post Format

1. Open a post in the Block Editor
2. In the **right sidebar**, click **Post** (not Block)
3. Expand the **Format** section (it may be under "Status & visibility" or listed directly)
4. Select the desired format

> **Important**: If you don't see the Format option, make sure you're editing a **Post** (not a Page). Post Formats only apply to Posts.

## Social Integration

### Trove media collection

Link a book, movie, album, comic, TV show/episode, or video game from
[Trove](https://trove.schweg.xyz/) in a blog post by pasting its public item URL
on its own line. WordPress turns it into a card with the title, cover, and item
description. In the block editor, you can also use an **Embed** block with the URL.

For your own title or a short personal note, add a **Shortcode** block:

```text
[trove url="https://trove.schweg.xyz/books/13" note="What I’ve been reading lately."]
```

The optional `title` attribute overrides the item title. Notes are plain text;
write longer reviews in the surrounding post blocks. Cards follow the blog’s
light/dark appearance and open the item on Trove in the same tab.

Only public numeric item URLs on `https://trove.schweg.xyz` are supported.
Profile URLs, collection/account pages, and URLs with query strings are not
embedded. The blog uses public Open Graph metadata; it does not need a Trove
account, API key, or access to private collection data. Successful previews are
cached for six hours. If an item is unavailable or the preview request fails,
the card remains a link and retries after five minutes. Cover images are loaded
from their public image hosts, without sending a referrer.

Deployment needs the new `inc/trove.php` file along with the updated
`functions.php` and `style.css`. Ensure the WordPress host permits outbound HTTPS
to Trove; if page metadata changes, delete the corresponding
`tacobout_trove_` transient or wait for cache expiry. Existing saved WordPress
embed caches may also need clearing when testing changes to previously embedded
URLs. No production content is created by this integration.

This theme is designed to work with:

- **[ActivityPub](https://wordpress.org/plugins/activitypub/)** — Federate posts to Mastodon and the fediverse
- **[Enable Mastodon Apps](https://wordpress.org/plugins/enable-mastodon-apps/)** — Use Mastodon apps with your blog
- **[ATmosphere](https://github.com/Automattic/wordpress-atmosphere)** — Sync posts to Bluesky/ATProto

## Customization

### Header Categories

The header ships with links to Writing, Scrapbook, Links, and Podcast categories. To change:

1. Go to **Appearance → Editor → Navigation**
2. Edit the navigation menu
3. Replace the links with your actual category URLs

### Footer Social Links

Edit the footer template part to update your Bluesky and Mastodon profile URLs.

## Requirements

- WordPress 6.4+
- PHP 8.0+


## Open Social Engagement

The badge and backward-compatible integer `interaction_count` now mean **approved,
locally recorded engagement**, not global social totals. `tacobout_get_engagement($post_id)`
and the read-only posts REST field `engagement` provide the breakdown. The number can
increase after deployment because recognized reactions excluded from WordPress's
ordinary `comment_count` are now included. The speech-bubble design and CSS selectors
remain intact; titles and accessible labels say “locally recorded interactions.”

- `comments`: approved ordinary comments with no protocol marker. This is not proof of
  native origin when an older importer omits metadata.
- `other_comments`: approved pingbacks/trackbacks and ordinary comments with unknown protocols.
- `replies`, `likes`, `reposts`, `quotes`: sums of the two protocol breakdowns.
- `activitypub` / `atproto`: approved `comment` (also legacy empty type), `like`,
  `repost`, and `quote` rows bearing the exact `protocol` metadata value.
- `total`: comments + other_comments + replies + likes + reposts + quotes.
- `calculated_at`: UTC time the local aggregate was computed, not a remote synchronization time.
  `updated_at` and `last_synced_at` are null because the theme cannot prove a complete sync.
- `is_partial` is always true. `is_stale` refers only to failed local reads, not remote
  freshness. Zero means no matching approved local records, not no remote engagement.
  `source=local_query_failed` marks a database read failure, with a prior snapshot if available.

ActivityPub source: locally received WordPress comments/reactions tagged `protocol=activitypub`.
Current upstream stores Create replies as comments and Like/Announce as `like`/`repost`;
settings, moderation, federation delivery, and plugin version determine what actually arrives.
No remote server scraping occurs. Mentions without a persisted matching record are not counted.

ATProto source: locally imported WordPress rows tagged `protocol=atproto`. Current ATmosphere
upstream imports replies/likes/reposts and exposes `atmosphere_reaction_synced`. Standalone
Bluesky quote ingestion is unverified; `coverage.atproto_quotes=no_verified_import_lane`
marks this limitation. A quote record is counted if a compatible importer actually stores
one with the verified schema. The theme does not infer engagement from a footer profile link.

Remote records deduplicate by **post + protocol + normalized comment type + source_id**,
case-sensitively. Without a source ID, each local row counts once. Duplicate metadata uses
its earliest row. Distinct protocols remain distinct; cross-protocol bridges and missing
identities cannot be deduplicated reliably. Native duplicate comments remain distinct.
There is no AppView count added to imported replies, so no remote/local reply overlap.
Unknown reaction types/protocols and private notes are excluded; spam, pending, trashed,
and deleted comments are excluded.

Rendering performs zero remote social API calls. Ingestion remains with the plugins:
ATmosphere upstream schedules hourly reaction/reply sync and daily reply backfill;
ActivityPub handles incoming delivery. Disabling a plugin retains approved historical
records but stops that plugin's ingestion. No new theme scheduler, table, credentials,
remote cache, or competing polling process was introduced.

The local aggregate cache is `tacobout_engagement/post_{ID}`, with a 300-second TTL
and explicit age check. WordPress's `comment` cache generation catches inserts, edits,
status changes, deletes and moves. Targeted hooks also clear it after comment count/post
cache updates, deletion, protocol/source metadata changes, and Atmosphere imports.
Raw SQL writers should use WordPress APIs or explicitly invalidate the affected post;
unhooked changes otherwise take up to five minutes to be read. The previous two
inconsistent cache-key systems are no longer read or written.

Guest HTML and eligible public posts REST reads use `max-age=60, s-maxage=120,
must-revalidate`. Authenticated, nonce/Authorization-bearing, edit-context, error,
revision, and Mastodon Apps REST responses are left to WordPress/plugin policy.
With functioning hooks and caches honoring origin headers/Age, new **local** data can
remain in shared HTML/REST for up to two minutes; allow three minutes conservatively
if a downstream browser cache resets Age. Unhooked local changes add five minutes
(seven minutes, or eight conservatively). Already open pages do not update themselves.
Remote delivery/indexing, plugin backlog, cron delays, and proxy policy overrides are
additional and potentially unbounded. This is a local freshness budget, not a global SLA.

After deploying, purge existing HTML and posts REST cache objects once so old day-long
stale allowances do not survive. For routine local refresh, use:

```sh
wp tacobout engagement 123
wp tacobout engagement 123 --refresh
wp cron event list --fields=hook,next_run_gmt,recurrence
wp cron event run atmosphere_sync_reactions
wp cron event run atmosphere_backfill_replies
```

The custom CLI command reports per-type counts, calculation time, candidate ActivityPub
object permalink, ATProto root mapping and scheduled sync/backfill times. `--refresh`
recalculates this post's local snapshot; it does not fetch social data or purge CDN HTML.
The two plugin cron commands apply only when the corresponding deployed plugin registers
those hooks. Check `wp plugin list`, relevant sync settings and moderation first.
Do not run the destructive integration fixture against production.

On low-traffic sites, use a real system scheduler running `wp cron event run --due-now`
every minute under the WordPress account. Set `DISABLE_WP_CRON` only after confirming
that scheduler works. Inspect plugin diagnostics/debug logging for auth failures,
rate limits, last successful run, duration and backlog; this theme does not fabricate
those metrics or log on every page request. Verify proxy headers with `curl -I` and
compare a post's CLI snapshot with `wp-json/wp/v2/posts/123?_fields=id,interaction_count,engagement`.

The full audit, source references, ranked causes, testing and production checks are in
[docs/open-social-engagement-audit.md](docs/open-social-engagement-audit.md).

## Recovered Local Layout (3.8.2)

Restores the uncommitted 3.8.1 theme from the latest uploaded Mac copy. The feed uses
three-column dynamic masonry on desktop, two columns on tablets, and one on phones.
Cards fill the shortest available column and recalculate after media loads, window
resizes, content expansion, and infinite-scroll inserts. Content-aware wide cards,
natural media proportions, expandable long posts, and the gallery viewer are restored.
Gallery/chat formats and featured-image fallback handling match the local copy.

This recovery retains Trove embeds and the normalized engagement, cache, and REST
fixes already merged on GitHub. The local 3.8.1 changes were absent from GitHub;
installing its older theme files replaced that local layout. Version 3.8.2 identifies
the combined recovery.

## Discovery sidebar and trending posts

The first homepage grid includes a top-right sidebar linking to
https://infopages.pages.dev/, the three latest written reviews in Trove's
anonymous public community activity feed, and trending blog posts. The sidebar
occupies a masonry slot, allowing cards to fill its left side and continue below
it. On narrow screens it joins the single-column feed. Pagination/infinite scroll
never inserts a second sidebar; archive/search/author grids are unchanged.

Both this sidebar and the single-post template use `[tacobout_trending]`, ranked
by **Jetpack post views over the past seven days**, rather than lifetime comment
counts. Jetpack Stats must be active, connected, and permitted to return stats
for the site's plan. Installing Jetpack alone does not guarantee data access.
Rankings cache for 30 minutes; unavailable/empty data retries after five minutes
and shows an explicitly labeled latest-posts fallback. Only published,
unprotected blog posts qualify; the single-post list excludes the current post.
Site Kit's authenticated Google reports are not queried or exposed to visitors.

Trove reviews refresh in the background every eight hours (three times a day); the last successful
snapshot persists across empty responses, cold starts, and failed requests.
Only public written reviews are included, with author/rating text and an excerpt;
no authenticated or private collection data is requested. This uses Trove's
current public HTML rather than a documented RSS/API contract, so a change to
its activity markup may require updating the parser. When unavailable the
sidebar retains the link to Trove.

Deploy `inc/sidebar.php`, `functions.php`, `style.css`,
`tacobout-infinite-scroll.js`, and `templates/single.html` together. If WordPress
has a saved Site Editor override of the single template, replace its old trending
query with a Shortcode block containing `[tacobout_trending]`. Purge page caches
after deployment and verify the Jetpack-backed heading on the live site.

The sidebar follows the recovered grid breakpoints: one column below 760px,
two from 760–1050px, and three above 1050px. It occupies the upper-right slot
at both two and three columns; posts continue alongside and beneath it. Only
at ultra-wide widths of 2000px and above does it move outside the three-column
post grid into a separate 320px right rail.

The sidebar uses a purple Playground feature, public Trove cover images with
reviewer attribution and ratings, review excerpts, and numbered trending post
previews with featured thumbnails where available. It follows the theme’s
light/dark colors, supports keyboard focus and reduced motion, and adapts its
spacing to narrow columns. Cover images use public HTTPS URLs and send no
referrer. Missing covers and unavailable stats remain usable without fabricated
images or metrics. Version 3.8.3 refreshes cached theme styles after deployment.

Single-post pages now use the complete shared discovery sidebar. A targeted
`render_block_core/column` filter refreshes only columns marked
`tacobout-sidebar-sticky` on single posts, including saved Site Editor template
overrides. It preserves the column wrapper and replaces the old sidebar contents
with public reviews, seven-day Jetpack trending (or its labeled fallback), and
the compact Playground link. No saved template records are modified. Purge the
single-post HTML cache after deployment to remove old rendered sidebars.

## Trove background review refresh

Sidebar rendering reads the persistent `tacobout_reviews_snapshot` option and
never waits on Trove. WordPress cron refreshes it every eight hours (three times a day). A scan reads
up to five anonymous public activity pages to find the three latest written
reviews; watched/read/listened events do not qualify. Each job makes one bounded
30-second request, and a timeout or non-200 response retries once after 60 seconds
to allow a sleeping host to wake up. Empty or failed scans never erase the last
successful snapshot. Only one scan starts within the five-minute lock window.
On the first WordPress request after deployment, the theme automatically replaces
an existing 15-minute review event with the eight-hour schedule. Other cron jobs
and any in-progress review-page continuation are unaffected. New reviews can take
eight hours plus cron and page-cache delays to appear.
The first scheduled scan starts after deployment; until it completes, an existing
nonempty transient is carried forward or the link-only empty state remains.

After deployment, run the first refresh under the WordPress account:

```sh
wp cron event run tacobout_refresh_public_reviews
wp cron event run --due-now
wp option get tacobout_reviews_snapshot --format=json
```

Continuation pages run at least five seconds later; run due events again until
the snapshot is populated, or let the normal cron runner finish them. Use a real
system scheduler running `wp cron event run --due-now` every minute on sites
whose HTML is fully cached or has low traffic: WordPress cron needs execution
even when requests do not reach PHP. Purge cached homepage and single-post HTML
after the initial snapshot populates. `fetched_at` records the last successful
scan; retained reviews can be older during outages. Theme switching clears both
refresh hooks and the lock, while preserving the last good review snapshot.
