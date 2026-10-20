/** Small Site Editor labels for server-rendered, queried-author-only sections. */
(function (blocks, blockEditor, element, i18n) {
	'use strict';
	[
		['tacobout/author-banner', i18n.__('Author banner', 'tacobout'), i18n.__('Shows the current author’s banner and approved artwork on public author pages.', 'tacobout')],
		['tacobout/author-profile', i18n.__('Author profile', 'tacobout'), i18n.__('Shows the current author’s profile and available ActivityPub follow controls.', 'tacobout')],
	].forEach(([name, title, description]) => blocks.registerBlockType(name, {
		apiVersion: 3,
		title,
		category: 'theme',
		supports: { html: false, inserter: false },
		edit: () => element.createElement('div', blockEditor.useBlockProps(), description),
		save: () => null,
	}));
})(window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n);
