package io.aiku.droid.ui.scan

import android.content.Context
import android.hardware.input.InputManager
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.view.InputDevice
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.settings.ScanMode
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.theme.LocalAikuColors

enum class ScannerReadyReason(@StringRes val body: Int) {
    ScannerChosen(R.string.scan_ready_scanner_chosen),
    ScannerConnected(R.string.scan_ready_scanner_connected),
}

fun scannerReadyReason(mode: ScanMode, hasExternalScanner: Boolean): ScannerReadyReason? = when (mode) {
    ScanMode.Automatic -> ScannerReadyReason.ScannerConnected.takeIf { hasExternalScanner }
    ScanMode.Scanner -> ScannerReadyReason.ScannerChosen
    ScanMode.Camera -> null
}

data class KeyboardDevice(val isVirtual: Boolean, val isKeyboard: Boolean, val isAlphabetic: Boolean, val isExternal: Boolean?)

val KeyboardDevice.isExternalScanner: Boolean get() = !isVirtual && isKeyboard && isAlphabetic && isExternal != false

private fun InputDevice.keyboardDevice(): KeyboardDevice = KeyboardDevice(
    isVirtual = isVirtual,
    isKeyboard = supportsSource(InputDevice.SOURCE_KEYBOARD),
    isAlphabetic = keyboardType == InputDevice.KEYBOARD_TYPE_ALPHABETIC,
    isExternal = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) isExternal else null,
)

private fun hasExternalScanner(): Boolean = runCatching {
    InputDevice.getDeviceIds().any { id -> InputDevice.getDevice(id)?.keyboardDevice()?.isExternalScanner == true }
}.getOrDefault(false)

@Composable
private fun rememberHasExternalScanner(): Boolean {
    val context = LocalContext.current
    var hasScanner by remember { mutableStateOf(hasExternalScanner()) }

    DisposableEffect(context) {
        val inputManager = context.inputManager()
        val listener = object : InputManager.InputDeviceListener {
            override fun onInputDeviceAdded(deviceId: Int) {
                hasScanner = hasExternalScanner()
            }

            override fun onInputDeviceRemoved(deviceId: Int) {
                hasScanner = hasExternalScanner()
            }

            override fun onInputDeviceChanged(deviceId: Int) {
                hasScanner = hasExternalScanner()
            }
        }
        inputManager?.registerInputDeviceListener(listener, Handler(Looper.getMainLooper()))
        hasScanner = hasExternalScanner()
        onDispose { inputManager?.unregisterInputDeviceListener(listener) }
    }

    return hasScanner
}

private fun Context.inputManager(): InputManager? = runCatching { getSystemService(InputManager::class.java) }.getOrNull()

data class ScanCamera(val scannerReady: ScannerReadyReason?, val useCamera: () -> Unit) {
    val isOn: Boolean get() = scannerReady == null
}

@Composable
fun rememberScanCamera(): ScanCamera {
    val preferences by LocalScanController.current.preferences.collectAsStateWithLifecycle()
    val hasExternalScanner = rememberHasExternalScanner()
    var isCameraChosen by rememberSaveable { mutableStateOf(false) }

    return ScanCamera(
        scannerReady = scannerReadyReason(preferences.mode, hasExternalScanner).takeUnless { isCameraChosen },
        useCamera = { isCameraChosen = true },
    )
}

@Composable
fun ScannerReadyNotice(reason: ScannerReadyReason, onUseCamera: () -> Unit, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .padding(24.dp)
            .widthIn(max = 420.dp)
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .padding(20.dp)
            .semantics { liveRegion = LiveRegionMode.Polite },
    ) {
        Icon(painter = painterResource(R.drawable.ic_ui_scanner), contentDescription = null, tint = colors.link, modifier = Modifier.size(32.dp))
        Text(
            text = stringResource(R.string.scan_ready_title),
            fontSize = 17.sp,
            fontWeight = FontWeight.Bold,
            color = colors.text,
            textAlign = TextAlign.Center,
        )
        Text(
            text = stringResource(reason.body),
            fontSize = 14.sp,
            lineHeight = 20.sp,
            color = colors.textSecondary,
            textAlign = TextAlign.Center,
        )
        AikuButton(
            text = stringResource(R.string.scan_ready_use_camera),
            onClick = onUseCamera,
            style = ButtonStyle.Scan,
            icon = R.drawable.ic_ui_camera,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}
