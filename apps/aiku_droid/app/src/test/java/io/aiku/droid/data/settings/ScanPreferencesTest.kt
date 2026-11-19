package io.aiku.droid.data.settings

import org.junit.Assert.assertEquals
import org.junit.Test

class ScanPreferencesTest {

    private val preferences = MemoryPreferences()

    @Test
    fun `a new phone scans automatically with sound and vibration on`() {
        val settings = DeviceSettings(preferences)

        assertEquals(ScanPreferences(mode = ScanMode.Automatic, isSoundOn = true, isVibrationOn = true), settings.scanPreferences.value)
    }

    @Test
    fun `a change applies at once and is remembered on the phone`() {
        val chosen = ScanPreferences(mode = ScanMode.Scanner, isSoundOn = false, isVibrationOn = true)

        DeviceSettings(preferences).updateScanPreferences(chosen)

        assertEquals(chosen, DeviceSettings(preferences).scanPreferences.value)
    }

    @Test
    fun `every mode survives a restart`() {
        ScanMode.entries.forEach { mode ->
            DeviceSettings(preferences).updateScanPreferences(ScanPreferences(mode = mode, isVibrationOn = false))

            val restored = DeviceSettings(preferences).scanPreferences.value

            assertEquals(mode, restored.mode)
            assertEquals(false, restored.isVibrationOn)
        }
    }

    @Test
    fun `the current value updates without a restart`() {
        val settings = DeviceSettings(preferences)

        settings.updateScanPreferences(ScanPreferences(mode = ScanMode.Camera))

        assertEquals(ScanMode.Camera, settings.scanPreferences.value.mode)
    }

    @Test
    fun `an unknown stored mode falls back to automatic`() {
        preferences.values["scan_mode"] = "Laser"

        assertEquals(ScanMode.Automatic, DeviceSettings(preferences).scanPreferences.value.mode)
    }

    @Test
    fun `scanning preferences leave the appearance alone`() {
        val settings = DeviceSettings(preferences)
        val appearance = settings.appearance.value

        settings.updateScanPreferences(ScanPreferences(mode = ScanMode.Scanner))

        assertEquals(appearance, DeviceSettings(preferences).appearance.value)
    }
}
