# Installed theme reconciliation — 2026-10-20

The complete active-theme snapshot was supplied from today's completed EasyWP
files backup. The archive was materialized from Library without changing its
identity: `libfile_06811f4cb8988191b0c6b23ec7731303`,
`tacobout-live-theme-3.8.24-2026-10-20.zip`, SHA-256
`71bca237c0de13651d04cbe07a92b6bbebd1fabece28279c6f998ab936e539a7`.
The ZIP hash and every one of its 212 theme files were checked against the included
manifest. The other two ZIP entries are the manifest and scan report, outside the
theme directory. No snapshot source code was executed or copied wholesale into git.
The supplied pattern scan reported no unresolved findings; its single dummy URL
fixture finding is not evidence of a credential. Pattern scanning is not a proof
that arbitrary code can never contain secrets.

Comparison target: main `4f4872f5576665e251bebadc2ea2ce8f8a1bb896` and PR357
`45386b2bd5d5fd36b480d0d993fb1d0e22692f2e` before this reconciliation.
Main has 234 tracked files: 14 shared paths differ, eight snapshot-only paths are
older generated assets, and 30 main-only paths contain newer features/tests/docs.
All other shared files are byte-identical. PR357 adds deployment tooling and
workflow changes but had not otherwise altered runtime theme content.

## Every differing shared path

| Path | Finding and disposition |
| --- | --- |
| `style.css` | Live version 3.8.24; main 3.8.23 plus the pending horizontal feed-video rule. No other source differences. Retain the rule and advance candidate to 3.8.25. |
| `tests/ReleaseQualityGatesTest.php` | Only the expected version differs. Advance to 3.8.25 with the source header. |
| `seasonal/controller.js` | Live is the older committed overlay controller. Main adds the pending safe reading gutter/reserved fallback fix. Retain main. |
| `seasonal/seasonal.css` | Main adds the pending reserved-slot and measurement rules. Retain main. |
| `docs/seasonal-appearance.md` | Main documents those pending placement changes and regression checks. Retain main. |
| `.github/workflows/tests.yml` | Live predates browser regression jobs. Retain main plus PR357's deployment tests and immutable main artifact job. Live workflow files are preserved remotely and are not deployed. |
| `editorial.css` | Main adds approved author profiles, banner ratio and post metadata avatar rules. Retain main. |
| `functions.php` | Main loads the three approved author/avatar integration modules and supplies REST feed avatar configuration. Retain main. |
| `parts/post-meta.html` | Main adds the approved scoped post author avatar before the author name. Retain main. |
| `templates/single.html` | Main adds the approved single-post byline avatar. Retain main. |
| `templates/author.html` | Main has the approved two-column native ActivityPub profile. Retain main. |
| `tacobout-infinite-scroll.js` | Main renders the approved REST-loaded post card avatars. Retain main. |
| `scripts/build-assets.cjs` | Main includes the two new author scripts. Retain main. |
| `asset-manifest.json` | Live points to its older source assets. All 13 entries match source bytes and SHA filename prefixes. Regenerate from the integrated candidate, including the new style version and 15 current sources. |

The live controller, CSS, templates and other differences match older git blobs;
the differing version header and its test account for the remaining source edits.
The higher live version does not establish an additional functional fix. No
live-only functional source change was found to port or discard.

## Snapshot-only generated assets

These eight files are valid older content-fingerprinted assets. They are not
copied into the release or removed remotely. Normal deployment preserves files
outside its desired manifest, so caches referring to them can continue to load.

- `editorial.4691e0651a462988.css`
- `editorial.76eb5476fb7975d1.css`
- `seasonal/controller.54f466ce8d81b59b.js`
- `seasonal/seasonal.44789e2653e46296.css`
- `style.ec869b15b70d9796.css`
- `style.fa11f6d6798ef886.css`
- `tacobout-infinite-scroll.65d890f4e882a1cc.js`
- `tacobout-infinite-scroll.b8d084cd61361c35.js`

Main-only runtime files are the three approved mascot PNGs, the author profile,
explicit avatar and post avatar PHP modules, the two author frontend/editor scripts
and their fingerprints, and newer style/editorial/seasonal/infinite-scroll assets.
The other main-only paths are feature documentation and tests. All are intentional
pending repository additions, not missing live customizations.

## Evidence and remaining gates

`.github/deployment/live-snapshot-content.json` records all 212 original file
hashes and sizes. `release-content-review.json` records the candidate content delta
and files preserved remotely. Neither file grants deployment approval.

Archive theme entries carry uniform mode 0600, and the included manifest records
bytes/hash but no installed permissions. Archive packaging permissions cannot
establish the server's actual modes. Therefore the executable baseline retains
`status: unreconciled`, with no invented mode-sensitive digest or approved release
commit. Production remains disabled and the absolute SFTP root remains unset.

Next, separately authorized temporary SFTP access must independently pin host
identity, verify the canonical active root, and read current hashes and modes.
Compare those bytes with this snapshot; investigate any intervening drift. An
`inspect` snapshot may be obtained only after the protected environment, approved
credentials and verified root are in place. Actual POSIX replacement support is
still untested and is probed only after a durable backup during an approved write.

After reviewed integration is merged, the successful main Tests run produces the
immutable release. Its exact commit and ZIP hash must be selected in a subsequent
reviewed baseline configuration change, together with actual current file modes,
digest and replacement map. The reviewed content candidate is not a substitute
for that artifact identity. Retain production disabled until every gate passes.
Current dashboard backup attestation must still be provided for the actual write.
Site Editor templates/global styles and all content/settings remain in the database
and are neither inferred from this snapshot nor reset by the deployment tooling.
