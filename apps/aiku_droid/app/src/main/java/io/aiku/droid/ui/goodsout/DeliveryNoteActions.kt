package io.aiku.droid.ui.goodsout

import androidx.annotation.DrawableRes
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteAction
import io.aiku.droid.data.api.DeliveryNoteWrite
import io.aiku.droid.data.api.IncompleteSetPart
import io.aiku.droid.data.api.NoteActionKey
import io.aiku.droid.data.api.NoteField
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.goodsout.extra.HintText
import io.aiku.droid.ui.goodsout.extra.KICKER_SEPARATOR
import io.aiku.droid.ui.navigation.AddShipmentRoute
import io.aiku.droid.ui.navigation.ExternalShipmentRoute
import io.aiku.droid.ui.theme.LocalAikuColors

private val footerActionKeys = listOf(
    NoteActionKey.SET_AS_PACKED,
    NoteActionKey.START_PACKING,
    NoteActionKey.ADD_SHIPMENT,
    NoteActionKey.GET_EXTERNAL_SHIPMENT,
    NoteActionKey.FINALISE_AND_DISPATCH,
    NoteActionKey.FINALISE,
    NoteActionKey.DISPATCH,
    NoteActionKey.PUT_BACK_INCOMPLETE_SETS,
    NoteActionKey.TAKE_OVER,
    NoteActionKey.START_PICKING,
    NoteActionKey.RESUME_PICKING,
    NoteActionKey.SET_AS_PICKED,
    NoteActionKey.AUTO_FINISH_WAITING,
    NoteActionKey.BACK_TO_PICKING,
)

private val menuActionKeys = listOf(
    NoteActionKey.ASSIGN_PICKER,
    NoteActionKey.REMOVE_PICKER,
    NoteActionKey.CHANGE_PACKER,
    NoteActionKey.CHANGE_TROLLEY,
    NoteActionKey.CHANGE_PICKED_BAY,
    NoteActionKey.UNDO_SET_AS_PICKED,
    NoteActionKey.UNDO_PACKING,
    NoteActionKey.UNPACK,
    NoteActionKey.CANCEL,
)

internal val DeliveryNote.footerActions: List<DeliveryNoteAction>
    get() = footerActionKeys.mapNotNull(::action).filterNot { it.key == NoteActionKey.ADD_SHIPMENT && shipments.isNotEmpty() }

internal val DeliveryNote.menuActions: List<DeliveryNoteAction> get() = menuActionKeys.mapNotNull(::action)

internal val DeliveryNote.addAnotherShipment: DeliveryNoteAction?
    get() = action(NoteActionKey.ADD_SHIPMENT)?.takeIf { shipments.isNotEmpty() }

internal sealed interface FooterItem {
    val action: DeliveryNoteAction

    data class Button(override val action: DeliveryNoteAction, val isPrimary: Boolean) : FooterItem
    data class Notice(override val action: DeliveryNoteAction) : FooterItem
}

internal val DeliveryNote.footerItems: List<FooterItem>
    get() {
        val actions = footerActions
        val primary = actions.firstOrNull { !it.isDisabled }
        return actions.map { action -> if (action.isDisabled) FooterItem.Notice(action) else FooterItem.Button(action, isPrimary = action == primary) }
    }

internal sealed interface NoteSheet {
    data class Trolley(val action: DeliveryNoteAction) : NoteSheet
    data class PickedBay(val action: DeliveryNoteAction) : NoteSheet
    data object Picker : NoteSheet
    data class Packer(val action: DeliveryNoteAction) : NoteSheet
    data class Cancel(val action: DeliveryNoteAction) : NoteSheet
    data class Address(val action: DeliveryNoteAction) : NoteSheet
    data class EditNote(val field: NoteField) : NoteSheet
    data class Confirm(val action: DeliveryNoteAction) : NoteSheet
    data class Line(val lineId: Long) : NoteSheet
    data object Documents : NoteSheet
    data class DeleteShipment(val shipmentId: Long) : NoteSheet
}

internal fun DeliveryNoteAction.sheet(): NoteSheet = when (key) {
    NoteActionKey.START_PICKING, NoteActionKey.CHANGE_TROLLEY -> NoteSheet.Trolley(this)
    NoteActionKey.SET_AS_PICKED, NoteActionKey.CHANGE_PICKED_BAY -> NoteSheet.PickedBay(this)
    NoteActionKey.ASSIGN_PICKER, NoteActionKey.REMOVE_PICKER -> NoteSheet.Picker
    NoteActionKey.CHANGE_PACKER -> NoteSheet.Packer(this)
    NoteActionKey.CANCEL -> NoteSheet.Cancel(this)
    NoteActionKey.EDIT_DELIVERY_ADDRESS -> NoteSheet.Address(this)
    else -> NoteSheet.Confirm(this)
}

internal sealed interface ActionStep {
    data class Run(val write: DeliveryNoteWrite) : ActionStep
    data class Sheet(val sheet: NoteSheet) : ActionStep
    data class Open(val route: Any) : ActionStep
    data class Blocked(val reason: String) : ActionStep
}

internal fun DeliveryNote.stepFor(action: DeliveryNoteAction): ActionStep = when {
    action.isDisabled -> ActionStep.Blocked(action.disabledReason.orEmpty())
    action.key == NoteActionKey.RESUME_PICKING -> ActionStep.Run(DeliveryNoteWrite.StartPicking(trolleyId = null))
    action.key == NoteActionKey.START_PICKING && isDropshipping -> ActionStep.Run(DeliveryNoteWrite.StartPicking(trolleyId = null))
    action.key == NoteActionKey.START_PACKING -> ActionStep.Run(DeliveryNoteWrite.StartPacking)
    action.key == NoteActionKey.ADD_SHIPMENT -> ActionStep.Open(AddShipmentRoute(id = id, reference = reference))
    action.key == NoteActionKey.GET_EXTERNAL_SHIPMENT -> ActionStep.Open(ExternalShipmentRoute(id = id, reference = reference))
    else -> ActionStep.Sheet(action.sheet())
}

@DrawableRes
internal fun DeliveryNoteAction.icon(): Int? = when (key) {
    NoteActionKey.TAKE_OVER -> R.drawable.ic_ui_lock
    NoteActionKey.SET_AS_PICKED, NoteActionKey.PUT_BACK_INCOMPLETE_SETS, NoteActionKey.FINALISE -> R.drawable.ic_ui_check
    NoteActionKey.SET_AS_PACKED -> R.drawable.ic_ui_packed
    NoteActionKey.BACK_TO_PICKING, NoteActionKey.UNDO_SET_AS_PICKED, NoteActionKey.UNDO_PACKING, NoteActionKey.UNPACK -> R.drawable.ic_ui_undo
    NoteActionKey.START_PACKING -> R.drawable.ic_ui_box
    NoteActionKey.ADD_SHIPMENT, NoteActionKey.GET_EXTERNAL_SHIPMENT, NoteActionKey.FINALISE_AND_DISPATCH, NoteActionKey.DISPATCH -> R.drawable.ic_ui_truck
    else -> null
}

@Composable
internal fun NoteFooterItems(items: List<FooterItem>, loadingKey: String?, onAction: (DeliveryNoteAction) -> Unit) {
    items.forEach { item ->
        when (item) {
            is FooterItem.Notice -> DisabledActionNotice(action = item.action)
            is FooterItem.Button -> AikuButton(
                text = item.action.label,
                onClick = { onAction(item.action) },
                style = if (item.isPrimary) ButtonStyle.Primary else ButtonStyle.Outline,
                icon = item.action.icon(),
                isLoading = item.action.key == loadingKey,
                height = if (item.isPrimary) 52.dp else 48.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}

@Composable
internal fun DisabledActionNotice(action: DeliveryNoteAction, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = modifier.fillMaxWidth().heightIn(min = 44.dp).semantics(mergeDescendants = true) {},
    ) {
        Icon(painter = painterResource(R.drawable.ic_ui_lock), contentDescription = null, tint = colors.warn, modifier = Modifier.size(20.dp))
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = action.label, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = action.disabledReason.orEmpty(), fontSize = 13.sp, color = colors.warnInk)
        }
    }
}

@Composable
internal fun ActionProblem(problem: String?) {
    problem?.let { InlineAlert(title = it, tone = AlertTone.Danger) }
}

@Composable
internal fun ActionConfirmSheet(
    title: String,
    subtitle: String?,
    confirmLabel: String,
    isWorking: Boolean,
    problem: String?,
    onConfirm: () -> Unit,
    onDismiss: () -> Unit,
    @DrawableRes icon: Int? = null,
    confirmStyle: ButtonStyle = ButtonStyle.Primary,
    cancelLabel: String = stringResource(R.string.dialog_cancel),
    hint: String? = null,
    content: @Composable ColumnScope.() -> Unit = {},
) {
    AikuBottomSheet(
        title = title,
        subtitle = subtitle,
        onDismiss = onDismiss,
        footer = {
            ActionProblem(problem)
            AikuButton(
                text = confirmLabel,
                onClick = onConfirm,
                style = confirmStyle,
                icon = icon,
                isLoading = isWorking,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
            AikuButton(
                text = cancelLabel,
                onClick = onDismiss,
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        },
    ) {
        content()
        hint?.let { HintText(text = it) }
    }
}

@Composable
internal fun TakeOverSheet(note: DeliveryNote, action: DeliveryNoteAction, isWorking: Boolean, problem: String?, onConfirm: () -> Unit, onDismiss: () -> Unit) {
    val isPacking = note.stage == DeliveryNoteStage.Packing
    val handler = (if (isPacking) note.packer ?: note.picker else note.picker)?.name?.takeIf { it.isNotBlank() }
    val subtitle = when {
        handler == null -> stringResource(if (isPacking) R.string.goodsout_no_packer else R.string.goodsout_no_picker)
        isPacking -> stringResource(R.string.goodsout_packed_by, handler)
        else -> stringResource(R.string.goodsout_take_over_picked_by, handler)
    }
    val hint = when {
        handler == null -> stringResource(if (isPacking) R.string.goodsout_take_over_hint_no_packer else R.string.goodsout_take_over_hint_no_picker)
        isPacking -> stringResource(R.string.goodsout_take_over_hint_packing, handler)
        else -> stringResource(R.string.goodsout_take_over_hint, handler)
    }

    ActionConfirmSheet(
        title = stringResource(R.string.goodsout_take_over_title, note.reference),
        subtitle = subtitle,
        confirmLabel = action.label,
        icon = R.drawable.ic_ui_lock,
        hint = hint,
        isWorking = isWorking,
        problem = problem,
        onConfirm = onConfirm,
        onDismiss = onDismiss,
    )
}

@Composable
internal fun PutBackSheet(note: DeliveryNote, action: DeliveryNoteAction, isWorking: Boolean, problem: String?, onConfirm: () -> Unit, onDismiss: () -> Unit) {
    ActionConfirmSheet(
        title = stringResource(R.string.goodsout_put_back_title),
        subtitle = stringResource(R.string.goodsout_delivery_note_reference, note.reference),
        confirmLabel = action.label,
        icon = R.drawable.ic_ui_check,
        hint = stringResource(R.string.goodsout_put_back_hint),
        isWorking = isWorking,
        problem = problem,
        onConfirm = onConfirm,
        onDismiss = onDismiss,
    ) {
        IncompleteSetParts(parts = note.incompleteSets)
    }
}

@Composable
internal fun IncompleteSetParts(parts: List<IncompleteSetPart>, modifier: Modifier = Modifier) {
    if (parts.isEmpty()) {
        return
    }
    ListGroup(modifier = modifier) {
        parts.forEachIndexed { index, part ->
            if (index > 0) {
                RowDivider()
            }
            ItemRow(
                code = part.code.ifBlank { stringResource(R.string.goodsout_line_unknown_sko) },
                subtitle = listOfNotNull(
                    part.name.takeIf { it.isNotBlank() },
                    part.locations.filter { it.isNotBlank() }.takeIf { it.isNotEmpty() }?.let { stringResource(R.string.goodsout_put_back_to, it.joinToString(", ")) },
                ).joinToString(KICKER_SEPARATOR),
                chip = stringResource(R.string.goodsout_put_back_chip),
                chipTone = Tone.Warn,
                done = quantityText(part.quantity, part.quantityLabel),
            )
        }
    }
}

@Composable
internal fun AutoFinishWaitingSheet(note: DeliveryNote, action: DeliveryNoteAction, isWorking: Boolean, problem: String?, onConfirm: () -> Unit, onDismiss: () -> Unit) {
    ActionConfirmSheet(
        title = stringResource(R.string.goodsout_auto_finish_title),
        subtitle = stringResource(R.string.goodsout_delivery_note_reference, note.reference),
        confirmLabel = action.label,
        hint = stringResource(R.string.goodsout_auto_finish_hint),
        isWorking = isWorking,
        problem = problem,
        onConfirm = onConfirm,
        onDismiss = onDismiss,
    )
}

@Composable
internal fun SetAsPackedSheet(note: DeliveryNote, action: DeliveryNoteAction, isWorking: Boolean, problem: String?, onConfirm: () -> Unit, onDismiss: () -> Unit) {
    ActionConfirmSheet(
        title = stringResource(R.string.goodsout_set_as_packed_title, note.reference),
        subtitle = null,
        confirmLabel = action.label,
        icon = R.drawable.ic_ui_packed,
        hint = stringResource(if (note.stage == DeliveryNoteStage.Packing) R.string.goodsout_set_as_packed_packing_hint else R.string.goodsout_set_as_packed_hint),
        isWorking = isWorking,
        problem = problem,
        onConfirm = onConfirm,
        onDismiss = onDismiss,
    )
}

@Composable
internal fun DispatchConfirmSheet(note: DeliveryNote, action: DeliveryNoteAction, isWorking: Boolean, problem: String?, onConfirm: () -> Unit, onDismiss: () -> Unit) {
    val invoices = action.key != NoteActionKey.DISPATCH && note.type != REPLACEMENT_TYPE
    val dispatches = action.key != NoteActionKey.FINALISE
    val effects = listOfNotNull(
        if (invoices) stringResource(R.string.goodsout_finalise_effect) else null,
        if (dispatches) stringResource(R.string.goodsout_dispatch_effect) else null,
    )

    ActionConfirmSheet(
        title = stringResource(R.string.goodsout_action_confirm_title, action.label, note.reference),
        subtitle = shipmentSummary(note).takeIf { it.isNotEmpty() },
        confirmLabel = action.label,
        icon = action.icon(),
        hint = effects.joinToString(" ").takeIf { it.isNotEmpty() },
        isWorking = isWorking,
        problem = problem,
        onConfirm = onConfirm,
        onDismiss = onDismiss,
    )
}
