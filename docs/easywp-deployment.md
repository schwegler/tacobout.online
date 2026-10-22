# Historical SFTP deployment (retired)

PR #357 introduced a manually triggered, gated SFTP workflow, not a push-triggered production deployment. That machinery has been removed in favor of WordPress fetching public release assets. No credentials, repository secrets, environments or hosting settings were changed.

See [WordPress release updates](wordpress-updates.md) for the replacement. The historical `.github/deployment/` snapshot, baseline and content review, `deployment/tests/fixtures/reviewed-3.8.25-release.json`, and [live reconciliation record](live-theme-reconciliation.md) remain evidence only, not approval for installation. The previously encrypted backups require the historical recovery tooling from PR #357 and its original key; do not discard those backups or keys.
