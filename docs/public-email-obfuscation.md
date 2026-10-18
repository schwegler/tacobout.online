# Public email obfuscation

This deters basic raw-source scrapers; it does not prevent spam or hide the address from HTML-aware crawlers, browser automation, source-repository readers, or visitors. Previously published copies may remain indexed.

The shared creator footer uses WordPress core `antispambot()` on its fixed public address. Its HTML character references retain native mail links without JavaScript, with keyboard/screen-reader access, copy-link behavior and print support. Do not escape the generated entities a second time or add a plaintext duplicate in metadata. WordPress content/database/plugin output is outside this repository audit and should be checked in rendered production HTML before release.

No account, DNS, routing, or authentication settings change. Run `python3 scripts/check_public_email.py` for the source regression check. Manual release check: Tab to Contact, activate it, copy its destination; where address text is visible, copy it and inspect print preview, with JavaScript disabled as well as enabled.
