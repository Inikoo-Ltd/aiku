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

class DeviceSettings(private val preferences: SharedPreferences) : WarehouseSelection {

    private val currentAppearance = MutableStateFlow(readAppearance())

    val appearance: StateFlow<Appearance> = currentAppearance.asStateFlow()

    fun updateAppearance(appearance: Appearance) {
        preferences.edit {
            putString(THEME_KEY, appearance.theme.name)
            putString(MODE_KEY, appearance.mode.name)
        }
        currentAppearance.value = appearance
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

    private companion object {
        const val THEME_KEY = "appearance_theme"
        const val MODE_KEY = "appearance_mode"
        const val SELECTED_WAREHOUSE_KEY = "selected_warehouse_id"
        const val NO_WAREHOUSE = -1L
    }
}
