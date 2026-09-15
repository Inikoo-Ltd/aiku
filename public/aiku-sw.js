self.addEventListener('push', (event) => {
    const data = event.data ? event.data.json() : {}

    event.waitUntil(
        self.registration.showNotification(data.title || 'Aiku', {
            body: data.body || '',
            icon: '/favicon-180.png',
            badge: '/favicon-32.png',
            tag: data.tag,
            data: { url: data.url || '/' },
        })
    )
})

self.addEventListener('notificationclick', (event) => {
    event.notification.close()
    const url = event.notification.data?.url || '/'

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            const existing = windowClients.find((client) => client.url === url)
            if (existing) {
                return existing.focus()
            }

            return self.clients.openWindow(url)
        })
    )
})
