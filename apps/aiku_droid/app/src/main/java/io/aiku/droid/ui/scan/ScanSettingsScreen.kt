package io.aiku.droid.ui.scan

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.foundation.selection.toggleable
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.RadioButton
import androidx.compose.material3.RadioButtonDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private enum class ScanInputMode(@StringRes val label: Int, @StringRes val caption: Int) {
    Automatic(R.string.scan_mode_automatic, R.string.scan_mode_automatic_caption),
    ScannerDevice(R.string.scan_input_device, R.string.scan_mode_device_caption),
    Camera(R.string.scan_input_camera, R.string.scan_mode_camera_caption),
}

private object FeedbackSample {
    const val EXPECTED_SKU = "SKU-00733"
    const val WRONG_SKU = "SKU-00412"
    const val EXTRA_SKU = "SKU-02210"
    const val EXPECTED_LOCATION = "A-03-02"
    const val WRONG_LOCATION = "B-01-04"
    const val DELIVERY = "SD-1093"
    const val UNKNOWN_BARCODE = "5060123456789"
    const val PICKED = 4
    const val REQUIRED = 6
}

private data class ExampleAction(val label: String, val isPrimary: Boolean)

@Composable
fun ScanSettingsScreen() {
    val can = LocalWarehouse.current.can

    RequireAbility(isAllowed = can.canResolveScans(), title = stringResource(R.string.scan_settings_title)) {
        AppScreen(
            header = {
                AppHeader(
                    title = stringResource(R.string.scan_settings_title),
                    kicker = stringResource(R.string.scan_result_kicker),
                    showMenu = false,
                )
            },
        ) {
            ScanInputCard()
            ScanFeedbackSection()
        }
    }
}

@Composable
private fun ScanInputCard() {
    val colors = LocalAikuColors.current
    var mode by rememberSaveable { mutableStateOf(ScanInputMode.Automatic) }
    var isSoundOn by rememberSaveable { mutableStateOf(true) }
    var isVibrationOn by rememberSaveable { mutableStateOf(true) }

    AikuCard(modifier = Modifier.fakeData(12.dp)) {
        Text(text = stringResource(R.string.scan_input_heading), fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
        Column(verticalArrangement = Arrangement.spacedBy(12.dp), modifier = Modifier.selectableGroup()) {
            ScanInputMode.entries.forEach { option ->
                ScanInputOption(option = option, isSelected = option == mode, onSelect = { mode = option })
            }
        }
        ScanToggleRow(label = stringResource(R.string.scan_sound), isChecked = isSoundOn, onToggle = { isSoundOn = it })
        ScanToggleRow(label = stringResource(R.string.scan_vibrate), isChecked = isVibrationOn, onToggle = { isVibrationOn = it })
    }
}

@Composable
private fun ScanInputOption(option: ScanInputMode, isSelected: Boolean, onSelect: () -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(ControlShape)
            .background(if (isSelected) colors.soft else colors.surface)
            .border(if (isSelected) 2.dp else 1.dp, if (isSelected) colors.primary else colors.border, ControlShape)
            .selectable(selected = isSelected, role = Role.RadioButton, onClick = onSelect)
            .padding(12.dp),
    ) {
        RadioButton(
            selected = isSelected,
            onClick = null,
            colors = RadioButtonDefaults.colors(selectedColor = colors.primary, unselectedColor = colors.borderStrong),
        )
        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = stringResource(option.label), fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
            Text(text = stringResource(option.caption), fontSize = 12.sp, color = colors.textTertiary)
        }
    }
}

@Composable
private fun ScanToggleRow(label: String, isChecked: Boolean, onToggle: (Boolean) -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 44.dp)
            .toggleable(value = isChecked, role = Role.Checkbox, onValueChange = onToggle),
    ) {
        Text(text = label, fontSize = 14.sp, color = colors.text, modifier = Modifier.weight(1f))
        Checkbox(
            checked = isChecked,
            onCheckedChange = null,
            colors = CheckboxDefaults.colors(
                checkedColor = colors.primary,
                uncheckedColor = colors.borderStrong,
                checkmarkColor = colors.onPrimary,
            ),
        )
    }
}

@Composable
private fun ScanFeedbackSection() {
    val colors = LocalAikuColors.current
    val solid = rememberScanSolidColors()

    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Column(verticalArrangement = Arrangement.spacedBy(4.dp), modifier = Modifier.padding(horizontal = 4.dp)) {
            Text(text = stringResource(R.string.scan_feedback_heading), fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = stringResource(R.string.scan_feedback_intro), fontSize = 13.sp, color = colors.textTertiary)
        }

        FeedbackExample(label = stringResource(R.string.scan_feedback_correct_label), note = stringResource(R.string.scan_feedback_correct_note)) {
            SolidFeedback(
                container = solid.success,
                icon = R.drawable.ic_scan_check,
                title = "${FeedbackSample.EXPECTED_SKU} +1",
                detail = pluralStringResource(R.plurals.scan_feedback_picked_of, FeedbackSample.PICKED, FeedbackSample.PICKED, FeedbackSample.REQUIRED),
                detailColor = solid.successSoft,
            )
        }
        FeedbackExample(label = stringResource(R.string.scan_feedback_wrong_item_label), note = stringResource(R.string.scan_feedback_wrong_item_note)) {
            SolidFeedback(
                container = solid.danger,
                icon = R.drawable.ic_scan_close,
                title = stringResource(R.string.scan_feedback_wrong_item),
                detail = stringResource(R.string.scan_feedback_wrong_item_detail, FeedbackSample.WRONG_SKU, FeedbackSample.EXPECTED_SKU),
                detailColor = solid.dangerSoft,
            )
        }
        FeedbackExample(label = stringResource(R.string.scan_feedback_wrong_location_label), note = stringResource(R.string.scan_feedback_double_tone_note)) {
            SolidFeedback(
                container = solid.warn,
                icon = R.drawable.ic_section_locations,
                title = stringResource(R.string.scan_feedback_wrong_location, FeedbackSample.WRONG_LOCATION),
                detail = stringResource(R.string.scan_feedback_wrong_location_detail, FeedbackSample.EXPECTED_LOCATION, FeedbackSample.EXPECTED_SKU),
                detailColor = solid.warnSoft,
            )
        }
        FeedbackExample(label = stringResource(R.string.scan_feedback_unknown_label), note = stringResource(R.string.scan_feedback_double_tone_note)) {
            OutlinedFeedback(
                borderColor = colors.dangerBorder,
                icon = R.drawable.ic_scan_unknown,
                iconTint = colors.danger,
                title = stringResource(R.string.scan_feedback_unknown),
                titleColor = colors.dangerInk,
                detail = stringResource(R.string.scan_feedback_unknown_detail, FeedbackSample.UNKNOWN_BARCODE),
                detailColor = colors.dangerInk,
                actions = listOf(
                    ExampleAction(stringResource(R.string.scan_type_code), isPrimary = false),
                    ExampleAction(stringResource(R.string.scan_feedback_report), isPrimary = false),
                ),
            )
        }
        FeedbackExample(label = stringResource(R.string.scan_feedback_not_on_delivery_label), note = stringResource(R.string.scan_feedback_waits_note)) {
            OutlinedFeedback(
                borderColor = colors.warnBorder,
                icon = R.drawable.ic_alert_triangle,
                iconTint = colors.warn,
                title = stringResource(R.string.scan_feedback_not_on_delivery, FeedbackSample.EXTRA_SKU, FeedbackSample.DELIVERY),
                titleColor = colors.warnInk,
                detail = stringResource(R.string.scan_feedback_add_extra_question),
                detailColor = colors.warn,
                actions = listOf(
                    ExampleAction(stringResource(R.string.scan_feedback_add_extra), isPrimary = true),
                    ExampleAction(stringResource(R.string.scan_feedback_ignore), isPrimary = false),
                ),
            )
        }
        FeedbackExample(label = stringResource(R.string.scan_feedback_complete_label), note = stringResource(R.string.scan_feedback_medium_note)) {
            SolidFeedback(
                container = solid.warn,
                icon = R.drawable.ic_scan_complete,
                title = pluralStringResource(R.plurals.scan_feedback_all_picked, FeedbackSample.REQUIRED, FeedbackSample.REQUIRED),
                detail = stringResource(R.string.scan_feedback_all_picked_detail),
                detailColor = solid.warnSoft,
            )
        }
    }
}

@Composable
private fun FeedbackExample(label: String, note: String, content: @Composable () -> Unit) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fakeData(12.dp)
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surfaceMuted)
            .border(1.dp, colors.border, CardShape)
            .padding(16.dp),
    ) {
        Text(text = label, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = colors.textTertiary)
        content()
        Text(text = note, fontSize = 12.sp, color = colors.textTertiary)
    }
}

@Composable
private fun SolidFeedback(
    container: Color,
    @DrawableRes icon: Int,
    title: String,
    detail: String,
    detailColor: Color,
) {
    val content = rememberScanSolidColors().surface

    Row(
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(ControlShape)
            .background(container)
            .padding(14.dp),
    ) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = content, modifier = Modifier.size(22.dp))
        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = title, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = content)
            Text(text = detail, fontSize = 13.sp, color = detailColor)
        }
    }
}

@Composable
private fun OutlinedFeedback(
    borderColor: Color,
    @DrawableRes icon: Int,
    iconTint: Color,
    title: String,
    titleColor: Color,
    detail: String,
    detailColor: Color,
    actions: List<ExampleAction>,
) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(ControlShape)
            .background(colors.surface)
            .border(1.dp, borderColor, ControlShape)
            .padding(14.dp),
    ) {
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            Icon(painter = painterResource(icon), contentDescription = null, tint = iconTint, modifier = Modifier.size(22.dp))
            Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(text = title, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = titleColor)
                Text(text = detail, fontSize = 13.sp, color = detailColor)
            }
        }
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            actions.forEach { action ->
                Box(
                    contentAlignment = Alignment.Center,
                    modifier = Modifier
                        .weight(1f)
                        .heightIn(min = 44.dp)
                        .clip(ControlShape)
                        .background(if (action.isPrimary) colors.primary else colors.surface)
                        .border(1.dp, if (action.isPrimary) colors.primaryBorder else colors.borderStrong, ControlShape),
                ) {
                    Text(
                        text = action.label,
                        fontSize = 14.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = if (action.isPrimary) colors.onPrimary else colors.textSecondary,
                    )
                }
            }
        }
    }
}

@Preview
@Composable
private fun ScanSettingsScreenPreview() {
    AikuDroidTheme {
        ScanSettingsScreen()
    }
}
