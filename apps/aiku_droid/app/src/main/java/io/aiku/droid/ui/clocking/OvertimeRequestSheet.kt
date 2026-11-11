package io.aiku.droid.ui.clocking

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.OvertimeRequest
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ToastKind
import java.time.LocalDate
import java.time.LocalTime

private const val FIELD_TYPE = "overtime_type_id"
private const val FIELD_DATE = "requested_date"
private const val FIELD_START = "requested_start_at"
private const val FIELD_END = "requested_end_at"
private const val FIELD_DURATION = "requested_duration_minutes"
private const val FIELD_DURATION_HOURS = "duration_hours"
private const val FIELD_REASON = "reason"
private const val MAX_DURATION_DIGITS = 2
private const val MINUTES_PER_HOUR = 60
private const val YEARS_AROUND = 1

@Composable
internal fun OvertimeRequestSheet(editing: OvertimeRequest?, onDismiss: () -> Unit) {
    val controller = LocalOvertime.current
    val state by controller.state.collectAsStateWithLifecycle()
    val clockingState by LocalClocking.current.state.collectAsStateWithLifecycle()
    val toasts = LocalToasts.current
    val zone = clockingState.zone
    val today = clockingState.today
    val savedMessage = stringResource(if (editing == null) R.string.clocking_overtime_requested else R.string.clocking_overtime_updated)
    var typeId by remember { mutableStateOf(editing?.overtimeTypeId) }
    var date by remember { mutableStateOf(parseClockingDate(editing?.requestedDate)) }
    var startTime by remember { mutableStateOf(overtimeStartTime(editing?.requestedStartAt, zone)) }
    var hours by remember { mutableStateOf(editing?.requestedDurationMinutes?.let { (it / MINUTES_PER_HOUR).toString() }.orEmpty()) }
    var minutes by remember { mutableStateOf(editing?.requestedDurationMinutes?.let { (it % MINUTES_PER_HOUR).toString() }.orEmpty()) }
    var reason by remember { mutableStateOf(editing?.reason.orEmpty()) }
    var isPickingDate by remember { mutableStateOf(false) }
    var isPickingTime by remember { mutableStateOf(false) }
    var isPickingType by remember { mutableStateOf(false) }
    val submission = state.submission
    val durationError = submission.errorFor(FIELD_DURATION, FIELD_DURATION_HOURS, FIELD_END)

    LaunchedEffect(Unit) { controller.resetSubmission() }
    LaunchedEffect(submission) {
        if (submission == HrSubmission.Saved) {
            toasts.show(savedMessage, ToastKind.Success)
            controller.resetSubmission()
            onDismiss()
        }
    }

    AikuBottomSheet(
        title = stringResource(if (editing == null) R.string.clocking_overtime_request else R.string.clocking_overtime_edit_title),
        onDismiss = onDismiss,
    ) {
        HrPickerField(
            label = stringResource(R.string.clocking_overtime_type),
            value = state.typeOptions.firstOrNull { it.value == typeId }?.label ?: editing?.takeIf { it.overtimeTypeId == typeId }?.overtimeTypeName,
            placeholder = stringResource(R.string.clocking_form_choose_type),
            icon = R.drawable.ic_ui_chevron_down,
            onClick = { isPickingType = true },
            error = submission.errorFor(FIELD_TYPE),
            helper = if (state.typeOptions.isEmpty()) stringResource(R.string.clocking_overtime_no_types) else null,
        )
        HrPickerField(
            label = stringResource(R.string.clocking_overtime_date_worked),
            value = date?.let { clockingDateLabel(it.toString(), HR_DATE_PATTERN) },
            placeholder = stringResource(R.string.clocking_form_choose_date),
            icon = R.drawable.ic_ui_chevron_down,
            onClick = { isPickingDate = true },
            error = submission.errorFor(FIELD_DATE),
        )
        HrPickerField(
            label = stringResource(R.string.clocking_overtime_start_time),
            value = startTime?.let(::hourMinute),
            placeholder = stringResource(R.string.clocking_form_choose_time),
            icon = R.drawable.ic_ui_clock,
            onClick = { isPickingTime = true },
            error = submission.errorFor(FIELD_START),
        )
        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
            HrFieldLabel(text = stringResource(R.string.clocking_overtime_duration))
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth()) {
                HrTextField(
                    label = stringResource(R.string.clocking_overtime_hours),
                    value = hours,
                    onValueChange = { value -> hours = value.filter(Char::isDigit).take(MAX_DURATION_DIGITS) },
                    keyboardType = KeyboardType.Number,
                    modifier = Modifier.weight(1f),
                )
                HrTextField(
                    label = stringResource(R.string.clocking_overtime_minutes),
                    value = minutes,
                    onValueChange = { value -> minutes = value.filter(Char::isDigit).take(MAX_DURATION_DIGITS) },
                    keyboardType = KeyboardType.Number,
                    modifier = Modifier.weight(1f),
                )
            }
            durationError?.let { HrFieldError(message = it) }
        }
        HrTextField(
            label = stringResource(R.string.clocking_overtime_reason),
            value = reason,
            onValueChange = { reason = it },
            placeholder = stringResource(R.string.clocking_overtime_reason_placeholder),
            error = submission.errorFor(FIELD_REASON),
            singleLine = false,
        )
        HrSubmissionProblem(
            submission = submission,
            shownFields = listOf(FIELD_TYPE, FIELD_DATE, FIELD_START, FIELD_END, FIELD_DURATION, FIELD_DURATION_HOURS, FIELD_REASON),
        )
        HrFormButtons(
            submitLabel = stringResource(if (editing == null) R.string.clocking_form_submit else R.string.clocking_form_save),
            isSending = submission == HrSubmission.Sending,
            onSubmit = {
                val input = overtimeRequestInput(
                    typeId = typeId,
                    date = date,
                    startTime = startTime,
                    durationMinutes = overtimeDurationMinutes(hours, minutes),
                    reason = reason,
                    zone = zone,
                    lieuRequestedMinutes = editing?.lieuRequestedMinutes ?: 0,
                )
                if (editing == null) {
                    controller.requestOvertime(input)
                } else {
                    controller.updateOvertime(editing.id, input)
                }
            },
            onCancel = onDismiss,
        )
    }

    if (isPickingType) {
        HrOptionSheet(
            title = stringResource(R.string.clocking_overtime_type),
            options = state.typeOptions.map { HrOption(value = it.value, label = it.label) },
            selected = typeId,
            onPick = { typeId = it },
            onDismiss = { isPickingType = false },
        )
    }
    if (isPickingDate) {
        HrDatePickerDialog(
            initial = date ?: today,
            yearRange = today.year - YEARS_AROUND..today.year + YEARS_AROUND,
            isSelectable = { true },
            onPick = { picked: LocalDate -> date = picked },
            onDismiss = { isPickingDate = false },
        )
    }
    if (isPickingTime) {
        HrTimePickerDialog(
            title = stringResource(R.string.clocking_overtime_start_time),
            initial = startTime ?: LocalTime.now(zone).withSecond(0).withNano(0),
            onPick = { picked -> startTime = picked },
            onDismiss = { isPickingTime = false },
        )
    }
}
