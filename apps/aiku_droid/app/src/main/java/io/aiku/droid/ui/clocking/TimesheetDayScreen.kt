package io.aiku.droid.ui.clocking

import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.ClockingEvent
import io.aiku.droid.data.api.ClockingSession
import io.aiku.droid.data.api.ClockingStatus
import io.aiku.droid.data.api.Timesheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.EmptyState
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.SkeletonStatCard
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.theme.LocalAikuColors
import java.time.Duration
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId

private const val SESSION_STATUS_CLOSED = "closed"
private const val SESSION_STATUS_ERROR = "error"

private enum class ClockingType(val key: String, @StringRes val label: Int) {
    ClockingMachine("clocking-machine", R.string.clocking_type_clocking_machine),
    Manual("manual", R.string.clocking_type_manual),
    SelfCheck("self-check", R.string.clocking_type_self_check),
    System("system", R.string.clocking_type_system),
}

private data class TimesheetClocking(val event: ClockingEvent, val isClockIn: Boolean)

@Composable
fun TimesheetDayScreen(date: String) {
    val navigator = LocalAppNavigator.current
    val clocking = LocalClocking.current
    val state by clocking.state.collectAsStateWithLifecycle()
    var showAdjustment by remember { mutableStateOf(false) }
    val day = parseClockingDate(date)
    val dayState = state.day?.takeIf { it.date == day }
    val timesheet = dayState?.timesheet
    val now = rememberClockingNow()
    val kicker = listOf(stringResource(R.string.clocking_day_kicker), LocalStaff.current.displayName)
        .filter { it.isNotBlank() }
        .joinToString(" · ")

    val today = state.today
    val canRequestCorrection = timesheet != null &&
        timesheet.id != null &&
        !timesheet.hasPendingAdjustment &&
        day != null &&
        !day.isAfter(today) &&
        !day.isBefore(today.minusMonths(1))

    LaunchedEffect(day) { day?.let(clocking::loadDay) }

    AppScreen(
        header = { AppHeader(title = clockingDateLabel(date, CLOCKING_DAY_TITLE_PATTERN), kicker = kicker) },
        activeTab = null,
        showBar = false,
        spacing = 16.dp,
        footer = {
            if (timesheet?.hasPendingAdjustment == true) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
                ) {
                    StatusBadge(text = stringResource(R.string.clocking_correction_pending), tone = Tone.Warn)
                    Text(
                        text = stringResource(R.string.clocking_correction_pending_body),
                        fontSize = 12.sp,
                        color = LocalAikuColors.current.textTertiary,
                        modifier = Modifier.weight(1f),
                    )
                }
            }
            if (canRequestCorrection) {
                AikuButton(
                    text = stringResource(R.string.clocking_request_correction),
                    onClick = { showAdjustment = true },
                    style = ButtonStyle.Soft,
                    icon = R.drawable.ic_ui_bell,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
            AikuButton(
                text = stringResource(R.string.clocking_day_back_to_history),
                onClick = navigator::back,
                style = ButtonStyle.Outline,
                height = 44.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        },
    ) {
        dayState?.problem?.let { problem -> ClockingLoadProblem(problem = problem, onRetry = { day?.let(clocking::loadDay) }) }
        when {
            timesheet != null -> TimesheetDayContent(timesheet = timesheet, status = state.status, zone = state.zone, today = state.today, now = now)
            day != null && (dayState == null || dayState.isLoading) -> {
                SkeletonStatCard()
                SkeletonListCard(rows = 3)
            }
            dayState?.problem == null -> EmptyState(
                icon = R.drawable.ic_ui_clock,
                title = stringResource(R.string.clocking_day_empty_title),
                body = stringResource(R.string.clocking_day_empty_body),
            )
        }
    }

    if (showAdjustment && timesheet != null) {
        ClockingAdjustmentSheet(
            onDismiss = { showAdjustment = false },
            timesheet = timesheet,
            onSaved = {
                day?.let(clocking::loadDay)
                clocking.refresh()
            },
        )
    }
}

@Composable
private fun TimesheetDayContent(timesheet: Timesheet, status: ClockingStatus?, zone: ZoneId, today: LocalDate, now: Instant) {
    val noValue = stringResource(R.string.clocking_no_value)
    val isToday = parseClockingDate(timesheet.date) == today
    val clockings = timesheet.sessions
        .flatMap { session -> listOfNotNull(session.clockIn?.let { TimesheetClocking(it, isClockIn = true) }, session.clockOut?.let { TimesheetClocking(it, isClockIn = false) }) }
        .sortedBy { parseClockingInstant(it.event.clockedAt) }

    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
        TimesheetDayStat(value = durationText(timesheetWorkingSeconds(timesheet, status, now)), label = stringResource(R.string.clocking_working), modifier = Modifier.weight(1f))
        TimesheetDayStat(
            value = if (timesheet.breaksSeconds > 0) durationText(timesheet.breaksSeconds) else noValue,
            label = stringResource(R.string.clocking_breaks),
            modifier = Modifier.weight(1f),
        )
        TimesheetDayStat(value = clockings.size.toString(), label = stringResource(R.string.clocking_clockings), modifier = Modifier.weight(1f))
    }
    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
        TimesheetDayStat(
            value = stringResource(R.string.clocking_count_pair, timesheet.clockInCount, timesheet.clockOutCount),
            label = stringResource(R.string.clocking_day_clock_in_out),
            isWarning = timesheet.clockOutCount < timesheet.clockInCount,
            modifier = Modifier.weight(1f),
        )
        TimesheetDayStat(
            value = stringResource(R.string.clocking_count_pair, timesheet.numberOpenTimeTrackers, timesheet.numberTimeTrackers),
            label = stringResource(R.string.clocking_day_trackers_open_all),
            isWarning = timesheet.numberOpenTimeTrackers > 0,
            modifier = Modifier.weight(1f),
        )
    }
    if (timesheet.sessions.isNotEmpty()) {
        GroupLabel(text = stringResource(R.string.clocking_day_trackers))
        ListGroup {
            timesheet.sessions.forEachIndexed { index, session ->
                if (index > 0) {
                    RowDivider()
                }
                TimesheetTrackerRow(session = session, zone = zone, isToday = isToday, now = now)
            }
        }
    }
    if (clockings.isNotEmpty()) {
        GroupLabel(text = stringResource(R.string.clocking_clockings))
        ListGroup {
            clockings.forEachIndexed { index, clocking ->
                if (index > 0) {
                    RowDivider()
                }
                TimesheetEventRow(clocking = clocking, zone = zone)
            }
        }
    }
    val placedClockings = clockings.filter { it.event.workplace != null || it.event.clockingMachine != null }
    if (placedClockings.isNotEmpty()) {
        GroupLabel(text = stringResource(R.string.clocking_day_places))
        AikuCard(spacing = 12.dp) {
            placedClockings.forEach { clocking ->
                val direction = stringResource(if (clocking.isClockIn) R.string.clocking_day_in else R.string.clocking_day_out)
                ClockingDetailRow(
                    label = stringResource(R.string.clocking_day_event, direction, clockingTime(clocking.event.clockedAt, zone) ?: noValue),
                    value = listOfNotNull(clocking.event.workplace?.name, clocking.event.clockingMachine?.name)
                        .filter { it.isNotBlank() }
                        .joinToString(" · ")
                        .ifEmpty { noValue },
                )
            }
        }
    }
}

@Composable
private fun TimesheetDayStat(value: String, label: String, modifier: Modifier = Modifier, isWarning: Boolean = false) {
    val colors = LocalAikuColors.current

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(2.dp),
        modifier = modifier
            .clip(ClockingTileShape)
            .background(if (isWarning) colors.warnSoft else colors.surface)
            .border(1.dp, if (isWarning) colors.warnBorder else colors.border, ClockingTileShape)
            .padding(horizontal = 4.dp, vertical = 10.dp),
    ) {
        Text(text = value, fontSize = 18.sp, fontWeight = FontWeight.Bold, color = if (isWarning) colors.warn else colors.text, maxLines = 1)
        Text(text = label, fontSize = 11.sp, color = colors.textTertiary, maxLines = 1)
    }
}

@Composable
private fun TimesheetTrackerRow(session: ClockingSession, zone: ZoneId, isToday: Boolean, now: Instant) {
    val colors = LocalAikuColors.current
    val noValue = stringResource(R.string.clocking_no_value)
    val isOngoing = session.isOpen && isToday
    val start = clockingTime(session.startsAt, zone) ?: noValue
    val end = clockingTime(session.endsAt, zone) ?: if (isOngoing) stringResource(R.string.clocking_timesheets_now) else noValue
    val kind = when {
        isOngoing -> stringResource(R.string.clocking_day_tracker_ongoing)
        session.isOpen -> stringResource(R.string.clocking_day_tracker_no_clock_out)
        else -> stringResource(R.string.clocking_day_tracker_in_out)
    }
    val startedAt = parseClockingInstant(session.startsAt)
    val seconds = session.duration ?: startedAt?.takeIf { isOngoing }?.let { Duration.between(it, now).seconds.coerceAtLeast(0) }
    val (badge, tone) = when {
        session.status == SESSION_STATUS_ERROR || (session.isOpen && !isToday) -> stringResource(R.string.clocking_day_error) to Tone.Danger
        session.status == SESSION_STATUS_CLOSED || !session.isOpen -> stringResource(R.string.clocking_day_closed) to Tone.Success
        else -> stringResource(R.string.clocking_day_open) to Tone.Primary
    }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 12.dp),
    ) {
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = stringResource(R.string.clocking_range_arrow, start, end), fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = kind, fontSize = 13.sp, color = colors.textTertiary)
        }
        Column(horizontalAlignment = Alignment.End, verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(text = seconds?.let { durationText(it) } ?: noValue, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = colors.text)
            StatusBadge(text = badge, tone = tone)
        }
    }
}

@Composable
private fun TimesheetEventRow(clocking: TimesheetClocking, zone: ZoneId) {
    val colors = LocalAikuColors.current
    val noValue = stringResource(R.string.clocking_no_value)
    val direction = stringResource(if (clocking.isClockIn) R.string.clocking_day_in else R.string.clocking_day_out)
    val type = ClockingType.entries.firstOrNull { it.key == clocking.event.type }
    val summary = type?.let { stringResource(R.string.clocking_day_event, direction, stringResource(it.label)) } ?: direction
    val note = clocking.event.notes?.takeIf { it.isNotBlank() }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 10.dp),
    ) {
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier
                .size(40.dp)
                .clip(CircleShape)
                .background(colors.surfaceMuted)
                .border(1.dp, colors.border, CircleShape),
        ) {
            Text(text = noValue, fontSize = 11.sp, color = colors.textTertiary)
        }
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = clockingTime(clocking.event.clockedAt, zone) ?: noValue, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = summary, fontSize = 12.sp, color = colors.textTertiary)
            if (note != null) {
                Text(text = stringResource(R.string.clocking_session_note, note), fontSize = 12.sp, lineHeight = 17.sp, color = colors.textSecondary)
            }
        }
    }
}
