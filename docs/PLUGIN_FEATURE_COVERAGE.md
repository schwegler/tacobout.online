# Installed capabilities and preservation

Evidence collected read-only on 2026-10-18: signed-in plugin list (sanitized inventory in installed-plugins.json), relevant AP/AT settings controls, public REST type registry and exact installed plugin source. Presence of a plugin is not evidence every optional module is enabled. No production settings were changed.

## Public post types versus post formats

| Type | Handling |
|---|---|
| post | Mixed feed, native single, comments, tags/categories, author/date/search, plugin syndication. All ten WordPress formats remain supported: standard, aside, audio, chat, gallery, image, link, quote, status, video. Local fixtures seed each format; no conversion to standard posts. |
| page | Native page and About templates; original page body remains editable. AP/AT production settings currently exclude pages. |
| attachment | Native media/assets and core fallback. No new attachment archive or changed public exposure. |
| ap_actor, ap_post, ap_extrafield, ap_extrafield_blog | ActivityPub-owned actor, received-post and profile metadata types; retain native plugin routes/blocks. Do not add these to public article queries. |
| feedback, jetpack_form, jp_act_log_event, jp_pay_order, jp_pay_product | Jetpack-owned form/activity/payment records and definitions. Presence verified in the public type registry, not evidence of active paid commerce. No public editorial feed exposure added. |
| ema-post | Enable Mastodon Apps' app-post type; visibility depends on its configuration. Native single/fallback remains available for any public instance; do not force into post-only feeds. Live visibility not independently verified. |
| ema-announce, ema-dm-{user}, EMA mapping | Plugin source marks announcements, direct messages and mappings non-public. Preserve that boundary; no DM content was read or displayed. |
| Core internal types | Navigation, blocks, templates/parts, global styles, font libraries and other core bookkeeping remain core-owned. No automatic saved-template deletion. |

## Installed plugin matrix

| Plugin | Verified feature / implementation decision |
|---|---|
| ActivityPub 9.3.1 | Both author and blog profiles enabled; posts supported, pages/media excluded; receive likes/reblogs and automatic reaction approval enabled; hashtags and creator attribution enabled. Preserve native actors, WebFinger, JSON representation, outgoing media, quote policies and moderation. Native follow/profile/extra fields and Posts/Posts & Replies tabs retained; native reactions/actions styled, never replaced with invented network totals. |
| ATmosphere 2.4.1 | Public handle tacobout.online connected; automatic post publication on, link-card long-form mode, automatic formatting; pages/media excluded; outgoing eligible WP replies and incoming replies/likes/reposts enabled. Read stored bsky record mapping only. Preserve plugin OAuth, standard.site/document metadata and publishing pipeline. AT quote import remains unverified. |
| Enable Mastodon Apps 1.7.0 | Preserve app API, post-format support, ActivityPub integration and private types. No additional public exposure or separate timeline invented. |
| WebFinger 4.1.0 | Preserve plugin discovery routes; no hard-coded competing handler or account changes. |
| FediBoost 1.1.0 | Existing federation amplification/automation retained; no triggers or queues changed. Delivery behavior not exercised. |
| Jetpack 16.3.1 | Native comment/form/media/plugin output retained. Stats sidebar degrades truthfully to Latest Posts when view data is unavailable. Optional modules/analytics/payment configuration not assumed or modified. |
| Akismet 5.7.2 | Comment spam processing retained; no bypass or moderation change. Live submission not sent. |
| Captcha Code 3.33 | Native comment-form hook remains; no replacement form or captcha bypass. Live submission not tested. |
| WP Consent API 2.1.0 | Consent hooks retained; no assumption it is a full consent banner or that all modules are gated. |
| Site Kit by Google 1.189.0 | Analytics/consent output stays plugin-owned via wp_head/wp_footer. No credentials or analytics configuration changed. |
| Open Graph 3.0.1 | Retain metadata hooks and AP creator metadata; no second manual OpenGraph generator. |
| Revive Social 9.4.4 | Existing scheduling/publishing behavior retained; no queue reads/writes or new job. |
| Revive To Sky 1.1.4 | Existing AT social scheduling retained; no connection, cadence or queue change. |
| Advanced Cron Manager 2.7.0 | Administrative scheduling retained; no production cron changes. |
| Admin and Site Enhancements 9.2.1 | Existing editorial/admin enhancements retained; enabled submodules not exhaustively inspected. |
| Press This 2.0.2 | Capture/editor workflow remains unchanged. |
| Secure MCP Server 1.5.2 | Existing read access used where available; no permissions or authentication change. |
| WordPress Importer 0.9.6 | Administrative import remains unchanged; no production import run. |

Two must-use plugins and one drop-in appeared in the production inventory, but their identities/configuration were not collected in the sanitized snapshot. Their runtime output/hook boundaries are preserved; compatibility is a staging check, not a verified claim.

## Other theme features

Native navigation and nested menus; footer socials and RSS; light/dark persistence; keyboard focus and reduced motion; full article body/captions/source links; aside expansion; native media controls; gallery/image affordances; ALT badges; source/outbound cards; local engagement caching/invalidation; category/tag progressive loading with a labeled broader-feed separator; scoped native search/author pagination; back to top; 404 recovery/search; native comment threading/reply/form; privacy-preserving no-store Trove reviews and fallback stats are retained. Existing registered hooks and wp_head/wp_footer remain. Review screenshots are fixture evidence, not proof that remote services delivered successfully.
