package io.aiku.droid.ui.clocking

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
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun TimesheetDayScreen(date: String) {
    val navigator = LocalAppNavigator.current
    var showAdjustment by remember { mutableStateOf(false) }
    val day = ClockingDayDetailSample
    val kicker = listOf(stringResource(R.string.clocking_day_kicker), LocalStaff.current.displayName)
        .filter { it.isNotBlank() }
        .joinToString(" · ")

    AppScreen(
        header = { AppHeader(isSample = true, title = clockingDateLabel(date, CLOCKING_DAY_TITLE_PATTERN), kicker = kicker) },
        activeTab = null,
        showBar = false,
        spacing = 16.dp,
        footer = {
            AikuButton(
                text = stringResource(R.string.clocking_day_request_adjustment),
                onClick = { showAdjustment = true },
                style = ButtonStyle.Soft,
                icon = R.drawable.ic_ui_bell,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
            AikuButton(
                text = stringResource(R.string.clocking_day_back_to_history),
                onClick = navigator::back,
                style = ButtonStyle.Outline,
                height = 44.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        },
    ) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth().fakeData(cornerRadius = 10.dp)) {
            TimesheetDayStat(value = day.working, label = stringResource(R.string.clocking_working), modifier = Modifier.weight(1f))
            TimesheetDayStat(value = day.breaks, label = stringResource(R.string.clocking_breaks), modifier = Modifier.weight(1f))
            TimesheetDayStat(value = day.clockings, label = stringResource(R.string.clocking_clockings), modifier = Modifier.weight(1f))
        }
        GroupLabel(text = stringResource(R.string.clocking_day_trackers))
        ListGroup(modifier = Modifier.fakeData()) {
            day.trackers.forEachIndexed { index, tracker ->
                if (index > 0) {
                    RowDivider()
                }
                TimesheetTrackerRow(tracker = tracker)
            }
        }
        GroupLabel(text = stringResource(R.string.clocking_clockings))
        ListGroup(modifier = Modifier.fakeData()) {
            day.events.forEachIndexed { index, event ->
                if (index > 0) {
                    RowDivider()
                }
                TimesheetEventRow(event = event)
            }
        }
        AikuCard(modifier = Modifier.fakeData(), spacing = 12.dp) {
            ClockingDetailRow(label = stringResource(R.string.clocking_day_workplace), value = day.workplace)
            ClockingDetailRow(label = stringResource(R.string.clocking_day_shift), value = day.shift)
        }
    }

    if (showAdjustment) {
        ClockingAdjustmentSheet(date = date, onDismiss = { showAdjustment = false }, onRequested = navigator::back)
    }
}

@Composable
private fun TimesheetDayStat(value: String, label: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(2.dp),
        modifier = modifier
            .clip(ClockingTileShape)
            .background(colors.surface)
            .border(1.dp, colors.border, ClockingTileShape)
            .padding(horizontal = 4.dp, vertical = 10.dp),
    ) {
        Text(text = value, fontSize = 18.sp, fontWeight = FontWeight.Bold, color = colors.text, maxLines = 1)
        Text(text = label, fontSize = 11.sp, color = colors.textTertiary, maxLines = 1)
    }
}

@Composable
private fun TimesheetTrackerRow(tracker: ClockingTrackerSample) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 12.dp),
    ) {
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = tracker.range, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = tracker.kind, fontSize = 13.sp, color = colors.textTertiary)
        }
        Column(horizontalAlignment = Alignment.End, verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(text = tracker.duration, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = colors.text)
            StatusBadge(text = stringResource(R.string.clocking_day_closed), tone = Tone.Success)
        }
    }
}

@Composable
private fun TimesheetEventRow(event: ClockingEventSample) {
    val colors = LocalAikuColors.current

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
            if (event.hasPhoto) {
                Icon(painter = painterResource(R.drawable.ic_ui_camera), contentDescription = null, tint = colors.textTertiary, modifier = Modifier.size(18.dp))
            } else {
                Text(text = stringResource(R.string.clocking_no_value), fontSize = 11.sp, color = colors.textTertiary)
            }
        }
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = event.time, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = event.summary, fontSize = 12.sp, color = colors.textTertiary)
        }
    }
}
