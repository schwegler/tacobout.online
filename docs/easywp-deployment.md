# EasyWP theme deployment

A manually launched SFTP-only workflow for an immutable, tested theme artifact.
No credentials, secrets, environment/security settings or production files are
changed by implementing this workflow. Initial production configuration remains
disabled and live-file drift is unresolved until an actual installed theme snapshot
is reviewed. Dashboard host backup confirmation is mandatory before writes.

Implementation, setup gates and rollback limits follow below.
