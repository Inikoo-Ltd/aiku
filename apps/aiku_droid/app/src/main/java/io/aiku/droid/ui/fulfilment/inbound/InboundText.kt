package io.aiku.droid.ui.fulfilment.inbound

import io.aiku.droid.R
import io.aiku.droid.data.api.Pallet
import io.aiku.droid.data.api.PalletDelivery
import io.aiku.droid.data.api.PalletDeliveryWrite
import io.aiku.droid.data.api.PalletIncidentState
import io.aiku.droid.data.api.PalletWrite
import io.aiku.droid.ui.goodsin.GoodsInEvent

internal fun palletDeliveryDoneText(done: GoodsInEvent.Done, delivery: PalletDelivery, text: (Int, Array<out Any>) -> String): String? =
    when (val action = done.action) {
        PalletDeliveryWrite.Receive -> text(R.string.fulfilment_inbound_toast_received, arrayOf(delivery.reference))
        PalletDeliveryWrite.StartBooking -> text(R.string.fulfilment_inbound_toast_booking_started, arrayOf(delivery.reference))
        PalletDeliveryWrite.FinishBooking -> text(R.string.fulfilment_inbound_toast_booked_in, arrayOf(delivery.reference))
        is PalletDeliveryWrite.BookIn -> {
            val pallet = delivery.pallet(action.palletId)
            text(R.string.fulfilment_book_in_booked_toast, arrayOf(pallet?.reference.orEmpty(), pallet?.locationCode ?: action.target.code))
        }
        is PalletDeliveryWrite.NotReceived -> text(R.string.fulfilment_not_received_toast, arrayOf(delivery.pallet(action.palletId)?.reference.orEmpty()))
        is PalletDeliveryWrite.UndoNotReceived -> text(R.string.fulfilment_inbound_toast_undo_not_received, arrayOf(delivery.pallet(action.palletId)?.reference.orEmpty()))
        else -> null
    }

internal fun palletDoneText(done: GoodsInEvent.Done, pallet: Pallet, text: (Int, Array<out Any>) -> String): String? = when (val action = done.action) {
    is PalletWrite.Move -> text(R.string.fulfilment_inbound_toast_moved, arrayOf(pallet.reference, pallet.locationCode ?: action.target.code))
    is PalletWrite.Incident -> text(
        if (action.state == PalletIncidentState.Lost) R.string.fulfilment_incident_lost_toast else R.string.fulfilment_incident_damaged_toast,
        arrayOf(pallet.reference),
    )
    else -> null
}
