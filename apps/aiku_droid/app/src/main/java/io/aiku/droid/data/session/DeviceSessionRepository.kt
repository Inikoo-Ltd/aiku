package io.aiku.droid.data.session

import android.content.SharedPreferences
import android.util.Base64
import androidx.core.content.edit
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.serialization.json.Json

class DeviceSessionRepository(
    private val preferences: SharedPreferences,
    private val cipher: KeystoreCipher,
) : SessionRepository {

    private val currentSession = MutableStateFlow(restoreRememberedSession())

    override val session: StateFlow<StaffSession?> = currentSession.asStateFlow()

    override fun start(session: StaffSession, rememberOnDevice: Boolean) {
        currentSession.value = session
        if (rememberOnDevice) {
            val encrypted = cipher.encrypt(Json.encodeToString(session).toByteArray(Charsets.UTF_8))
            preferences.edit { putString(REMEMBERED_SESSION_KEY, Base64.encodeToString(encrypted, Base64.NO_WRAP)) }
        } else {
            forgetRememberedSession()
        }
    }

    override fun end() {
        currentSession.value = null
        forgetRememberedSession()
    }

    private fun restoreRememberedSession(): StaffSession? {
        val stored = preferences.getString(REMEMBERED_SESSION_KEY, null) ?: return null

        return runCatching {
            val decrypted = cipher.decrypt(Base64.decode(stored, Base64.NO_WRAP))
            Json.decodeFromString<StaffSession>(decrypted.toString(Charsets.UTF_8))
        }.getOrElse {
            forgetRememberedSession()
            null
        }
    }

    private fun forgetRememberedSession() {
        preferences.edit { remove(REMEMBERED_SESSION_KEY) }
    }

    private companion object {
        const val REMEMBERED_SESSION_KEY = "remembered_session"
    }
}
