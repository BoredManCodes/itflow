// ITFlow service worker - only handles Web Push delivery (ticket replies,
// assignments, task approvals, etc.) so notifications fire even with no
// ITFlow tab or browser window open. Not a full offline/asset cache.

self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
    var data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = {};
    }

    var title = data.title || 'ITFlow';
    var url = data.url || '/agent/notifications.php';

    event.waitUntil(self.registration.showNotification(title, {
        body: data.body || '',
        icon: '/favicon.ico',
        data: { url: url }
    }));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var url = (event.notification.data && event.notification.data.url) || '/agent/notifications.php';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windowClients) {
            for (var i = 0; i < windowClients.length; i++) {
                var client = windowClients[i];
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        })
    );
});
