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
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.LeaveRequestInput
import io.aiku.droid.data.api.LeaveSession
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.SegmentedControl
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.ToggleRow
import java.time.LocalDate

private const val FIELD_TYPE = "type"
private const val FIELD_START = "start_date"
private const val FIELD_END = "end_date"
private const val FIELD_DURATION = "duration_days"
private const val FIELD_SESSION = "session"
private const val FIELD_REASON = "reason"
private const val FIELD_ATTACHMENTS = "attachments"
private const val YEARS_AHEAD = 2

private enum class LeaveDateField { Start, End }

@Composable
internal fun LeaveRequestSheet(onDismiss: () -> Unit) {
    val controller = LocalLeave.current
    val state by controller.state.collectAsStateWithLifecycle()
    val clockingState by LocalClocking.current.state.collectAsStateWithLifecycle()
    val toasts = LocalToasts.current
    val locale = LocalConfiguration.current.locales[0]
    val contentResolver = LocalContext.current.contentResolver
    val requestedMessage = stringResource(R.string.clocking_leave_requested)
    val typeOptions = state.overview?.typeOptions.orEmpty()
    val holidays = remember(state.overview) { state.overview?.holidays.orEmpty().mapNotNull { parseClockingDate(it.date) }.toSet() }
    val today = clockingState.today
    var typeCode by remember { mutableStateOf<String?>(null) }
    var startDate by remember { mutableStateOf<LocalDate?>(null) }
    var endDate by remember { mutableStateOf<LocalDate?>(null) }
    var isHalfDay by remember { mutableStateOf(false) }
    var isAfternoon by remember { mutableStateOf(false) }
    var reason by remember { mutableStateOf("") }
    var files by remember { mutableStateOf(emptyList<PickedAttachment>()) }
    var pickingDate by remember { mutableStateOf<LeaveDateField?>(null) }
    var isPickingType by remember { mutableStateOf(false) }
    val selectedType = typeOptions.firstOrNull { it.code == typeCode }
    val isMedical = selectedType?.isMedical == true
    val submission = state.submission
    val shownFields = buildList {
        addAll(listOf(FIELD_TYPE, FIELD_START, FIELD_END, FIELD_DURATION, FIELD_REASON))
        if (isHalfDay) add(FIELD_SESSION)
        if (isMedical) add(FIELD_ATTACHMENTS)
    }

    LaunchedEffect(Unit) { controller.resetSubmission() }
    LaunchedEffect(submission) {
        if (submission == HrSubmission.Saved) {
            toasts.show(requestedMessage, ToastKind.Success)
            controller.resetSubmission()
            onDismiss()
        }
    }

    AikuBottomSheet(title = stringResource(R.string.clocking_leave_request), onDismiss = onDismiss) {
        HrPickerField(
            label = stringResource(R.string.clocking_leave_type),
            value = selectedType?.let { it.label.ifBlank { it.code } },
            placeholder = stringResource(R.string.clocking_form_choose_type),
            icon = R.drawable.ic_ui_chevron_down,
            onClick = { isPickingType = true },
            error = submission.errorFor(FIELD_TYPE),
            helper = when {
                typeOptions.isEmpty() -> stringResource(R.string.clocking_leave_no_types)
                else -> selectedType?.maxDaysPerYear?.let { maximum ->
                    pluralStringResource(R.plurals.clocking_leave_max_days, dayQuantity(maximum), formatDays(maximum, locale))
                }
            },
        )
        ToggleRow(
            label = stringResource(R.string.clocking_leave_half_day_toggle),
            checked = isHalfDay,
            onCheckedChange = { isHalfDay = it },
        )
        if (isHalfDay) {
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                SegmentedControl(
                    options = listOf(stringResource(R.string.clocking_leave_morning), stringResource(R.string.clocking_leave_afternoon)),
                    selectedIndex = if (isAfternoon) 1 else 0,
                    onSelect = { isAfternoon = it == 1 },
                )
                submission.errorFor(FIELD_SESSION)?.let { HrFieldError(message = it) }
            }
        }
        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth()) {
                HrPickerField(
                    label = stringResource(if (isHalfDay) R.string.clocking_leave_date else R.string.clocking_leave_start),
                    value = startDate?.let { clockingDateLabel(it.toString(), CLOCKING_DAY_SHORT_PATTERN) },
                    placeholder = stringResource(R.string.clocking_form_choose_date),
                    icon = R.drawable.ic_ui_chevron_down,
                    onClick = { pickingDate = LeaveDateField.Start },
                    error = if (isHalfDay) submission.errorFor(FIELD_START, FIELD_END) else submission.errorFor(FIELD_START),
                    modifier = Modifier.weight(1f),
                )
                if (!isHalfDay) {
                    HrPickerField(
                        label = stringResource(R.string.clocking_leave_end),
                        value = endDate?.let { clockingDateLabel(it.toString(), CLOCKING_DAY_SHORT_PATTERN) },
                        placeholder = stringResource(R.string.clocking_form_choose_date),
                        icon = R.drawable.ic_ui_chevron_down,
                        onClick = { pickingDate = LeaveDateField.End },
                        error = submission.errorFor(FIELD_END),
                        modifier = Modifier.weight(1f),
                    )
                }
            }
            HrFieldHelper(text = stringResource(R.string.clocking_leave_holidays_hint))
            submission.errorFor(FIELD_DURATION)?.let { HrFieldError(message = it) }
        }
        HrTextField(
            label = stringResource(R.string.clocking_leave_reason),
            value = reason,
            onValueChange = { reason = it },
            error = submission.errorFor(FIELD_REASON),
            singleLine = false,
        )
        if (isMedical) {
            HrAttachmentPicker(
                label = stringResource(R.string.clocking_leave_certificate),
                addLabel = stringResource(R.string.clocking_leave_upload_certificate),
                files = files,
                onFilesChange = { files = it },
                error = submission.errorFor(FIELD_ATTACHMENTS),
            )
        }
        HrSubmissionProblem(submission = submission, shownFields = shownFields)
        HrFormButtons(
            submitLabel = stringResource(R.string.clocking_form_submit),
            isSending = submission == HrSubmission.Sending,
            onSubmit = {
                controller.requestLeave(
                    LeaveRequestInput(
                        type = typeCode,
                        startDate = startDate,
                        endDate = if (isHalfDay) startDate else endDate,
                        isHalfDay = isHalfDay,
                        session = when {
                            !isHalfDay -> LeaveSession.FULL
                            isAfternoon -> LeaveSession.AFTERNOON
                            else -> LeaveSession.MORNING
                        },
                        reason = reason.trim(),
                        attachments = if (isMedical) files.toUploadFiles(contentResolver) else emptyList(),
                    ),
                )
            },
            onCancel = onDismiss,
        )
    }

    if (isPickingType) {
        HrOptionSheet(
            title = stringResource(R.string.clocking_leave_type),
            options = typeOptions.map { HrOption(value = it.code, label = it.label.ifBlank { it.code }) },
            selected = typeCode,
            onPick = { typeCode = it },
            onDismiss = { isPickingType = false },
        )
    }
    pickingDate?.let { field ->
        val earliest = if (field == LeaveDateField.End) startDate ?: today else today
        HrDatePickerDialog(
            initial = (if (field == LeaveDateField.Start) startDate else endDate ?: startDate)?.takeIf { !it.isBefore(earliest) },
            yearRange = today.year..today.year + YEARS_AHEAD,
            isSelectable = { date -> !date.isBefore(earliest) && date !in holidays },
            onPick = { picked ->
                if (field == LeaveDateField.Start) {
                    startDate = picked
                    if (endDate?.isBefore(picked) != false) {
                        endDate = picked
                    }
                } else {
                    endDate = picked
                }
            },
            onDismiss = { pickingDate = null },
        )
    }
}
