package io.aiku.droid.push

import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import io.aiku.droid.AikuDroidApplication
import io.aiku.droid.AppContainer

class AikuMessagingService : FirebaseMessagingService() {

    private val container: AppContainer get() = (application as AikuDroidApplication).container

    @Suppress("OVERRIDE_DEPRECATION")
    override fun onNewToken(token: String) {
        container.pushRegistration.onNewToken(token)
    }

    override fun onMessageReceived(message: RemoteMessage) {
        val data = message.data
        val title = message.notification?.title.orEmpty()
        val body = message.notification?.body.orEmpty()
        val tag = PushData.tagOf(data) ?: message.notification?.tag ?: message.messageId.orEmpty()
        if (title.isNotBlank() || body.isNotBlank()) {
            container.workNotifications.show(WorkMessage(title = title, body = body, data = data, tag = tag))
        }
        container.pushInbox.messageArrived()
    }
}
