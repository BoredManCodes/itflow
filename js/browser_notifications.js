// Browser (OS-level) notifications for new tickets, replies, assignments, etc.
// Polls agent/ajax.php?get_new_notifications for anything added to the notifications
// table for the logged in user since the last check, and surfaces it via the
// Notification API. Falls back to doing nothing if the browser can't/won't allow it.

(function () {

    if (typeof Notification === "undefined" || !window.itflowNotifyUserId) {
        return;
    }

    var userId = window.itflowNotifyUserId;
    var lastIdKey = "itflow_notif_last_id_" + userId;
    var pollMs = 30000;

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
        if (localStorage.getItem(lastIdKey) === null) {
            baseline();
        }
        setInterval(poll, pollMs);
    }

    if (Notification.permission === "granted") {
        start();
    } else if (Notification.permission !== "denied") {
        // Ask on the first click, rather than immediately on page load
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

})();
