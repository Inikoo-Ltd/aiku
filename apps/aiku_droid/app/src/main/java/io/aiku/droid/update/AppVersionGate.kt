package io.aiku.droid.update

import android.content.SharedPreferences
import androidx.core.content.edit
import io.aiku.droid.data.api.AppVersionApi
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.PlatformVersion
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed interface AppUpdate {
    data object None : AppUpdate
    data class Required(val latestVersionName: String, val updateUrl: String?) : AppUpdate
    data class Available(val latestVersionCode: Int, val latestVersionName: String, val updateUrl: String?) : AppUpdate
}

fun appUpdateFor(installedVersionCode: Int, version: PlatformVersion): AppUpdate = when {
    installedVersionCode < version.minimumVersionCode -> AppUpdate.Required(version.latestVersionName, version.updateUrl)
    installedVersionCode < version.latestVersionCode -> AppUpdate.Available(version.latestVersionCode, version.latestVersionName, version.updateUrl)
    else -> AppUpdate.None
}

class AppVersionGate(
    private val api: AppVersionApi,
    private val installedVersionCode: Int,
    private val preferences: SharedPreferences,
    private val scope: CoroutineScope,
    private val clock: () -> Long,
) {
    private val mutableUpdate = MutableStateFlow<AppUpdate>(AppUpdate.None)
    val update: StateFlow<AppUpdate> = mutableUpdate.asStateFlow()

    private val mutableBanner = MutableStateFlow<AppUpdate.Available?>(null)
    val banner: StateFlow<AppUpdate.Available?> = mutableBanner.asStateFlow()

    private var isChecking = false
    private var answeredAt: Long? = null

    fun check() {
        val lastAnswer = answeredAt
        if (isChecking || (lastAnswer != null && clock() - lastAnswer < CHECK_INTERVAL_MILLIS)) {
            return
        }
        isChecking = true
        scope.launch {
            try {
                val result = api.androidVersion()
                if (result is GoodsOutResult.Loaded) {
                    answeredAt = clock()
                    publish(appUpdateFor(installedVersionCode, result.value))
                }
            } finally {
                isChecking = false
            }
        }
    }

    fun dismissBanner() {
        val shown = mutableBanner.value ?: return
        preferences.edit { putInt(DISMISSED_VERSION_KEY, shown.latestVersionCode) }
        mutableBanner.value = null
    }

    private fun publish(update: AppUpdate) {
        mutableUpdate.value = update
        mutableBanner.value = (update as? AppUpdate.Available)
            ?.takeIf { it.latestVersionCode != preferences.getInt(DISMISSED_VERSION_KEY, NO_VERSION) }
    }

    companion object {
        const val CHECK_INTERVAL_MILLIS = 10 * 60 * 1000L
        private const val DISMISSED_VERSION_KEY = "dismissed_update_version_code"
        private const val NO_VERSION = 0
    }
}
