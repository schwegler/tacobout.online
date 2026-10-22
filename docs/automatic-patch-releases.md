# Automatic patch releases

The default branch is **main**. After the manually verified **3.9.0** seed,
`Automatic patch releases` publishes 3.9.1, 3.9.2, etc. for green merged PRs.
Normal merge commits and squash merges are recognized through GitHub's merged-PR
association. A direct push without a merged PR does not create a release.
WordPress installation remains manual; no hosting access is used.

## Exact-source gate

The coordinator wakes when any existing main CI workflow completes. Before a
merge can release, its newest **push** runs for these exact paths must all be
completed successfully, with the required jobs also successful:

- `tests.yml`: test, seasonal-placement, release-tooling and theme-artifact.
- `trove-cards.yml`: browsers.
- `public-email.yml`: contact.

The repository, default branch and SHA must match; PR/fork runs, similarly named
workflows, missing/skipped jobs, old successful runs and in-progress/failed
reruns cannot satisfy the gate. It checks again after candidate validation and
before making the release stable. Pending older merges hold newer ones back.
Failed merges never release; a later green fix may release without waiting for
the failed source. Once a newer source ships, an older source is not released
later as a rollback.

## Queue, versions and immutable packages

One workflow concurrency group serializes all coordinators, without cancelling
the active run. GitHub may replace a pending event, so the coordinator does not
rely on delivery of every event: it scans the whole unreleased first-parent
main backlog from the last published source, in order. One surviving event can
process several green merges. A later event/rerun resumes the remaining backlog.

Main keeps the minor-version base, currently 3.9.0. For each patch, a detached
child of the exact green merge changes only the `style.css` version, generated
stylesheet fingerprint and asset manifest. A byte/tree check rejects other
runtime changes. This child is tagged, never pushed to main: no bump PR loop
and no installation occurs. The next number exceeds all reserved stable tags
in the current minor series, including interrupted drafts. New major/minor
series require a deliberate manually verified seed release first.

The candidate reruns PHP, JavaScript, fingerprint, contact and package tests.
The complete browser/native-core CI is required on the exact merged product
source; only the proven version/fingerprint metadata differs in its child.
The deterministic builder reads committed runtime files and verifies the ZIP.

Four assets are attached to a draft before publication:

- `theme.zip`, `release.json`, `tacobout-update.json` use the established updater format.
- `release-source.json` binds the merge SHA, tagged release-child SHA, version and ZIP checksum.

Every uploaded byte is downloaded and compared with the verified local build;
the hosted ZIP and metadata are checked before the draft becomes stable/latest.
Tags never move and assets are never overwritten. An interrupted tag/draft for
the same source resumes using its original version. Missing assets can be
added to a draft; conflicting/unknown assets or an incomplete published release
stop the job. An already published matching release is an idempotent no-op.
A concurrent manual stable release stops draft promotion when detected, rather
than silently replacing its ordering. Avoid publishing competing manual patches
while this coordinator is active.

## Credentials and trust

The workflow uses the existing repository `GITHUB_TOKEN`, with the same
contents-write capability as the established release publisher, plus read-only
Actions/PR access to inspect CI and merged-PR identity. No PAT, new secret,
application installation, repository settings change or hosting credential is
required. Fork/PR workflow events cannot start the publishing job; only trusted
main push events and manual runs on main are accepted. Checkout credentials
are not persisted. No downloaded PR artifact is executed.

Releases created with `GITHUB_TOKEN` do not recursively trigger the manual
`release: published` workflow. This coordinator therefore owns its own complete
source gate, candidate checks, immutable upload and download verification.
The existing manual release flow remains available for planned minor releases.

## Activation and recovery

Merging this workflow activates it automatically. The first green merge after
the 3.9.0 seed, including the activation merge, is eligible for 3.9.1.

Actions → Automatic patch releases → Run workflow on **main** defaults to a
read-only release plan (`dry_run=true`). To resume a failed/network-interrupted
publication, rerun the failed workflow or run it with `dry_run=false`. Inspect
the reported tag/draft before retrying a conflict. Do not delete/recreate tags,
replace assets, weaken the source gate or rerun the manual release upload over
an automatic patch. No automatic retry changes credentials or security settings.

Tests use isolated local Git repositories and an in-memory GitHub service; they
exercise ordering, coalesced events, pending/failed gates, deterministic children,
partial uploads, collisions, retries and dry runs without public writes.

References: [workflow-run security](https://docs.github.com/en/actions/reference/workflows-and-actions/events-that-trigger-workflows#workflow_run),
[concurrency](https://docs.github.com/en/actions/how-tos/write-workflows/choose-when-workflows-run/control-workflow-concurrency),
[token event behavior](https://docs.github.com/en/actions/how-tos/writing-workflows/choosing-when-your-workflow-runs/triggering-a-workflow#triggering-a-workflow-from-a-workflow).
