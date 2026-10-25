(function () {
    "use strict";

    // Defense-in-depth: don't run inside the Site Editor's preview iframe
    if (window.location.search.indexOf('wp_theme_preview') !== -1) {
        return;
    }

    function processImage(img) {
        if (!img || img.closest('.tacobout-alt-wrapper')) return;

        const altText = img.getAttribute('alt');
        if (!altText) return;

        // Skip if the alt text is just an emoji
        const emojiRegex = /^[\p{Extended_Pictographic}\u{1F3FB}-\u{1F3FF}\u{200D}\u{FE0F}]+$/u;
        if (emojiRegex.test(altText)) return;

        const wrapper = document.createElement('div');
        wrapper.className = 'tacobout-alt-wrapper';

        // Insert wrapper and move image inside
        if (img.parentNode) {
            img.parentNode.insertBefore(wrapper, img);
            wrapper.appendChild(img);
        }

        const badge = document.createElement('button');
        badge.setAttribute('type', 'button');
        badge.className = 'tacobout-alt-badge';
        badge.textContent = 'ALT';
        badge.setAttribute('aria-label', 'Toggle ALT text');
        badge.setAttribute('aria-expanded', 'false');

        const tooltipId = 'alt-tooltip-' + Math.random().toString(36).substring(2, 11);
        badge.setAttribute('aria-controls', tooltipId);

        const tooltip = document.createElement('div');
        tooltip.id = tooltipId;
        tooltip.className = 'tacobout-alt-tooltip';
        tooltip.textContent = altText;
        tooltip.setAttribute('hidden', 'true');
        tooltip.setAttribute('aria-hidden', 'true');

        wrapper.appendChild(badge);
        wrapper.appendChild(tooltip);
    }

    function processContainer(container) {
        if (!container || container.nodeType !== 1) return;
        if (container.classList && container.classList.contains('tacobout-alt-wrapper')) return;

        if (container.tagName === 'IMG') {
            if (container.closest('.wp-block-post-content, .wp-block-post-featured-image')) {
                processImage(container);
            }
            return;
        }

        const images = container.querySelectorAll('.wp-block-post-content img[alt]:not([alt=""]), .wp-block-post-featured-image img[alt]:not([alt=""])');
        images.forEach(processImage);
    }

    /**
     * Scan for images with alt text and wrap them with an ALT badge + tooltip.
     * Safe to call repeatedly (e.g. after infinite scroll appends new posts).
     */
    function attachAltBadges(root) {
        if (root && root.nodeType === 1) {
            processContainer(root);
            return;
        }
        const images = document.querySelectorAll('.wp-block-post-content img[alt]:not([alt=""]), .wp-block-post-featured-image img[alt]:not([alt=""])');
        images.forEach(processImage);
    }

    /**
     * Toggle a tooltip's visibility state.
     */
    function toggleTooltip(badge, tooltip, forceClose) {
        const isHidden = tooltip.hasAttribute('hidden');
        if (forceClose || !isHidden) {
            tooltip.setAttribute('hidden', 'true');
            tooltip.setAttribute('aria-hidden', 'true');
            badge.setAttribute('aria-expanded', 'false');
        } else {
            tooltip.removeAttribute('hidden');
            tooltip.setAttribute('aria-hidden', 'false');
            badge.setAttribute('aria-expanded', 'true');
        }
    }

    /**
     * Single delegated click listener — handles badge toggles AND
     * closing tooltips when clicking outside. Replaces the old pattern
     * of adding per-image document listeners (which caused a memory leak).
     */
    document.addEventListener('click', function (e) {
        // Check if the click is on an ALT badge
        const badge = e.target.closest('.tacobout-alt-badge');
        if (badge) {
            e.preventDefault();
            e.stopPropagation();
            const wrapper = badge.closest('.tacobout-alt-wrapper');
            if (!wrapper) return;
            const tooltip = wrapper.querySelector('.tacobout-alt-tooltip');
            if (!tooltip) return;
            toggleTooltip(badge, tooltip, false);
            return;
        }

        // Click was outside any badge — close all open tooltips
        const openTooltips = document.querySelectorAll('.tacobout-alt-tooltip:not([hidden])');
        openTooltips.forEach(function (tooltip) {
            const wrapper = tooltip.closest('.tacobout-alt-wrapper');
            if (wrapper && !wrapper.contains(e.target)) {
                const relatedBadge = wrapper.querySelector('.tacobout-alt-badge');
                if (relatedBadge) {
                    toggleTooltip(relatedBadge, tooltip, true);
                }
            }
        });
    });

    /**
     * Single delegated keydown listener — close all open tooltips on Escape.
     */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const openTooltips = document.querySelectorAll('.tacobout-alt-tooltip:not([hidden])');
            openTooltips.forEach(function (tooltip) {
                const wrapper = tooltip.closest('.tacobout-alt-wrapper');
                if (!wrapper) return;
                const badge = wrapper.querySelector('.tacobout-alt-badge');
                if (badge) {
                    toggleTooltip(badge, tooltip, true);
                    badge.focus();
                }
            });
        }
    });

    // Run on DOM content loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachAltBadges);
    } else {
        attachAltBadges();
    }

    // Create an observer to watch for new images added to the DOM (like via infinite scroll)
    // ⚡ Bolt: Process addedNodes in MutationObserver instead of re-scanning the entire document
    // with querySelectorAll. This avoids O(N) full-document DOM traversals on infinite scroll inserts,
    // significantly reducing CPU usage and main-thread execution time.
    var pendingNodes = [];
    var debounceTimer;

    function processPendingNodes() {
        var nodesToProcess = pendingNodes;
        pendingNodes = [];
        for (var i = 0; i < nodesToProcess.length; i++) {
            processContainer(nodesToProcess[i]);
        }
    }

    var observer = new MutationObserver(function (mutations) {
        var hasNewNodes = false;
        for (var i = 0; i < mutations.length; i++) {
            var added = mutations[i].addedNodes;
            for (var j = 0; j < added.length; j++) {
                if (added[j].nodeType === 1) {
                    pendingNodes.push(added[j]);
                    hasNewNodes = true;
                }
            }
        }
        if (hasNewNodes) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(processPendingNodes, 100);
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
})();
