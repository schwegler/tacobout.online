# Explicit ActivityPub icon precedence

Follow-up to PR354: retain valid explicitly configured ActivityPub icon attachments
across WordPress avatar APIs and discovery; keep existing fallbacks for other users.
Review plugin hooks before adding adapters. No account or production mutations.

## Native behavior and narrow adapter

Inspected ActivityPub 9.3.1 `includes/model/class-user.php`: its actor icon chooses
an explicit `activitypub_icon` image attachment before calling `get_avatar_url()`.
Its avatar filter handles remote comments, not local user icons. WebFinger 4.1.0
`includes/class-webfinger.php` builds the optional avatar link with `get_avatar_url()`.

The theme therefore adds one final `get_avatar_data` filter (PHP_INT_MAX), covering
both ordinary `get_avatar_url()` callers and fallback plugins that short-circuit
`pre_get_avatar_data`. No actor JSON or WebFinger filter is added, and neither
plugin's actor/discovery implementation is duplicated. WordPress's standard avatar
APIs deliver the corrected URL to WebFinger automatically.

Only a positive explicit attachment ID in the user's `activitypub_icon` option,
pointing to a non-trashed image attachment with an HTTP(S) URL, overrides existing
avatar data. The URL is the original attachment URL, matching ActivityPub's icon
selection. `found_avatar` becomes true; other arguments, such as dimensions and
classes, remain unchanged. Missing/invalid metadata returns the original array.
Explicit `force_default` remains respected.

Local numeric IDs, WP_User, WP_Post authors, registered WP_Comment users and normal
registered email lookups are supported. Anonymous/remote comments never resolve by
an asserted email address; their plugin-supplied avatar remains unchanged. Gravatar
hash identifiers are not treated as registered email addresses. Resolution calls no
avatar API or actor fallback, and a try/finally guard prevents recursion through
third-party attachment/user-option filters without remaining locked after an error.
No persistent extra cache is introduced: normal WordPress metadata invalidation
continues to apply. Existing stored explicit icons are honored even if ActivityPub
is inactive. No IDs, mascot names, image URLs, credentials or roles are hardcoded.

This covers standard WordPress avatar URL/data consumers. A third-party plugin that
rewrites final avatar HTML independently of those APIs can still require its own
configuration; this PR does not disable it or change its security/settings.

## Configured banners

PR354's new author banner still used a fixed height and `object-fit: cover`, which
could crop these supplied 1500x500 compositions. Author-page configured banners now
keep a responsive 3:1 container with `object-fit: contain`. This is scoped to the
new author layout; ordinary default art and profile blocks elsewhere are unchanged.
No source image is edited or regenerated. Full native/plugin banner changes become
visible only when PR354 and this follow-up are deployed; neither is deployed here.

Read-only inspected source media: Facet 126401, Telly 126403, Hitch 126405. Each is
1500x500. Browser proofs used their actual downloaded JPEG bytes at
1440/1024/768/390px, with complete character heads/antennae and no overflow. These
are local template-fixture proofs, not production deployment screenshots or new users.

## Validation and limits

- PHPUnit covers valid/missing/malformed/deleted/non-image attachment metadata,
  unsafe/missing URLs, identity inputs, remote comments, force_default, recursion
  and guard recovery after exceptions.
- `tests/avatar-precedence-integration.php` exercises real WordPress + ActivityPub
  + WebFinger with an existing disposable local user/icon and a competing pre-data
  fallback. It checks URL/data, native WebFinger avatar links and force_default.
  It makes no content/account writes and refuses non-loopback sites.
- `tests/author-banner-browser.py` checks 3:1 dimensions and containment at four
  breakpoints in CI. Set AUTHOR_BANNER_ASSETS to a directory containing the actual
  facet/telly/hitch-banner.jpg files for the optional source-image visual checks.
- Production plugin version/settings and remote clients' cached WebFinger/avatar
  responses remain unverified. Deployment stays held pending the live theme backup
  and drift reconciliation documented for PR354. No merge/deploy or live mutations.

Upstream sources: https://wordpress.org/plugins/activitypub/ and
https://wordpress.org/plugins/webfinger/ .
