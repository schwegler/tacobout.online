(function () {
    "use strict";

    function attachAltBadges() {
        const images = document.querySelectorAll('img[alt]:not([alt=""])');

        images.forEach(img => {
            // Check if we already wrapped this image
            if (img.closest('.tacobout-alt-wrapper')) return;

            const wrapper = document.createElement('div');
            wrapper.className = 'tacobout-alt-wrapper';

            // Insert wrapper and move image inside
            img.parentNode.insertBefore(wrapper, img);
            wrapper.appendChild(img);

            const badge = document.createElement('button');
            badge.className = 'tacobout-alt-badge';
            badge.textContent = 'ALT';
            badge.setAttribute('aria-label', 'Toggle ALT text');

            const tooltip = document.createElement('div');
            tooltip.className = 'tacobout-alt-tooltip';
            tooltip.textContent = img.getAttribute('alt');
            tooltip.setAttribute('hidden', 'true');
            tooltip.setAttribute('aria-hidden', 'true');

            badge.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                const isHidden = tooltip.hasAttribute('hidden');
                if (isHidden) {
                    tooltip.removeAttribute('hidden');
                    tooltip.setAttribute('aria-hidden', 'false');
                } else {
                    tooltip.setAttribute('hidden', 'true');
                    tooltip.setAttribute('aria-hidden', 'true');
                }
            });

            // Close tooltip when clicking outside
            document.addEventListener('click', (e) => {
                if (!wrapper.contains(e.target) && !tooltip.hasAttribute('hidden')) {
                    tooltip.setAttribute('hidden', 'true');
                    tooltip.setAttribute('aria-hidden', 'true');
                }
            });

            wrapper.appendChild(badge);
            wrapper.appendChild(tooltip);
        });
    }

    // Run on DOM content loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachAltBadges);
    } else {
        attachAltBadges();
    }

    // Create an observer to watch for new images added to the DOM (like via infinite scroll)
    const observer = new MutationObserver((mutations) => {
        let shouldRun = false;
        for (const mutation of mutations) {
            if (mutation.addedNodes.length) {
                shouldRun = true;
                break;
            }
        }
        if (shouldRun) {
            attachAltBadges();
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
})();
