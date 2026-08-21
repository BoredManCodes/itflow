// Browser (OS-level) notifications for new tickets, replies, assignments, etc.
// Polls agent/ajax.php?get_new_notifications for anything added to the notifications
// table for the logged in user since the last check, and surfaces it via the
// Notification API. Falls back to doing nothing if the browser can't/won't allow it.

window.ItflowNotify = (function () {

    var supported = typeof Notification !== "undefined" && window.itflowNotifyUserId;
    var userId = window.itflowNotifyUserId;
    var lastIdKey = "itflow_notif_last_id_" + userId;
    var pollMs = 30000;
    var started = false;

    function getLastId() {
        return parseInt(localStorage.getItem(lastIdKey), 10) || 0;
    }

    function setLastId(id) {
        localStorage.setItem(lastIdKey, id);
    }

    // First time this browser has checked for this user - baseline on whatever
    // is already unread so we don't fire a wall of popups for old notifications
    function baseline() {
        jQuery.get("/agent/ajax.php", { get_new_notifications: true, since_id: 0 }, function (items) {
            var maxId = 0;
            (items || []).forEach(function (item) {
                if (item.id > maxId) {
                    maxId = item.id;
                }
            });
            setLastId(maxId);
        }, "json");
    }

    function showNotifications(items) {
        if (!items || !items.length) {
            return;
        }

        if (items.length > 4) {
            // Avoid a wall of popups - summarise instead
            var summary = new Notification("ITFlow", {
                body: items.length + " new notifications",
                icon: "/favicon.ico",
                tag: "itflow-notif-summary"
            });
            summary.onclick = function () {
                window.focus();
                window.location.href = "/agent/notifications.php";
                summary.close();
            };
        } else {
            items.forEach(function (item) {
                var notification = new Notification(item.type || "ITFlow", {
                    body: item.text,
                    icon: "/favicon.ico",
                    tag: "itflow-notif-" + item.id
                });
                notification.onclick = function () {
                    window.focus();
                    window.location.href = item.action || "/agent/notifications.php";
                    notification.close();
                };
            });
        }
    }

    function poll() {
        if (Notification.permission !== "granted") {
            return;
        }

        jQuery.get("/agent/ajax.php", { get_new_notifications: true, since_id: getLastId() }, function (items) {
            if (!items || !items.length) {
                return;
            }

            showNotifications(items);

            var maxId = getLastId();
            items.forEach(function (item) {
                if (item.id > maxId) {
                    maxId = item.id;
                }
            });
            setLastId(maxId);
        }, "json");
    }

    function start() {
        if (started) {
            return;
        }
        started = true;

        if (localStorage.getItem(lastIdKey) === null) {
            baseline();
        }
        setInterval(poll, pollMs);
    }

    if (supported) {
        if (Notification.permission === "granted") {
            start();
        } else if (Notification.permission !== "denied") {
            // Ask on the first click anywhere, rather than immediately on page load
            var askOnce = function () {
                document.removeEventListener("click", askOnce);
                Notification.requestPermission().then(function (permission) {
                    if (permission === "granted") {
                        start();
                    }
                });
            };
            document.addEventListener("click", askOnce);
        }
    }

    // Called from a button click (e.g. agent/user/user_details.php) to prove the
    // whole pipeline - permission, AJAX poll, Notification API - actually works.
    // Returns a status string via the callback: "granted", "denied", or "unsupported".
    function test(callback) {
        callback = callback || function () {};

        if (!supported) {
            callback("unsupported");
            return;
        }

        function fireTest() {
            jQuery.post("/agent/ajax.php", {
                send_test_browser_notification: true,
                csrf_token: jQuery('input[name="csrf_token"]').val()
            }, function () {
                start(); // no-op if already running
                poll();  // don't wait for the interval
                callback("granted");
            }, "json");
        }

        if (Notification.permission === "granted") {
            fireTest();
        } else if (Notification.permission === "denied") {
            callback("denied");
        } else {
            Notification.requestPermission().then(function (permission) {
                if (permission === "granted") {
                    fireTest();
                } else {
                    callback(permission);
                }
            });
        }
    }

    return { test: test };

})();
