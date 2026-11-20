package io.aiku.droid.data.offline

import android.content.SharedPreferences
import androidx.core.content.edit
import java.time.Instant

data class OfflineTime(val instant: Instant, val isServerTime: Boolean)

class ServerClock(
    private val preferences: SharedPreferences,
    private val elapsedRealtime: () -> Long,
    private val bootCount: () -> Int?,
    private val deviceClock: () -> Instant = Instant::now,
) {

    private data class Anchor(val serverTime: Instant, val elapsedAt: Long, val boot: Int?)

    @Volatile
    private var anchor: Anchor? = restored()

    @Volatile
    private var savedElapsedAt: Long? = null

    fun record(serverTime: Instant) {
        val elapsedNow = elapsedRealtime()
        val recorded = Anchor(serverTime = serverTime, elapsedAt = elapsedNow, boot = bootCount())
        anchor = recorded
        val saved = savedElapsedAt
        if (saved == null || elapsedNow - saved >= SAVE_EVERY_MILLIS || elapsedNow < saved) {
            savedElapsedAt = elapsedNow
            preferences.edit {
                putLong(SERVER_TIME, recorded.serverTime.toEpochMilli())
                putLong(ELAPSED_AT, recorded.elapsedAt)
                putInt(BOOT, recorded.boot ?: NO_BOOT)
            }
        }
    }

    fun now(): OfflineTime {
        val current = anchor?.takeIf(::isFromThisBoot) ?: return OfflineTime(deviceClock(), isServerTime = false)
        return OfflineTime(current.serverTime.plusMillis(elapsedRealtime() - current.elapsedAt), isServerTime = true)
    }

    private fun isFromThisBoot(candidate: Anchor): Boolean {
        val elapsedNow = elapsedRealtime()
        if (elapsedNow < candidate.elapsedAt) {
            return false
        }
        val currentBoot = bootCount()
        return candidate.boot == null || currentBoot == null || candidate.boot == currentBoot
    }

    private fun restored(): Anchor? {
        if (!preferences.contains(SERVER_TIME)) {
            return null
        }
        val boot = preferences.getInt(BOOT, NO_BOOT).takeIf { it != NO_BOOT }
        return Anchor(
            serverTime = Instant.ofEpochMilli(preferences.getLong(SERVER_TIME, 0L)),
            elapsedAt = preferences.getLong(ELAPSED_AT, 0L),
            boot = boot,
        ).takeIf(::isFromThisBoot)
    }

    private companion object {
        const val SERVER_TIME = "anchor_server_time"
        const val ELAPSED_AT = "anchor_elapsed_at"
        const val BOOT = "anchor_boot"
        const val NO_BOOT = -1
        const val SAVE_EVERY_MILLIS = 60_000L
    }
}
