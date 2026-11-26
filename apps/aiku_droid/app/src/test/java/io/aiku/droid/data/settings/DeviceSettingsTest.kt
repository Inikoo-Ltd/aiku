package io.aiku.droid.data.settings

import io.aiku.droid.ui.theme.AikuTheme
import io.aiku.droid.ui.theme.Appearance
import io.aiku.droid.ui.theme.ThemeMode
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class DeviceSettingsTest {

    private val preferences = MemoryPreferences()

    @Test
    fun `colour per organisation is off by default and one theme is kept for every organisation`() {
        val settings = DeviceSettings(preferences)
        assertFalse(settings.isThemePerOrganisation.value)

        settings.showOrganisation(1)
        settings.updateAppearance(Appearance(theme = AikuTheme.Rosewater, mode = ThemeMode.Dark))
        settings.showOrganisation(2)

        assertEquals(Appearance(theme = AikuTheme.Rosewater, mode = ThemeMode.Dark), settings.appearance.value)
    }

    @Test
    fun `with colour per organisation on, each organisation keeps its own theme and the mode is shared`() {
        val settings = DeviceSettings(preferences)
        settings.updateAppearance(Appearance(theme = AikuTheme.DeepSea, mode = ThemeMode.Light))
        settings.updateThemePerOrganisation(true)

        settings.showOrganisation(1)
        settings.updateAppearance(Appearance(theme = AikuTheme.UkAw, mode = ThemeMode.Dark))
        settings.showOrganisation(2)

        assertEquals(Appearance(theme = AikuTheme.DeepSea, mode = ThemeMode.Dark), settings.appearance.value)

        settings.updateAppearance(Appearance(theme = AikuTheme.Amoled, mode = ThemeMode.Dark))
        settings.showOrganisation(1)
        assertEquals(AikuTheme.UkAw, settings.appearance.value.theme)

        val restarted = DeviceSettings(preferences)
        assertTrue(restarted.isThemePerOrganisation.value)
        restarted.showOrganisation(2)
        assertEquals(Appearance(theme = AikuTheme.Amoled, mode = ThemeMode.Dark), restarted.appearance.value)

        restarted.updateThemePerOrganisation(false)
        assertEquals(AikuTheme.DeepSea, restarted.appearance.value.theme)
    }

    @Test
    fun `the app opens on Home unless the user chooses where they left off`() {
        assertFalse(DeviceSettings(preferences).isOpeningOnLastScreen.value)

        DeviceSettings(preferences).updateOpenOnLastScreen(true)

        assertTrue(DeviceSettings(preferences).isOpeningOnLastScreen.value)
    }

    @Test
    fun `the last screen is kept per user and warehouse`() {
        val settings = DeviceSettings(preferences)
        val goodsOut = SavedScreen(type = "io.aiku.droid.ui.navigation.GoodsOutBacklogRoute", payload = "{}")

        settings.saveLastScreen(userId = 5, warehouseId = 1, screen = goodsOut)

        assertEquals(goodsOut, DeviceSettings(preferences).lastScreen(userId = 5, warehouseId = 1))
        assertNull(settings.lastScreen(userId = 5, warehouseId = 2))
        assertNull(settings.lastScreen(userId = 6, warehouseId = 1))
    }
}
