package io.aiku.droid.ui.scan

import android.view.KeyEvent
import android.view.KeyboardShortcutGroup
import android.view.Menu
import android.view.Window
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.lifecycle.compose.LifecycleResumeEffect

enum class ScanSource { Camera, Hardware }

data class Scan(val code: String, val source: ScanSource)

class ScanController {
    private val listeners = mutableListOf<(Scan) -> Unit>()

    val isListening: Boolean get() = listeners.isNotEmpty()

    fun listen(listener: (Scan) -> Unit): () -> Unit {
        listeners += listener
        return { listeners -= listener }
    }

    fun emit(scan: Scan): Boolean {
        val listener = listeners.lastOrNull() ?: return false
        listener(scan)
        return true
    }
}

val LocalScanController = staticCompositionLocalOf { ScanController() }

@Composable
fun ScanEffect(enabled: Boolean = true, onScan: (Scan) -> Unit) {
    val controller = LocalScanController.current
    val currentOnScan by rememberUpdatedState(onScan)

    LifecycleResumeEffect(controller, enabled) {
        val stopListening = if (enabled) controller.listen { scan -> currentOnScan(scan) } else null
        onPauseOrDispose { stopListening?.invoke() }
    }
}

class HardwareScanKeys(
    private val scans: ScanController,
    private val detector: HardwareScanDetector = HardwareScanDetector(),
) {
    private val consumedKeyCodes = mutableSetOf<Int>()

    fun handle(event: KeyEvent, isTextFieldFocused: Boolean): Boolean {
        if (event.action == KeyEvent.ACTION_UP) {
            return consumedKeyCodes.remove(event.keyCode)
        }
        if (event.action != KeyEvent.ACTION_DOWN) {
            return false
        }
        if (!scans.isListening || isTextFieldFocused || event.repeatCount > 0 || event.isCtrlPressed || event.isAltPressed || event.isMetaPressed) {
            detector.reset()
            return false
        }
        val result = detector.onKey(event.toScanKey(), event.eventTime)
        if (result.isConsumed) {
            consumedKeyCodes += event.keyCode
        }
        result.code?.let { code -> scans.emit(Scan(code = code, source = ScanSource.Hardware)) }
        return result.isConsumed
    }
}

class HardwareScanWindowCallback(
    private val delegate: Window.Callback,
    private val keys: HardwareScanKeys,
    private val isTextFieldFocused: () -> Boolean,
) : Window.Callback by delegate {

    override fun dispatchKeyEvent(event: KeyEvent): Boolean = keys.handle(event, isTextFieldFocused()) || delegate.dispatchKeyEvent(event)

    override fun onProvideKeyboardShortcuts(data: MutableList<KeyboardShortcutGroup>?, menu: Menu?, deviceId: Int) {
        delegate.onProvideKeyboardShortcuts(data, menu, deviceId)
    }

    override fun onPointerCaptureChanged(hasCapture: Boolean) {
        delegate.onPointerCaptureChanged(hasCapture)
    }
}

private fun KeyEvent.toScanKey(): ScanKey {
    val character = unicodeChar
    return when {
        keyCode == KeyEvent.KEYCODE_ENTER || keyCode == KeyEvent.KEYCODE_NUMPAD_ENTER -> ScanKey.Enter
        KeyEvent.isModifierKey(keyCode) || keyCode == KeyEvent.KEYCODE_CAPS_LOCK -> ScanKey.Modifier
        character != 0 && !Character.isISOControl(character) && Character.isBmpCodePoint(character) -> ScanKey.Typed(character.toChar())
        else -> ScanKey.Other
    }
}
