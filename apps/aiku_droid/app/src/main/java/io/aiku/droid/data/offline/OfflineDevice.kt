package io.aiku.droid.data.offline

import android.content.SharedPreferences
import androidx.core.content.edit
import java.util.UUID

class OfflineDevice(private val preferences: SharedPreferences) {

    val deviceId: String by lazy {
        preferences.getString(DEVICE_ID, null)?.takeIf { it.isNotBlank() } ?: UUID.randomUUID().toString().also { created ->
            preferences.edit(commit = true) { putString(DEVICE_ID, created) }
        }
    }

    @Synchronized
    fun lastSequence(userId: Long): Long = preferences.getLong(sequenceKey(userId), 0L)

    @Synchronized
    fun reserveSequences(userId: Long, count: Int): List<Long> {
        if (count <= 0) {
            return emptyList()
        }
        val last = lastSequence(userId)
        val reserved = (1..count).map { last + it }
        preferences.edit(commit = true) { putLong(sequenceKey(userId), reserved.last()) }
        return reserved
    }

    fun noticesSentFor(userId: Long): String? = preferences.getString(NOTICES_PREFIX + userId, null)

    fun markNoticesSent(userId: Long, sessionKey: String) {
        preferences.edit { putString(NOTICES_PREFIX + userId, sessionKey) }
    }

    private fun sequenceKey(userId: Long): String = SEQUENCE_PREFIX + userId

    private companion object {
        const val DEVICE_ID = "device_id"
        const val SEQUENCE_PREFIX = "last_sequence_"
        const val NOTICES_PREFIX = "notices_sent_"
    }
}
