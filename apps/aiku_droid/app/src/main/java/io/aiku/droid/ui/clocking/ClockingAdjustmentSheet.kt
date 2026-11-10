package io.aiku.droid.ui.clocking

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ToastKind

@Composable
fun ClockingAdjustmentSheet(date: String, onDismiss: () -> Unit) {
    ClockingAdjustmentSheet(date = date, onDismiss = onDismiss, onRequested = {})
}

@Composable
internal fun ClockingAdjustmentSheet(date: String, onDismiss: () -> Unit, onRequested: () -> Unit) {
    val toasts = LocalToasts.current
    val requestedMessage = stringResource(R.string.clocking_adjustment_requested)
    val sample = ClockingAdjustmentRequestSample
    var timeLeft by remember { mutableStateOf(sample.time) }
    var reason by remember { mutableStateOf(sample.reason) }

    AikuBottomSheet(
        title = stringResource(R.string.clocking_adjustment_title, clockingDateLabel(date, CLOCKING_DAY_SHORT_PATTERN)),
        subtitle = stringResource(R.string.clocking_adjustment_subtitle),
        onDismiss = onDismiss,
    ) {
        ClockingField(label = stringResource(R.string.clocking_adjustment_missing), value = sample.missing)
        ClockingField(
            label = stringResource(R.string.clocking_adjustment_time),
            value = timeLeft,
            onValueChange = { timeLeft = it },
            helper = stringResource(R.string.clocking_adjustment_shift_ended, sample.shiftEnd),
        )
        ClockingField(
            label = stringResource(R.string.clocking_adjustment_reason),
            value = reason,
            onValueChange = { reason = it },
        )
        AikuButton(
            text = stringResource(R.string.clocking_adjustment_request),
            onClick = {
                toasts.show(requestedMessage, ToastKind.Success)
                onDismiss()
                onRequested()
            },
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )
        AikuButton(
            text = stringResource(R.string.dialog_cancel),
            onClick = onDismiss,
            style = ButtonStyle.Outline,
            height = 44.dp,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}
