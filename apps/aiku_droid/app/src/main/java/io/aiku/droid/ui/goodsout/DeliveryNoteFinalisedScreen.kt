package io.aiku.droid.ui.goodsout

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.Tone

@Composable
fun DeliveryNoteFinalisedScreen(note: DeliveryNoteViewModel, reference: String) {
    DeliveryNoteScreen(note = note, reference = reference)
}

@Composable
internal fun shipmentSummary(note: DeliveryNote): String {
    val shippers = note.shipments.mapNotNull { it.shipper?.takeIf { name -> name.isNotBlank() } }.distinct()
    val parcelCount = note.parcels.size
    return listOfNotNull(
        when {
            note.isCollection -> stringResource(R.string.goodsout_collection)
            shippers.isNotEmpty() -> shippers.joinToString(", ")
            else -> null
        },
        parcelCount.takeIf { it > 0 }?.let { pluralStringResource(R.plurals.goodsout_parcel_count, it, it) },
        weightText(note.parcelWeightKg() ?: note.summary.estimatedWeightKg),
    ).joinToString(" · ")
}

@Composable
internal fun FinalisedSummary(note: DeliveryNote) {
    val itemsToDispatch = note.lines.count { it.quantityPacked > 0 }
    val notPicked = note.lines.count { it.quantityNotPicked > 0 }

    SummaryCard(
        title = stringResource(if (note.isCollection) R.string.goodsout_ready_to_collect else R.string.goodsout_ready_to_dispatch),
        body = shipmentSummary(note).ifEmpty { stringResource(R.string.goodsout_no_shipment_yet) },
    )
    if (note.lines.isNotEmpty()) {
        ListGroup {
            IconInfoRow(
                icon = R.drawable.ic_ui_check,
                title = pluralStringResource(R.plurals.goodsout_items_to_dispatch, itemsToDispatch, itemsToDispatch),
                subtitle = if (notPicked > 0) pluralStringResource(R.plurals.goodsout_items_not_picked, notPicked, notPicked) else "",
                tone = Tone.Success,
            )
        }
    }
}
