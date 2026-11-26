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

data class SavedScreen(val type: String, val payload: String)

class DeviceSettings(private val preferences: SharedPreferences) : WarehouseSelection, ScanSettings {

    private var baseAppearance = readAppearance()

    private var activeOrganisationId: Long? = null

    private val currentThemePerOrganisation = MutableStateFlow(preferences.getBoolean(THEME_PER_ORGANISATION_KEY, false))

    val isThemePerOrganisation: StateFlow<Boolean> = currentThemePerOrganisation.asStateFlow()

    private val currentAppearance = MutableStateFlow(effectiveAppearance())

    val appearance: StateFlow<Appearance> = currentAppearance.asStateFlow()

    private val currentOpenOnLastScreen = MutableStateFlow(preferences.getBoolean(OPEN_ON_LAST_SCREEN_KEY, false))

    val isOpeningOnLastScreen: StateFlow<Boolean> = currentOpenOnLastScreen.asStateFlow()

    private val currentScanPreferences = MutableStateFlow(readScanPreferences())

    override val scanPreferences: StateFlow<ScanPreferences> = currentScanPreferences.asStateFlow()

    fun updateAppearance(appearance: Appearance) {
        val organisationId = activeOrganisationId
        if (currentThemePerOrganisation.value && organisationId != null) {
            preferences.edit {
                putString(organisationThemeKey(organisationId), appearance.theme.name)
                putString(MODE_KEY, appearance.mode.name)
            }
            baseAppearance = baseAppearance.copy(mode = appearance.mode)
        } else {
            preferences.edit {
                putString(THEME_KEY, appearance.theme.name)
                putString(MODE_KEY, appearance.mode.name)
            }
            baseAppearance = appearance
        }
        currentAppearance.value = effectiveAppearance()
    }

    fun updateThemePerOrganisation(isOn: Boolean) {
        preferences.edit { putBoolean(THEME_PER_ORGANISATION_KEY, isOn) }
        currentThemePerOrganisation.value = isOn
        currentAppearance.value = effectiveAppearance()
    }

    fun showOrganisation(organisationId: Long?) {
        activeOrganisationId = organisationId
        currentAppearance.value = effectiveAppearance()
    }

    fun updateOpenOnLastScreen(isOn: Boolean) {
        preferences.edit { putBoolean(OPEN_ON_LAST_SCREEN_KEY, isOn) }
        currentOpenOnLastScreen.value = isOn
    }

    fun lastScreen(userId: Long, warehouseId: Long): SavedScreen? {
        val saved = preferences.getString(lastScreenKey(userId, warehouseId), null) ?: return null
        val type = saved.substringBefore(SAVED_SCREEN_SEPARATOR)
        val payload = saved.substringAfter(SAVED_SCREEN_SEPARATOR, missingDelimiterValue = "")
        return SavedScreen(type = type, payload = payload).takeIf { type.isNotBlank() && payload.isNotBlank() }
    }

    fun saveLastScreen(userId: Long, warehouseId: Long, screen: SavedScreen) {
        preferences.edit { putString(lastScreenKey(userId, warehouseId), screen.type + SAVED_SCREEN_SEPARATOR + screen.payload) }
    }

    private fun effectiveAppearance(): Appearance {
        val organisationId = activeOrganisationId
        val organisationTheme = if (currentThemePerOrganisation.value && organisationId != null) {
            AikuTheme.entries.firstOrNull { it.name == preferences.getString(organisationThemeKey(organisationId), null) }
        } else {
            null
        }
        return baseAppearance.copy(theme = organisationTheme ?: baseAppearance.theme)
    }

    private fun organisationThemeKey(organisationId: Long) = ORGANISATION_THEME_KEY_PREFIX + organisationId

    private fun lastScreenKey(userId: Long, warehouseId: Long) = "$LAST_SCREEN_KEY_PREFIX${userId}_$warehouseId"

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
        const val THEME_PER_ORGANISATION_KEY = "appearance_theme_per_organisation"
        const val ORGANISATION_THEME_KEY_PREFIX = "appearance_theme_organisation_"
        const val OPEN_ON_LAST_SCREEN_KEY = "open_on_last_screen"
        const val LAST_SCREEN_KEY_PREFIX = "last_screen_"
        const val SAVED_SCREEN_SEPARATOR = "\n"
    }
}
