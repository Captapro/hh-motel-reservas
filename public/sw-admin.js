self.addEventListener('push', function (event) {
    const data = event.data ? event.data.json() : {};
    event.waitUntil(self.registration.showNotification(data.title || 'HH Admin', {
        body: data.body || '',
        icon: '/images/icon-192.png',
        badge: '/images/icon-192.png',
        data: { url: data.url || '/panel' },
    }));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windows) {
        for (const client of windows) {
            if (client.url.includes('/panel') && 'focus' in client) return client.focus();
        }
        return self.clients.openWindow(event.notification.data.url);
    }));
});
