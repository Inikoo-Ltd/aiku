package io.aiku.droid.push

import android.Manifest
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.app.NotificationChannelCompat
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import io.aiku.droid.MainActivity
import io.aiku.droid.R

const val WORK_CHANNEL_ID = "work"

data class WorkMessage(val title: String, val body: String, val data: Map<String, String>, val tag: String)

class WorkNotifications(private val context: Context) : ShownNotifications {

    fun createChannel() {
        val channel = NotificationChannelCompat.Builder(WORK_CHANNEL_ID, NotificationManagerCompat.IMPORTANCE_HIGH)
            .setName(context.getString(R.string.push_channel_work_name))
            .setDescription(context.getString(R.string.push_channel_work_description))
            .build()
        NotificationManagerCompat.from(context).createNotificationChannel(channel)
    }

    fun show(message: WorkMessage) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) {
            return
        }
        val intent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            message.data.forEach { (key, value) -> putExtra(key, value) }
        }
        val contentIntent = PendingIntent.getActivity(
            context,
            message.tag.hashCode(),
            intent,
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT,
        )
        val notification = NotificationCompat.Builder(context, WORK_CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_ui_bell)
            .setContentTitle(message.title)
            .setContentText(message.body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(message.body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_MESSAGE)
            .setAutoCancel(true)
            .setContentIntent(contentIntent)
            .build()
        NotificationManagerCompat.from(context).notify(message.tag, WORK_NOTIFICATION_ID, notification)
    }

    override fun clear() {
        NotificationManagerCompat.from(context).cancelAll()
    }

    private companion object {
        const val WORK_NOTIFICATION_ID = 0
    }
}
