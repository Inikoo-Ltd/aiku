package io.aiku.droid.ui.components

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
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.toggleable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.RadioButton
import androidx.compose.material3.RadioButtonDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.stateDescription
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun QuantityStepper(
    value: Int,
    onValueChange: (Int) -> Unit,
    modifier: Modifier = Modifier,
    min: Int = 0,
    max: Int? = null,
    showMax: Boolean = false,
    helper: String? = null,
    isError: Boolean = false,
) {
    StepperLayout(
        shownValue = if (showMax && max != null) "$value / $max" else value.toString(),
        canDecrease = value > min,
        canIncrease = max == null || value < max,
        onDecrease = { onValueChange(value - 1) },
        onIncrease = { onValueChange(value + 1) },
        modifier = modifier,
        helper = helper,
        isError = isError,
    )
}

@Composable
fun QuantityStepper(
    value: Double,
    valueText: String,
    onValueChange: (Double) -> Unit,
    min: Double,
    max: Double,
    modifier: Modifier = Modifier,
    helper: String? = null,
) {
    StepperLayout(
        shownValue = valueText,
        canDecrease = value > min,
        canIncrease = value < max,
        onDecrease = { onValueChange((value - 1).coerceIn(min, max)) },
        onIncrease = { onValueChange((value + 1).coerceIn(min, max)) },
        modifier = modifier,
        helper = helper,
        isError = false,
    )
}

@Composable
private fun StepperLayout(
    shownValue: String,
    canDecrease: Boolean,
    canIncrease: Boolean,
    onDecrease: () -> Unit,
    onIncrease: () -> Unit,
    modifier: Modifier,
    helper: String?,
    isError: Boolean,
) {
    val colors = LocalAikuColors.current

    Column(modifier = modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier
                .fillMaxWidth()
                .height(50.dp)
                .clip(ControlShape)
                .background(colors.surface)
                .border(1.dp, if (isError) colors.danger else colors.borderStrong, ControlShape)
                .semantics { stateDescription = shownValue },
        ) {
            StepperButton(
                symbol = "−",
                description = stringResource(R.string.stepper_decrease),
                isEnabled = canDecrease,
                onClick = onDecrease,
            )
            Box(modifier = Modifier.width(1.dp).fillMaxHeight().background(colors.border))
            Text(
                text = shownValue,
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
                textAlign = TextAlign.Center,
                modifier = Modifier.weight(1f),
            )
            Box(modifier = Modifier.width(1.dp).fillMaxHeight().background(colors.border))
            StepperButton(
                symbol = "+",
                description = stringResource(R.string.stepper_increase),
                isEnabled = canIncrease,
                onClick = onIncrease,
            )
        }
        if (helper != null) {
            Text(text = helper, fontSize = 13.sp, color = if (isError) colors.danger else colors.textTertiary)
        }
    }
}

@Composable
private fun StepperButton(symbol: String, description: String, isEnabled: Boolean, onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .width(56.dp)
            .fillMaxHeight()
            .background(colors.surfaceMuted)
            .clickable(enabled = isEnabled, role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Text(text = symbol, fontSize = 22.sp, color = if (isEnabled) colors.textSecondary else colors.faint)
    }
}

@Composable
fun CheckboxRow(
    label: String,
    checked: Boolean,
    onCheckedChange: (Boolean) -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    description: String? = null,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = modifier
            .fillMaxWidth()
            .heightIn(min = 44.dp)
            .toggleable(value = checked, enabled = enabled, role = Role.Checkbox, onValueChange = onCheckedChange),
    ) {
        Checkbox(
            checked = checked,
            onCheckedChange = null,
            enabled = enabled,
            colors = CheckboxDefaults.colors(checkedColor = colors.primary, checkmarkColor = colors.onPrimary, uncheckedColor = colors.borderStrong),
        )
        SelectionLabel(label = label, description = description, enabled = enabled)
    }
}

@Composable
fun RadioRow(
    label: String,
    selected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    description: String? = null,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = modifier
            .fillMaxWidth()
            .heightIn(min = 44.dp)
            .selectable(selected = selected, enabled = enabled, role = Role.RadioButton, onClick = onClick),
    ) {
        RadioButton(
            selected = selected,
            onClick = null,
            enabled = enabled,
            colors = RadioButtonDefaults.colors(selectedColor = colors.primary, unselectedColor = colors.borderStrong),
        )
        SelectionLabel(label = label, description = description, enabled = enabled)
    }
}

@Composable
fun ToggleRow(
    label: String,
    checked: Boolean,
    onCheckedChange: (Boolean) -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    description: String? = null,
) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .heightIn(min = 44.dp)
            .toggleable(value = checked, enabled = enabled, role = Role.Switch, onValueChange = onCheckedChange),
    ) {
        SelectionLabel(label = label, description = description, enabled = enabled, modifier = Modifier.weight(1f))
        AikuToggle(checked = checked)
    }
}

@Composable
fun AikuToggle(checked: Boolean, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = if (checked) Alignment.CenterEnd else Alignment.CenterStart,
        modifier = modifier
            .size(width = 44.dp, height = 26.dp)
            .clip(CircleShape)
            .background(if (checked) colors.primary else colors.borderStrong)
            .padding(3.dp),
    ) {
        Box(modifier = Modifier.size(20.dp).clip(CircleShape).background(Color.White))
    }
}

@Composable
private fun SelectionLabel(label: String, description: String?, enabled: Boolean, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(modifier = modifier.padding(vertical = 4.dp), verticalArrangement = Arrangement.spacedBy(2.dp)) {
        Text(text = label, fontSize = 15.sp, color = if (enabled) colors.text else colors.textQuaternary)
        if (description != null) {
            Text(text = description, fontSize = 13.sp, color = colors.textTertiary)
        }
    }
}
