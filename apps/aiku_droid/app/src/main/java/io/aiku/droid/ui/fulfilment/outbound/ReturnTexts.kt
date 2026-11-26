package io.aiku.droid.ui.fulfilment.outbound

import android.content.res.Resources
import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNoteAction
import io.aiku.droid.data.api.PalletReturn
import io.aiku.droid.data.api.PalletReturnActionKey
import io.aiku.droid.data.api.PalletReturnScanStatus
import io.aiku.droid.data.api.PalletReturnWrite
import io.aiku.droid.ui.components.ConfirmDialog
import io.aiku.droid.ui.components.forConfirm

internal fun actionLabel(actions: List<DeliveryNoteAction>, key: String): String? = actions.firstOrNull { it.key == key }?.label?.takeIf { it.isNotBlank() }

internal fun returnDoneText(action: Any, palletReturn: PalletReturn, resources: Resources): String? = when (action) {
    PalletReturnWrite.StartPicking -> resources.getString(R.string.fulfilment_out_toast_started, palletReturn.reference)
    PalletReturnWrite.FinishPicking -> resources.getString(R.string.fulfilment_out_toast_picked, palletReturn.reference)
    PalletReturnWrite.Dispatch -> resources.getString(R.string.fulfilment_return_dispatched_title, palletReturn.reference)
    PalletReturnWrite.PrintInserts -> resources.getString(R.string.fulfilment_out_toast_inserts_printed)
    is PalletReturnWrite.PickPallet -> resources.getString(R.string.fulfilment_return_picked_toast, palletLineReference(palletReturn, action.lineId))
    is PalletReturnWrite.PickItem -> itemReference(palletReturn, action.lineId).let { reference ->
        action.quantity?.let { resources.getString(R.string.fulfilment_out_toast_item_picked, it, reference) }
            ?: resources.getString(R.string.fulfilment_return_picked_toast, reference)
    }
    is PalletReturnWrite.NotPicked -> resources.getString(
        R.string.fulfilment_out_toast_not_picked,
        palletReturn.palletLine(action.lineId)?.pallet?.reference ?: itemReference(palletReturn, action.lineId),
    )
    is PalletReturnWrite.Undo -> resources.getString(
        R.string.fulfilment_out_toast_undone,
        palletReturn.palletLine(action.lineId)?.pallet?.reference ?: itemReference(palletReturn, action.lineId),
    )
    is PalletReturnWrite.PickFromOtherPallet -> resources.getQuantityString(R.plurals.fulfilment_out_toast_other_pallet, action.quantity, action.quantity)
    is PalletReturnWrite.SetParcels -> resources.getString(R.string.fulfilment_out_toast_parcels_saved)
    is PalletReturnWrite.AddShipment -> resources.getString(R.string.fulfilment_out_toast_shipment_added)
    is PalletReturnWrite.DeleteShipment -> resources.getString(R.string.fulfilment_out_toast_shipment_deleted)
    is PalletReturnWrite.PrintLabel -> resources.getString(R.string.fulfilment_out_toast_label_printed)
    is PalletReturnWrite.SetLeaving -> resources.getString(if (action.collection) R.string.fulfilment_leaving_toast_collects else R.string.fulfilment_leaving_toast_shipped, palletReturn.reference)
    else -> null
}

internal fun returnOtherText(event: OutboundEvent, resources: Resources): String? = when (event) {
    is OutboundEvent.Scanned -> when (event.status) {
        PalletReturnScanStatus.PICKED -> resources.getString(R.string.fulfilment_return_picked_toast, event.code)
        PalletReturnScanStatus.ALREADY_PICKED -> resources.getString(R.string.fulfilment_out_scan_already_picked, event.code)
        PalletReturnScanStatus.NO_STOCK -> resources.getString(R.string.fulfilment_out_scan_no_stock, event.code)
        PalletReturnScanStatus.WRONG_STATE -> resources.getString(R.string.fulfilment_out_scan_wrong_state)
        else -> resources.getString(R.string.fulfilment_out_not_on_return, event.code)
    }
    is OutboundEvent.PalletFound -> resources.getString(R.string.fulfilment_out_pallet_found, event.reference)
    is OutboundEvent.NotOnReturn -> resources.getString(R.string.fulfilment_out_not_on_return, event.code)
    else -> null
}

private fun palletLineReference(palletReturn: PalletReturn, lineId: Long): String = palletReturn.palletLine(lineId)?.pallet?.reference.orEmpty()

private fun itemReference(palletReturn: PalletReturn, lineId: Long): String = palletReturn.itemLine(lineId)?.first?.storedItem?.reference.orEmpty()

@Composable
internal fun StartPickingDialog(palletReturn: PalletReturn, onConfirm: () -> Unit, onDismiss: () -> Unit) {
    ConfirmDialog(
        title = stringResource(R.string.fulfilment_out_start_picking_title, palletReturn.reference),
        body = stringResource(R.string.fulfilment_out_start_picking_body),
        confirmLabel = actionLabel(palletReturn.availableActions, PalletReturnActionKey.START_PICKING) ?: stringResource(R.string.fulfilment_out_start_picking),
        confirmLook = fulfilmentOutboundActionLook(PalletReturnActionKey.START_PICKING).forConfirm(),
        onConfirm = onConfirm,
        onDismiss = onDismiss,
    )
}

@Composable
internal fun FinishPickingDialog(palletReturn: PalletReturn, onConfirm: () -> Unit, onDismiss: () -> Unit) {
    ConfirmDialog(
        title = stringResource(R.string.fulfilment_out_set_as_picked_title, palletReturn.reference),
        body = stringResource(R.string.fulfilment_out_set_as_picked_body),
        confirmLabel = actionLabel(palletReturn.availableActions, PalletReturnActionKey.FINISH_PICKING) ?: stringResource(R.string.fulfilment_out_set_as_picked),
        confirmLook = fulfilmentOutboundActionLook(PalletReturnActionKey.FINISH_PICKING).forConfirm(),
        onConfirm = onConfirm,
        onDismiss = onDismiss,
    )
}

@Composable
internal fun DispatchDialog(palletReturn: PalletReturn, onConfirm: () -> Unit, onDismiss: () -> Unit) {
    val body = listOfNotNull(
        stringResource(if (palletReturn.isItems) R.string.fulfilment_out_dispatch_items_body else R.string.fulfilment_out_dispatch_pallets_body),
        palletReturn.platform?.label?.takeIf { it.isNotBlank() }?.let { stringResource(R.string.fulfilment_out_dispatch_platform_body, it) },
    ).joinToString(" ")

    ConfirmDialog(
        title = stringResource(R.string.fulfilment_out_dispatch_title, palletReturn.reference),
        body = body,
        confirmLabel = actionLabel(palletReturn.availableActions, PalletReturnActionKey.DISPATCH) ?: stringResource(R.string.fulfilment_return_dispatch),
        confirmLook = fulfilmentOutboundActionLook(PalletReturnActionKey.DISPATCH).forConfirm(),
        onConfirm = onConfirm,
        onDismiss = onDismiss,
    )
}
