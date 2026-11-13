package io.aiku.droid.ui.goodsout.extra

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteAction
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ToggleRow
import io.aiku.droid.ui.goodsout.ActionConfirmSheet

@Composable
internal fun CancelDeliveryNoteSheet(
    note: DeliveryNote,
    action: DeliveryNoteAction,
    isWorking: Boolean,
    problem: String?,
    onConfirm: (createReturn: Boolean) -> Unit,
    onDismiss: () -> Unit,
) {
    var createReturn by remember { mutableStateOf(true) }

    ActionConfirmSheet(
        title = stringResource(R.string.goodsout_extra_cancel_title, note.reference),
        subtitle = stringResource(R.string.goodsout_extra_cancel_subtitle),
        confirmLabel = action.label.ifBlank { stringResource(R.string.goodsout_extra_cancel_delivery_note) },
        confirmStyle = ButtonStyle.Danger,
        cancelLabel = stringResource(R.string.goodsout_extra_keep_it),
        hint = stringResource(R.string.goodsout_extra_cancel_hint),
        isWorking = isWorking,
        problem = problem,
        onConfirm = { onConfirm(createReturn) },
        onDismiss = onDismiss,
    ) {
        ToggleRow(
            label = stringResource(R.string.goodsout_extra_cancel_create_return),
            checked = createReturn,
            onCheckedChange = { createReturn = it },
            enabled = !isWorking,
            description = stringResource(if (createReturn) R.string.goodsout_extra_cancel_with_return else R.string.goodsout_extra_cancel_without_return),
        )
    }
}
