// Sidebar quick timer - a one-click stopwatch that isn't tied to any ticket
// until you stop it and choose (or create) a ticket to log the time against.
// State lives in localStorage so it survives page navigation.
(function () {
    document.addEventListener("DOMContentLoaded", function () {

        var navItem = document.getElementById("quickTimerNavItem");
        var toggleBtn = document.getElementById("quickTimerToggle");
        var stopBtn = document.getElementById("quickTimerStop");
        var iconEl = document.getElementById("quickTimerIcon");
        var displayEl = document.getElementById("quickTimerDisplay");
        var saveModalTrigger = document.getElementById("quickTimerSaveModalTrigger");

        if (!toggleBtn) {
            return; // Widget isn't on this page / user lacks the ticketing module
        }

        var STORAGE_START = "quickTimer_startTime";
        var STORAGE_PAUSED = "quickTimer_pausedSeconds";
        var tickInterval = null;

        function pad(val) {
            return val < 10 ? "0" + val : val;
        }

        function formatTime(totalSeconds) {
            var hours = Math.floor(totalSeconds / 3600);
            var minutes = Math.floor((totalSeconds % 3600) / 60);
            var seconds = totalSeconds % 60;
            return pad(hours) + ":" + pad(minutes) + ":" + pad(seconds);
        }

        function getElapsedSeconds() {
            var pausedSeconds = parseInt(localStorage.getItem(STORAGE_PAUSED) || "0", 10);
            var startTime = parseInt(localStorage.getItem(STORAGE_START) || "0", 10);
            if (!startTime) {
                return pausedSeconds;
            }
            return pausedSeconds + Math.floor((Date.now() - startTime) / 1000);
        }

        function isRunning() {
            return !!localStorage.getItem(STORAGE_START);
        }

        function isActive() {
            return isRunning() || parseInt(localStorage.getItem(STORAGE_PAUSED) || "0", 10) > 0;
        }

        function stopTicking() {
            if (tickInterval) {
                clearInterval(tickInterval);
                tickInterval = null;
            }
        }

        function render() {
            var running = isRunning();
            var active = isActive();

            if (active) {
                displayEl.textContent = formatTime(getElapsedSeconds());
                displayEl.classList.remove("d-none");
                stopBtn.classList.remove("d-none");
            } else {
                displayEl.classList.add("d-none");
                stopBtn.classList.add("d-none");
            }

            iconEl.className = running ? "nav-icon fas fa-pause" : "nav-icon fas fa-play";
            toggleBtn.title = running ? "Pause timer" : (active ? "Resume timer" : "Start timer");

            if (navItem) {
                navItem.classList.toggle("quick-timer-running", running);
            }
        }

        function startTicking() {
            stopTicking();
            tickInterval = setInterval(render, 1000);
        }

        function start() {
            localStorage.setItem(STORAGE_START, Date.now().toString());
            startTicking();
            render();
        }

        function pause() {
            localStorage.setItem(STORAGE_PAUSED, getElapsedSeconds().toString());
            localStorage.removeItem(STORAGE_START);
            stopTicking();
            render();
        }

        function resetToIdle() {
            localStorage.removeItem(STORAGE_START);
            localStorage.removeItem(STORAGE_PAUSED);
            stopTicking();
            render();
        }

        function openSaveModal() {
            var elapsed = getElapsedSeconds();
            var hours = Math.floor(elapsed / 3600);
            var minutes = Math.floor((elapsed % 3600) / 60);
            var seconds = elapsed % 60;

            if (!saveModalTrigger) {
                return;
            }

            saveModalTrigger.setAttribute(
                "data-modal-url",
                "modals/ticket/quick_timer_save.php?hours=" + hours + "&minutes=" + minutes + "&seconds=" + seconds
            );
            saveModalTrigger.click();
        }

        toggleBtn.addEventListener("click", function () {
            if (isRunning()) {
                pause();
            } else {
                start();
            }
        });

        stopBtn.addEventListener("click", function (e) {
            e.stopPropagation();
            if (isRunning()) {
                pause();
            }
            openSaveModal();
        });

        // The save/discard modal is loaded via ajax-modal, so it doesn't exist in
        // the DOM until openSaveModal() fires - wire it up with delegated handlers
        document.addEventListener("click", function (e) {
            var discardBtn = e.target.closest && e.target.closest("#quickTimerDiscardBtn");
            if (discardBtn) {
                resetToIdle();
                jQuery(discardBtn).closest(".modal").modal("hide");
            }
        });

        document.addEventListener("submit", function (e) {
            if (e.target && e.target.id === "quickTimerSaveForm") {
                // A real page navigation follows (post.php redirects to the
                // ticket), so clear state now rather than waiting on a response
                resetToIdle();
            }
        });

        // Restore whatever state was left running/paused on a previous page
        if (isRunning()) {
            startTicking();
        }
        render();
    });
})();
