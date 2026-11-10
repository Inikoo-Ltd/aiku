package io.aiku.droid.ui.clocking

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.ClockScanQrRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun WorkingHoursSheet(onDismiss: () -> Unit) {
    val navigator = LocalAppNavigator.current
    var isShiftSchedule by remember { mutableStateOf(false) }
    var selectedShift by remember { mutableIntStateOf(0) }

    AikuBottomSheet(
        title = stringResource(R.string.clocking_working_hours_title),
        subtitle = stringResource(R.string.clocking_working_hours_subtitle),
        onDismiss = onDismiss,
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.selectableGroup()) {
            ChoiceRow(
                title = stringResource(R.string.clocking_working_hours_default),
                subtitle = stringResource(R.string.clocking_working_hours_default_body, CLOCKING_DEFAULT_HOURS_SAMPLE),
                selected = !isShiftSchedule,
                onClick = { isShiftSchedule = false },
                modifier = Modifier.fakeData(cornerRadius = 10.dp).heightIn(min = 64.dp),
            )
            ChoiceRow(
                title = stringResource(R.string.clocking_working_hours_shift),
                subtitle = stringResource(R.string.clocking_working_hours_shift_body),
                selected = isShiftSchedule,
                onClick = { isShiftSchedule = true },
                modifier = Modifier.heightIn(min = 64.dp),
            )
        }
        if (isShiftSchedule) {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                GroupLabel(text = stringResource(R.string.clocking_working_hours_choose_shift))
                Column(
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                    modifier = Modifier.fakeData(cornerRadius = 8.dp).selectableGroup(),
                ) {
                    ClockingShiftsSample.forEachIndexed { index, shift ->
                        ClockingShiftRow(shift = shift, isSelected = index == selectedShift, onClick = { selectedShift = index })
                    }
                }
            }
        }
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth().padding(top = 4.dp)) {
            AikuButton(
                text = stringResource(R.string.dialog_cancel),
                onClick = onDismiss,
                style = ButtonStyle.Outline,
                height = 50.dp,
                modifier = Modifier.weight(1f),
            )
            AikuButton(
                text = stringResource(R.string.clocking_working_hours_confirm),
                onClick = {
                    onDismiss()
                    navigator.open(ClockScanQrRoute)
                },
                height = 50.dp,
                modifier = Modifier.weight(1f),
            )
        }
    }
}

@Composable
private fun ClockingShiftRow(shift: ClockingShiftSample, isSelected: Boolean, onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 48.dp)
            .clip(ControlShape)
            .background(if (isSelected) colors.soft else colors.surface)
            .border(if (isSelected) 2.dp else 1.dp, if (isSelected) colors.primary else colors.border, ControlShape)
            .selectable(selected = isSelected, role = Role.RadioButton, onClick = onClick)
            .padding(horizontal = 14.dp),
    ) {
        Text(text = shift.name, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.text, modifier = Modifier.weight(1f))
        Text(text = shift.hours, fontSize = 14.sp, fontWeight = FontWeight.Medium, color = colors.textTertiary)
    }
}
