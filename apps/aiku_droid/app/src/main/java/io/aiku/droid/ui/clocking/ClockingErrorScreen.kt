package io.aiku.droid.ui.clocking

import android.content.ActivityNotFoundException
import android.content.Intent
import android.net.Uri
import android.provider.Settings
import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.ClockScanQrRoute
import io.aiku.droid.ui.navigation.ClockingRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.LocalAikuColors

internal enum class ClockingErrorKind(
    val key: String,
    @StringRes val kicker: Int,
    @DrawableRes val icon: Int,
    val tone: Tone,
    @StringRes val title: Int,
    @StringRes val body: Int,
) {
    QrInvalid("qr_invalid", R.string.clocking_error_kicker_qr, R.drawable.ic_ui_qr, Tone.Danger, R.string.clocking_error_qr_title, R.string.clocking_error_qr_body),
    LocationBlocked(
        "location_blocked",
        R.string.clocking_error_kicker_qr,
        R.drawable.ic_ui_location_off,
        Tone.Warn,
        R.string.clocking_error_location_title,
        R.string.clocking_error_location_body,
    ),
    NoPin("no_pin", R.string.clocking_error_kicker_pin, R.drawable.ic_ui_pin, Tone.Neutral, R.string.clocking_error_no_pin_title, R.string.clocking_error_no_pin_body),
    Offline("offline", R.string.clocking_error_kicker_qr, R.drawable.ic_ui_wifi_off, Tone.Warn, R.string.clocking_error_offline_title, R.string.clocking_error_offline_body),
    CameraBlocked(
        "camera_blocked",
        R.string.clocking_error_kicker_qr,
        R.drawable.ic_ui_camera,
        Tone.Neutral,
        R.string.clocking_error_camera_title,
        R.string.clocking_error_camera_body,
    ),
    ;

    companion object {
        fun fromKey(key: String): ClockingErrorKind = entries.firstOrNull { it.key == key } ?: QrInvalid
    }
}

@Composable
fun ClockingErrorScreen(kind: String, message: String? = null, scanned: String? = null) {
    ClockingErrorScreen(error = ClockingErrorKind.fromKey(kind), message = message, scanned = scanned)
}

@Composable
internal fun ClockingErrorScreen(error: ClockingErrorKind, message: String?, scanned: String?) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val clocking = LocalClocking.current
    val state by clocking.state.collectAsStateWithLifecycle()
    val context = LocalContext.current
    var showWorkingHours by remember { mutableStateOf(false) }
    val backToClocking = { navigator.switchSection(ClockingRoute) }
    val openCameraWithoutLocation = {
        if (state.status?.hasShifts == true) {
            showWorkingHours = true
        } else {
            clocking.prepareScan(ClockingScanSetup())
            navigator.switchSection(ClockScanQrRoute)
        }
    }

    AppScreen(
        header = { AppHeader(title = stringResource(R.string.clocking_title), kicker = stringResource(error.kicker)) },
        activeTab = MainTab.Menu,
        showBar = true,
        contentPadding = PaddingValues(start = 20.dp, end = 20.dp, top = 32.dp, bottom = 16.dp),
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(14.dp),
            modifier = Modifier.fillMaxWidth(),
        ) {
            ClockingIconCircle(icon = error.icon, tone = error.tone)
            Text(text = stringResource(error.title), fontSize = 22.sp, fontWeight = FontWeight.Bold, color = colors.text, textAlign = TextAlign.Center)
            Text(
                text = stringResource(error.body),
                fontSize = 15.sp,
                lineHeight = 22.sp,
                color = colors.textTertiary,
                textAlign = TextAlign.Center,
                modifier = Modifier.widthIn(max = 320.dp),
            )
            if (error == ClockingErrorKind.QrInvalid) {
                if (message == null) {
                    ListGroup(modifier = Modifier.fakeData()) {
                        ClockingErrorDetailRow(label = stringResource(R.string.clocking_error_message), value = CLOCKING_ERROR_MESSAGE_SAMPLE)
                        RowDivider()
                        ClockingErrorDetailRow(label = stringResource(R.string.clocking_error_scanned), value = CLOCKING_ERROR_SCANNED_SAMPLE)
                    }
                } else {
                    ListGroup {
                        ClockingErrorDetailRow(label = stringResource(R.string.clocking_error_message), value = message)
                        if (scanned != null) {
                            RowDivider()
                            ClockingErrorDetailRow(label = stringResource(R.string.clocking_error_scanned), value = scanned)
                        }
                    }
                }
            }
            Column(verticalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth().padding(top = 8.dp)) {
                when (error) {
                    ClockingErrorKind.QrInvalid -> {
                        ClockingErrorPrimaryButton(
                            text = stringResource(R.string.clocking_error_open_camera_again),
                            onClick = {
                                clocking.restartScan()
                                navigator.switchSection(ClockScanQrRoute)
                            },
                        )
                        ClockingErrorSecondaryButton(text = stringResource(R.string.clocking_error_back_to_clocking), onClick = backToClocking)
                    }
                    ClockingErrorKind.LocationBlocked -> {
                        ClockingErrorPrimaryButton(text = stringResource(R.string.clocking_error_open_camera_anyway), onClick = openCameraWithoutLocation)
                        ClockingErrorSecondaryButton(text = stringResource(R.string.clocking_error_try_again), onClick = backToClocking)
                    }
                    ClockingErrorKind.NoPin -> {
                        ClockingErrorPrimaryButton(text = stringResource(R.string.clocking_error_back_to_clocking), onClick = backToClocking)
                    }
                    ClockingErrorKind.Offline -> {
                        ClockingErrorPrimaryButton(text = stringResource(R.string.clocking_error_try_again), onClick = backToClocking)
                    }
                    ClockingErrorKind.CameraBlocked -> {
                        ClockingErrorPrimaryButton(
                            text = stringResource(R.string.clocking_error_open_settings),
                            onClick = {
                                try {
                                    context.startActivity(
                                        Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.fromParts("package", context.packageName, null)),
                                    )
                                } catch (exception: ActivityNotFoundException) {
                                    context.startActivity(Intent(Settings.ACTION_SETTINGS))
                                }
                            },
                        )
                        ClockingErrorSecondaryButton(text = stringResource(R.string.clocking_error_back_to_clocking), onClick = backToClocking)
                    }
                }
            }
        }
    }

    if (showWorkingHours) {
        WorkingHoursSheet(
            onDismiss = { showWorkingHours = false },
            onConfirm = { workScheduleId ->
                showWorkingHours = false
                clocking.prepareScan(ClockingScanSetup(workScheduleId = workScheduleId))
                navigator.switchSection(ClockScanQrRoute)
            },
        )
    }
}

@Composable
private fun ClockingErrorDetailRow(label: String, value: String) {
    val colors = LocalAikuColors.current

    Row(horizontalArrangement = Arrangement.spacedBy(12.dp), modifier = Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 10.dp)) {
        Text(text = label, fontSize = 13.sp, color = colors.textTertiary)
        Text(
            text = value,
            fontSize = 13.sp,
            fontWeight = FontWeight.SemiBold,
            color = colors.text,
            textAlign = TextAlign.End,
            maxLines = 4,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f),
        )
    }
}

@Composable
private fun ClockingErrorPrimaryButton(text: String, onClick: () -> Unit) {
    AikuButton(text = text, onClick = onClick, height = 52.dp, modifier = Modifier.fillMaxWidth())
}

@Composable
private fun ClockingErrorSecondaryButton(text: String, onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    AikuButton(
        text = text,
        onClick = onClick,
        style = ButtonStyle.Outline,
        height = 52.dp,
        modifier = Modifier.fillMaxWidth().clip(ControlShape).background(colors.surface),
    )
}
