package io.aiku.droid.ui.actions

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.runtime.toMutableStateList
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.Inter
import io.aiku.droid.ui.theme.LocalAikuColors

private object TransferSample {
    const val SKU = "SKU-00733"
    const val FROM_LOCATION = "A-03-02"
    const val FROM_QUANTITY = 30
    const val TO_LOCATION = "B-01-04"
    const val TO_LOCATION_NOTE = "Area B · 6 already here"
    const val QUANTITY = 10
}

private object AdjustSample {
    const val SKU = "SKU-00733"
    const val LOCATION = "A-03-02"
    const val ON_HAND = 30
    const val CHANGE = -2
    const val NOTE = "Broken in box"
}

private data class AuditSampleLine(val sku: String, val name: String, val count: String)

private object AuditSample {
    const val LOCATION = "A-12-03"
    val lines = listOf(
        AuditSampleLine(sku = "SKU-00953", name = "Incense – sandalwood", count = "8"),
        AuditSampleLine(sku = "SKU-00412", name = "Lavender oil 10ml", count = ""),
        AuditSampleLine(sku = "SKU-01187", name = "Soy candle – vanilla", count = ""),
    )
}

private enum class AdjustmentReason(@StringRes val label: Int) {
    WriteOff(R.string.actions_reason_write_off),
    Found(R.string.actions_reason_found),
    Adjustment(R.string.actions_reason_adjustment),
    Consumption(R.string.actions_reason_consumption),
}

@Composable
fun TransferStockSheet(onDismiss: () -> Unit) {
    AikuBottomSheet(title = stringResource(R.string.actions_transfer_title), onDismiss = onDismiss) {
        TransferSheetContent(onConfirm = onDismiss)
    }
}

@Composable
fun AdjustStockSheet(onDismiss: () -> Unit) {
    AikuBottomSheet(title = stringResource(R.string.actions_adjust_title), onDismiss = onDismiss) {
        AdjustSheetContent(onConfirm = onDismiss)
    }
}

@Composable
fun AuditLocationSheet(onDismiss: () -> Unit) {
    AikuBottomSheet(title = stringResource(R.string.actions_audit_title), onDismiss = onDismiss) {
        AuditSheetContent(onConfirm = onDismiss)
    }
}

@Composable
private fun TransferSheetContent(onConfirm: () -> Unit) {
    val colors = LocalAikuColors.current
    var quantity by rememberSaveable { mutableIntStateOf(TransferSample.QUANTITY) }

    Column(
        verticalArrangement = Arrangement.spacedBy(16.dp),
        modifier = Modifier
            .fakeData(12.dp)
            .fillMaxWidth()
            .padding(8.dp),
    ) {
        SheetContext(icon = R.drawable.ic_actions_transfer, text = TransferSample.SKU)
        SheetField(label = stringResource(R.string.actions_from)) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier
                    .fillMaxWidth()
                    .height(50.dp)
                    .clip(ControlShape)
                    .background(colors.surfaceMuted)
                    .border(1.dp, colors.border, ControlShape)
                    .padding(horizontal = 12.dp),
            ) {
                Text(
                    text = TransferSample.FROM_LOCATION,
                    fontSize = 16.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = colors.text,
                    modifier = Modifier.weight(1f),
                )
                Text(
                    text = stringResource(R.string.actions_in_location, TransferSample.FROM_QUANTITY),
                    fontSize = 13.sp,
                    color = colors.textTertiary,
                )
            }
        }
        SheetField(label = stringResource(R.string.actions_to)) {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                Box(
                    contentAlignment = Alignment.CenterStart,
                    modifier = Modifier
                        .weight(1f)
                        .height(50.dp)
                        .clip(ControlShape)
                        .border(1.dp, colors.primary, ControlShape)
                        .padding(horizontal = 12.dp),
                ) {
                    Text(text = TransferSample.TO_LOCATION, fontSize = 16.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
                }
                ScanLocationButton()
            }
            Text(text = TransferSample.TO_LOCATION_NOTE, fontSize = 12.sp, color = colors.success)
        }
        SheetField(label = stringResource(R.string.actions_quantity)) {
            QuantityStepper(
                value = quantity.toString(),
                valueColor = colors.text,
                onDecrease = { quantity = (quantity - 1).coerceAtLeast(1) },
                onIncrease = { quantity = (quantity + 1).coerceAtMost(TransferSample.FROM_QUANTITY) },
            )
            Text(
                text = stringResource(R.string.actions_move_all_hint, TransferSample.FROM_QUANTITY),
                fontSize = 12.sp,
                color = colors.textTertiary,
            )
        }
    }
    AikuButton(
        text = pluralStringResource(R.plurals.actions_transfer_move_units, quantity, quantity),
        onClick = onConfirm,
        modifier = Modifier.fillMaxWidth(),
        height = 52.dp,
    )
}

@Composable
private fun AdjustSheetContent(onConfirm: () -> Unit) {
    val colors = LocalAikuColors.current
    var reason by rememberSaveable { mutableStateOf(AdjustmentReason.WriteOff) }
    var change by rememberSaveable { mutableIntStateOf(AdjustSample.CHANGE) }
    var note by rememberSaveable { mutableStateOf(AdjustSample.NOTE) }
    val inLocation = stringResource(R.string.actions_in_location_code, AdjustSample.LOCATION)
    val result = buildAnnotatedString {
        append("${AdjustSample.ON_HAND} → ")
        withStyle(SpanStyle(fontWeight = FontWeight.Bold, color = colors.text)) { append((AdjustSample.ON_HAND + change).toString()) }
        append(" ")
        append(inLocation)
    }

    Column(
        verticalArrangement = Arrangement.spacedBy(16.dp),
        modifier = Modifier
            .fakeData(12.dp)
            .fillMaxWidth()
            .padding(8.dp),
    ) {
        SheetContext(icon = R.drawable.ic_actions_adjust, text = "${AdjustSample.SKU} · ${AdjustSample.LOCATION}")
        SheetField(label = stringResource(R.string.actions_adjust_reason), spacing = 8.dp) {
            ReasonChips(selected = reason, onSelect = { reason = it })
        }
        SheetField(label = stringResource(R.string.actions_adjust_change)) {
            QuantityStepper(
                value = signedQuantity(change),
                valueColor = if (change < 0) colors.danger else colors.text,
                onDecrease = { change = (change - 1).coerceAtLeast(-AdjustSample.ON_HAND) },
                onIncrease = { change += 1 },
            )
            Text(text = result, fontSize = 13.sp, color = colors.textTertiary)
        }
        SheetField(label = stringResource(R.string.actions_adjust_note)) {
            NoteField(value = note, onValueChange = { note = it })
        }
    }
    AikuButton(
        text = stringResource(R.string.actions_adjust_save),
        onClick = onConfirm,
        modifier = Modifier.fillMaxWidth(),
        height = 52.dp,
    )
}

@Composable
private fun AuditSheetContent(onConfirm: () -> Unit) {
    val colors = LocalAikuColors.current
    val counts = remember { AuditSample.lines.map { it.count }.toMutableStateList() }
    val remaining = counts.count { it.isBlank() }

    Column(
        verticalArrangement = Arrangement.spacedBy(14.dp),
        modifier = Modifier
            .fakeData(12.dp)
            .fillMaxWidth()
            .padding(8.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            Icon(painter = painterResource(R.drawable.ic_actions_audit), contentDescription = null, tint = colors.link, modifier = Modifier.size(22.dp))
            Text(
                text = AuditSample.LOCATION,
                fontSize = 16.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.text,
                modifier = Modifier.weight(1f),
            )
            StatusBadge(text = stringResource(R.string.actions_audit_in_process), tone = Tone.Lime)
        }
        Text(text = stringResource(R.string.actions_audit_intro), fontSize = 13.sp, color = colors.textTertiary)
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .clip(CardShape)
                .border(1.dp, colors.border, CardShape),
        ) {
            AuditSample.lines.forEachIndexed { index, line ->
                if (index > 0) RowDivider()
                AuditLineRow(
                    line = line,
                    count = counts[index],
                    onCountChange = { typed -> counts[index] = typed.filter(Char::isDigit).take(5) },
                )
            }
        }
    }
    DashedButton(text = stringResource(R.string.actions_audit_found_sku), onClick = {})
    AikuButton(
        text = if (remaining > 0) {
            pluralStringResource(R.plurals.actions_audit_complete_left, remaining, remaining)
        } else {
            stringResource(R.string.actions_audit_complete)
        },
        onClick = onConfirm,
        enabled = remaining == 0,
        modifier = Modifier.fillMaxWidth(),
        height = 52.dp,
    )
}

@Composable
private fun SheetContext(@DrawableRes icon: Int, text: String) {
    val colors = LocalAikuColors.current

    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = colors.link, modifier = Modifier.size(22.dp))
        Text(text = text, fontSize = 13.sp, color = colors.textTertiary)
    }
}

@Composable
private fun SheetField(label: String, spacing: Dp = 6.dp, content: @Composable ColumnScope.() -> Unit) {
    val colors = LocalAikuColors.current

    Column(verticalArrangement = Arrangement.spacedBy(spacing), modifier = Modifier.fillMaxWidth()) {
        Text(text = label, fontSize = 14.sp, fontWeight = FontWeight.Medium, color = colors.textSecondary)
        content()
    }
}

@Composable
private fun ScanLocationButton() {
    val colors = LocalAikuColors.current
    val description = stringResource(R.string.actions_scan_location)

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(50.dp)
            .clip(ControlShape)
            .background(colors.scan)
            .clickable(role = Role.Button, onClickLabel = description, onClick = {})
            .semantics { contentDescription = description },
    ) {
        Icon(painter = painterResource(R.drawable.ic_scan), contentDescription = null, tint = colors.onScan, modifier = Modifier.size(22.dp))
    }
}

@Composable
private fun QuantityStepper(
    value: String,
    valueColor: Color,
    onDecrease: () -> Unit,
    onIncrease: () -> Unit,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .fillMaxWidth()
            .height(50.dp)
            .clip(ControlShape)
            .border(1.dp, colors.borderStrong, ControlShape),
    ) {
        StepperButton(
            symbol = stringResource(R.string.actions_minus_symbol),
            description = stringResource(R.string.actions_decrease),
            onClick = onDecrease,
        )
        Box(modifier = Modifier.width(1.dp).fillMaxHeight().background(colors.border))
        Text(
            text = value,
            fontSize = 18.sp,
            fontWeight = FontWeight.Bold,
            color = valueColor,
            textAlign = TextAlign.Center,
            modifier = Modifier.weight(1f),
        )
        Box(modifier = Modifier.width(1.dp).fillMaxHeight().background(colors.border))
        StepperButton(
            symbol = stringResource(R.string.actions_plus_symbol),
            description = stringResource(R.string.actions_increase),
            onClick = onIncrease,
        )
    }
}

@Composable
private fun StepperButton(symbol: String, description: String, onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .width(56.dp)
            .fillMaxHeight()
            .background(colors.surfaceMuted)
            .clickable(role = Role.Button, onClickLabel = description, onClick = onClick),
    ) {
        Text(text = symbol, fontSize = 22.sp, color = colors.text)
    }
}

@Composable
private fun ReasonChips(selected: AdjustmentReason, onSelect: (AdjustmentReason) -> Unit) {
    val colors = LocalAikuColors.current

    Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.selectableGroup()) {
        AdjustmentReason.entries.chunked(2).forEach { pair ->
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                pair.forEach { reason ->
                    val isSelected = reason == selected
                    Box(
                        contentAlignment = Alignment.Center,
                        modifier = Modifier
                            .weight(1f)
                            .heightIn(min = 40.dp)
                            .clip(CircleShape)
                            .background(if (isSelected) colors.navActive else Color.Transparent)
                            .then(if (isSelected) Modifier else Modifier.border(1.dp, colors.borderStrong, CircleShape))
                            .selectable(selected = isSelected, role = Role.RadioButton, onClick = { onSelect(reason) })
                            .padding(horizontal = 14.dp),
                    ) {
                        Text(
                            text = stringResource(reason.label),
                            fontSize = 14.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = if (isSelected) colors.navAccent else colors.text,
                            maxLines = 1,
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun NoteField(value: String, onValueChange: (String) -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .fillMaxWidth()
            .height(50.dp)
            .clip(ControlShape)
            .border(1.dp, colors.borderStrong, ControlShape)
            .padding(horizontal = 12.dp),
    ) {
        BasicTextField(
            value = value,
            onValueChange = onValueChange,
            singleLine = true,
            textStyle = TextStyle(fontFamily = Inter, fontSize = 15.sp, color = colors.text),
            cursorBrush = SolidColor(colors.primary),
            modifier = Modifier.weight(1f),
        )
    }
}

@Composable
private fun AuditLineRow(line: AuditSampleLine, count: String, onCountChange: (String) -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 10.dp),
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Text(text = line.sku, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = line.name, fontSize = 12.sp, color = colors.textTertiary)
        }
        CountField(
            value = count,
            onValueChange = onCountChange,
            description = stringResource(R.string.actions_audit_count_for, line.sku),
        )
    }
}

@Composable
private fun CountField(value: String, onValueChange: (String) -> Unit, description: String) {
    val colors = LocalAikuColors.current
    val notCounted = stringResource(R.string.actions_audit_not_counted)
    var isFocused by remember { mutableStateOf(false) }

    BasicTextField(
        value = value,
        onValueChange = onValueChange,
        singleLine = true,
        textStyle = TextStyle(
            fontFamily = Inter,
            fontSize = 16.sp,
            fontWeight = FontWeight.Bold,
            color = colors.text,
            textAlign = TextAlign.Center,
        ),
        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
        cursorBrush = SolidColor(colors.primary),
        modifier = Modifier
            .size(width = 72.dp, height = 44.dp)
            .clip(ControlShape)
            .border(1.dp, if (isFocused) colors.primary else colors.borderStrong, ControlShape)
            .onFocusChanged { isFocused = it.isFocused }
            .semantics { contentDescription = description },
        decorationBox = { innerTextField ->
            Box(contentAlignment = Alignment.Center, modifier = Modifier.fillMaxSize()) {
                if (value.isEmpty() && !isFocused) {
                    Text(text = notCounted, fontSize = 16.sp, color = colors.faint)
                }
                innerTextField()
            }
        },
    )
}

@Composable
private fun DashedButton(text: String, onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 44.dp)
            .clip(ControlShape)
            .dashedBorder(color = colors.faint, cornerRadius = 8.dp)
            .clickable(role = Role.Button, onClick = onClick),
    ) {
        Text(text = text, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
    }
}

private fun Modifier.dashedBorder(color: Color, cornerRadius: Dp): Modifier = drawBehind {
    val strokeWidth = 1.dp.toPx()
    drawRoundRect(
        color = color,
        topLeft = Offset(strokeWidth / 2, strokeWidth / 2),
        size = Size(size.width - strokeWidth, size.height - strokeWidth),
        cornerRadius = CornerRadius(cornerRadius.toPx()),
        style = Stroke(
            width = strokeWidth,
            pathEffect = PathEffect.dashPathEffect(floatArrayOf(4.dp.toPx(), 3.dp.toPx())),
        ),
    )
}

private fun signedQuantity(quantity: Int): String = when {
    quantity > 0 -> "+$quantity"
    quantity < 0 -> "−${-quantity}"
    else -> "0"
}

@Composable
private fun SheetPreviewFrame(content: @Composable ColumnScope.() -> Unit) {
    Column(
        verticalArrangement = Arrangement.spacedBy(16.dp),
        modifier = Modifier
            .background(LocalAikuColors.current.surface)
            .padding(16.dp),
        content = content,
    )
}

@Preview
@Composable
private fun TransferSheetPreview() {
    AikuDroidTheme {
        SheetPreviewFrame { TransferSheetContent(onConfirm = {}) }
    }
}

@Preview
@Composable
private fun AdjustSheetPreview() {
    AikuDroidTheme {
        SheetPreviewFrame { AdjustSheetContent(onConfirm = {}) }
    }
}

@Preview
@Composable
private fun AuditSheetPreview() {
    AikuDroidTheme {
        SheetPreviewFrame { AuditSheetContent(onConfirm = {}) }
    }
}
