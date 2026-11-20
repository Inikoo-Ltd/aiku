package io.aiku.droid.push

import android.app.Notification
import android.content.Context
import androidx.core.app.NotificationChannelCompat
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import io.aiku.droid.R
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

const val OFFLINE_SYNC_CHANNEL_ID = "offline_sync"

object OfflineOpen {
    const val EXTRA = "offline_open"
    const val RESOLVE_CONFLICTS = "resolve_conflicts"
    const val NEEDS_YOU_TAG = "offline_needs_you"
}

class OfflineOpenRequests {
    private val pending = MutableStateFlow(false)

    val isPending: StateFlow<Boolean> = pending.asStateFlow()

    fun request() {
        pending.value = true
    }

    fun take(): Boolean {
        val wasPending = pending.value
        pending.value = false
        return wasPending
    }
}

class OfflineNotices(private val context: Context, private val workNotifications: WorkNotifications) {

    fun needYou(count: Int) {
        if (count <= 0) {
            return
        }
        workNotifications.createChannel()
        workNotifications.show(
            WorkMessage(
                title = context.resources.getQuantityString(R.plurals.offline_notification_need_you_title, count, count),
                body = context.getString(R.string.offline_notification_need_you_body),
                data = mapOf(OfflineOpen.EXTRA to OfflineOpen.RESOLVE_CONFLICTS),
                tag = OfflineOpen.NEEDS_YOU_TAG,
            ),
        )
    }

    fun sending(): Notification {
        val channel = NotificationChannelCompat.Builder(OFFLINE_SYNC_CHANNEL_ID, NotificationManagerCompat.IMPORTANCE_LOW)
            .setName(context.getString(R.string.offline_sync_channel_name))
            .setDescription(context.getString(R.string.offline_sync_channel_description))
            .build()
        NotificationManagerCompat.from(context).createNotificationChannel(channel)
        return NotificationCompat.Builder(context, OFFLINE_SYNC_CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_ui_bell)
            .setContentTitle(context.getString(R.string.offline_sync_sending))
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .setOngoing(true)
            .setSilent(true)
            .build()
    }
}
