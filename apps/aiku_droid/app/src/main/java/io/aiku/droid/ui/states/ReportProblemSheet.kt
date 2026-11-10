package io.aiku.droid.ui.states

import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
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
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.Inter
import io.aiku.droid.ui.theme.LocalAikuColors

enum class ReportProblemCategory(@StringRes val label: Int) {
    UnknownBarcode(R.string.states_report_unknown_barcode_category),
    WrongStock(R.string.states_report_wrong_stock),
    DataProblem(R.string.states_report_data_problem),
    AppError(R.string.states_report_app_error),
    Other(R.string.states_report_other),
}

@Composable
fun ReportProblemSheet(onDismiss: () -> Unit) {
    ReportProblemSheet(onDismiss = onDismiss, initialCategory = ReportProblemCategory.UnknownBarcode)
}

@Composable
fun ReportProblemSheet(onDismiss: () -> Unit, initialCategory: ReportProblemCategory) {
    val toasts = LocalToasts.current
    val sentMessage = stringResource(R.string.states_report_sent)

    AikuBottomSheet(
        title = stringResource(R.string.states_report_title),
        subtitle = stringResource(R.string.states_report_subtitle),
        onDismiss = onDismiss,
    ) {
        ReportProblemContent(
            initialCategory = initialCategory,
            onSend = {
                onDismiss()
                toasts.show(sentMessage)
            },
            onCancel = onDismiss,
        )
    }
}

@Composable
private fun ReportProblemContent(initialCategory: ReportProblemCategory, onSend: () -> Unit, onCancel: () -> Unit) {
    val colors = LocalAikuColors.current
    var category by remember { mutableStateOf(initialCategory) }
    var details by remember { mutableStateOf(STATES_REPORT_DETAILS_SAMPLE) }

    FlowRow(
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier.fillMaxWidth().selectableGroup(),
    ) {
        ReportProblemCategory.entries.forEach { option ->
            ReportCategoryChip(
                label = stringResource(option.label),
                isSelected = option == category,
                onClick = { category = option },
            )
        }
    }
    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(
            text = stringResource(R.string.states_report_what_happened),
            fontSize = 14.sp,
            fontWeight = FontWeight.Medium,
            color = colors.textSecondary,
        )
        ReportDetailsField(value = details, onValueChange = { details = it })
    }
    Column(
        verticalArrangement = Arrangement.spacedBy(6.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(ControlShape)
            .background(colors.surfaceMuted)
            .border(1.dp, colors.border, ControlShape)
            .padding(12.dp),
    ) {
        Text(
            text = stringResource(R.string.states_report_added_automatically),
            fontSize = 12.sp,
            fontWeight = FontWeight.Bold,
            color = colors.textSecondary,
        )
        Text(
            text = stringResource(R.string.states_report_added_details),
            fontSize = 12.sp,
            fontWeight = FontWeight.Normal,
            color = colors.textTertiary,
        )
    }
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .statesDashedBorder(color = colors.borderStrong, cornerRadius = 8.dp)
            .padding(12.dp),
    ) {
        Icon(
            painter = painterResource(R.drawable.ic_scan_camera),
            contentDescription = null,
            tint = colors.textTertiary,
            modifier = Modifier.size(20.dp),
        )
        Text(
            text = stringResource(R.string.states_report_add_photo),
            fontSize = 14.sp,
            fontWeight = FontWeight.Normal,
            color = colors.textTertiary,
        )
    }
    AikuButton(
        text = stringResource(R.string.states_report_send),
        onClick = onSend,
        modifier = Modifier.fillMaxWidth(),
        height = 52.dp,
    )
    AikuButton(
        text = stringResource(R.string.dialog_cancel),
        onClick = onCancel,
        modifier = Modifier.fillMaxWidth(),
        style = ButtonStyle.Outline,
        height = 44.dp,
    )
}

@Composable
private fun ReportCategoryChip(label: String, isSelected: Boolean, onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .heightIn(min = 40.dp)
            .clip(CircleShape)
            .background(if (isSelected) colors.soft else colors.surface)
            .border(
                width = if (isSelected) 2.dp else 1.dp,
                color = if (isSelected) colors.primary else colors.borderStrong,
                shape = CircleShape,
            )
            .selectable(selected = isSelected, role = Role.RadioButton, onClick = onClick)
            .padding(horizontal = 14.dp),
    ) {
        Text(
            text = label,
            fontSize = 14.sp,
            fontWeight = FontWeight.SemiBold,
            color = if (isSelected) colors.link else colors.textSecondary,
        )
    }
}

@Composable
private fun ReportDetailsField(value: String, onValueChange: (String) -> Unit) {
    val colors = LocalAikuColors.current
    val shape = RoundedCornerShape(6.dp)

    BasicTextField(
        value = value,
        onValueChange = onValueChange,
        textStyle = TextStyle(fontFamily = Inter, fontSize = 16.sp, color = colors.text),
        cursorBrush = SolidColor(colors.primary),
        keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
        maxLines = 5,
        modifier = Modifier
            .fakeData(6.dp)
            .fillMaxWidth()
            .heightIn(min = 48.dp)
            .clip(shape)
            .background(colors.surface)
            .border(1.dp, colors.borderStrong, shape)
            .padding(horizontal = 12.dp, vertical = 13.dp),
    )
}

@Preview(showBackground = true)
@Composable
private fun ReportProblemSheetPreview() {
    AikuDroidTheme {
        StatesPreviewSurface {
            ReportProblemContent(initialCategory = ReportProblemCategory.UnknownBarcode, onSend = {}, onCancel = {})
        }
    }
}
