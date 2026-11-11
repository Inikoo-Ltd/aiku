package io.aiku.droid.ui.clocking

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.ClockingStatus
import io.aiku.droid.data.api.Timesheet
import io.aiku.droid.data.api.TimesheetPeriod
import io.aiku.droid.data.api.TimesheetStatistics
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.EmptyState
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.SkeletonStatCard
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.TimesheetDayRoute
import io.aiku.droid.ui.theme.LocalAikuColors
import java.time.DayOfWeek
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.time.temporal.ChronoUnit
import java.util.Locale
import java.time.format.TextStyle as DateTextStyle

private const val CHART_FULL_DAY_SECONDS = 9f * 3600f
private const val DAYS_AFTER_MONDAY = 6L
private const val STATISTICS_PER_ROW = 3
private const val DAY_OF_MONTH_PATTERN = "dd"
private const val DAY_AND_MONTH_PATTERN = "dd MMM"
private const val FULL_DATE_PATTERN = "dd MMM yyyy"
private val WeekSwitcherShape = RoundedCornerShape(10.dp)
private val ChartBarShape = RoundedCornerShape(topStart = 4.dp, topEnd = 4.dp)
private val ChartBarMaxHeight = 52.dp
private val ChartBarMinHeight = 3.dp

@Composable
fun TimesheetsScreen() {
    val navigator = LocalAppNavigator.current
    val clocking = LocalClocking.current
    val state by clocking.state.collectAsStateWithLifecycle()
    var requestedWeek by rememberSaveable { mutableStateOf<String?>(null) }
    val requestedFrom = parseClockingDate(requestedWeek)
    val now = rememberClockingNow()
    val thisMonday = state.thisMonday
    val week = state.week.takeIf { it.requestedFrom == requestedFrom }
    val period = week?.period
    val weekStart = requestedFrom ?: parseClockingDate(period?.from) ?: thisMonday
    val canGoNext = weekStart.isBefore(thisMonday)

    LaunchedEffect(requestedFrom) { clocking.loadWeek(requestedFrom) }

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.clocking_tab_timesheets),
                kicker = clockingKicker(stringResource(R.string.module_clocking)),
                tabs = clockingSectionTabs(active = ClockingSection.Timesheets),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        TimesheetWeekSwitcher(
            label = timesheetWeekLabel(weekStart = weekStart, thisMonday = thisMonday),
            range = timesheetWeekRange(weekStart = weekStart),
            canGoNext = canGoNext,
            onPrevious = { requestedWeek = weekStart.minusWeeks(1).toString() },
            onNext = {
                val next = weekStart.plusWeeks(1)
                requestedWeek = next.takeIf { it.isBefore(thisMonday) }?.toString()
            },
        )
        week?.problem?.let { problem -> ClockingLoadProblem(problem = problem, onRetry = { clocking.loadWeek(requestedFrom) }) }
        when {
            period != null -> {
                TimesheetWeekSummary(period = period, weekStart = weekStart, status = state.status, today = state.today, now = now)
                period.statistics?.let { TimesheetStatisticsGrid(statistics = it) }
                if (period.timesheets.isEmpty()) {
                    EmptyState(
                        icon = R.drawable.ic_ui_clock,
                        title = stringResource(R.string.clocking_timesheets_empty_title),
                        body = stringResource(R.string.clocking_timesheets_empty_body),
                    )
                } else {
                    ListGroup {
                        period.timesheets.sortedByDescending { it.date }.forEachIndexed { index, timesheet ->
                            if (index > 0) {
                                RowDivider()
                            }
                            TimesheetDayRow(
                                timesheet = timesheet,
                                status = state.status,
                                zone = state.zone,
                                today = state.today,
                                now = now,
                                onClick = { navigator.open(TimesheetDayRoute(date = timesheet.date)) },
                            )
                        }
                    }
                }
            }
            week?.problem == null -> {
                SkeletonStatCard()
                SkeletonListCard(rows = 3)
            }
        }
        Text(
            text = stringResource(R.string.clocking_timesheets_note),
            fontSize = 12.sp,
            lineHeight = 18.sp,
            color = LocalAikuColors.current.textTertiary,
            modifier = Modifier.padding(horizontal = 4.dp),
        )
    }
}

@Composable
private fun timesheetWeekLabel(weekStart: LocalDate, thisMonday: LocalDate): String {
    val weeksAgo = ChronoUnit.WEEKS.between(weekStart, thisMonday).toInt()
    return when {
        weeksAgo <= 0 -> stringResource(R.string.clocking_timesheets_this_week)
        weeksAgo == 1 -> stringResource(R.string.clocking_timesheets_last_week)
        else -> pluralStringResource(R.plurals.clocking_timesheets_weeks_ago, weeksAgo, weeksAgo)
    }
}

@Composable
private fun timesheetWeekRange(weekStart: LocalDate): String {
    val locale = LocalConfiguration.current.locales[0]
    val weekEnd = weekStart.plusDays(DAYS_AFTER_MONDAY)
    val startPattern = when {
        weekStart.year != weekEnd.year -> FULL_DATE_PATTERN
        weekStart.month != weekEnd.month -> DAY_AND_MONTH_PATTERN
        else -> DAY_OF_MONTH_PATTERN
    }
    return stringResource(
        R.string.clocking_range,
        weekStart.format(DateTimeFormatter.ofPattern(startPattern, locale)),
        weekEnd.format(DateTimeFormatter.ofPattern(FULL_DATE_PATTERN, locale)),
    )
}

@Composable
private fun TimesheetWeekSwitcher(label: String, range: String, canGoNext: Boolean, onPrevious: () -> Unit, onNext: () -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(WeekSwitcherShape)
            .background(colors.surface)
            .border(1.dp, colors.border, WeekSwitcherShape)
            .padding(4.dp),
    ) {
        TimesheetWeekArrow(
            icon = R.drawable.ic_ui_back,
            description = stringResource(R.string.clocking_timesheets_previous_week),
            tint = colors.textSecondary,
            enabled = true,
            onClick = onPrevious,
        )
        Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.weight(1f)) {
            Text(text = label, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text, textAlign = TextAlign.Center)
            Text(text = range, fontSize = 12.sp, color = colors.textTertiary, textAlign = TextAlign.Center)
        }
        TimesheetWeekArrow(
            icon = R.drawable.ic_ui_next,
            description = stringResource(R.string.clocking_timesheets_next_week),
            tint = if (canGoNext) colors.textSecondary else colors.faint,
            enabled = canGoNext,
            onClick = onNext,
        )
    }
}

@Composable
private fun TimesheetWeekArrow(@DrawableRes icon: Int, description: String, tint: Color, enabled: Boolean, onClick: () -> Unit) {
    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(44.dp)
            .clip(RoundedCornerShape(8.dp))
            .clickable(enabled = enabled, role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = tint, modifier = Modifier.size(22.dp))
    }
}

@Composable
private fun TimesheetWeekSummary(period: TimesheetPeriod, weekStart: LocalDate, status: ClockingStatus?, today: LocalDate, now: Instant) {
    val colors = LocalAikuColors.current

    AikuCard(padding = 16.dp, spacing = 12.dp) {
        Row(modifier = Modifier.fillMaxWidth()) {
            Text(text = stringResource(R.string.clocking_timesheets_working_time), fontSize = 13.sp, color = colors.textTertiary, modifier = Modifier.weight(1f))
            period.targetSeconds?.let { target ->
                Text(text = stringResource(R.string.clocking_timesheets_target, durationText(target)), fontSize = 13.sp, color = colors.textTertiary)
            }
        }
        Text(text = durationText(weekWorkingSeconds(period, status, now)), fontSize = 30.sp, fontWeight = FontWeight.Bold, color = colors.text)
        TimesheetWeekChart(timesheets = period.timesheets, weekStart = weekStart, status = status, today = today, now = now)
        Row(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
            TimesheetWeekFigure(label = stringResource(R.string.clocking_breaks), value = durationText(period.timesheets.sumOf { it.breaksSeconds }))
            TimesheetWeekFigure(label = stringResource(R.string.clocking_timesheets_days), value = period.timesheets.size.toString())
        }
    }
}

@Composable
private fun TimesheetWeekChart(timesheets: List<Timesheet>, weekStart: LocalDate, status: ClockingStatus?, today: LocalDate, now: Instant) {
    val colors = LocalAikuColors.current
    val locale = LocalConfiguration.current.locales[0]
    val chartDescription = stringResource(R.string.clocking_timesheets_chart)
    val timesheetsByDate = timesheets.associateBy { it.date }

    Row(
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        verticalAlignment = Alignment.Bottom,
        modifier = Modifier
            .fillMaxWidth()
            .height(72.dp)
            .semantics { contentDescription = chartDescription },
    ) {
        DayOfWeek.entries.forEach { dayOfWeek ->
            val timesheet = timesheetsByDate[weekStart.plusDays(dayOfWeek.ordinal.toLong()).toString()]
            val barColor = when (timesheet?.let { timesheetDayStatus(it, today) }) {
                null -> colors.divider
                TimesheetDayStatus.Error -> colors.danger
                TimesheetDayStatus.Open -> colors.primary
                TimesheetDayStatus.Complete -> colors.success
            }
            val workedSeconds = timesheet?.let { timesheetWorkingSeconds(it, status, now) } ?: 0L
            val fraction = (workedSeconds / CHART_FULL_DAY_SECONDS).coerceIn(0f, 1f)

            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(4.dp, Alignment.Bottom),
                modifier = Modifier.weight(1f).fillMaxHeight(),
            ) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(maxOf(ChartBarMaxHeight * fraction, ChartBarMinHeight))
                        .clip(ChartBarShape)
                        .background(barColor),
                )
                Text(text = dayOfWeek.getDisplayName(DateTextStyle.NARROW, locale), fontSize = 11.sp, color = colors.textTertiary, maxLines = 1)
            }
        }
    }
}

@Composable
private fun TimesheetWeekFigure(label: String, value: String) {
    val colors = LocalAikuColors.current

    Row(horizontalArrangement = Arrangement.spacedBy(4.dp), verticalAlignment = Alignment.CenterVertically) {
        Text(text = label, fontSize = 12.sp, color = colors.textTertiary)
        Text(text = value, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = colors.text)
    }
}

@Composable
private fun TimesheetDayRow(timesheet: Timesheet, status: ClockingStatus?, zone: ZoneId, today: LocalDate, now: Instant, onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val locale: Locale = LocalConfiguration.current.locales[0]
    val date = parseClockingDate(timesheet.date)
    val dayStatus = timesheetDayStatus(timesheet, today)
    val noValue = stringResource(R.string.clocking_no_value)
    val clockings = timesheet.sessions.sumOf { it.clockingCount }
    val start = clockingTime(timesheet.firstClockIn, zone) ?: noValue
    val end = when (dayStatus) {
        TimesheetDayStatus.Open -> stringResource(R.string.clocking_timesheets_now)
        TimesheetDayStatus.Error -> noValue
        TimesheetDayStatus.Complete -> clockingTime(timesheet.lastClockOut, zone) ?: noValue
    }
    val summary = when (dayStatus) {
        TimesheetDayStatus.Open -> pluralStringResource(R.plurals.clocking_timesheets_day_open, clockings, clockings)
        TimesheetDayStatus.Error -> stringResource(R.string.clocking_timesheets_day_error)
        TimesheetDayStatus.Complete -> pluralStringResource(
            R.plurals.clocking_timesheets_day_complete,
            clockings,
            clockings,
            if (timesheet.breaksSeconds > 0) durationText(timesheet.breaksSeconds) else noValue,
        )
    }
    val summaryColor = when (dayStatus) {
        TimesheetDayStatus.Error -> colors.danger
        TimesheetDayStatus.Open -> colors.link
        TimesheetDayStatus.Complete -> colors.textTertiary
    }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .background(if (dayStatus == TimesheetDayStatus.Error) colors.dangerSoft else colors.surface)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 12.dp),
    ) {
        Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.width(44.dp)) {
            Text(
                text = date?.dayOfWeek?.getDisplayName(DateTextStyle.SHORT, locale)?.uppercase(locale).orEmpty(),
                fontSize = 11.sp,
                fontWeight = FontWeight.Bold,
                color = colors.textTertiary,
                maxLines = 1,
            )
            Text(
                text = date?.dayOfMonth?.toString()?.padStart(2, '0') ?: timesheet.date,
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
                maxLines = 1,
            )
        }
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(
                text = stringResource(R.string.clocking_range_arrow, start, end),
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.text,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Text(text = summary, fontSize = 12.sp, color = summaryColor, maxLines = 1, overflow = TextOverflow.Ellipsis)
            if (timesheet.hasPendingAdjustment) {
                StatusBadge(text = stringResource(R.string.clocking_correction_pending), tone = Tone.Warn, modifier = Modifier.padding(top = 2.dp))
            }
        }
        Text(text = durationText(timesheetWorkingSeconds(timesheet, status, now)), fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
    }
}

private data class TimesheetStatistic(@StringRes val label: Int, val count: Int, val tone: Tone)

@Composable
private fun TimesheetStatisticsGrid(statistics: TimesheetStatistics) {
    val figures = listOf(
        TimesheetStatistic(R.string.clocking_statistics_on_time, statistics.onTime, Tone.Success),
        TimesheetStatistic(R.string.clocking_statistics_late_clock_in, statistics.lateClockIn, Tone.Warn),
        TimesheetStatistic(R.string.clocking_statistics_early_clock_out, statistics.earlyClockOut, Tone.Warn),
        TimesheetStatistic(R.string.clocking_statistics_no_clock_out, statistics.noClockOut, Tone.Warn),
        TimesheetStatistic(R.string.clocking_statistics_invalid, statistics.invalid, Tone.Danger),
        TimesheetStatistic(R.string.clocking_statistics_total, statistics.total, Tone.Neutral),
    )

    Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
        figures.chunked(STATISTICS_PER_ROW).forEach { row ->
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth().height(IntrinsicSize.Min)) {
                row.forEach { figure -> TimesheetStatisticTile(figure = figure, modifier = Modifier.weight(1f).fillMaxHeight()) }
            }
        }
    }
}

@Composable
private fun TimesheetStatisticTile(figure: TimesheetStatistic, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val toneColors = colors.tone(figure.tone)
    val isHighlighted = figure.count > 0 && figure.tone != Tone.Neutral

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(2.dp),
        modifier = modifier
            .clip(ClockingTileShape)
            .background(if (isHighlighted) toneColors.container else colors.surface)
            .border(1.dp, colors.border, ClockingTileShape)
            .padding(horizontal = 6.dp, vertical = 10.dp),
    ) {
        Text(
            text = figure.count.toString(),
            fontSize = 18.sp,
            fontWeight = FontWeight.Bold,
            color = if (isHighlighted) toneColors.content else colors.text,
            maxLines = 1,
        )
        Text(text = stringResource(figure.label), fontSize = 11.sp, lineHeight = 14.sp, color = colors.textTertiary, textAlign = TextAlign.Center)
    }
}
