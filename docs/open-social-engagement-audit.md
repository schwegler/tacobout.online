# Open Social Engagement Audit

Audit date: October 8, 2026. Repository: `schwegler/tacobout.online`.
Changes are local and have not been deployed. Production checks were read-only.

## 1. Executive Summary

Two repository defects are proven: the `clean_post_cache` invalidator deleted the
wrong cache key/group, and a filtered WordPress comment count was advertised as
complete social engagement. Current upstream ActivityPub and ATmosphere explicitly
exclude reactions from that count. This explains how locally persisted likes and
reposts can remain absent indefinitely, even after every cache expires.

Implemented a small local engagement layer, unified invalidation, structured REST
output, truthful labels, bounded HTTP cache policy and targeted CLI inspection.
No new social polling process or render-time social request was introduced.

Production serves the old cache policies: the homepage was a cache HIT with Age
1466 seconds; the posts REST response was cacheable with the old directives.
We cannot prove which defect delayed a particular production interaction without
the deployed plugin versions, database records, object cache and cron diagnostics.
The audit identifies concrete code defects and observed caching, not an invented
production incident diagnosis.

## 2. Existing Architecture

Reconnaissance inventoried every tracked file and traced the engagement source paths before editing. PHP: functions,
Trove helper, five patterns, test bootstrap and two test suites. JS: infinite-scroll,
header and ALT badge. Seven block templates and four parts define layout/comments.
`theme.json`, public/editor CSS and documentation were reviewed. No installed
WordPress/plugin source, production wp-config, cron configuration or credentials
were present. All requested search terms were included in a repository scan.

| Category | External → plugin → local storage → old normalization → presentation |
|---|---|
| Native comments | Submission → WP comment APIs → approved wp_comments → filtered posts.comment_count → helper/cache → badge/REST |
| AP replies | Inbox Create → Interactions::persist/wp_new_comment → type comment, protocol=activitypub, source_id → same count path |
| AP likes | Inbox Like → moderated type like, AP identity metadata → excluded by enabled reaction filter → absent from old badge |
| AP boosts | Inbox Announce → type repost, AP identity metadata → same exclusion → absent from old badge |
| AT replies | ATmosphere notifications/own records + reply backfill → type comment, protocol=atproto/source_id → old count path |
| AT likes | ATmosphere sync → type like + AT metadata → reaction exclusion → absent from old badge |
| AT reposts | ATmosphere sync → type repost + AT metadata → same exclusion → absent from old badge |
| AT quotes | No verified standalone import lane in inspected upstream; no old theme reader → unsupported |

Old count: `posts/tacobout_int_count_{ID}`, 3600 seconds. REST field `interaction_count`
and server HTML used the same helper. Infinite scroll fetched that field and built
the same badge. CSS hides AP reaction blocks in the magazine grid, making the
missing counts more consequential. Single posts use a standard comments block;
related posts are still ordered by comment_count, not normalized engagement.

Mastodon Apps compatibility contains OAuth redirect handling and two v2 stubs, not
an ingestion/count store. NodeInfo/WebFinger provide discovery, not reaction totals.
The only theme external HTTP fetch was Trove metadata (six-hour/5-minute transients),
not social engagement. Published-post count uses a separate 12-hour transient.
No theme social cron, AT URI mapping reader, AppView reader or reaction normalization
existed before this patch.

## 3. Root Causes

Ranked by confirmed evidence and practical impact. “Likely” concerns the mechanism;
production applicability must be checked against deployed versions and records.

| Rank | Candidate / evidence | Likelihood and impact | Verification / fix |
|---|---|---|---|
| 1 | Filtered comment_count excludes reactions in both current upstream plugins | Confirmed upstream; high if deployed behavior matches. Persistent undercount | Compare approved type/protocol rows with posts.comment_count. New direct local aggregate fixes this |
| 2 | Guest HTML has 3600s shared TTL + 86400s SWR; live homepage cache HIT, Age 1466 | Confirmed production policy; high visible delay | Compare origin/CLI with edge HTML/Age. New 120s shared TTL; purge old objects once |
| 3 | clean_post_cache deletes counts/tacobout_interaction_count_ID, helper writes posts/tacobout_int_count_ID | Confirmed repository bug; missed recount invalidation lasts up to an hour with persistent cache | Prime, recount/clean post cache, inspect helper. One key/group and generation guard now |
| 4 | Posts REST has 1800s shared TTL + 3600s SWR; live endpoint cacheable | Confirmed production policy; high infinite-scroll delay potential; observed request was MISS | Compare fresh CLI and REST/Age. Narrow public reads and use 120s shared TTL |
| 5 | ATmosphere hourly sync / daily reply backfill | Confirmed current upstream schedule, deployed health unknown; high ingestion delay | wp plugin list; cron event list; run known plugin hooks; inspect moderation/sync options |
| 6 | Traffic-triggered WP-Cron stalled or disabled | Unverified; potentially indefinite ingestion failure | Check DISABLE_WP_CRON and external scheduler logs. Use reliable minute scheduler |
| 7 | ATProto not imported at all | Theme had no ingestion; current upstream does import selected types. Production settings/version unknown | Inspect protocol=atproto rows and sync settings. Enable/repair deployed plugin ingestion |
| 8 | Lost mapping / changed account / multi-record syndication | Upstream mapping is post meta; production state unknown. Can lose all engagement for affected records | Inspect root URI and URI index against syndicated records; repair/backfill in plugin |
| 9 | Reaction totals only in meta / alternate schemas | Not the inspected upstream primary store; possible older/different integration. High undercount if present | Inventory deployed plugin code/meta/tables; add a verified adapter rather than guess keys |
| 10 | Imports bypass hooks, metadata arrives late, duplicate caches | Current upstream uses normal comment APIs; Atmosphere writes metadata afterward. Direct SQL unverified | Trace insert/meta hooks; check object-cache persistence. Metadata hooks + generation + age ceiling now cover local paths |

Remote indexing, moderation, missed delivery and deleted remote events may also
change completeness; expiry alone cannot recover activity never imported.

## 4. ActivityPub Findings

Inspected Automattic upstream commit
[c6cb8d14b295546459a1ce8a80614f877e1a4278](https://github.com/Automattic/wordpress-activitypub/tree/c6cb8d14b295546459a1ce8a80614f877e1a4278),
header version 9.3.1. Main includes unreleased APIs; this is not evidence of the
installed production version. Production HTML references ActivityPub reaction assets.

`includes/collection/class-interactions.php` persists through wp_new_comment or
wp_update_comment, tags protocol/source identity, and offers a current upstream
`get_counts()` aggregate cached against core's comment generation. It groups ALL
approved rows by type, so it cannot alone distinguish AP from ATProto rows.
`includes/class-comment.php` registers like/repost/quote and filters the post count
to exclude enabled reaction types. Like/Announce handlers gate on settings and
handle inbound activities; neither federation nor notification delivery is globally
complete. Replies traverse normal moderation. Undo/deletion needs plugin ingestion
before local records change.

New counting uses this verified storage shape directly and partitions by protocol,
without depending on an unreleased method. AP replies/likes/reposts and persisted
AP quotes are countable locally. Mentions are not a separate metric; an imported
reply is counted once, while remote-only mentions are absent. Reactions held by
moderation or disabled by settings are not approved local engagement. No Mastodon
HTML scraping, remote-server enumeration or render-time polling is used.

## 5. AT Protocol Findings

Inspected Automattic upstream commit
[e82000ba2cbc2e5dfb547935f7e24a0a0655b35d](https://github.com/Automattic/wordpress-atmosphere/tree/e82000ba2cbc2e5dfb547935f7e24a0a0655b35d),
header version 2.4.1. The README's Jetstrea download slug returned HTTP 404; it was
replaced with the verified ATmosphere source link, not assumed to identify production.

`Reaction_Sync` processes notifications and own-repo streams into comment/like/repost
rows, with protocol=atproto, source_id=AT URI and supporting CID/DID metadata. It
applies WordPress moderation and emits `atmosphere_reaction_synced` after writing
metadata. The root syndication URI/CID are `_atmosphere_bsky_uri` and
`_atmosphere_bsky_cid`; multi-record posts also have a URI index. These are mappings,
not count snapshots. The root contains the DID/rkey; a footer handle is insufficient.
The inspected dispatcher has no standalone quote import case. Quote content inside
a reply is not evidence of a quote-count synchronization lane.

Official ATProto lexicons expose optional `replyCount`, `likeCount`, `repostCount`,
`quoteCount` on postView. `app.bsky.feed.getPosts` supports up to 25 URIs per read;
`getPostThread` supplies thread views; `getQuotes` supplies paginated quote views.
Public reads can use `https://public.api.bsky.app/xrpc/…` without a user's password.
QuoteCount is an AppView view, not a universal guarantee across deleted/blocked/
moderated records. No API call was added because a second synchronization system
would need verified installed mappings, multi-record attribution, opt-out settings,
and reply-overlap policy. Blindly adding replyCount to imported comments doubles
representation. It would also bypass local moderation if treated as equivalent.

Current theme supports imported local AT replies/likes/reposts. Quote coverage is
explicitly unverified; zero does not mean no Bluesky quotes. Any future compatible
persisted quote row can be normalized, but this patch does not manufacture it.

Sources: [postView fields](https://github.com/bluesky-social/atproto/blob/main/lexicons/app/bsky/feed/defs.json),
[getPosts](https://github.com/bluesky-social/atproto/blob/main/lexicons/app/bsky/feed/getPosts.json),
[getQuotes](https://github.com/bluesky-social/atproto/blob/main/lexicons/app/bsky/feed/getQuotes.json).

## 6. Caching Findings

WordPress core updates approved comment counts, cleans the post cache and emits
wp_update_comment_count. Current core excludes private notes, and plugin filters
may exclude more types. Pingbacks/trackbacks and other approved types can otherwise
contribute. get_comments_number reads post.comment_count and then applies a filter;
it is not necessarily identical to the raw property and is not a social aggregate.

Old comment hooks often invalidate ordinary inserts successfully. They did not
fix the unrelated clean_post_cache key mismatch, direct SQL writers, or the count's
incorrect semantics. Default in-process object cache disappears after the request;
persistent caching makes the one-hour TTL meaningful across requests. A deletion
hook before removal can be repopulated by an intervening reader; post-deletion
invalidation plus generation now closes that path.

Let D = external delivery/indexing + plugin schedule/backlog/retry + any plugin-local
cache delay. For caches honoring origin directives and Age, conservative serialized
local allowances before the patch were:

| Path | Old calculation | New event-driven calculation | New if hooks bypassed |
|---|---|---|---|
| HTML | 3600 object + 3600 shared + 86400 SWR = 93600s (26h) | 120s (2m) shared | 300 local + 120 shared = 420s (7m) |
| REST | 3600 object + 1800 shared + 3600 SWR = 9000s (2h30m) | 120s (2m) shared | 420s (7m) |

These are configured allowance budgets, not observed delays or a guaranteed finite
maximum. Correct standard Age handling normally prevents a browser from restarting
an already aged response. If a downstream cache resets age, conservatively add
old browser 600s HTML / 300s REST (26h10m / 2h35m), or new 60s (3m / 8m).
Independent cache implementations, extra proxy TTLs and stale-on-error overrides
must be checked; wrong local semantics can remain wrong forever.

End-to-end time is D plus the relevant local allowance. Healthy hourly ATmosphere
polling adds roughly up to one hour plus cron execution/pagination/indexing latency;
daily backfill adds up to a day. Without a dependable scheduler/delivery, D is
unbounded. A remotely missing event and a stopped plugin are not fixed by local TTL.
There is no extra theme REST body cache or remote social cache in the new design.
DB-error fallback intentionally permits stale data beyond TTL, explicitly marked.
Already-open browser documents stay unchanged until navigation/reload.

Live homepage and REST headers confirm origin/edge caching relevance; they do not
prove a specific production record was stale or that all intermediaries honor the
new policy. Existing cached objects need a deployment purge. Routine invalidation
is targeted/local; no full-site cache clear is required for recalculation.

## 7. Implementation

- `inc/engagement.php`: grouped SQL aggregate, protocol/type breakdown, source-ID
  deduplication, age/generation cache checks, error fallback, common invalidation,
  verified Atmosphere import hook and private CLI diagnostics.
- `functions.php`: loads helper, removes both obsolete count-cache implementations,
  preserves integer REST field and adds engagement; narrows REST caching to public
  successful post reads. HTML policy runs after query/protocol handling and excludes
  previews, errors, feeds, search, protected posts and signed/protocol requests.
  Existing security headers remain. Minor coding-standard fixes included.
- `tacobout-infinite-scroll.js`: truthful accessible/title labels; existing field and
  selector retained; no front-end social polling added.
- `style.css`: metadata description now says optional plugin compatibility.
- `README.md`: actual semantics, source, partial coverage, cache windows, cron,
  troubleshooting and CLI commands; corrected upstream link.
- `tests/FunctionsTest.php`: legacy helper test now uses normalized cache.
- `tests/EngagementTest.php`: unit counting/cache/failure/metadata/REST policy coverage.
- `tests/engagement-integration.php`: disposable real WordPress/MariaDB lifecycle test.
- `docs/open-social-engagement-audit.md`: this audit and operational checks.

No template or CSS selector rewrite. No dependency changes, new database table,
post-column migration or secrets. SQL uses WordPress's existing indexed comment/meta
relations, a prepared numeric post ID, an allowlisted set of public comment types,
and deterministic earliest metadata rows. Per-type aggregation avoids loading
comment bodies. One local aggregate per cold post is retained rather than adding
a persistent custom snapshot table. Cache generation is global, so activity on one
post can cause recomputation elsewhere; this favors correctness over fine-grained
complexity. Large archives may benefit from future batch priming after profiling.

## 8. Engagement Semantics

All counters count approved LOCAL records. comments = unmarked normal comment rows;
other_comments = pingbacks/trackbacks or normal comments with unknown protocols.
Protocol replies = comment/legacy empty type; likes/reposts/quotes = matching types
with exact recognized protocol metadata. Top-level type totals sum AP and ATProto.
Total = comments + other_comments + replies + likes + reposts + quotes.
Pending/spam/trash/deleted/private notes/unknown reaction schemas are excluded.

A stored remote source identity deduplicates within post/protocol/normalized type;
source IDs are compared case-sensitively. Distinct native comments count separately;
missing source identity uses row ID. Cross-protocol bridges and conflicting metadata
cannot be inferred safely. Duplicate meta rows use the earliest row. The theme adds
no remote AppView totals, so a reply's local and remote representations cannot be
blindly summed. Imported local duplicates sharing the source count once.

`interaction_count` stays integer and now equals this documented local total. It
can increase versus the old filtered count. This is an explicit semantic correction,
not a silent claim of completeness. The accessible label/title describes local
coverage; the legacy speech bubble stays for design compatibility.

`calculated_at` timestamps computation only. `updated_at` and `last_synced_at` are
null because there is no verified full-network refresh clock. `is_partial=true`
and coverage strings describe incomplete federation/import coverage. `is_stale`
means a local query failed, not that remote ingestion is current. Query failure
returns an available previous snapshot or marked zero; the legacy integer alone
cannot expose the failure, so consumers should read engagement metadata.

## 9. Testing

Test runtime uses disposable containers because host PHP/composer were unavailable.
No live comments, posts or configuration were modified. Commands and results:

```sh
php vendor/bin/phpunit
php vendor/bin/phpcs --standard=WordPress-Core functions.php inc/engagement.php
wp eval-file /theme/tests/engagement-integration.php
node --check tacobout-infinite-scroll.js
```

Run all PHP syntax checks with `php -l` on each tracked PHP source. PHPUnit covers
existing theme/Trove behavior and new local normalization/cache/REST behavior.
Integration runs WordPress 7.1.3 / MariaDB 11, tests approved native/AP/AT records,
zero counts, duplicate identity/meta, moderation, private notes, insertion with late
metadata, deletion, status changes, moves, plugin hook invalidation, expiry, REST,
strict SQL mode and marked DB-outage fallback. It installs no social plugins and
throws on any attempted HTTP request, proving rendering does not depend on optional
plugins or remote response success. Fixtures simulate the verified storage contract;
they do not prove actual production inbox/polling behavior.

No new remote reader exists, so API timeout/malformed JSON/429 behavior remains the
plugin's responsibility. This suite does not test the plugins' network ingestion;
see deployment checks. Final results: **36 PHPUnit tests / 112 assertions passed**, **23 real WordPress/database
checks passed**, all **13 PHP files passed syntax checks**, all **three JavaScript files
passed node --check**, and **WordPress-Core PHPCS passed on functions.php and
inc/engagement.php with zero errors/warnings**. git diff --check also passed.

## 10. Production Checks

1. Record `wp plugin list`, WordPress/PHP versions, `wp cron event list
   --fields=hook,next_run_gmt,recurrence`, and object-cache plugin status. Identify
   the actual ATProto plugin rather than relying on the stale Jetstrea name.
2. Deploy the complete theme change including inc/engagement.php; purge old HTML
   and posts REST edge objects once. Keep a rollback copy. Do not run the destructive
   integration fixture on production.
3. Pick one mapped public post. Run `wp tacobout engagement POST_ID --refresh` and
   compare REST `_fields=id,interaction_count,engagement`, initial badge and infinite
   scroll. Confirm zero counts hide the badge and partial metadata remains present.
4. Inspect approved comment type/protocol/source_id rows for that post. Compare
   filtered comment_count with the new breakdown; verify moderation and duplicate
   identity semantics on staging. Check URI root/index mappings for older/thread posts.
5. On staging send a real AP reply, Like and Announce; inspect receipt, moderation,
   persistence and updated local count, then HTTP visibility within the local budget.
   Test Undo/delete and ensure the plugin removes or changes corresponding local rows.
6. Send AT reply/like/repost against the mapped record, run registered sync/backfill
   hooks, inspect import options and moderation. Check a standalone quote remains
   labeled unsupported rather than claiming a global zero.
7. Inspect `Cache-Control`, `Age`, `X-Cache` and Vary on homepage, post, archive and
   posts REST. Check ActivityPub negotiated responses cannot reuse cached HTML,
   and authenticated Mastodon notifications are not forced public by the theme.
8. Configure/verify minute system cron; examine missed schedules, plugin logs, last
   successful sync, pagination backlog, duration, 429/retry and auth failures. A due
   timestamp is not proof of a successful poll. Enable debug logging only as needed;
   do not publish tokens or raw private notifications.
9. Disable optional integrations on staging; local historical counts should still
   render. Block remote API access; normal theme engagement rendering must still work.
10. Profile cold/warm archives with Query Monitor. Confirm zero social remote calls,
    cache hits and acceptable aggregate-query cost at production comment volume.

## 11. Remaining Limitations

Production installed versions, raw rows, cron health and proxy override configuration
are unavailable. A single production interaction's actual delaying layer is not
proven. No guarantee that every remote event reaches local storage. AP reaction
settings, moderation, partial AT notification history, remote deletions, thread/index
mapping and account changes remain ingestion concerns.

No new AppView quote/global count synchronization, remote last-success diagnostics,
CDN purge integration or automatic update of already-open pages. CLI refresh changes
only a local post snapshot. No original content IDs means no guaranteed bridge
cross-protocol deduplication. Old imports without protocol/source markers remain
ambiguous. New recognized quote normalization is not proof of AT quote ingestion.

Tests exercise core hooks and verified schemas, not real federation signatures,
OAuth credentials, plugin polling or a Redis-backed multi-worker race. Concurrent
local aggregate misses can repeat cheap SQL; no external thundering herd exists.
A hook-free raw SQL mutation can remain cached for five minutes, plus edge/browser.
Global comment generations may reduce cache hit rate on very active sites.

## 12. Recommended Next Steps

1. Complete the production record/cron/version comparison and reliable system cron;
   this determines whether ingestion or presentation still limits freshness.
2. Prefer plugin-side observability for actual sync start/success/error, duration,
   refreshed records, backlog and rate-limit/retry state. Keep it private.
3. If global AT counts are wanted, build an opt-in site plugin/MU-plugin adapter
   against verified mappings: batch getPosts, prioritize recent/active posts, rotate
   older posts, use cross-worker locking/backoff, preserve last-good snapshots, and
   expose network freshness. Replace local AT type totals with remote snapshots
   rather than adding them; define moderation and imported-reply representation first.
   Include URI-index/multi-record and quote/reply overlap policies.
4. Add target/surrogate-key CDN purges if tighter freshness is required, keeping
   the short bounded cache policy as a fallback.
5. Profile and batch-prime local aggregates only if cold archive cost warrants it;
   consider moving synchronization ownership out of the theme before expanding it.
