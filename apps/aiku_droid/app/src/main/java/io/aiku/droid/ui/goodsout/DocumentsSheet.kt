package io.aiku.droid.ui.goodsout

import androidx.annotation.StringRes
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.goodsout.extra.HintText

internal object DocumentKey {
    const val DELIVERY_NOTE = "delivery-note"
    const val PACKING_LIST = "packing-list"
    const val GIFT_MESSAGE = "gift-message"
}

internal data class NoteDocument(val key: String, @StringRes val title: Int, val url: String)

internal val DeliveryNote.documentList: List<NoteDocument>
    get() = listOfNotNull(
        documents.deliveryNotePdfUrl?.takeIf { it.isNotBlank() }?.let { NoteDocument(key = DocumentKey.DELIVERY_NOTE, title = R.string.goodsout_document_delivery_note, url = it) },
        boxPackingList?.pdfUrl?.takeIf { it.isNotBlank() }?.let { NoteDocument(key = DocumentKey.PACKING_LIST, title = R.string.goodsout_document_packing_list, url = it) },
        documents.giftMessagePdfUrl?.takeIf { it.isNotBlank() }?.let { NoteDocument(key = DocumentKey.GIFT_MESSAGE, title = R.string.goodsout_document_gift_message, url = it) },
    )

internal fun documentsFolder(note: DeliveryNote): String = "documents-${note.id}"

internal fun documentFileName(note: DeliveryNote, key: String): String = "${note.reference.ifBlank { note.id.toString() }}-$key.pdf"

@Composable
internal fun DocumentsSheet(note: DeliveryNote, openingKey: String?, onOpen: (NoteDocument) -> Unit, onDismiss: () -> Unit) {
    val documents = note.documentList
    val openLabel = stringResource(R.string.goodsout_open_pdf)
    val openingLabel = stringResource(R.string.goodsout_opening)

    AikuBottomSheet(
        title = stringResource(R.string.goodsout_documents),
        subtitle = stringResource(R.string.goodsout_delivery_note_reference, note.reference),
        icon = R.drawable.ic_ui_document,
        onDismiss = onDismiss,
        footer = {
            AikuButton(
                text = stringResource(R.string.goodsout_close),
                onClick = onDismiss,
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        },
    ) {
        if (documents.isEmpty()) {
            EmptyChoices(text = stringResource(R.string.goodsout_documents_none))
        } else {
            ListGroup {
                documents.forEachIndexed { index, document ->
                    if (index > 0) {
                        RowDivider()
                    }
                    IconInfoRow(
                        icon = R.drawable.ic_ui_document,
                        title = stringResource(document.title),
                        subtitle = "",
                        actionLabel = if (openingKey == document.key) openingLabel else openLabel,
                        onAction = { onOpen(document) },
                        onClick = { onOpen(document) },
                    )
                }
            }
        }
        HintText(text = stringResource(R.string.goodsout_documents_hint))
    }
}
