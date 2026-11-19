package io.aiku.droid.data.settings

import android.content.SharedPreferences
import androidx.core.content.edit
import io.aiku.droid.ui.theme.AikuTheme
import io.aiku.droid.ui.theme.Appearance
import io.aiku.droid.ui.theme.ThemeMode
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

interface WarehouseSelection {
    var selectedWarehouseId: Long?
}

class DeviceSettings(private val preferences: SharedPreferences) : WarehouseSelection, ScanSettings {

    private val currentAppearance = MutableStateFlow(readAppearance())

    val appearance: StateFlow<Appearance> = currentAppearance.asStateFlow()

    private val currentScanPreferences = MutableStateFlow(readScanPreferences())

    override val scanPreferences: StateFlow<ScanPreferences> = currentScanPreferences.asStateFlow()

    fun updateAppearance(appearance: Appearance) {
        preferences.edit {
            putString(THEME_KEY, appearance.theme.name)
            putString(MODE_KEY, appearance.mode.name)
        }
        currentAppearance.value = appearance
    }

    override fun updateScanPreferences(chosen: ScanPreferences) {
        preferences.edit {
            putString(SCAN_MODE_KEY, chosen.mode.name)
            putBoolean(SCAN_SOUND_KEY, chosen.isSoundOn)
            putBoolean(SCAN_VIBRATION_KEY, chosen.isVibrationOn)
        }
        currentScanPreferences.value = chosen
    }

    override var selectedWarehouseId: Long?
        get() = preferences.getLong(SELECTED_WAREHOUSE_KEY, NO_WAREHOUSE).takeIf { it != NO_WAREHOUSE }
        set(value) = preferences.edit {
            if (value == null) remove(SELECTED_WAREHOUSE_KEY) else putLong(SELECTED_WAREHOUSE_KEY, value)
        }

    private fun readAppearance() = Appearance(
        theme = AikuTheme.entries.firstOrNull { it.name == preferences.getString(THEME_KEY, null) } ?: AikuTheme.DeepSea,
        mode = ThemeMode.entries.firstOrNull { it.name == preferences.getString(MODE_KEY, null) } ?: ThemeMode.System,
    )

    private fun readScanPreferences(): ScanPreferences {
        val defaults = ScanPreferences()
        return ScanPreferences(
            mode = ScanMode.entries.firstOrNull { it.name == preferences.getString(SCAN_MODE_KEY, null) } ?: defaults.mode,
            isSoundOn = preferences.getBoolean(SCAN_SOUND_KEY, defaults.isSoundOn),
            isVibrationOn = preferences.getBoolean(SCAN_VIBRATION_KEY, defaults.isVibrationOn),
        )
    }

    private companion object {
        const val THEME_KEY = "appearance_theme"
        const val MODE_KEY = "appearance_mode"
        const val SELECTED_WAREHOUSE_KEY = "selected_warehouse_id"
        const val SCAN_MODE_KEY = "scan_mode"
        const val SCAN_SOUND_KEY = "scan_sound"
        const val SCAN_VIBRATION_KEY = "scan_vibration"
        const val NO_WAREHOUSE = -1L
    }
}
