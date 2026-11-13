package io.aiku.droid.ui.goodsout

import androidx.annotation.StringRes
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.NoteField
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.goodsout.extra.HintText
import io.aiku.droid.ui.goodsout.extra.PlainTextField

internal const val NOTE_MAX_LENGTH = 4000

internal fun DeliveryNote.noteText(field: NoteField): String? = when (field) {
    NoteField.Warehouse -> notes.warehouse.text
    NoteField.Shipping -> notes.shipping.text
}

@StringRes
internal fun noteTitle(field: NoteField): Int = when (field) {
    NoteField.Warehouse -> R.string.goodsout_note_warehouse
    NoteField.Shipping -> R.string.goodsout_note_shipping_label
}

@Composable
internal fun EditNoteSheet(
    note: DeliveryNote,
    field: NoteField,
    isWorking: Boolean,
    problem: String?,
    onSave: (String) -> Unit,
    onDismiss: () -> Unit,
) {
    var text by remember { mutableStateOf(note.noteText(field).orEmpty()) }

    AikuBottomSheet(
        title = stringResource(noteTitle(field)),
        subtitle = stringResource(R.string.goodsout_delivery_note_reference, note.reference),
        icon = R.drawable.ic_ui_edit,
        onDismiss = onDismiss,
        footer = {
            ActionProblem(problem)
            AikuButton(
                text = stringResource(R.string.goodsout_note_save),
                onClick = { onSave(text) },
                isLoading = isWorking,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
            AikuButton(
                text = stringResource(R.string.dialog_cancel),
                onClick = onDismiss,
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        },
    ) {
        PlainTextField(
            value = text,
            onValueChange = { text = it.take(NOTE_MAX_LENGTH) },
            placeholder = stringResource(R.string.goodsout_note_placeholder),
            isMultiLine = true,
            minHeight = 120.dp,
        )
        HintText(text = stringResource(R.string.goodsout_note_edit_hint))
    }
}
