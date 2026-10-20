# Post-card and byline avatars

Small avatars in shared index-card metadata, excluding author/profile pages, and
beside the existing linked single-post username. Uses native core/avatar blocks and
WordPress avatar URLs; based on merged PR355 for explicit ActivityPub icon precedence.
No accounts, post ownership, permissions or production settings are changed.

## Paths and behavior

`parts/post-meta.html` is shared by `templates/home.html`, `index.html`,
`archive.html` (including category, tag and date archives), `search.html`, and
`patterns/query.php`. It now contains one opt-in native `core/avatar` block before
existing category/date metadata. The same part remains used by `author.html`, but
its opt-in avatar renders nothing on author/profile requests. Normal profile and
comment avatars are unmarked and untouched.

`templates/single.html` groups one unlinked 24px avatar with the existing linked
`core/post-author-name`, retaining one visible username and one author link.

The scoped `render_block_core/avatar` adapter resolves `postId` from each block's
context, not the globally queried author. It uses WordPress avatar URL/data APIs
with merged PR355's explicit ActivityPub attachment precedence, and sanitizes URLs.
Gravatar remains the fallback when no valid explicit attachment exists. No new
user fields, REST endpoints, permissions, account operations or write handlers.

Card avatar links have an accessible "Posts by [display name]" label; their images
have empty alt. If an opted-in card avatar is changed to unlinked, its image gets a
meaningful author alt instead. Single byline images are decorative (empty alt and
aria-hidden) because the adjacent existing username conveys identity. Both intrinsic
and CSS dimensions stay 24×24; 48px fallback URLs support high-density screens.
Images use containment, lazy loading and async decoding. Existing avatar visibility
settings are honored; absent post/author/URL data produces no empty avatar slot.

Infinite-scroll home/category/tag cards are a separate JavaScript renderer. They now
request native embedded public author data alongside existing featured media/terms,
then use its filtered `avatar_urls[48]`, name and archive link. No additional manual
author fetch, duplicated avatar REST field, private email/role data or competing
fallback system is introduced. Existing escaping and DOMPurify remain in use. The
server's avatar visibility preference also controls these appended cards.

## Validation

- PHPUnit scope checks cover unmarked avatars, author exclusion, global visibility,
  wrong contexts, missing post context and per-post author lookup.
- `tests/post-avatars-integration.php` renders actual native blocks on a guarded,
  existing disposable local WordPress fixture and asserts image URL, dimensions,
  accessible link, profile exclusion, and an unlinked decorative single avatar.
- `tests/post-avatars-browser.py` tests actual infinite-scroll JavaScript with native
  embedded author-shaped REST data at 1440/768/390/320px in light/dark. It verifies
  one avatar in appended metadata, escaped labels, matching filtered URLs, 24px
  rendered/intrinsic sizes and no overflow. This runs in CI.
- Optional POST_AVATAR_BASE_URL mode accepts loopback only. Tested real local home,
  category, tag, search and date routes and single-post pages at 1440/390px in
  light/dark; each feed card has exactly one avatar, each byline one existing name
  and one decorative avatar. Author feeds have none of these card avatars and retain
  their native profile avatar. Screenshots inspected for placement and spacing.
- Existing PHP/Node/browser regressions remain required. No live mutations, merge
  or deployment occur in this task. Existing production backup/drift blocker remains
  separate. Production plugin/caching differences require post-deployment checks.

## Deployment after separate approval

Deploy the complete reviewed theme and matching generated assets. Preserve/review
saved Site Editor overrides: a saved `post-meta` part or Single template can override
these source-file changes. Apply the new native avatar block/byline group only after
backing up and reviewing such overrides; never reset user-authored templates blindly.
Purge HTML/assets and verify first-page and appended home/category/tag cards, search,
date and other archive results, one single-post byline, and zero added profile-feed
avatars. Check the existing custom AP icon and a user relying on Gravatar fallback.
No metadata reattribution or account setup is required.
