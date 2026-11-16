package io.aiku.droid.ui.goodsout.extra

import androidx.annotation.StringRes
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.LinePicking
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.goodsout.ActionProblem
import io.aiku.droid.ui.goodsout.pickingText

internal enum class UndoStep(
    @StringRes val title: Int,
    @StringRes val subtitle: Int,
    @StringRes val confirm: Int,
    @StringRes val hint: Int?,
) {
    SetAsPicked(
        R.string.goodsout_extra_undo_set_as_picked_title,
        R.string.goodsout_extra_undo_set_as_picked_subtitle,
        R.string.goodsout_extra_undo_set_as_picked_confirm,
        R.string.goodsout_extra_undo_hint,
    ),
    Packing(
        R.string.goodsout_extra_undo_packing_title,
        R.string.goodsout_extra_undo_packing_subtitle,
        R.string.goodsout_extra_undo_packing_confirm,
        null,
    ),
    Unpack(
        R.string.goodsout_extra_unpack_title,
        R.string.goodsout_extra_unpack_subtitle,
        R.string.goodsout_extra_unpack_confirm,
        null,
    ),
}

@Composable
internal fun UndoStepConfirmSheet(
    reference: String,
    step: UndoStep,
    onConfirm: () -> Unit,
    onBack: () -> Unit,
    isWorking: Boolean = false,
    problem: String? = null,
) {
    AikuBottomSheet(
        title = stringResource(step.title),
        subtitle = stringResource(step.subtitle, reference),
        onDismiss = onBack,
    ) {
        step.hint?.let { HintText(text = stringResource(it)) }
        ActionProblem(problem)
        SheetButtons {
            AikuButton(
                text = stringResource(step.confirm),
                onClick = onConfirm,
                icon = R.drawable.ic_ui_undo,
                isLoading = isWorking,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
            AikuButton(
                text = stringResource(R.string.dialog_cancel),
                onClick = onBack,
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}

@Composable
internal fun UndoPickingSheet(code: String, picking: LinePicking, isWorking: Boolean, problem: String?, onConfirm: () -> Unit, onBack: () -> Unit) {
    AikuBottomSheet(
        title = stringResource(R.string.goodsout_undo_picking_title),
        subtitle = listOf(code, pickingText(picking)).filter { it.isNotBlank() }.joinToString(KICKER_SEPARATOR),
        onDismiss = onBack,
    ) {
        HintText(text = stringResource(if (picking.isNotPicked) R.string.goodsout_undo_not_picked_hint else R.string.goodsout_undo_picking_hint))
        ActionProblem(problem)
        SheetButtons {
            AikuButton(
                text = stringResource(R.string.goodsout_undo),
                onClick = onConfirm,
                icon = R.drawable.ic_ui_undo,
                isLoading = isWorking,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
            AikuButton(
                text = stringResource(R.string.goodsout_back),
                onClick = onBack,
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}
