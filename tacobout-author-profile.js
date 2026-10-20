/** Optional convenience only; ActivityPub retains its native follow/copy dialog. */
(function () {
	'use strict';
	if (!navigator.clipboard || !window.isSecureContext) return;
	document.querySelectorAll('[data-profile-handle]').forEach(button => {
		button.hidden = false;
		button.addEventListener('click', async () => {
			const status = button.nextElementSibling;
			try {
				await navigator.clipboard.writeText(button.dataset.profileHandle);
				status.textContent = button.dataset.copiedLabel;
			} catch (_) {
				status.textContent = button.dataset.failedLabel;
			}
		});
	});
})();
