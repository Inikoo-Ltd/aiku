package io.aiku.droid.ui.clocking

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
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
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.TimesheetDayRoute
import io.aiku.droid.ui.theme.LocalAikuColors
import java.time.DayOfWeek
import java.time.LocalDate
import java.time.format.TextStyle as DateTextStyle

private const val CHART_FULL_DAY_HOURS = 9f
private val WeekSwitcherShape = RoundedCornerShape(10.dp)
private val ChartBarShape = RoundedCornerShape(topStart = 4.dp, topEnd = 4.dp)
private val ChartBarMaxHeight = 52.dp
private val ChartBarMinHeight = 3.dp

@Composable
fun TimesheetsScreen() {
    val navigator = LocalAppNavigator.current
    var showAdjustment by remember { mutableStateOf(false) }
    var weekIndex by rememberSaveable { mutableIntStateOf(0) }
    val week = ClockingWeeksSample[weekIndex]

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.clocking_tab_timesheets),
                kicker = clockingKicker(stringResource(R.string.module_clocking)),
                tabs = clockingSectionTabs(active = ClockingSection.Timesheets, onAdjustment = { showAdjustment = true }),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        TimesheetWeekSwitcher(
            week = week,
            canGoNext = weekIndex > 0,
            onPrevious = { weekIndex = (weekIndex + 1).coerceAtMost(ClockingWeeksSample.lastIndex) },
            onNext = { weekIndex = (weekIndex - 1).coerceAtLeast(0) },
        )
        TimesheetWeekSummary(week = week)
        ListGroup(modifier = Modifier.fakeData()) {
            week.entries.forEachIndexed { index, day ->
                if (index > 0) {
                    RowDivider()
                }
                TimesheetDayRow(day = day, onClick = { navigator.open(TimesheetDayRoute(date = day.date)) })
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

    if (showAdjustment) {
        ClockingAdjustmentSheet(date = CLOCKING_ADJUSTMENT_DATE_SAMPLE, onDismiss = { showAdjustment = false })
    }
}

@Composable
private fun TimesheetWeekSwitcher(week: ClockingWeekSample, canGoNext: Boolean, onPrevious: () -> Unit, onNext: () -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier
            .fakeData(cornerRadius = 10.dp)
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
            Text(text = week.label, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text, textAlign = TextAlign.Center)
            Text(text = week.range, fontSize = 12.sp, color = colors.textTertiary, textAlign = TextAlign.Center)
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
private fun TimesheetWeekSummary(week: ClockingWeekSample) {
    val colors = LocalAikuColors.current

    AikuCard(modifier = Modifier.fakeData(), padding = 16.dp, spacing = 12.dp) {
        Row(modifier = Modifier.fillMaxWidth()) {
            Text(text = stringResource(R.string.clocking_timesheets_working_time), fontSize = 13.sp, color = colors.textTertiary, modifier = Modifier.weight(1f))
            Text(text = stringResource(R.string.clocking_timesheets_target, CLOCKING_WEEK_TARGET_SAMPLE), fontSize = 13.sp, color = colors.textTertiary)
        }
        Text(text = week.total, fontSize = 30.sp, fontWeight = FontWeight.Bold, color = colors.text)
        TimesheetWeekChart(week = week)
        Row(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
            TimesheetWeekFigure(label = stringResource(R.string.clocking_breaks), value = week.breaks)
            TimesheetWeekFigure(label = stringResource(R.string.clocking_timesheets_days), value = week.days)
        }
    }
}

@Composable
private fun TimesheetWeekChart(week: ClockingWeekSample) {
    val colors = LocalAikuColors.current
    val locale = LocalConfiguration.current.locales[0]
    val chartDescription = stringResource(R.string.clocking_timesheets_chart)
    val entriesByDay = week.entries.associateBy { entry -> runCatching { LocalDate.parse(entry.date).dayOfWeek }.getOrNull() }

    Row(
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        verticalAlignment = Alignment.Bottom,
        modifier = Modifier
            .fillMaxWidth()
            .height(72.dp)
            .semantics { contentDescription = chartDescription },
    ) {
        DayOfWeek.entries.forEach { dayOfWeek ->
            val entry = entriesByDay[dayOfWeek]
            val barColor = when (entry?.state) {
                null -> colors.divider
                ClockingDayState.Error -> colors.danger
                ClockingDayState.Open -> colors.primary
                ClockingDayState.Complete -> colors.success
            }
            val fraction = ((entry?.hours ?: 0f) / CHART_FULL_DAY_HOURS).coerceIn(0f, 1f)

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
private fun TimesheetDayRow(day: ClockingWeekDaySample, onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val locale = LocalConfiguration.current.locales[0]
    val date = runCatching { LocalDate.parse(day.date) }.getOrNull()
    val summaryColor = when (day.state) {
        ClockingDayState.Error -> colors.danger
        ClockingDayState.Open -> colors.link
        ClockingDayState.Complete -> colors.textTertiary
    }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .background(if (day.state == ClockingDayState.Error) colors.dangerSoft else colors.surface)
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
                text = date?.dayOfMonth?.toString()?.padStart(2, '0') ?: day.date,
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
                maxLines = 1,
            )
        }
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = day.range, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.text, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text(text = day.summary, fontSize = 12.sp, color = summaryColor, maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
        Text(text = day.worked, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
    }
}
