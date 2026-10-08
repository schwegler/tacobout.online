# Tacobout Social — WordPress Theme

A personal magazine theme for **schwegler** at [tacobout.online](https://tacobout.online). Ultra-modern tumblog with deep Bluesky/ATProto and ActivityPub integration.

## Features

- **Full Site Editing (FSE)** — Customize headers, footers, and templates in the Site Editor
- **Post Format-Aware Feed** — Video posts show embeds, audio shows players, statuses show inline text, standard posts show excerpts with featured images. Fully automatic.
- **Magazine Grid** — 2-column responsive grid with the latest post spanning full width as a hero (only on page 1)
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

See [the companion integration notes](docs/trove-integration.md) for a prompt to
coordinate changes in Trove’s separate environment.

This theme is designed to work with:

- **[ActivityPub](https://wordpress.org/plugins/activitypub/)** — Federate posts to Mastodon and the fediverse
- **[Enable Mastodon Apps](https://wordpress.org/plugins/enable-mastodon-apps/)** — Use Mastodon apps with your blog
- **[Jetstrea/Atmosphere](https://wordpress.org/plugins/jetstrea/)** — Sync posts to Bluesky/ATProto

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
