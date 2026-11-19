package io.aiku.droid.push

import android.content.Context
import android.util.Log
import com.google.firebase.FirebaseApp
import com.google.firebase.messaging.FirebaseMessaging
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlin.coroutines.resume

class FirebasePushTokenSource(private val context: Context) : PushTokenSource {

    override val isAvailable: Boolean
        get() = FirebaseApp.getApps(context).isNotEmpty()

    @Suppress("DEPRECATION")
    override suspend fun currentToken(): String? {
        if (!isAvailable) {
            return null
        }
        return suspendCancellableCoroutine { continuation ->
            runCatching { FirebaseMessaging.getInstance().token }
                .onFailure { exception ->
                    Log.w(LOG_TAG, "Push token unavailable", exception)
                    continuation.resume(null)
                }
                .onSuccess { task ->
                    task.addOnCompleteListener { completed ->
                        if (!completed.isSuccessful) {
                            Log.w(LOG_TAG, "Push token unavailable", completed.exception)
                        }
                        continuation.resume(if (completed.isSuccessful) completed.result else null)
                    }
                }
        }
    }

    private companion object {
        const val LOG_TAG = "AikuPush"
    }
}
