package io.aiku.droid.ui.goodsout.extra

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.NoteActionKey
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.goodsout.ActionProblem
import io.aiku.droid.ui.goodsout.goodsOutActionLook

@Composable
internal fun BackToPickingSheet(
    reference: String,
    waitingLines: Int,
    bay: String?,
    confirmLabel: String,
    onConfirm: () -> Unit,
    onDismiss: () -> Unit,
    isWorking: Boolean = false,
    problem: String? = null,
) {
    AikuBottomSheet(
        title = stringResource(R.string.goodsout_extra_back_to_picking_title),
        subtitle = pluralStringResource(R.plurals.goodsout_extra_back_to_picking_subtitle, waitingLines, reference, waitingLines),
        onDismiss = onDismiss,
    ) {
        HintText(
            text = bay?.let { stringResource(R.string.goodsout_extra_back_to_picking_hint, it) }
                ?: stringResource(R.string.goodsout_back_to_picking_hint_no_bay),
        )
        ActionProblem(problem)
        SheetButtons {
            AikuButton(
                text = confirmLabel,
                onClick = onConfirm,
                style = goodsOutActionLook(NoteActionKey.BACK_TO_PICKING).style,
                icon = goodsOutActionLook(NoteActionKey.BACK_TO_PICKING).icon,
                isLoading = isWorking,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
            AikuButton(
                text = stringResource(R.string.goodsout_extra_keep_waiting),
                onClick = onDismiss,
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}
