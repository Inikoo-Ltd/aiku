package io.aiku.droid.ui.profile

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.provider.Settings
import androidx.annotation.StringRes
import androidx.core.app.NotificationManagerCompat
import io.aiku.droid.R
import io.aiku.droid.push.WORK_CHANNEL_ID

private const val PACKAGE_SCHEME = "package"

fun notificationsAllowed(isAppAllowed: Boolean, workChannelImportance: Int?): Boolean =
    isAppAllowed && workChannelImportance != NotificationManagerCompat.IMPORTANCE_NONE

@StringRes
fun notificationsValue(isAllowed: Boolean): Int = if (isAllowed) R.string.app_notifications_on else R.string.app_notifications_off

fun Context.areWorkNotificationsAllowed(): Boolean {
    val manager = NotificationManagerCompat.from(this)
    return notificationsAllowed(manager.areNotificationsEnabled(), manager.getNotificationChannelCompat(WORK_CHANNEL_ID)?.importance)
}

fun Context.openNotificationSettings(): Boolean = listOf(
    Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS).putExtra(Settings.EXTRA_APP_PACKAGE, packageName),
    Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.fromParts(PACKAGE_SCHEME, packageName, null)),
).any { intent ->
    try {
        startActivity(intent)
        true
    } catch (exception: ActivityNotFoundException) {
        false
    }
}
