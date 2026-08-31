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

    // Converts the VAPID public key (base64url, from window.itflowVapidPublicKey)
    // into the Uint8Array PushManager.subscribe() expects
    function urlBase64ToUint8Array(base64String) {
        var padding = "=".repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
        var rawData = window.atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    // Registers the service worker and subscribes for real Web Push - this is
    // what lets a notification fire with no ITFlow tab or browser open at
    // all, unlike the polling below which only works while a tab is open.
    // Safe to call repeatedly - reuses an existing subscription if there is one.
    function subscribeForPush() {
        if (!("serviceWorker" in navigator) || !("PushManager" in window) || !window.itflowVapidPublicKey) {
            return;
        }

        navigator.serviceWorker.register("/sw.js").then(function (registration) {
            return registration.pushManager.getSubscription().then(function (existing) {
                if (existing) {
                    return existing;
                }
                return registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(window.itflowVapidPublicKey)
                });
            });
        }).then(function (subscription) {
            if (!subscription) {
                return;
            }
            var json = subscription.toJSON();
            itflowPost("/agent/ajax.php", {
                save_push_subscription: true,
                endpoint: json.endpoint,
                p256dh: json.keys.p256dh,
                auth: json.keys.auth,
                csrf_token: csrfToken()
            });
        }).catch(function () {
            // Push subscription is a bonus on top of the polling below - if it
            // fails (blocked, unsupported, etc.) there's nothing more to do here
        });
    }

    function csrfToken() {
        var input = document.querySelector('input[name="csrf_token"]');
        return input ? input.value : '';
    }

    function getLastId() {
        return parseInt(localStorage.getItem(lastIdKey), 10) || 0;
    }

    function setLastId(id) {
        localStorage.setItem(lastIdKey, id);
    }

    // First time this browser has checked for this user - baseline on whatever
    // is already unread so we don't fire a wall of popups for old notifications
    function baseline() {
        itflowGet("/agent/ajax.php", { get_new_notifications: true, since_id: 0 }, function (data) {
            var items;
            try {
                items = JSON.parse(data);
            } catch (e) {
                return;
            }

            var maxId = 0;
            (items || []).forEach(function (item) {
                if (item.id > maxId) {
                    maxId = item.id;
                }
            });
            setLastId(maxId);
        });
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

        itflowGet("/agent/ajax.php", { get_new_notifications: true, since_id: getLastId() }, function (data) {
            var items;
            try {
                items = JSON.parse(data);
            } catch (e) {
                return;
            }

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
        });
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
        subscribeForPush();
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
            itflowPost("/agent/ajax.php", {
                send_test_browser_notification: true,
                csrf_token: csrfToken()
            }, function () {
                start(); // no-op if already running
                poll();  // don't wait for the interval
                callback("granted");
            });
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
