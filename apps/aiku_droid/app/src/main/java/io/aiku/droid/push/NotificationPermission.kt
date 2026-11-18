package io.aiku.droid.push

import android.Manifest
import android.content.SharedPreferences
import android.content.pm.PackageManager
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.annotation.RequiresApi
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.platform.LocalContext
import androidx.core.content.ContextCompat
import androidx.core.content.edit

class NotificationPrompt(private val preferences: SharedPreferences, private val tokens: PushTokenSource) {

    val shouldAsk: Boolean get() = tokens.isAvailable && !preferences.getBoolean(ASKED_KEY, false)

    fun markAsked() {
        preferences.edit { putBoolean(ASKED_KEY, true) }
    }

    private companion object {
        const val ASKED_KEY = "notification_permission_asked"
    }
}

@Composable
fun AskForNotificationsOnce(prompt: NotificationPrompt) {
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
        AskForNotifications(prompt)
    }
}

@RequiresApi(Build.VERSION_CODES.TIRAMISU)
@Composable
private fun AskForNotifications(prompt: NotificationPrompt) {
    val context = LocalContext.current
    val launcher = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { }

    LaunchedEffect(prompt) {
        val isGranted = ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED
        if (prompt.shouldAsk && !isGranted) {
            prompt.markAsked()
            launcher.launch(Manifest.permission.POST_NOTIFICATIONS)
        }
    }
}
