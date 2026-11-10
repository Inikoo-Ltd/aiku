package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.goodsout.extra.WaitingLineSheet

private enum class LineSheetStep { Actions, PickLocation, Waiting }

@Composable
fun LineActionsSheet(reference: String, skuCode: String, onDismiss: () -> Unit) {
    var step by remember { mutableStateOf(LineSheetStep.Actions) }

    when (step) {
        LineSheetStep.Actions -> LineActionsContent(
            skuCode = skuCode,
            onDismiss = onDismiss,
            onPickElsewhere = { step = LineSheetStep.PickLocation },
            onWaiting = { step = LineSheetStep.Waiting },
        )
        LineSheetStep.PickLocation -> PickLocationFlow(skuCode = skuCode, onDone = onDismiss, onCancel = { step = LineSheetStep.Actions })
        LineSheetStep.Waiting -> WaitingLineSheet(reference = reference, skuCode = skuCode, onDismiss = onDismiss)
    }
}

@Composable
private fun LineActionsContent(
    skuCode: String,
    onDismiss: () -> Unit,
    onPickElsewhere: () -> Unit,
    onWaiting: () -> Unit,
) {
    val toasts = LocalToasts.current
    val left = GoodsOutSamples.LINE_SHEET_REQUIRED - GoodsOutSamples.LINE_SHEET_PICKED
    var selectedReason by remember { mutableIntStateOf(0) }
    val reasons = GoodsOutSamples.notPickedReasons
    val pickedAllText = stringResource(R.string.goodsout_line_picked_all_toast, skuCode, left)
    val notPickedText = stringResource(R.string.goodsout_line_not_picked_toast, skuCode, reasons[selectedReason].lowercase())

    AikuBottomSheet(
        title = pluralStringResource(R.plurals.goodsout_line_title, GoodsOutSamples.LINE_SHEET_REQUIRED, skuCode, GoodsOutSamples.LINE_SHEET_PICKED, GoodsOutSamples.LINE_SHEET_REQUIRED),
        subtitle = GoodsOutSamples.LINE_SHEET_DETAIL,
        onDismiss = onDismiss,
    ) {
        AikuButton(
            text = pluralStringResource(R.plurals.goodsout_pick_all, left, left),
            onClick = {
                toasts.show(pickedAllText, ToastKind.Success)
                onDismiss()
            },
            icon = R.drawable.ic_ui_check,
            height = 52.dp,
            modifier = Modifier.fillMaxWidth().fakeData(cornerRadius = 8.dp),
        )
        AikuButton(
            text = stringResource(R.string.goodsout_pick_other_location),
            onClick = onPickElsewhere,
            style = ButtonStyle.Outline,
            icon = R.drawable.ic_ui_location,
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )
        GroupLabel(text = stringResource(R.string.goodsout_not_picked_reason))
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fakeData(cornerRadius = 10.dp)) {
            reasons.forEachIndexed { index, reason ->
                ChoiceRow(title = reason, selected = index == selectedReason, onClick = { selectedReason = index })
            }
        }
        AikuButton(
            text = pluralStringResource(R.plurals.goodsout_mark_not_picked, left, left),
            onClick = {
                toasts.show(notPickedText, ToastKind.Success)
                onDismiss()
            },
            style = ButtonStyle.Danger,
            height = 52.dp,
            modifier = Modifier.fillMaxWidth().fakeData(cornerRadius = 8.dp),
        )
        GroupLabel(text = stringResource(R.string.goodsout_cant_finish_line))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            AikuButton(
                text = stringResource(R.string.goodsout_set_as_waiting),
                onClick = onWaiting,
                style = ButtonStyle.Outline,
                icon = R.drawable.ic_ui_pause,
                height = 52.dp,
                modifier = Modifier.weight(1f),
            )
            AikuButton(
                text = stringResource(R.string.goodsout_pass_to_cs),
                onClick = onWaiting,
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.weight(1f),
            )
        }
        AikuButton(
            text = stringResource(R.string.goodsout_close),
            onClick = onDismiss,
            style = ButtonStyle.Outline,
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}
