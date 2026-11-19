package io.aiku.droid.ui.scan

import io.aiku.droid.data.settings.ScanMode
import io.aiku.droid.data.settings.ScanPreferences
import kotlinx.coroutines.flow.MutableStateFlow
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class ScanFeedbackTest {

    private class RecordingSignals : ScanSignals {
        val played = mutableListOf<String>()
        override fun successTone() {
            played += "success tone"
        }

        override fun errorTone() {
            played += "error tone"
        }

        override fun successVibration() {
            played += "success vibration"
        }

        override fun errorVibration() {
            played += "error vibration"
        }
    }

    private val preferences = MutableStateFlow(ScanPreferences())
    private val signals = RecordingSignals()
    private val controller = ScanController(preferences = preferences, signals = signals)

    @Test
    fun `a hardware scan taken by a screen beeps and vibrates once`() {
        val received = mutableListOf<Scan>()
        controller.listen { received += it }

        val isTaken = controller.emit(Scan(code = "AR24811", source = ScanSource.Hardware))

        assertTrue(isTaken)
        assertEquals(listOf(Scan("AR24811", ScanSource.Hardware)), received)
        assertEquals(listOf("success tone", "success vibration"), signals.played)
    }

    @Test
    fun `a hardware scan nobody listens to gives no feedback`() {
        val isTaken = controller.emit(Scan(code = "AR24811", source = ScanSource.Hardware))

        assertFalse(isTaken)
        assertTrue(signals.played.isEmpty())
    }

    @Test
    fun `a camera scan is signalled by the camera, not again by the controller`() {
        controller.listen { }

        controller.emit(Scan(code = "SKO-1", source = ScanSource.Camera))

        assertTrue(signals.played.isEmpty())
    }

    @Test
    fun `a scan that is not found plays the error tone and a double vibration`() {
        controller.feedback.rejected()

        assertEquals(listOf("error tone", "error vibration"), signals.played)
    }

    @Test
    fun `sound off keeps the vibration`() {
        preferences.value = ScanPreferences(isSoundOn = false)

        controller.feedback.accepted()
        controller.feedback.rejected()

        assertEquals(listOf("success vibration", "error vibration"), signals.played)
    }

    @Test
    fun `vibration off keeps the sound`() {
        preferences.value = ScanPreferences(isVibrationOn = false)

        controller.feedback.accepted()
        controller.feedback.rejected()

        assertEquals(listOf("success tone", "error tone"), signals.played)
    }

    @Test
    fun `both off is silent and still`() {
        preferences.value = ScanPreferences(mode = ScanMode.Scanner, isSoundOn = false, isVibrationOn = false)
        controller.listen { }

        controller.emit(Scan(code = "AR24811", source = ScanSource.Hardware))
        controller.feedback.rejected()

        assertTrue(signals.played.isEmpty())
    }

    @Test
    fun `a change in Profile applies to the next scan`() {
        controller.listen { }
        controller.emit(Scan(code = "A", source = ScanSource.Hardware))
        preferences.value = ScanPreferences(isSoundOn = false, isVibrationOn = false)

        controller.emit(Scan(code = "B", source = ScanSource.Hardware))

        assertEquals(listOf("success tone", "success vibration"), signals.played)
    }
}
