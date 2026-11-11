package io.aiku.droid.ui.clocking

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.ClockingAdjustmentInput
import io.aiku.droid.data.api.Timesheet
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.SegmentedControl
import io.aiku.droid.ui.components.ToastKind
import java.time.LocalDate
import java.time.LocalTime

private const val FIELD_DATE = "date"
private const val FIELD_START = "requested_start_at"
private const val FIELD_END = "requested_end_at"
private const val FIELD_REASON = "reason"
private const val FIELD_ATTACHMENTS = "attachments"

private enum class MissingClocking { ClockIn, ClockOut, Both }

private fun defaultMissingClocking(timesheet: Timesheet?): MissingClocking = when {
    timesheet == null -> MissingClocking.Both
    timesheet.endTimeLocal == null -> MissingClocking.ClockOut
    timesheet.startTimeLocal == null -> MissingClocking.ClockIn
    else -> MissingClocking.Both
}

@Composable
fun ClockingAdjustmentSheet(onDismiss: () -> Unit, timesheet: Timesheet? = null, onSaved: () -> Unit = {}) {
    val controller = LocalClockingAdjustments.current
    val clockingState by LocalClocking.current.state.collectAsStateWithLifecycle()
    val state by controller.state.collectAsStateWithLifecycle()
    val toasts = LocalToasts.current
    val contentResolver = LocalContext.current.contentResolver
    val requestedMessage = stringResource(R.string.clocking_adjustment_requested)
    val today = clockingState.today
    val fixedDate = parseClockingDate(timesheet?.date)
    var chosenDate by remember { mutableStateOf<LocalDate?>(null) }
    var missing by remember { mutableStateOf(defaultMissingClocking(timesheet)) }
    var startTime by remember { mutableStateOf(parseHourMinute(timesheet?.startTimeLocal)) }
    var endTime by remember { mutableStateOf(parseHourMinute(timesheet?.endTimeLocal)) }
    var reason by remember { mutableStateOf("") }
    var files by remember { mutableStateOf(emptyList<PickedAttachment>()) }
    var isPickingDate by remember { mutableStateOf(false) }
    var pickingStart by remember { mutableStateOf<Boolean?>(null) }
    val submission = state.submission
    val earliest = today.minusMonths(1)
    val shownFields = buildList {
        if (fixedDate == null) add(FIELD_DATE)
        if (missing != MissingClocking.ClockOut) add(FIELD_START)
        if (missing != MissingClocking.ClockIn) add(FIELD_END)
        add(FIELD_REASON)
        add(FIELD_ATTACHMENTS)
    }

    LaunchedEffect(Unit) { controller.resetSubmission() }
    LaunchedEffect(submission) {
        if (submission == HrSubmission.Saved) {
            toasts.show(requestedMessage, ToastKind.Success)
            controller.resetSubmission()
            onSaved()
            onDismiss()
        }
    }

    AikuBottomSheet(
        title = fixedDate?.let { stringResource(R.string.clocking_adjustment_title, clockingDateLabel(it.toString(), CLOCKING_DAY_SHORT_PATTERN)) }
            ?: stringResource(R.string.clocking_tab_adjustment),
        subtitle = stringResource(R.string.clocking_adjustment_subtitle),
        onDismiss = onDismiss,
    ) {
        if (fixedDate == null) {
            HrPickerField(
                label = stringResource(R.string.clocking_adjustment_date),
                value = chosenDate?.let { clockingDateLabel(it.toString(), HR_DATE_PATTERN) },
                placeholder = stringResource(R.string.clocking_form_choose_date),
                icon = R.drawable.ic_ui_chevron_down,
                onClick = { isPickingDate = true },
                error = submission.errorFor(FIELD_DATE),
            )
        }
        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
            HrFieldLabel(
                text = stringResource(if (timesheet == null) R.string.clocking_adjustment_missing else R.string.clocking_adjustment_to_correct),
            )
            SegmentedControl(
                options = listOf(
                    stringResource(R.string.clocking_clock_in),
                    stringResource(R.string.clocking_clock_out),
                    stringResource(R.string.clocking_adjustment_both),
                ),
                selectedIndex = missing.ordinal,
                onSelect = { missing = MissingClocking.entries[it] },
            )
        }
        if (missing != MissingClocking.ClockOut) {
            HrPickerField(
                label = stringResource(R.string.clocking_adjustment_time_in),
                value = startTime?.let(::hourMinute),
                placeholder = stringResource(R.string.clocking_form_choose_time),
                icon = R.drawable.ic_ui_clock,
                onClick = { pickingStart = true },
                error = submission.errorFor(FIELD_START),
            )
        }
        if (missing != MissingClocking.ClockIn) {
            HrPickerField(
                label = stringResource(R.string.clocking_adjustment_time_out),
                value = endTime?.let(::hourMinute),
                placeholder = stringResource(R.string.clocking_form_choose_time),
                icon = R.drawable.ic_ui_clock,
                onClick = { pickingStart = false },
                error = submission.errorFor(FIELD_END),
            )
        }
        HrTextField(
            label = stringResource(R.string.clocking_adjustment_reason),
            value = reason,
            onValueChange = { reason = it },
            placeholder = stringResource(R.string.clocking_adjustment_reason_placeholder),
            error = submission.errorFor(FIELD_REASON),
            singleLine = false,
        )
        HrAttachmentPicker(
            label = stringResource(R.string.clocking_attachments_label),
            addLabel = stringResource(R.string.clocking_attachments_add),
            files = files,
            onFilesChange = { files = it },
            error = submission.errorFor(FIELD_ATTACHMENTS),
        )
        HrSubmissionProblem(submission = submission, shownFields = shownFields)
        HrFormButtons(
            submitLabel = stringResource(R.string.clocking_adjustment_request),
            isSending = submission == HrSubmission.Sending,
            onSubmit = {
                controller.requestAdjustment(
                    ClockingAdjustmentInput(
                        date = fixedDate ?: chosenDate,
                        timesheetId = timesheet?.id,
                        requestedStartAt = startTime?.takeIf { missing != MissingClocking.ClockOut }?.let(::hourMinute),
                        requestedEndAt = endTime?.takeIf { missing != MissingClocking.ClockIn }?.let(::hourMinute),
                        reason = reason.trim(),
                        attachments = files.toUploadFiles(contentResolver),
                    ),
                )
            },
            onCancel = onDismiss,
        )
    }

    if (isPickingDate) {
        HrDatePickerDialog(
            initial = chosenDate ?: today,
            yearRange = earliest.year..today.year,
            isSelectable = { !it.isBefore(earliest) && !it.isAfter(today) },
            onPick = { chosenDate = it },
            onDismiss = { isPickingDate = false },
        )
    }
    pickingStart?.let { isStart ->
        HrTimePickerDialog(
            title = stringResource(if (isStart) R.string.clocking_adjustment_time_in else R.string.clocking_adjustment_time_out),
            initial = (if (isStart) startTime else endTime) ?: LocalTime.now(clockingState.zone).withSecond(0).withNano(0),
            onPick = { picked ->
                if (isStart) {
                    startTime = picked
                } else {
                    endTime = picked
                }
            },
            onDismiss = { pickingStart = null },
        )
    }
}
