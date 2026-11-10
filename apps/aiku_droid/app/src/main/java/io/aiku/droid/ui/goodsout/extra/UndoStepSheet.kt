package io.aiku.droid.ui.goodsout.extra

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
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.navigation.DeliveryNoteFinalisedRoute
import io.aiku.droid.ui.navigation.DeliveryNotePackingRoute
import io.aiku.droid.ui.navigation.DeliveryNotePickedRoute
import io.aiku.droid.ui.navigation.DeliveryNotePickingRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse

internal enum class UndoStep(
    @StringRes val label: Int,
    @StringRes val caption: Int,
    @StringRes val title: Int,
    @StringRes val subtitle: Int,
    @StringRes val confirm: Int,
    @StringRes val hint: Int?,
) {
    SetAsPicked(
        R.string.goodsout_extra_undo_set_as_picked,
        R.string.goodsout_extra_undo_set_as_picked_caption,
        R.string.goodsout_extra_undo_set_as_picked_title,
        R.string.goodsout_extra_undo_set_as_picked_subtitle,
        R.string.goodsout_extra_undo_set_as_picked_confirm,
        R.string.goodsout_extra_undo_hint,
    ),
    Packing(
        R.string.goodsout_extra_undo_packing,
        R.string.goodsout_extra_undo_packing_caption,
        R.string.goodsout_extra_undo_packing_title,
        R.string.goodsout_extra_undo_packing_subtitle,
        R.string.goodsout_extra_undo_packing_confirm,
        null,
    ),
    Unpack(
        R.string.goodsout_extra_unpack,
        R.string.goodsout_extra_unpack_caption,
        R.string.goodsout_extra_unpack_title,
        R.string.goodsout_extra_unpack_subtitle,
        R.string.goodsout_extra_unpack_confirm,
        null,
    ),
    Undispatch(
        R.string.goodsout_extra_undispatch,
        R.string.goodsout_extra_undispatch_caption,
        R.string.goodsout_extra_undispatch_title,
        R.string.goodsout_extra_undispatch_subtitle,
        R.string.goodsout_extra_undispatch_confirm,
        null,
    ),
    ;

    fun destination(reference: String): Any = when (this) {
        SetAsPicked -> DeliveryNotePickingRoute(reference)
        Packing -> DeliveryNotePickedRoute(reference)
        Unpack -> DeliveryNotePackingRoute(reference)
        Undispatch -> DeliveryNoteFinalisedRoute(reference)
    }
}

@Composable
fun UndoStepSheet(reference: String, onDismiss: () -> Unit) {
    var showMore by remember { mutableStateOf(false) }

    if (showMore) {
        DeliveryNoteMoreSheet(reference = reference, onDismiss = onDismiss)
    } else {
        UndoStepConfirmSheet(
            reference = reference,
            step = UndoStep.SetAsPicked,
            onBack = { showMore = true },
            onDone = onDismiss,
        )
    }
}

@Composable
internal fun UndoStepConfirmSheet(reference: String, step: UndoStep, onBack: () -> Unit, onDone: () -> Unit) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can

    AikuBottomSheet(
        title = stringResource(step.title),
        subtitle = stringResource(step.subtitle, reference),
        onDismiss = onBack,
    ) {
        step.hint?.let { HintText(text = stringResource(it)) }
        SheetButtons {
            if (can.pickAndPack) {
                AikuButton(
                    text = stringResource(step.confirm),
                    onClick = {
                        onDone()
                        navigator.open(step.destination(reference))
                    },
                    icon = R.drawable.ic_ui_undo,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
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
