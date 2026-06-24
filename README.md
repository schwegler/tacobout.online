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
