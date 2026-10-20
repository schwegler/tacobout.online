# Author profile redesign

Implements the approved compact profile rail and chronological feed design for
ordinary WordPress users. ActivityPub remains responsible for federation identity,
follow controls, counts and the Posts & Replies / Posts filter. No users, passwords,
roles, existing post attribution, or production data are changed by this PR.

The desktop layout uses a 1000px container with a compact details rail; it stacks
below 801px. Deployment and verification notes follow with the implementation.

## Behavior and ownership

- `/author/{user_nicename}/`, its feeds, the WordPress main author query, post
  ownership, revision attribution and existing schema/federation ownership stay native.
- ActivityPub's inherited Follow Me block supplies the real actor name, avatar,
  summary, handle, post/follower counts and remote-follow/copy dialog. Extra Fields
  and Posts & Replies / Posts remain the plugin's blocks. No counts are hardcoded.
- The theme's optional Copy handle button uses the resolved actor's public handle,
  escapes attributes, reports clipboard failure accessibly, and is hidden without
  secure Clipboard API support. The native follow dialog remains available.
- The actor model's header image (including its configured per-user banner) wins
  over theme decoration. Avatar fields are left entirely to ActivityPub / WordPress.
  Banners are decorative background-like images; avatars retain the plugin's alt text
  and use containment to preserve complete supplied artwork.
- Without an enabled actor or a usable Follow Me block, a native WordPress avatar,
  escaped display name and sanitized bio remain visible. No fake follow button,
  handle or follower count is invented. Without ActivityPub, its extra fields/tabs
  are unavailable; the inherited native author query and pagination still work.
- Only the four known user nicenames receive their own approved default decoration.
  Other authors receive a neutral banner. No user is created or modified on deployment.
- Two small server-rendered theme blocks provide the banner/profile wrapper; Site
  Editor placeholders describe their queried-author behavior. There is no new author
  taxonomy, login/account system, database migration, save handler, REST endpoint,
  capability change or nonce-bearing mutation to secure.

## Approved art

Original PNG bytes, without redrawing or holiday costume defaults:

| Author nicename | Source | Theme asset |
| --- | --- | --- |
| `schwegler` | Existing approved Schweg pencil mascot | `assets/brand/schweg-mascot-420.png` |
| `facet` | https://tacobout.online/wp-content/uploads/2026/10/facet-waving.png | `assets/brand/author-facet.png` |
| `telly` | https://tacobout.online/wp-content/uploads/2026/10/telly-microphone-wave.png | `assets/brand/author-telly.png` |
| `hitch` | https://tacobout.online/wp-content/uploads/2026/10/hitch-moots-mascot-normal.png | `assets/brand/author-hitch.png` |

These are presentation defaults for existing ordinary users, not account creation.
To use these PNGs as avatars, configure the existing ActivityPub user profile avatar
field after account setup is separately authorized. Do not replace a human user's
avatar or automatically reattribute any articles as part of deployment.

## Verification

Tested locally with WordPress 6.8.3, ActivityPub 9.3.1 and PHP 8.4. The theme still
requires PHP 8.0 / WordPress 6.4; a plugin version offering Posts & Replies (8.1+)
is needed for those controls. The fixture reuses an existing disposable local user,
seeds only local test posts/media and requires loopback, disabled cron and a blocking
external-HTTP filter. It must never run on production. No external follow request,
public actor delivery, WebFinger discovery, Mastodon login or production user was
exercised. Remote server behavior and the installed production plugin version/settings
remain deployment checks.

`tests/fixtures/author-profile.html` is representative native core/plugin markup
captured from this disposable fixture (WP 6.8.3 / ActivityPub 9.3.1). It retains the
native plugin CSS under its GPL-compatible upstream license to exercise CSS load
order and specificity without installing accounts/plugins in CI. It is a static
layout regression fixture, not a simulated federation backend. Source:
https://wordpress.org/plugins/activitypub/ and the plugin's `build/follow-me` files.

```sh
./vendor/bin/phpunit
node scripts/build-assets.cjs --check
node --test tests/*.test.cjs
python tests/author-profile-browser.py
```

For the real local WordPress integration mode, first seed the guarded fixture and
provide a local three-second clip at `wp-content/uploads/author-profile-clip.webm`:

```sh
wp eval-file tests/fixtures/author-profile-local.php
PROFILE_BASE_URL=http://127.0.0.1:8080 python tests/author-profile-browser.py
```

Set `CHROMIUM_EXECUTABLE` if using a system browser and `PROFILE_SCREENSHOTS` to
retain screenshots. The browser checks 1440/1024/768/390/320px in light/dark,
chronological source order, overflow, contained avatar art, control sizing and
headings. Local integration additionally exercises the real plugin follow dialog,
Escape dismissal, native video playback, Posts reply exclusion and filtered page 2.

## After deployment (not performed by this PR)

1. Deploy the complete theme including generated assets and new PNGs together;
   purge HTML and asset caches. Existing author URLs need no rewrite flush.
2. Check Appearance → Editor → Templates → Author. A saved author-specific or
   general Author template overrides the theme file; review/back up that override
   before resetting it or applying this layout. This PR never changes saved templates.
3. Verify the existing human author first: actual avatar/banner/bio, handle,
   dynamic counts, all profile links, native follow/copy dialog, both filters,
   source order, page 2 and original article destinations. Test video/audio/embed
   controls, keyboard focus, mobile layout and light/dark/seasonal appearances.
4. Separately authorized account setup can use WordPress's normal author selector
   and ActivityPub user profile fields for each mascot. Confirm the plugin's user
   federation settings and each intended distinct actor. This PR neither enables
   federation nor creates accounts or changes roles, emails, passwords or permissions.
5. Configure avatar and optional banner through existing plugin fields. If a custom
   banner is absent, the known nicename gets its approved code-native banner artwork.
   Keep mascot bios brief and identify the editorial mascot role; claim no human
   expertise, personal history or fabricated experience. Assign only articles the
   user deliberately selects; no automatic historical reassignment is included.
