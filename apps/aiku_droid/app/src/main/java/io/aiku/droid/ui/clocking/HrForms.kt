package io.aiku.droid.ui.clocking

import androidx.annotation.DrawableRes
import androidx.compose.foundation.ScrollState
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsFocusedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDefaults
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.SelectableDates
import androidx.compose.material3.Text
import androidx.compose.material3.TimePicker
import androidx.compose.material3.TimePickerDefaults
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.material3.rememberTimePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.snapshotFlow
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import io.aiku.droid.R
import io.aiku.droid.data.api.HrStatus
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.animateFocusProgress
import io.aiku.droid.ui.components.focusHalo
import io.aiku.droid.ui.theme.Inter
import io.aiku.droid.ui.theme.LocalAikuColors
import java.text.NumberFormat
import java.time.Instant
import java.time.LocalDate
import java.time.LocalTime
import java.time.ZoneOffset
import java.time.format.DateTimeFormatter
import java.util.Locale
import kotlinx.coroutines.flow.first

internal const val HR_DATE_PATTERN = "EEE dd MMM yyyy"
private const val RANGE_DAY_PATTERN = "dd"
private const val RANGE_DAY_MONTH_PATTERN = "dd MMM"
private const val RANGE_FULL_PATTERN = "dd MMM yyyy"
private const val LOAD_MORE_DISTANCE_DP = 320
private const val MAX_DAY_FRACTION_DIGITS = 1
private val HrFieldShape = RoundedCornerShape(6.dp)
private val HrDialogShape = RoundedCornerShape(14.dp)
private val HourMinute: DateTimeFormatter = DateTimeFormatter.ofPattern("HH:mm")

internal fun HrSubmission.errorFor(vararg fields: String): String? =
    (this as? HrSubmission.Rejected)?.fieldErrors?.entries
        ?.firstOrNull { (key, _) -> fields.any { field -> key.matchesField(field) } }
        ?.value

private fun String.matchesField(field: String): Boolean = this == field || startsWith("$field.")

internal fun hrStatusTone(status: String): Tone = when (status) {
    HrStatus.APPROVED -> Tone.Success
    HrStatus.PENDING -> Tone.Warn
    HrStatus.REJECTED -> Tone.Danger
    HrStatus.RECORDED -> Tone.Primary
    else -> Tone.Neutral
}

internal fun formatDays(days: Double, locale: Locale): String =
    NumberFormat.getNumberInstance(locale).apply { maximumFractionDigits = MAX_DAY_FRACTION_DIGITS }.format(days)

internal fun dayQuantity(days: Double): Int = if (days == Math.floor(days)) days.toInt() else Int.MAX_VALUE

internal fun hourMinute(time: LocalTime): String = time.format(HourMinute)

internal fun parseHourMinute(value: String?): LocalTime? = value?.let { runCatching { LocalTime.parse(it) }.getOrNull() }

private fun LocalDate.utcMillis(): Long = atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli()

private fun utcDateOf(millis: Long): LocalDate = Instant.ofEpochMilli(millis).atZone(ZoneOffset.UTC).toLocalDate()

@Composable
internal fun hrDateRange(startIso: String, endIso: String): String {
    val locale = LocalConfiguration.current.locales[0]
    val start = parseClockingDate(startIso)
    val end = parseClockingDate(endIso)
    if (start == null || end == null || start == end) {
        return clockingDateLabel(startIso, HR_DATE_PATTERN)
    }
    val startPattern = when {
        start.year != end.year -> RANGE_FULL_PATTERN
        start.month != end.month -> RANGE_DAY_MONTH_PATTERN
        else -> RANGE_DAY_PATTERN
    }
    return stringResource(
        R.string.clocking_range,
        start.format(DateTimeFormatter.ofPattern(startPattern, locale)),
        end.format(DateTimeFormatter.ofPattern(RANGE_FULL_PATTERN, locale)),
    )
}

@Composable
internal fun HrFieldLabel(text: String) {
    Text(text = text, fontSize = 14.sp, fontWeight = FontWeight.Medium, color = LocalAikuColors.current.textSecondary)
}

@Composable
internal fun HrFieldError(message: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        modifier = modifier.semantics { liveRegion = LiveRegionMode.Polite },
    ) {
        Icon(painter = painterResource(R.drawable.ic_alert_circle), contentDescription = null, tint = colors.danger, modifier = Modifier.padding(top = 1.dp).size(16.dp))
        Text(text = message, fontSize = 13.sp, lineHeight = 18.sp, color = colors.danger)
    }
}

@Composable
internal fun HrFieldHelper(text: String) {
    Text(text = text, fontSize = 13.sp, lineHeight = 18.sp, color = LocalAikuColors.current.textTertiary)
}

@Composable
internal fun HrTextField(
    label: String,
    value: String,
    onValueChange: (String) -> Unit,
    modifier: Modifier = Modifier,
    placeholder: String? = null,
    error: String? = null,
    singleLine: Boolean = true,
    keyboardType: KeyboardType = KeyboardType.Text,
) {
    val colors = LocalAikuColors.current
    val interactionSource = remember { MutableInteractionSource() }
    val isFocused by interactionSource.collectIsFocusedAsState()
    val focusProgress = animateFocusProgress(isFocused && error == null)
    val borderColor = when {
        error != null -> colors.danger
        else -> lerp(colors.borderStrong, colors.focus, focusProgress)
    }

    Column(modifier = modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(6.dp)) {
        HrFieldLabel(text = label)
        BasicTextField(
            value = value,
            onValueChange = onValueChange,
            singleLine = singleLine,
            minLines = if (singleLine) 1 else 2,
            textStyle = TextStyle(fontFamily = Inter, fontSize = 16.sp, lineHeight = 22.sp, color = colors.text),
            cursorBrush = SolidColor(colors.primary),
            keyboardOptions = KeyboardOptions(keyboardType = keyboardType),
            interactionSource = interactionSource,
            modifier = Modifier.fillMaxWidth().semantics { contentDescription = label },
            decorationBox = { inner ->
                Box(
                    contentAlignment = Alignment.CenterStart,
                    modifier = Modifier
                        .fillMaxWidth()
                        .heightIn(min = 48.dp)
                        .focusHalo(progress = focusProgress, color = colors.focus, cornerRadius = 6.dp)
                        .clip(HrFieldShape)
                        .background(colors.surface)
                        .border(1.dp, borderColor, HrFieldShape)
                        .padding(horizontal = 12.dp, vertical = 12.dp),
                ) {
                    if (value.isEmpty() && placeholder != null) {
                        Text(text = placeholder, fontSize = 16.sp, lineHeight = 22.sp, color = colors.textQuaternary)
                    }
                    inner()
                }
            },
        )
        error?.let { HrFieldError(message = it) }
    }
}

@Composable
internal fun HrPickerField(
    label: String,
    value: String?,
    placeholder: String,
    @DrawableRes icon: Int,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    error: String? = null,
    helper: String? = null,
) {
    val colors = LocalAikuColors.current

    Column(modifier = modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(6.dp)) {
        HrFieldLabel(text = label)
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(min = 48.dp)
                .clip(HrFieldShape)
                .background(colors.surface)
                .border(1.dp, if (error != null) colors.danger else colors.borderStrong, HrFieldShape)
                .clickable(role = Role.Button, onClickLabel = label, onClick = onClick)
                .padding(horizontal = 12.dp),
        ) {
            Text(
                text = value ?: placeholder,
                fontSize = 16.sp,
                color = if (value == null) colors.textQuaternary else colors.text,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
                modifier = Modifier.weight(1f),
            )
            Icon(painter = painterResource(icon), contentDescription = null, tint = colors.textTertiary, modifier = Modifier.size(18.dp))
        }
        helper?.let { HrFieldHelper(text = it) }
        error?.let { HrFieldError(message = it) }
    }
}

internal data class HrOption<T>(val value: T, val label: String)

@Composable
internal fun <T> HrOptionSheet(title: String, options: List<HrOption<T>>, selected: T?, onPick: (T) -> Unit, onDismiss: () -> Unit) {
    AikuBottomSheet(title = title, onDismiss = onDismiss) {
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.selectableGroup()) {
            options.forEach { option ->
                ChoiceRow(
                    title = option.label,
                    selected = option.value == selected,
                    onClick = {
                        onPick(option.value)
                        onDismiss()
                    },
                )
            }
        }
    }
}

@Composable
internal fun HrSubmissionProblem(submission: HrSubmission, shownFields: List<String>, modifier: Modifier = Modifier) {
    val messages = when (submission) {
        is HrSubmission.Rejected -> {
            val unshown = submission.fieldErrors.filterKeys { key -> shownFields.none { key.matchesField(it) } }.values.toList()
            when {
                unshown.isNotEmpty() -> unshown
                submission.fieldErrors.isEmpty() -> listOf(submission.message.ifBlank { stringResource(R.string.clocking_form_refused_fallback) })
                else -> emptyList()
            }
        }
        is HrSubmission.Failed -> listOf(
            stringResource(if (submission.problem == ClockingProblem.Offline) R.string.clocking_form_failed_offline else R.string.clocking_form_failed_server),
        )
        else -> emptyList()
    }

    if (messages.isNotEmpty()) {
        InlineAlert(
            title = messages.first(),
            body = messages.drop(1).joinToString(separator = "\n").ifEmpty { null },
            tone = AlertTone.Danger,
            modifier = modifier,
        )
    }
}

@Composable
internal fun HrFormButtons(submitLabel: String, isSending: Boolean, onSubmit: () -> Unit, onCancel: () -> Unit) {
    AikuButton(text = submitLabel, onClick = onSubmit, isLoading = isSending, height = 52.dp, modifier = Modifier.fillMaxWidth())
    AikuButton(text = stringResource(R.string.dialog_cancel), onClick = onCancel, style = ButtonStyle.Outline, height = 44.dp, modifier = Modifier.fillMaxWidth())
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
internal fun HrDatePickerDialog(
    initial: LocalDate?,
    yearRange: IntRange,
    isSelectable: (LocalDate) -> Boolean,
    onPick: (LocalDate) -> Unit,
    onDismiss: () -> Unit,
) {
    val colors = LocalAikuColors.current
    val currentIsSelectable by rememberUpdatedState(isSelectable)
    val selectableDates = remember(yearRange) {
        object : SelectableDates {
            override fun isSelectableDate(utcTimeMillis: Long): Boolean = currentIsSelectable(utcDateOf(utcTimeMillis))
            override fun isSelectableYear(year: Int): Boolean = year in yearRange
        }
    }
    val shownYears = listOfNotNull(yearRange.first, yearRange.last, initial?.year, LocalDate.now().year)
    val state = rememberDatePickerState(
        initialSelectedDateMillis = initial?.utcMillis(),
        yearRange = shownYears.min()..shownYears.max(),
        selectableDates = selectableDates,
    )
    val pickerColors = DatePickerDefaults.colors(
        containerColor = colors.surface,
        titleContentColor = colors.textTertiary,
        headlineContentColor = colors.text,
        weekdayContentColor = colors.textTertiary,
        subheadContentColor = colors.textSecondary,
        navigationContentColor = colors.textSecondary,
        yearContentColor = colors.text,
        disabledYearContentColor = colors.faint,
        currentYearContentColor = colors.link,
        selectedYearContentColor = colors.onPrimary,
        selectedYearContainerColor = colors.primary,
        dayContentColor = colors.text,
        disabledDayContentColor = colors.faint,
        selectedDayContentColor = colors.onPrimary,
        selectedDayContainerColor = colors.primary,
        todayContentColor = colors.link,
        todayDateBorderColor = colors.link,
        dividerColor = colors.divider,
    )

    DatePickerDialog(
        onDismissRequest = onDismiss,
        confirmButton = {
            HrDialogButton(
                text = stringResource(R.string.clocking_form_choose),
                onClick = {
                    state.selectedDateMillis?.let { onPick(utcDateOf(it)) }
                    onDismiss()
                },
            )
        },
        dismissButton = { HrDialogButton(text = stringResource(R.string.dialog_cancel), onClick = onDismiss) },
        colors = pickerColors,
    ) {
        DatePicker(state = state, colors = pickerColors)
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
internal fun HrTimePickerDialog(title: String, initial: LocalTime, onPick: (LocalTime) -> Unit, onDismiss: () -> Unit) {
    val colors = LocalAikuColors.current
    val state = rememberTimePickerState(initialHour = initial.hour, initialMinute = initial.minute, is24Hour = true)

    Dialog(onDismissRequest = onDismiss) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(16.dp),
            modifier = Modifier
                .fillMaxWidth()
                .shadow(elevation = 20.dp, shape = HrDialogShape, ambientColor = colors.shadow, spotColor = colors.shadow)
                .clip(HrDialogShape)
                .background(colors.surface)
                .padding(20.dp),
        ) {
            Text(text = title, fontSize = 18.sp, fontWeight = FontWeight.Bold, color = colors.text, modifier = Modifier.fillMaxWidth())
            TimePicker(
                state = state,
                colors = TimePickerDefaults.colors(
                    clockDialColor = colors.surfaceMuted,
                    clockDialSelectedContentColor = colors.onPrimary,
                    clockDialUnselectedContentColor = colors.text,
                    selectorColor = colors.primary,
                    containerColor = colors.surface,
                    timeSelectorSelectedContainerColor = colors.soft,
                    timeSelectorUnselectedContainerColor = colors.surfaceMuted,
                    timeSelectorSelectedContentColor = colors.link,
                    timeSelectorUnselectedContentColor = colors.text,
                ),
            )
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                AikuButton(
                    text = stringResource(R.string.dialog_cancel),
                    onClick = onDismiss,
                    style = ButtonStyle.Outline,
                    height = 44.dp,
                    modifier = Modifier.weight(1f),
                )
                AikuButton(
                    text = stringResource(R.string.clocking_form_choose),
                    onClick = {
                        onPick(LocalTime.of(state.hour, state.minute))
                        onDismiss()
                    },
                    height = 44.dp,
                    modifier = Modifier.weight(1f),
                )
            }
        }
    }
}

@Composable
private fun HrDialogButton(text: String, onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .heightIn(min = 44.dp)
            .clip(RoundedCornerShape(8.dp))
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 14.dp),
    ) {
        Text(text = text, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.link)
    }
}

@Composable
internal fun LoadMoreOnScroll(scrollState: ScrollState, list: PagedList<*>, onLoadMore: () -> Unit) {
    val currentOnLoadMore by rememberUpdatedState(onLoadMore)
    val distance = with(LocalDensity.current) { LOAD_MORE_DISTANCE_DP.dp.roundToPx() }
    val canLoadMore = list.canLoadMoreOnScroll

    LaunchedEffect(scrollState, list.items.size, canLoadMore) {
        if (canLoadMore) {
            snapshotFlow { scrollState.maxValue - scrollState.value <= distance }.first { it }
            currentOnLoadMore()
        }
    }
}

@Composable
internal fun PagedListFooter(list: PagedList<*>, onRetry: () -> Unit) {
    val colors = LocalAikuColors.current
    val loading = stringResource(R.string.loading)

    if (list.isLoadingMore) {
        Box(contentAlignment = Alignment.Center, modifier = Modifier.fillMaxWidth().heightIn(min = 44.dp).semantics { contentDescription = loading }) {
            CircularProgressIndicator(color = colors.link, strokeWidth = 2.5.dp, modifier = Modifier.size(22.dp))
        }
    }
    list.nextPageProblem?.let { problem ->
        InlineAlert(
            title = stringResource(R.string.clocking_load_more_failed),
            body = stringResource(if (problem == ClockingProblem.Offline) R.string.clocking_load_offline_body else R.string.clocking_load_server_body),
            tone = AlertTone.Warn,
            actionLabel = stringResource(R.string.clocking_error_try_again),
            onAction = onRetry,
        )
    }
}
