package io.aiku.droid.ui.goodsout

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteLine

@Composable
fun DeliveryNotePickingScreen(note: DeliveryNoteViewModel, reference: String) {
    DeliveryNoteScreen(note = note, reference = reference)
}

internal val DeliveryNote.nextLineToPick: DeliveryNoteLine?
    get() = lines.firstOrNull { it.quantityToPick > 0.0 && it.orgStock != null }

@Composable
internal fun PickingScanCard(note: DeliveryNote, onClick: () -> Unit) {
    val next = note.nextLineToPick

    if (next == null) {
        ScanHintCard(
            title = stringResource(R.string.goodsout_scan_to_pick),
            caption = stringResource(R.string.goodsout_scan_listening),
            value = stringResource(R.string.goodsout_all_lines_handled),
            detail = stringResource(R.string.goodsout_scan_nothing_left),
            onClick = onClick,
        )
        return
    }
    val code = next.orgStock?.code.orEmpty()
    val left = quantityText(next.quantityToPick, next.quantityToPickLabel)

    ScanHintCard(
        title = stringResource(R.string.goodsout_scan_to_pick),
        caption = stringResource(R.string.goodsout_scan_take_from),
        value = next.defaultLocation()?.code?.takeIf { it.isNotBlank() } ?: code,
        detail = stringResource(R.string.goodsout_scan_card_detail, code, pluralStringResource(R.plurals.goodsout_left_on_item_label, pluralQuantity(next.quantityToPick), left)),
        onClick = onClick,
    )
}
