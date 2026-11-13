package io.aiku.droid.ui.goodsout

import androidx.annotation.StringRes
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableDoubleStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteLine
import io.aiku.droid.data.api.DeliveryNoteWrite
import io.aiku.droid.data.api.LineLocation
import io.aiku.droid.data.api.LinePicking
import io.aiku.droid.data.api.NotPickedReason
import io.aiku.droid.data.api.WaitingKind
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.QuantityStepper
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.goodsout.extra.FieldLabel
import io.aiku.droid.ui.goodsout.extra.PlainTextField
import io.aiku.droid.ui.goodsout.extra.UndoPickingSheet
import io.aiku.droid.ui.goodsout.extra.WaitingLineSheet

private enum class LineWrite { PickAll, Pick, NotPicked }

private sealed interface LineStep {
    data object Actions : LineStep
    data object Location : LineStep
    data class Waiting(val kind: WaitingKind) : LineStep
    data class Undo(val picking: LinePicking) : LineStep
}

@StringRes
internal fun NotPickedReason.label(): Int = when (this) {
    NotPickedReason.OutOfStock -> R.string.goodsout_reason_out_of_stock
    NotPickedReason.CancelledByCustomer -> R.string.goodsout_reason_cancelled_by_customer
    NotPickedReason.CancelledByWarehouse -> R.string.goodsout_reason_cancelled_by_warehouse
    NotPickedReason.NotApplicable -> R.string.goodsout_reason_not_applicable
}

internal fun DeliveryNoteLine.defaultLocation(): LineLocation? =
    locations.firstOrNull { (it.quantityPicked ?: 0.0) > 0.0 } ?: locations.firstOrNull { (it.stock ?: 0.0) > 0.0 } ?: locations.firstOrNull()

@Composable
internal fun LineActionsSheet(
    note: DeliveryNote,
    line: DeliveryNoteLine,
    isWorking: Boolean,
    problem: String?,
    onAct: (DeliveryNoteWrite) -> Unit,
    onDismiss: () -> Unit,
) {
    var step by remember { mutableStateOf<LineStep>(LineStep.Actions) }
    var locationId by remember { mutableStateOf(line.defaultLocation()?.locationOrgStockId) }
    val location = line.locations.firstOrNull { it.locationOrgStockId == locationId } ?: line.defaultLocation()

    when (val shown = step) {
        LineStep.Actions -> LineActionsContent(
            note = note,
            line = line,
            location = location,
            isWorking = isWorking,
            problem = problem,
            onAct = onAct,
            onChooseLocation = { step = LineStep.Location },
            onWaiting = { step = LineStep.Waiting(it) },
            onUndo = { step = LineStep.Undo(it) },
            onDismiss = onDismiss,
        )
        LineStep.Location -> PickLocationSheet(
            line = line,
            selectedId = location?.locationOrgStockId,
            onChoose = { chosen ->
                locationId = chosen
                step = LineStep.Actions
            },
            onBack = { step = LineStep.Actions },
        )
        is LineStep.Waiting -> WaitingLineSheet(
            reference = note.reference,
            line = line,
            initialKind = shown.kind,
            isWorking = isWorking,
            problem = problem,
            onConfirm = { kind, quantity, waitingNote -> onAct(waitingWrite(line, kind, quantity, waitingNote)) },
            onBack = { step = LineStep.Actions },
        )
        is LineStep.Undo -> UndoPickingSheet(
            code = line.orgStock?.code.orEmpty(),
            picking = shown.picking,
            isWorking = isWorking,
            problem = problem,
            onConfirm = { onAct(DeliveryNoteWrite.UndoPicking(lineId = line.id, pickingId = shown.picking.id)) },
            onBack = { step = LineStep.Actions },
        )
    }
}

internal fun waitingWrite(line: DeliveryNoteLine, kind: WaitingKind, quantity: Double, note: String?): DeliveryNoteWrite.Waiting =
    DeliveryNoteWrite.Waiting(
        lineId = line.id,
        kind = kind,
        quantity = if (kind == WaitingKind.Warehouse) quantity + line.quantityWaitingWarehouse else quantity,
        note = note,
    )

@Composable
private fun LineActionsContent(
    note: DeliveryNote,
    line: DeliveryNoteLine,
    location: LineLocation?,
    isWorking: Boolean,
    problem: String?,
    onAct: (DeliveryNoteWrite) -> Unit,
    onChooseLocation: () -> Unit,
    onWaiting: (WaitingKind) -> Unit,
    onUndo: (LinePicking) -> Unit,
    onDismiss: () -> Unit,
) {
    val code = line.orgStock?.code?.takeIf { it.isNotBlank() } ?: stringResource(R.string.goodsout_line_unknown_sko)
    val toPick = line.quantityToPick
    val toPickText = quantityText(toPick, line.quantityToPickLabel)
    val hasLeft = toPick > 0.0
    val settings = note.settings
    var reason by remember { mutableStateOf(NotPickedReason.OutOfStock) }
    var notPickedNote by remember { mutableStateOf("") }
    var pending by remember { mutableStateOf<LineWrite?>(null) }

    AikuBottomSheet(
        title = stringResource(
            R.string.goodsout_line_sheet_title,
            code,
            quantityText(line.quantityPicked, line.quantityPickedLabel),
            quantityText(line.quantityRequired, line.quantityRequiredLabel),
        ),
        subtitle = listOfNotNull(location?.code?.takeIf { it.isNotBlank() }, line.orgStock?.name?.takeIf { it.isNotBlank() }).joinToString(" · ").takeIf { it.isNotEmpty() },
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
        if (hasLeft) {
            AikuButton(
                text = stringResource(R.string.goodsout_line_pick_all, toPickText),
                onClick = {
                    pending = LineWrite.PickAll
                    onAct(DeliveryNoteWrite.PickAll(lineId = line.id, locationOrgStockId = location?.locationOrgStockId))
                },
                icon = R.drawable.ic_ui_check,
                isLoading = isWorking && pending == LineWrite.PickAll,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
        if (location?.locationOrgStockId != null) {
            PickAtLocation(
                line = line,
                location = location,
                canChooseLocation = line.locations.size > 1,
                isWorking = isWorking && pending == LineWrite.Pick,
                onChooseLocation = onChooseLocation,
                onSave = { amount ->
                    pending = LineWrite.Pick
                    onAct(DeliveryNoteWrite.Pick(lineId = line.id, locationOrgStockId = location.locationOrgStockId, quantity = amount))
                },
            )
        }
        if (hasLeft && settings.allowPickerSetNotPicked) {
            GroupLabel(text = stringResource(R.string.goodsout_not_picked_reason))
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                NotPickedReason.entries.forEach { option ->
                    ChoiceRow(title = stringResource(option.label()), selected = option == reason, onClick = { reason = option })
                }
            }
            PlainTextField(
                value = notPickedNote,
                onValueChange = { notPickedNote = it },
                placeholder = stringResource(R.string.goodsout_note_optional),
            )
            AikuButton(
                text = stringResource(R.string.goodsout_line_mark_not_picked, toPickText),
                onClick = {
                    pending = LineWrite.NotPicked
                    onAct(DeliveryNoteWrite.NotPicked(lineId = line.id, quantity = null, reason = reason, note = notPickedNote))
                },
                style = ButtonStyle.Danger,
                isLoading = isWorking && pending == LineWrite.NotPicked,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
        if (hasLeft && settings.allowWaiting) {
            GroupLabel(text = stringResource(R.string.goodsout_cant_finish_line))
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                AikuButton(
                    text = stringResource(R.string.goodsout_set_as_waiting),
                    onClick = { onWaiting(WaitingKind.Warehouse) },
                    style = ButtonStyle.Outline,
                    icon = R.drawable.ic_ui_pause,
                    height = 52.dp,
                    modifier = Modifier.weight(1f),
                )
                AikuButton(
                    text = stringResource(R.string.goodsout_pass_to_cs),
                    onClick = { onWaiting(WaitingKind.Crm) },
                    style = ButtonStyle.Outline,
                    height = 52.dp,
                    modifier = Modifier.weight(1f),
                )
            }
        }
        if (line.pickings.isNotEmpty()) {
            GroupLabel(text = stringResource(R.string.goodsout_line_pickings))
            ListGroup {
                line.pickings.forEachIndexed { index, picking ->
                    if (index > 0) {
                        RowDivider()
                    }
                    IconInfoRow(
                        icon = if (picking.isNotPicked) R.drawable.ic_ui_warning else R.drawable.ic_ui_check,
                        tone = if (picking.isNotPicked) Tone.Danger else Tone.Success,
                        title = pickingText(picking),
                        subtitle = pickingDetail(picking).orEmpty(),
                        actionLabel = stringResource(R.string.goodsout_undo),
                        onAction = { onUndo(picking) },
                    )
                }
            }
        }
    }
}

@Composable
private fun PickAtLocation(
    line: DeliveryNoteLine,
    location: LineLocation,
    canChooseLocation: Boolean,
    isWorking: Boolean,
    onChooseLocation: () -> Unit,
    onSave: (Double) -> Unit,
) {
    val current = location.quantityPicked ?: 0.0
    val maximum = current + line.quantityToPick
    var amount by remember(location.locationOrgStockId, current, maximum) { mutableDoubleStateOf(current) }
    val stock = location.stockLabel?.takeIf { it.isNotBlank() } ?: location.stock?.let { quantityText(it) }
    if (maximum <= 0.0) {
        return
    }

    GroupLabel(text = stringResource(R.string.goodsout_line_pick_at))
    ListGroup {
        IconInfoRow(
            icon = R.drawable.ic_ui_location,
            title = location.code,
            subtitle = stock?.let { stringResource(R.string.goodsout_location_in_stock, it) }.orEmpty(),
            actionLabel = if (canChooseLocation) stringResource(R.string.goodsout_change) else null,
            onAction = if (canChooseLocation) onChooseLocation else null,
        )
    }
    QuantityStepper(
        value = amount,
        valueText = quantityText(amount),
        onValueChange = { amount = it },
        min = 0.0,
        max = maximum,
        helper = stringResource(R.string.goodsout_line_pick_at_helper, location.code),
    )
    if (amount != current) {
        AikuButton(
            text = stringResource(R.string.goodsout_line_save_pick, quantityText(amount), location.code),
            onClick = { onSave(amount) },
            style = ButtonStyle.Soft,
            isLoading = isWorking,
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}

@Composable
internal fun pickingText(picking: LinePicking): String {
    val quantity = quantityText(picking.quantity, picking.quantityLabel)
    val location = picking.locationCode?.takeIf { it.isNotBlank() }
    return when {
        picking.isNotPicked -> stringResource(R.string.goodsout_line_some_not_picked, quantity)
        location != null -> stringResource(R.string.goodsout_picking_from, quantity, location)
        else -> stringResource(R.string.goodsout_picking_picked, quantity)
    }
}

internal fun pickingDetail(picking: LinePicking): String? =
    if (picking.isNotPicked) picking.notPickedReasonLabel?.takeIf { it.isNotBlank() } else null
