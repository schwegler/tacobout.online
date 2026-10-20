# EasyWP theme deployment

The draft workflow is intentionally disabled. It does not configure credentials,
GitHub protection rules, or production. Only an explicit `workflow_dispatch` from
`main` can operate; pull request code never receives deployment secrets.

## Release and trust boundary

`Tests` builds `tested-theme-<commit>-<attempt>` only after its PHP, Node, browser,
and deployment-tooling jobs succeed on a push to main. Other independent workflows
are not implied by this gate. The ZIP contains tracked runtime theme files only,
with deterministic bytes and a SHA-256 manifest. Deployment downloads that exact
run/attempt artifact; it never rebuilds or executes downloaded artifact code.
The trusted workflow and tooling are checked out at the workflow's own immutable
SHA. All new actions and deployment dependencies are pinned, including wheel hashes.

The read-only validation job verifies the source workflow, main branch, successful
attempt, commit, artifact identity, and environment protection before the job that
can receive secrets. A fixed concurrency group serializes operations without
cancelling an active publication. An exclusive theme-local lock also rejects a
second writer. Neither mechanism prevents a human or unrelated host process from
editing files; freeze theme edits throughout the run.

## Setup prerequisites — separate approval required

1. Obtain a completed current EasyWP dashboard backup and a complete installed
   theme snapshot. The known live 3.8.24/seasonal drift versus repository 3.8.23 is
   unresolved. Do not substitute repository files or publicly fetched assets for
   the installed snapshot. Review and reconcile every intended replacement,
   preserving unrelated live changes, and increase the release version.
2. Verify the active theme and its canonical absolute SFTP directory independently.
   Set the reviewed `theme_root` and `active_theme_verified_at` in
   `.github/deployment/production.json`. The root must end in
   `/wp-content/themes/tacobout.online-main`; symlink ancestors are refused.
3. Configure `easywp-production` with required reviewers and an exact `main`
   deployment branch policy **before adding secrets**. This repository is public
   and supports that protection; no environment existed during implementation.
   The code validates protection through the API. The documented private-plan
   fallback requires a recorded unavailability reason and an admin/maintainer;
   it is weaker and is explicitly refused for this public repository.
4. With separate authorization, provision provider-supported temporary SFTP access
   with the narrowest available scope and expiry. EasyWP exposes SFTP, not a remote
   shell. Store environment secrets `EASYWP_SFTP_HOST`,
   `EASYWP_SFTP_USERNAME`, `EASYWP_SFTP_PASSWORD`, `EASYWP_KNOWN_HOSTS`, and
   `THEME_BACKUP_KEY`. Verify the host key through an independent trusted channel;
   a key collected by an unverified network scan alone is insufficient.
   `EASYWP_KNOWN_HOSTS` is a standard pinned known-hosts entry. Unknown or changed
   keys fail before password authentication; agent/local-key discovery is disabled.
5. Generate a random 32-byte AES key, base64 encode it for `THEME_BACKUP_KEY`, and
   retain a secure offline recovery copy. Never put secrets in dispatch inputs,
   commits, logs, or backup references. Losing this key makes encrypted backups
   unrecoverable. Retain previous keys if rotating.
6. Run `inspect` after the target and environment are verified. It reads only the
   selected theme, creates an encrypted snapshot, and reports its file digest.
   Download the encrypted backup and receipt; decrypt only in a trusted offline
   environment using `deployment.easywp.unseal`. Theme files may contain sensitive
   custom data: do not commit or upload plaintext snapshots.
7. In a separately reviewed configuration change, populate the baseline with
   `status: reconciled`, snapshot reference, `files` from `describe(snapshot)`,
   `digest` from `digest(files)`, the approved release commit and ZIP SHA-256, and
   `approved_replacements` from `changes(files, describe(release_files))`.
   These helpers are in `deployment/easywp.py` and `theme_release.py`.
   Approve each replacement against the real snapshot; do not mechanically approve
   repository differences. Set `enabled: true` only after all prerequisites pass.

No setup action above was performed by this PR. The baseline remains unreconciled,
the root unset, and production disabled.

## Explicit operations

Use Actions → EasyWP production theme → Run workflow on main. Inputs are data,
never shell commands or arbitrary remote paths.

- `inspect`: read-only theme snapshot and encrypted backup; no release inputs.
- `deploy`: supply the successful Tests run ID and attempt, exact tested commit,
  ZIP SHA-256 from its summary, and the current reviewed live file digest.
- `rollback`: supply the original deployment run ID and attempt, its release
  commit, the **backup.enc** SHA-256 from the receipt/summary, and a fresh live
  digest. This selects that run's encrypted pre-deployment backup, not a theme ZIP.
- `verify`: supply the tested release run/attempt, commit, and ZIP hash. HTTP only;
  useful after a separately authorized cache purge without redeploying files.

Every write requires the dashboard backup reference, its UTC creation time
(`YYYY-MM-DDTHH:MM:SSZ`, within 24 hours), and this exact confirmation:
`CONFIRM CURRENT EASYWP BACKUP AND FROZEN THEME EDITS`.
This records the operator's attestation; it cannot independently verify a host
backup. EasyWP's documented backup route is the dashboard; no backup API is used.
An environment reviewer must check the run inputs and release before approving.

## Publication and limits

Preflight rescans all regular files in the verified theme root and requires an
exact baseline digest, approved replacement map, artifact identity, and strictly
newer theme version. Links, unsafe paths, devices, oversized snapshots, missing
files, changed hashes, and unapproved drift fail closed. Unknown live files remain
in the backup and on the server; normal deployment never deletes old files.

The complete pre-write theme snapshot and plan are encrypted with authenticated
AES-GCM. Only ciphertext and a nonsensitive receipt are uploaded as the immutable
`theme-backup-<run>-<attempt>` artifact, retained for 30 days. The workflow downloads
and verifies that persisted artifact before any managed file replacement. Keep a
secure longer-lived copy where required; GitHub retention is not permanent backup.

After backup, a small probe verifies per-file SFTP POSIX replacement support.
Actual EasyWP support remains untested. Unsupported servers stop before managed
files change. Each replacement is staged, verified, and renamed within the same
parent; existing safe permissions are retained. Dependencies are written before
callers. The process is **sequential, not an atomic theme-directory swap**: users
can observe mixed versions during publication, and failures can leave a partial
update. Audit records identify completed replacements. No remote shell, WP-CLI,
git, rsync, destructive mirror, plugin/upload/config/database access, or automatic
rollback is used.

HTTP checks require successful homepage and author responses, absence of common
PHP fatal markers, fresh hashed stylesheet references, and exact bytes for all
generated CSS/JS assets. They do not prove every WordPress integration or media
interaction. CDN/page caches may require a separately authorized dashboard purge;
a stale response fails validation. Review author pages, follow controls, mobile
layout, and the changed features manually. Theme-file deployment preserves
Site Editor template/global-style database overrides, which can hide template
changes; it never resets those overrides. A file backup does not back up the
WordPress database, content, settings, plugins, or uploads.

## Partial failure and recovery

Stop theme edits and retain the run's audit, receipt, and encrypted backup. Read the
run result before retrying: failure does not imply no files changed. If a lock
remains after runner termination, inspect the host and audit, take a new snapshot,
and remove only the verified abandoned lock with separate operator approval.
Do not disable drift checks or force a rerun against a stale baseline.

For explicit rollback, inspect the current files and record their digest, confirm
a new current dashboard backup, then launch `rollback` referencing the original
run. Only managed files still matching either their original or release bytes are
eligible. Later human edits to managed files block recovery for review. Unknown
files and later unrelated edits are preserved. Files created by that release may
be removed only when their bytes still exactly match the release; empty created
directories may remain. The rollback itself receives a fresh persisted encrypted
backup before writes. Verify HTTP and visual behavior afterward.

If API artifacts expired, the key is unavailable, or remote state is ambiguous,
stop and restore reviewed theme files through the host's supported recovery path.
A full dashboard backup restore may also revert database content created since
that backup; choose it deliberately with the site owner. Never silently restore
an entire site to fix theme files.

## Validation and references

The tests use synthetic credentials and a loopback SFTP server. They cover strict
host identity, path/symlink refusal, missing durable backups, drift, unsupported
replacement support, partial writes, explicit rollback, unknown-file preservation,
encryption authentication, HTTP cache/asset failures, source/environment gates,
and deterministic real-repository build/load. No production SFTP capability or
GitHub deployment environment was exercised.

- [EasyWP SFTP access](https://www.namecheap.com/support/knowledgebase/article.aspx/9961/2285/easywp-how-to-access-your-wordpress-website-folders-and-files-via-sftp-video/)
- [EasyWP dashboard backups](https://www.namecheap.com/support/knowledgebase/article.aspx/10650/2280/easywp-how-to-manage-backups/)
- [GitHub deployment environments](https://docs.github.com/en/actions/reference/workflows-and-actions/deployments-and-environments)
