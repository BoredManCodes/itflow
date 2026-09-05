/**
 * Client portal first-visit tour.
 *
 * Only runs when client/index.php prints #portalTutorialConfig, which it does
 * once per contact (contact_portal_tutorial_seen_at is NULL). Steps are keyed
 * to data-tutorial-step attributes on nav.php/index.php elements; a step whose
 * target isn't on the page (contact lacks that permission, or the module is
 * disabled) is dropped rather than shown pointing at nothing - that's how the
 * Finance/Technical steps stay out of the tour for contacts who can't see
 * those nav sections in the first place.
 */
(function () {
    'use strict';

    /*
     * Waits for DOMContentLoaded before doing anything. This script tag sits
     * before footer.php's <script src=".../bootstrap.bundle.min.js">, so
     * running immediately would see window.bootstrap as undefined and skip
     * opening the collapsed mobile nav - the Tickets/Finance/Technical/Account
     * steps would then measure a hidden (0x0) target and the bubble would
     * render pinned uselessly at the top-left corner instead of pointing at
     * anything. DOMContentLoaded fires only after every synchronous <script>
     * in the document - including that later one - has already run.
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        var config = document.getElementById('portalTutorialConfig');
        if (!config) {
            return;
        }

        var contactName = config.getAttribute('data-contact-name') || '';
        var csrfToken = config.getAttribute('data-csrf-token') || '';
        var dismissUrl = config.getAttribute('data-dismiss-url') || 'post.php';
        var firstName = contactName.split(' ')[0] || contactName;

        var steps = [
            {
                title: 'Welcome' + (firstName ? ', ' + firstName : '') + '!',
                text: "Quick tour of your client portal - about 30 seconds. Click Skip tour anytime, and you won't see this again.",
                selector: null
            },
            {
                title: 'Open a ticket',
                text: 'Need help from us? Start here to submit a new support ticket.',
                selector: '[data-tutorial-step="new_ticket"]'
            },
            {
                title: 'Your tickets',
                text: 'Track every open and past support ticket here.',
                selector: '[data-tutorial-step="tickets"]'
            },
            {
                title: 'Finance',
                text: "Invoices, quotes and your account statement live here. Only you and other billing contacts on your account can see this - nobody else at your organization has access to financial information in the portal.",
                selector: '[data-tutorial-step="finance"]'
            },
            {
                title: 'Technical',
                text: "Documents, assets, domains and certificates live here. Only technical contacts can see this - other users at your organization won't have access to this section.",
                selector: '[data-tutorial-step="technical"]'
            },
            {
                title: 'Your account',
                text: "Manage your profile, view your activity log, or sign out from here. That's the tour!",
                selector: '[data-tutorial-step="account"]'
            }
        ];

        var activeSteps = steps.filter(function (step) {
            return !step.selector || document.querySelector(step.selector);
        });

        // Nothing but the welcome card would show - not worth interrupting for
        if (activeSteps.length <= 1) {
            return;
        }

        var currentIndex = 0;
        var navCollapse = document.getElementById('navbarSupportedContent');
        var navToggler = document.querySelector('.navbar-toggler');
        var expandedNavForTour = false;

        var blocker = document.createElement('div');
        blocker.className = 'portal-tutorial-blocker';

        var spot = document.createElement('div');
        spot.className = 'portal-tutorial-spot portal-tutorial-spot--hidden';

        var bubble = document.createElement('div');
        bubble.className = 'portal-tutorial-bubble';
        bubble.innerHTML =
            '<div class="portal-tutorial-bubble-arrow"></div>' +
            '<div class="portal-tutorial-step-count"></div>' +
            '<h5 class="portal-tutorial-title"></h5>' +
            '<p class="portal-tutorial-text"></p>' +
            '<div class="portal-tutorial-actions">' +
                '<button type="button" class="btn btn-link btn-sm portal-tutorial-skip">Skip tour</button>' +
                '<div class="portal-tutorial-actions-right">' +
                    '<button type="button" class="btn btn-outline-secondary btn-sm portal-tutorial-back">Back</button>' +
                    '<button type="button" class="btn btn-primary btn-sm portal-tutorial-next">Next</button>' +
                '</div>' +
            '</div>';

        document.body.appendChild(blocker);
        document.body.appendChild(spot);
        document.body.appendChild(bubble);

        var skipBtn = bubble.querySelector('.portal-tutorial-skip');
        var backBtn = bubble.querySelector('.portal-tutorial-back');
        var nextBtn = bubble.querySelector('.portal-tutorial-next');
        var titleEl = bubble.querySelector('.portal-tutorial-title');
        var textEl = bubble.querySelector('.portal-tutorial-text');
        var countEl = bubble.querySelector('.portal-tutorial-step-count');
        var arrowEl = bubble.querySelector('.portal-tutorial-bubble-arrow');

        function dismiss() {
            if (expandedNavForTour && navCollapse && window.bootstrap) {
                var collapseInstance = window.bootstrap.Collapse.getInstance(navCollapse);
                if (collapseInstance) {
                    collapseInstance.hide();
                }
            }

            blocker.remove();
            spot.remove();
            bubble.remove();
            document.removeEventListener('keydown', onKeydown);
            window.removeEventListener('resize', renderCurrentStep);

            if (typeof window.itflowPost === 'function') {
                window.itflowPost(dismissUrl, {
                    dismiss_portal_tutorial: 1,
                    csrf_token: csrfToken
                });
            }
        }

        function onKeydown(e) {
            if (e.key === 'Escape') {
                dismiss();
            } else if (e.key === 'Enter') {
                advance();
            }
        }

        function ensureNavOpen(callback) {
            if (!navCollapse || !navToggler || !window.bootstrap) {
                callback();
                return;
            }

            var isCollapsed = !navCollapse.classList.contains('show') && getComputedStyle(navToggler).display !== 'none';

            if (!isCollapsed) {
                callback();
                return;
            }

            expandedNavForTour = true;
            var collapseInstance = window.bootstrap.Collapse.getOrCreateInstance(navCollapse, { toggle: false });
            navCollapse.addEventListener('shown.bs.collapse', function onShown() {
                navCollapse.removeEventListener('shown.bs.collapse', onShown);
                callback();
            });
            collapseInstance.show();
        }

        function renderCurrentStep() {
            var step = activeSteps[currentIndex];

            titleEl.textContent = step.title;
            textEl.textContent = step.text;
            countEl.textContent = 'Step ' + (currentIndex + 1) + ' of ' + activeSteps.length;
            backBtn.disabled = currentIndex === 0;
            nextBtn.textContent = currentIndex === activeSteps.length - 1 ? 'Finish' : 'Next';

            var target = step.selector ? document.querySelector(step.selector) : null;

            if (!target) {
                spot.classList.add('portal-tutorial-spot--hidden');
                bubble.classList.add('portal-tutorial-bubble--center');
                bubble.style.top = '';
                bubble.style.left = '';
                arrowEl.className = 'portal-tutorial-bubble-arrow';
                return;
            }

            bubble.classList.remove('portal-tutorial-bubble--center');
            target.scrollIntoView({ block: 'center' });

            requestAnimationFrame(function () {
                var rect = target.getBoundingClientRect();
                var padding = 8;

                spot.classList.remove('portal-tutorial-spot--hidden');
                spot.style.top = (rect.top - padding) + 'px';
                spot.style.left = (rect.left - padding) + 'px';
                spot.style.width = (rect.width + padding * 2) + 'px';
                spot.style.height = (rect.height + padding * 2) + 'px';

                positionBubble(rect);
            });
        }

        function positionBubble(rect) {
            var bubbleRect = bubble.getBoundingClientRect();
            var gap = 14;
            var top;
            var placement;

            var spaceBelow = window.innerHeight - rect.bottom;
            var spaceAbove = rect.top;

            if (spaceBelow >= bubbleRect.height + gap || spaceBelow >= spaceAbove) {
                placement = 'top';
                top = rect.bottom + gap;
            } else {
                placement = 'bottom';
                top = rect.top - bubbleRect.height - gap;
            }

            var left = rect.left + (rect.width / 2) - (bubbleRect.width / 2);
            left = Math.max(12, Math.min(left, window.innerWidth - bubbleRect.width - 12));
            top = Math.max(12, Math.min(top, window.innerHeight - bubbleRect.height - 12));

            bubble.style.top = top + 'px';
            bubble.style.left = left + 'px';
            arrowEl.className = 'portal-tutorial-bubble-arrow portal-tutorial-bubble-arrow--' + placement;
            arrowEl.style.left = Math.max(12, Math.min(rect.left + rect.width / 2 - left, bubbleRect.width - 12)) + 'px';
        }

        function advance() {
            if (currentIndex >= activeSteps.length - 1) {
                dismiss();
                return;
            }
            currentIndex += 1;
            renderCurrentStep();
        }

        function goBack() {
            if (currentIndex === 0) {
                return;
            }
            currentIndex -= 1;
            renderCurrentStep();
        }

        skipBtn.addEventListener('click', dismiss);
        nextBtn.addEventListener('click', advance);
        backBtn.addEventListener('click', goBack);
        document.addEventListener('keydown', onKeydown);
        window.addEventListener('resize', renderCurrentStep);

        ensureNavOpen(renderCurrentStep);
    }
})();
