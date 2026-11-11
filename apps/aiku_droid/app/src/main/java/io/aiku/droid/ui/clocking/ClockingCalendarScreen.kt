package io.aiku.droid.ui.clocking

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
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
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.luminance
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.HolidayCalendar
import io.aiku.droid.data.api.HolidayRange
import io.aiku.droid.data.api.HrStatus
import io.aiku.droid.data.api.Leave
import io.aiku.droid.data.api.LeaveSession
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.LocalAikuColors
import java.time.DayOfWeek
import java.time.LocalDate
import java.time.YearMonth
import java.time.format.DateTimeFormatter
import java.time.format.TextStyle as DateTextStyle

private const val DAYS_PER_WEEK = 7
private const val MONTH_PATTERN = "LLLL yyyy"
private const val DAY_MONTH_PATTERN = "dd MMM"
private const val LIGHT_FILL_LUMINANCE = 0.5f
private const val PENDING_FILL_ALPHA = 0.22f
private val CalendarCellShape = RoundedCornerShape(8.dp)
private val MonthSwitcherShape = RoundedCornerShape(10.dp)
private val WeekendDays = setOf(DayOfWeek.SATURDAY, DayOfWeek.SUNDAY)

private fun Leave.covers(date: LocalDate): Boolean {
    val start = parseClockingDate(startDate) ?: return false
    val end = parseClockingDate(endDate) ?: start
    return !date.isBefore(start) && !date.isAfter(end)
}

private val Leave.isApproved: Boolean get() = status == HrStatus.APPROVED

private enum class LeaveDayFill { Full, Morning, Afternoon, HalfDay }

private val Leave.dayFill: LeaveDayFill
    get() = when {
        !isHalfDay && !isHalfDayDuration -> LeaveDayFill.Full
        session == LeaveSession.MORNING -> LeaveDayFill.Morning
        session == LeaveSession.AFTERNOON -> LeaveDayFill.Afternoon
        else -> LeaveDayFill.HalfDay
    }

@Composable
fun ClockingCalendarScreen() {
    val controller = LocalClockingCalendar.current
    val state by controller.state.collectAsStateWithLifecycle()
    val clockingState by LocalClocking.current.state.collectAsStateWithLifecycle()
    val today = clockingState.today
    var shownMonth by rememberSaveable { mutableStateOf<String?>(null) }
    var selectedDay by rememberSaveable { mutableStateOf<String?>(null) }
    val month = shownMonth?.let { runCatching { YearMonth.parse(it) }.getOrNull() } ?: YearMonth.from(today)
    val calendar = state.months[month]
    val problem = state.problem?.takeIf { state.failedMonth == month }
    val selected = parseClockingDate(selectedDay)?.takeIf { YearMonth.from(it) == month }

    LaunchedEffect(month) { controller.loadMonth(month) }

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.clocking_tab_calendar),
                kicker = clockingKicker(stringResource(R.string.module_clocking)),
                tabs = clockingSectionTabs(active = ClockingSection.Calendar),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        CalendarMonthSwitcher(
            month = month,
            holidayYear = calendar?.holidayYear?.label?.takeIf { it.isNotBlank() },
            onPrevious = {
                shownMonth = month.minusMonths(1).toString()
                selectedDay = null
            },
            onNext = {
                shownMonth = month.plusMonths(1).toString()
                selectedDay = null
            },
        )
        problem?.let { ClockingLoadProblem(problem = it, onRetry = { controller.loadMonth(month) }) }
        CalendarMonthGrid(
            month = month,
            today = today,
            calendar = calendar,
            selected = selected,
            onSelect = { selectedDay = it.toString() },
        )
        CalendarLegend(leaves = calendar?.leaves.orEmpty())
        CalendarDayDetails(day = selected, calendar = calendar)
        GroupLabel(text = stringResource(R.string.clocking_calendar_holidays))
        when {
            calendar == null && problem == null -> SkeletonListCard(rows = 2)
            calendar == null -> Unit
            calendar.holidayRanges.isEmpty() -> HrFieldHelper(text = stringResource(R.string.clocking_calendar_no_holidays))
            else -> ListGroup {
                calendar.holidayRanges.forEachIndexed { index, range ->
                    if (index > 0) {
                        RowDivider()
                    }
                    HolidayRangeRow(range = range, month = month)
                }
            }
        }
    }
}

@Composable
private fun CalendarMonthSwitcher(month: YearMonth, holidayYear: String?, onPrevious: () -> Unit, onNext: () -> Unit) {
    val colors = LocalAikuColors.current
    val locale = LocalConfiguration.current.locales[0]

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(MonthSwitcherShape)
            .background(colors.surface)
            .border(1.dp, colors.border, MonthSwitcherShape)
            .padding(4.dp),
    ) {
        CalendarArrow(icon = R.drawable.ic_ui_back, description = stringResource(R.string.clocking_calendar_previous_month), onClick = onPrevious)
        Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.weight(1f)) {
            Text(
                text = month.format(DateTimeFormatter.ofPattern(MONTH_PATTERN, locale)).replaceFirstChar { it.titlecase(locale) },
                fontSize = 15.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
                textAlign = TextAlign.Center,
            )
            if (holidayYear != null) {
                Text(text = stringResource(R.string.clocking_calendar_holiday_year, holidayYear), fontSize = 12.sp, color = colors.textTertiary, textAlign = TextAlign.Center)
            }
        }
        CalendarArrow(icon = R.drawable.ic_ui_next, description = stringResource(R.string.clocking_calendar_next_month), onClick = onNext)
    }
}

@Composable
private fun CalendarArrow(@DrawableRes icon: Int, description: String, onClick: () -> Unit) {
    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(44.dp)
            .clip(RoundedCornerShape(8.dp))
            .clickable(role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = LocalAikuColors.current.textSecondary, modifier = Modifier.size(22.dp))
    }
}

@Composable
private fun CalendarMonthGrid(month: YearMonth, today: LocalDate, calendar: HolidayCalendar?, selected: LocalDate?, onSelect: (LocalDate) -> Unit) {
    val colors = LocalAikuColors.current
    val locale = LocalConfiguration.current.locales[0]
    val holidays = calendar?.holidays.orEmpty().mapNotNull { holiday -> parseClockingDate(holiday.date)?.let { it to holiday.label } }.toMap()
    val leaves = calendar?.leaves.orEmpty()
    val leadingBlanks = month.atDay(1).dayOfWeek.value - 1
    val cells = List(leadingBlanks) { null } + (1..month.lengthOfMonth()).map { month.atDay(it) }

    AikuCard(padding = 10.dp, spacing = 4.dp) {
        Row(horizontalArrangement = Arrangement.spacedBy(4.dp), modifier = Modifier.fillMaxWidth()) {
            DayOfWeek.entries.forEach { dayOfWeek ->
                Text(
                    text = dayOfWeek.getDisplayName(DateTextStyle.NARROW, locale),
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    color = if (dayOfWeek in WeekendDays) colors.textQuaternary else colors.textTertiary,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.weight(1f).padding(vertical = 4.dp),
                )
            }
        }
        cells.chunked(DAYS_PER_WEEK).forEach { week ->
            Row(horizontalArrangement = Arrangement.spacedBy(4.dp), modifier = Modifier.fillMaxWidth()) {
                week.forEach { day ->
                    if (day == null) {
                        Box(modifier = Modifier.weight(1f).height(44.dp))
                    } else {
                        CalendarDayCell(
                            day = day,
                            isToday = day == today,
                            holiday = holidays[day],
                            leave = leaves.filter { it.covers(day) }.let { onDay -> onDay.firstOrNull { it.isApproved } ?: onDay.firstOrNull() },
                            isSelected = day == selected,
                            onClick = { onSelect(day) },
                            modifier = Modifier.weight(1f),
                        )
                    }
                }
                repeat(DAYS_PER_WEEK - week.size) { Box(modifier = Modifier.weight(1f).height(44.dp)) }
            }
        }
    }
}

@Composable
private fun CalendarDayCell(
    day: LocalDate,
    isToday: Boolean,
    holiday: String?,
    leave: Leave?,
    isSelected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val colors = LocalAikuColors.current
    val ink = rememberClockingLightColors().text
    val isWeekend = day.dayOfWeek in WeekendDays
    val leaveColor = leave?.let { leaveTypeColor(it.typeColorHex) ?: colors.textTertiary }
    val isSolidLeave = leave != null && leave.isApproved && holiday == null
    val fill = leave?.dayFill
    val background = when {
        holiday != null -> colors.dangerSoft
        isWeekend -> colors.surfaceMuted
        else -> colors.surface
    }
    val textColor = when {
        isToday -> colors.onPrimary
        isSolidLeave && fill == LeaveDayFill.Full && leaveColor != null -> if (leaveColor.luminance() > LIGHT_FILL_LUMINANCE) ink else Color.White
        holiday != null -> colors.danger
        isWeekend -> colors.textQuaternary
        else -> colors.text
    }
    val description = listOfNotNull(day.dayOfMonth.toString(), holiday, leave?.let { listOf(it.typeLabel, it.statusLabel).filter(String::isNotBlank).joinToString(" ") })
        .joinToString(", ")

    Box(
        contentAlignment = Alignment.Center,
        modifier = modifier
            .height(44.dp)
            .clip(CalendarCellShape)
            .background(background)
            .drawBehind {
                if (leaveColor != null) {
                    val paint = if (isSolidLeave) leaveColor else leaveColor.copy(alpha = PENDING_FILL_ALPHA)
                    val halfWidth = size.width / 2
                    val halfHeight = size.height / 2
                    when (fill) {
                        LeaveDayFill.Morning -> drawRect(color = paint, size = Size(halfWidth, size.height))
                        LeaveDayFill.Afternoon -> drawRect(color = paint, topLeft = Offset(halfWidth, 0f), size = Size(halfWidth, size.height))
                        LeaveDayFill.HalfDay -> drawRect(color = paint, topLeft = Offset(0f, halfHeight), size = Size(size.width, halfHeight))
                        else -> drawRect(color = paint)
                    }
                }
            }
            .then(
                when {
                    isSelected -> Modifier.border(2.dp, colors.link, CalendarCellShape)
                    leave != null && leaveColor != null && !leave.isApproved -> Modifier.border(1.5.dp, leaveColor, CalendarCellShape)
                    else -> Modifier
                },
            )
            .clickable(role = Role.Button, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier
                .size(30.dp)
                .clip(CircleShape)
                .background(if (isToday) colors.primary else Color.Transparent),
        ) {
            Text(
                text = day.dayOfMonth.toString(),
                fontSize = 14.sp,
                fontWeight = if (isToday || holiday != null || leave != null) FontWeight.Bold else FontWeight.Medium,
                color = textColor,
            )
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun CalendarLegend(leaves: List<Leave>) {
    val colors = LocalAikuColors.current
    val leaveTypes = leaves.distinctBy { it.type }

    FlowRow(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth().padding(horizontal = 4.dp)) {
        CalendarLegendItem(label = stringResource(R.string.clocking_calendar_legend_holiday)) {
            Box(modifier = Modifier.size(14.dp).clip(RoundedCornerShape(3.dp)).background(colors.dangerSoft).border(1.dp, colors.danger, RoundedCornerShape(3.dp)))
        }
        leaveTypes.forEach { leave ->
            CalendarLegendItem(label = leave.typeLabel.ifBlank { leave.type }) {
                Box(modifier = Modifier.size(10.dp).clip(CircleShape).background(leaveTypeColor(leave.typeColorHex) ?: colors.textTertiary))
            }
        }
        if (leaves.isNotEmpty()) {
            CalendarLegendItem(label = stringResource(R.string.clocking_calendar_legend_approved)) {
                Box(modifier = Modifier.size(14.dp).clip(RoundedCornerShape(3.dp)).background(colors.textTertiary))
            }
            CalendarLegendItem(label = stringResource(R.string.clocking_calendar_legend_pending)) {
                Box(
                    modifier = Modifier
                        .size(14.dp)
                        .clip(RoundedCornerShape(3.dp))
                        .background(colors.textTertiary.copy(alpha = PENDING_FILL_ALPHA))
                        .border(1.5.dp, colors.textTertiary, RoundedCornerShape(3.dp)),
                )
            }
            CalendarLegendItem(label = stringResource(R.string.clocking_calendar_legend_half_day)) {
                Box(modifier = Modifier.size(14.dp).clip(RoundedCornerShape(3.dp)).background(colors.surfaceMuted).border(1.dp, colors.border, RoundedCornerShape(3.dp))) {
                    Box(modifier = Modifier.size(width = 7.dp, height = 14.dp).background(colors.textTertiary))
                }
            }
        }
    }
}

@Composable
private fun CalendarLegendItem(label: String, swatch: @Composable () -> Unit) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
        swatch()
        Text(text = label, fontSize = 12.sp, color = LocalAikuColors.current.textTertiary)
    }
}

@Composable
private fun CalendarDayDetails(day: LocalDate?, calendar: HolidayCalendar?) {
    val colors = LocalAikuColors.current

    if (day == null) {
        HrFieldHelper(text = stringResource(R.string.clocking_calendar_tap_hint))
        return
    }
    val holidays = calendar?.holidays.orEmpty().filter { parseClockingDate(it.date) == day }
    val leaves = calendar?.leaves.orEmpty().filter { it.covers(day) }

    AikuCard(padding = 14.dp, spacing = 10.dp) {
        Text(text = clockingDateLabel(day.toString(), HR_DATE_PATTERN), fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
        if (holidays.isEmpty() && leaves.isEmpty()) {
            Text(text = stringResource(R.string.clocking_calendar_day_empty), fontSize = 13.sp, color = colors.textTertiary)
        }
        holidays.forEach { holiday ->
            CalendarDetailLine(dot = colors.danger, title = holiday.label, caption = stringResource(R.string.clocking_calendar_legend_holiday))
        }
        leaves.forEach { leave ->
            val halfDay = when (leave.dayFill) {
                LeaveDayFill.Full -> null
                LeaveDayFill.Morning -> listOf(stringResource(R.string.clocking_calendar_legend_half_day), stringResource(R.string.clocking_leave_morning))
                LeaveDayFill.Afternoon -> listOf(stringResource(R.string.clocking_calendar_legend_half_day), stringResource(R.string.clocking_leave_afternoon))
                LeaveDayFill.HalfDay -> listOf(stringResource(R.string.clocking_calendar_legend_half_day))
            }
            CalendarDetailLine(
                dot = leaveTypeColor(leave.typeColorHex) ?: colors.textTertiary,
                title = leave.typeLabel.ifBlank { leave.type },
                detail = halfDay?.joinToString(" · "),
                caption = hrDateRange(leave.startDate, leave.endDate),
                statusLabel = leave.statusLabel,
                status = leave.status,
            )
        }
    }
}

@Composable
private fun CalendarDetailLine(dot: Color, title: String, caption: String, detail: String? = null, statusLabel: String? = null, status: String? = null) {
    val colors = LocalAikuColors.current

    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        Box(modifier = Modifier.size(10.dp).clip(CircleShape).background(dot))
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = title, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
            if (detail != null) {
                Text(text = detail, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = colors.textSecondary)
            }
            Text(text = caption, fontSize = 12.sp, color = colors.textTertiary)
        }
        if (!statusLabel.isNullOrBlank() && status != null) {
            StatusBadge(text = statusLabel, tone = hrStatusTone(status))
        }
    }
}

@Composable
private fun HolidayRangeRow(range: HolidayRange, month: YearMonth) {
    val colors = LocalAikuColors.current
    val locale = LocalConfiguration.current.locales[0]
    val from = parseClockingDate(range.from)
    val to = parseClockingDate(range.to) ?: from
    val inMonth = from != null && to != null && YearMonth.from(from) == month && YearMonth.from(to) == month
    val dates = when {
        from == null || to == null -> range.from
        inMonth && from == to -> from.dayOfMonth.toString()
        inMonth -> stringResource(R.string.clocking_calendar_day_range, from.dayOfMonth.toString(), to.dayOfMonth.toString())
        from == to -> from.format(DateTimeFormatter.ofPattern(DAY_MONTH_PATTERN, locale))
        else -> stringResource(
            R.string.clocking_range,
            from.format(DateTimeFormatter.ofPattern(DAY_MONTH_PATTERN, locale)),
            to.format(DateTimeFormatter.ofPattern(DAY_MONTH_PATTERN, locale)),
        )
    }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 12.dp),
    ) {
        Box(modifier = Modifier.size(10.dp).clip(CircleShape).background(colors.danger))
        Text(text = dates, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = colors.text, modifier = Modifier.width(112.dp))
        Text(text = range.label, fontSize = 14.sp, color = colors.textSecondary, modifier = Modifier.weight(1f))
    }
}
