package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableDoubleStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteLine
import io.aiku.droid.data.api.DeliveryNoteWrite
import io.aiku.droid.data.api.LineBox
import io.aiku.droid.data.api.WriteErrorKey
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.QuantityStepper
import io.aiku.droid.ui.theme.LocalAikuColors
import java.math.BigDecimal
import java.math.RoundingMode
import kotlin.math.floor

private const val MAX_BOX_NUMBER = 999
private const val QUANTITY_DECIMALS = 3
private const val QUANTITY_TOLERANCE = 0.0005

private enum class PackLineStep { Main, ConfirmUnpack }

private enum class PackLineWrite { Pack, Boxes }

internal data class BoxInput(val box: String, val quantity: String)

internal enum class BoxProblem { BoxNumber, Quantity, OverPicked }

internal sealed interface BoxCheck {
    data class Valid(val boxes: List<LineBox>) : BoxCheck
    data class Invalid(val problems: Set<BoxProblem>) : BoxCheck
}

internal fun roundedQuantity(value: Double): Double = BigDecimal.valueOf(value).setScale(QUANTITY_DECIMALS, RoundingMode.HALF_UP).toDouble()

internal fun smallestPackAmount(left: Double): Double {
    val fraction = roundedQuantity(left - floor(left))
    return when {
        left <= 1.0 -> left
        fraction > 0.0 -> fraction
        else -> 1.0
    }
}

internal fun checkBoxes(inputs: List<BoxInput>, picked: Double): BoxCheck {
    val problems = mutableSetOf<BoxProblem>()
    val boxes = inputs.map { input ->
        val box = input.box.trim().toIntOrNull()
        val quantity = parseDecimal(input.quantity)
        if (box == null || box < 1 || box > MAX_BOX_NUMBER) {
            problems += BoxProblem.BoxNumber
        }
        if (quantity == null || quantity < 0.0) {
            problems += BoxProblem.Quantity
        }
        LineBox(box = box ?: 0, quantity = quantity ?: 0.0)
    }
    if (boxes.sumOf { it.quantity } > picked + QUANTITY_TOLERANCE) {
        problems += BoxProblem.OverPicked
    }
    return if (problems.isEmpty()) BoxCheck.Valid(boxes) else BoxCheck.Invalid(problems)
}

@Composable
internal fun PackLineSheet(
    note: DeliveryNote,
    line: DeliveryNoteLine,
    isWorking: Boolean,
    problem: String?,
    writeErrors: Map<String, List<String>>,
    onAct: (DeliveryNoteWrite) -> Unit,
    onDismiss: () -> Unit,
) {
    var step by remember { mutableStateOf(PackLineStep.Main) }
    val code = line.orgStock?.code?.takeIf { it.isNotBlank() } ?: stringResource(R.string.goodsout_line_unknown_sko)

    when (step) {
        PackLineStep.ConfirmUnpack -> ActionConfirmSheet(
            title = stringResource(R.string.goodsout_unpack_line_title, code),
            subtitle = stringResource(R.string.goodsout_line_packed_of, quantityText(line.quantityPacked, line.quantityPackedLabel), quantityText(line.quantityPicked, line.quantityPickedLabel)),
            confirmLabel = stringResource(R.string.goodsout_unpack),
            icon = R.drawable.ic_ui_undo,
            confirmStyle = ButtonStyle.Danger,
            hint = stringResource(if (note.stage == DeliveryNoteStage.Packed) R.string.goodsout_unpack_line_hint_packed else R.string.goodsout_unpack_line_hint),
            isWorking = isWorking,
            problem = problem,
            onConfirm = { onAct(DeliveryNoteWrite.UnpackLine(lineId = line.id)) },
            onDismiss = { step = PackLineStep.Main },
        )
        PackLineStep.Main -> PackLineContent(
            note = note,
            line = line,
            code = code,
            isWorking = isWorking,
            problem = problem,
            writeErrors = writeErrors,
            onAct = onAct,
            onUnpack = { step = PackLineStep.ConfirmUnpack },
            onDismiss = onDismiss,
        )
    }
}

@Composable
private fun PackLineContent(
    note: DeliveryNote,
    line: DeliveryNoteLine,
    code: String,
    isWorking: Boolean,
    problem: String?,
    writeErrors: Map<String, List<String>>,
    onAct: (DeliveryNoteWrite) -> Unit,
    onUnpack: () -> Unit,
    onDismiss: () -> Unit,
) {
    var pending by remember { mutableStateOf<PackLineWrite?>(null) }
    val canPack = note.stage == DeliveryNoteStage.Packing && line.quantityToPack > 0.0
    val canEditBoxes = note.boxPackingList != null && line.quantityPicked > 0.0

    AikuBottomSheet(
        title = stringResource(
            R.string.goodsout_pack_line_title,
            code,
            quantityText(line.quantityPacked, line.quantityPackedLabel),
            quantityText(line.quantityPicked, line.quantityPickedLabel),
        ),
        subtitle = line.orgStock?.name?.takeIf { it.isNotBlank() },
        onDismiss = onDismiss,
        footer = {
            ActionProblem(problem)
            AikuButton(
                text = stringResource(R.string.goodsout_close),
                onClick = onDismiss,
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        },
    ) {
        if (canPack) {
            PackAmount(
                line = line,
                isWorking = isWorking && pending == PackLineWrite.Pack,
                onPack = { quantity ->
                    pending = PackLineWrite.Pack
                    onAct(DeliveryNoteWrite.Pack(lineId = line.id, quantity = quantity))
                },
            )
        }
        if (line.quantityPacked > 0.0) {
            AikuButton(
                text = stringResource(R.string.goodsout_unpack),
                onClick = onUnpack,
                style = ButtonStyle.Outline,
                icon = R.drawable.ic_ui_undo,
                height = 48.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
        if (canEditBoxes) {
            BoxesEditor(
                line = line,
                serverMessages = writeErrors.filterKeys { it == WriteErrorKey.BOXES || it.startsWith(WriteErrorKey.BOXES + ".") }.values.flatten(),
                isWorking = isWorking && pending == PackLineWrite.Boxes,
                onSave = { boxes ->
                    pending = PackLineWrite.Boxes
                    onAct(DeliveryNoteWrite.SetBoxes(lineId = line.id, boxes = boxes))
                },
            )
        }
    }
}

@Composable
private fun PackAmount(line: DeliveryNoteLine, isWorking: Boolean, onPack: (Double?) -> Unit) {
    val left = line.quantityToPack
    var amount by remember(line.id, left) { mutableDoubleStateOf(left) }
    val isAll = amount >= left - QUANTITY_TOLERANCE
    val amountText = if (isAll) quantityText(left, line.quantityToPackLabel) else quantityText(amount)

    QuantityStepper(
        value = amount,
        valueText = amountText,
        onValueChange = { amount = roundedQuantity(it) },
        min = smallestPackAmount(left),
        max = left,
        helper = pluralStringResource(R.plurals.goodsout_left_to_pack_label, pluralQuantity(left), quantityText(left, line.quantityToPackLabel)),
    )
    AikuButton(
        text = stringResource(R.string.goodsout_pack_quantity, amountText),
        onClick = { onPack(if (isAll) null else amount) },
        icon = R.drawable.ic_ui_packed,
        isLoading = isWorking,
        height = 52.dp,
        modifier = Modifier.fillMaxWidth(),
    )
}

@Composable
private fun BoxesEditor(line: DeliveryNoteLine, serverMessages: List<String>, isWorking: Boolean, onSave: (List<LineBox>) -> Unit) {
    val colors = LocalAikuColors.current
    val saved = remember(line.boxes) { line.boxes.map { BoxInput(box = it.box.toString(), quantity = editableNumber(it.quantity)) } }
    var inputs by remember(line.boxes) { mutableStateOf(saved.ifEmpty { listOf(BoxInput(box = "1", quantity = editableNumber(line.quantityPicked))) }) }
    var problems by remember(line.boxes) { mutableStateOf(emptySet<BoxProblem>()) }
    val update: (List<BoxInput>) -> Unit = {
        inputs = it
        problems = emptySet()
    }
    val messages = problems.map { boxProblemText(it, line) } + serverMessages

    GroupLabel(text = stringResource(R.string.goodsout_boxes))
    inputs.forEachIndexed { index, input ->
        Row(verticalAlignment = Alignment.Bottom, horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            ParcelInputField(
                label = stringResource(R.string.goodsout_box_number),
                value = input.box,
                isWholeNumber = true,
                isError = BoxProblem.BoxNumber in problems && input.box.trim().toIntOrNull()?.let { it in 1..MAX_BOX_NUMBER } != true,
                onValueChange = { text -> update(inputs.mapIndexed { position, current -> if (position == index) current.copy(box = text) else current }) },
                modifier = Modifier.weight(1f),
            )
            ParcelInputField(
                label = stringResource(R.string.goodsout_box_quantity),
                value = input.quantity,
                isWholeNumber = false,
                isError = BoxProblem.Quantity in problems && parseDecimal(input.quantity) == null,
                onValueChange = { text -> update(inputs.mapIndexed { position, current -> if (position == index) current.copy(quantity = text) else current }) },
                modifier = Modifier.weight(1f),
            )
            RemoveBoxButton(
                description = stringResource(R.string.goodsout_box_remove, input.box.ifBlank { (index + 1).toString() }),
                onClick = { update(inputs.filterIndexed { position, _ -> position != index }) },
            )
        }
    }
    Text(
        text = stringResource(R.string.goodsout_box_add),
        fontSize = 14.sp,
        fontWeight = FontWeight.SemiBold,
        color = colors.link,
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 44.dp)
            .clickable(role = Role.Button) {
                val next = (inputs.mapNotNull { it.box.trim().toIntOrNull() }.maxOrNull() ?: 0) + 1
                update(inputs + BoxInput(box = next.coerceAtMost(MAX_BOX_NUMBER).toString(), quantity = ""))
            }
            .padding(vertical = 12.dp),
    )
    messages.forEach { message ->
        Text(text = message, fontSize = 12.sp, color = colors.danger)
    }
    if (inputs != saved) {
        AikuButton(
            text = stringResource(R.string.goodsout_boxes_save),
            onClick = {
                when (val result = checkBoxes(inputs, line.quantityPicked)) {
                    is BoxCheck.Valid -> onSave(result.boxes)
                    is BoxCheck.Invalid -> problems = result.problems
                }
            },
            style = ButtonStyle.Soft,
            isLoading = isWorking,
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}

@Composable
private fun boxProblemText(problem: BoxProblem, line: DeliveryNoteLine): String = when (problem) {
    BoxProblem.BoxNumber -> stringResource(R.string.goodsout_box_number_invalid, MAX_BOX_NUMBER)
    BoxProblem.Quantity -> stringResource(R.string.goodsout_box_quantity_invalid)
    BoxProblem.OverPicked -> stringResource(R.string.goodsout_box_over_picked, quantityText(line.quantityPicked, line.quantityPickedLabel))
}

@Composable
private fun RemoveBoxButton(description: String, onClick: () -> Unit) {
    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(44.dp)
            .clip(CircleShape)
            .clickable(role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Icon(painter = painterResource(R.drawable.ic_ui_close), contentDescription = null, tint = LocalAikuColors.current.textTertiary, modifier = Modifier.size(18.dp))
    }
}

@Composable
internal fun lineBoxesText(line: DeliveryNoteLine): String? {
    if (line.boxes.isEmpty()) {
        return null
    }
    val boxes = line.boxes.map { stringResource(R.string.goodsout_line_box, it.box, editableNumber(it.quantity)) }
    return stringResource(R.string.goodsout_line_boxes, boxes.joinToString(", "))
}

internal fun DeliveryNoteLine.canBeOpenedForPacking(note: DeliveryNote): Boolean =
    note.canPackLines && ((note.stage == DeliveryNoteStage.Packing && quantityToPack > 0.0) || quantityPacked > 0.0 || (note.boxPackingList != null && quantityPicked > 0.0))
