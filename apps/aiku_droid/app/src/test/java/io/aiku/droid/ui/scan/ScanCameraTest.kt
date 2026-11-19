package io.aiku.droid.ui.scan

import io.aiku.droid.data.settings.ScanMode
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class ScanCameraTest {

    private val bluetoothScanner = KeyboardDevice(isVirtual = false, isKeyboard = true, isAlphabetic = true, isExternal = true)

    @Test
    fun `automatic starts the camera when no scanner is connected`() {
        assertNull(scannerReadyReason(ScanMode.Automatic, hasExternalScanner = false))
    }

    @Test
    fun `automatic waits for the scanner when one is connected`() {
        assertEquals(ScannerReadyReason.ScannerConnected, scannerReadyReason(ScanMode.Automatic, hasExternalScanner = true))
    }

    @Test
    fun `bluetooth scanner never starts the camera by itself`() {
        assertEquals(ScannerReadyReason.ScannerChosen, scannerReadyReason(ScanMode.Scanner, hasExternalScanner = false))
        assertEquals(ScannerReadyReason.ScannerChosen, scannerReadyReason(ScanMode.Scanner, hasExternalScanner = true))
    }

    @Test
    fun `camera always starts the camera`() {
        assertNull(scannerReadyReason(ScanMode.Camera, hasExternalScanner = false))
        assertNull(scannerReadyReason(ScanMode.Camera, hasExternalScanner = true))
    }

    @Test
    fun `the camera is on only without a scanner-ready reason`() {
        assertTrue(ScanCamera(scannerReady = null, useCamera = {}).isOn)
        assertFalse(ScanCamera(scannerReady = ScannerReadyReason.ScannerChosen, useCamera = {}).isOn)
    }

    @Test
    fun `an external alphabetic keyboard counts as a scanner`() {
        assertTrue(bluetoothScanner.isExternalScanner)
    }

    @Test
    fun `the on-screen keyboard, built-in keys and other input devices do not count`() {
        assertFalse(bluetoothScanner.copy(isVirtual = true).isExternalScanner)
        assertFalse(bluetoothScanner.copy(isExternal = false).isExternalScanner)
        assertFalse(bluetoothScanner.copy(isAlphabetic = false).isExternalScanner)
        assertFalse(bluetoothScanner.copy(isKeyboard = false).isExternalScanner)
    }

    @Test
    fun `before Android 10 a physical alphabetic keyboard counts because external cannot be read`() {
        assertTrue(bluetoothScanner.copy(isExternal = null).isExternalScanner)
    }
}
