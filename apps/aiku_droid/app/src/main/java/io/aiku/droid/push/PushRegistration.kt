package io.aiku.droid.push

import io.aiku.droid.data.api.PushTokenApi
import io.aiku.droid.data.api.PushTokenRegistration
import io.aiku.droid.data.session.SessionRepository
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.filterNotNull
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.launch

interface PushTokenSource {
    val isAvailable: Boolean

    suspend fun currentToken(): String?
}

fun interface ShownNotifications {
    fun clear()
}

interface PushSignOut {
    suspend fun unregister(sessionToken: String)
}

class PushRegistration(
    private val api: PushTokenApi,
    private val tokens: PushTokenSource,
    private val sessions: SessionRepository,
    private val shown: ShownNotifications,
    private val appVersion: String,
    private val scope: CoroutineScope,
) : PushSignOut {

    fun start() {
        if (!tokens.isAvailable) {
            return
        }
        scope.launch {
            sessions.session
                .map { it?.token }
                .distinctUntilChanged()
                .filterNotNull()
                .collectLatest { sessionToken -> register(sessionToken) }
        }
    }

    fun onNewToken(fcmToken: String) {
        val sessionToken = sessions.session.value?.token ?: return
        if (fcmToken.isBlank()) {
            return
        }
        scope.launch { api.register(sessionToken, PushTokenRegistration(fcmToken = fcmToken, appVersion = appVersion)) }
    }

    override suspend fun unregister(sessionToken: String) {
        if (!tokens.isAvailable) {
            return
        }
        shown.clear()
        api.unregister(sessionToken)
    }

    private suspend fun register(sessionToken: String) {
        val fcmToken = tokens.currentToken()?.takeIf { it.isNotBlank() } ?: return
        api.register(sessionToken, PushTokenRegistration(fcmToken = fcmToken, appVersion = appVersion))
    }
}
