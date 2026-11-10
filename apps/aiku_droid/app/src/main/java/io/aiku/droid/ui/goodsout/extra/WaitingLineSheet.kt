package io.aiku.droid.ui.goodsout.extra

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
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
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.goodsout.LineActionsSheet
import io.aiku.droid.ui.navigation.LocalWarehouse

@Composable
fun WaitingLineSheet(reference: String, skuCode: String, onDismiss: () -> Unit) {
    var showLineActions by remember { mutableStateOf(false) }

    if (showLineActions) {
        LineActionsSheet(reference = reference, skuCode = skuCode, onDismiss = onDismiss)
    } else {
        WaitingLineChoiceSheet(
            reference = reference,
            skuCode = skuCode,
            onBack = { showLineActions = true },
            onDone = onDismiss,
        )
    }
}

@Composable
private fun WaitingLineChoiceSheet(reference: String, skuCode: String, onBack: () -> Unit, onDone: () -> Unit) {
    val can = LocalWarehouse.current.can
    val toasts = LocalToasts.current
    var reason by remember { mutableStateOf(WaitingReason.Warehouse) }
    var note by remember { mutableStateOf(GoodsOutExtraSamples.WAITING_NOTE) }
    val doneMessage = stringResource(R.string.goodsout_extra_waiting_set_toast, skuCode)

    AikuBottomSheet(
        title = stringResource(R.string.goodsout_extra_waiting_sheet_title, skuCode),
        subtitle = pluralStringResource(
            R.plurals.goodsout_extra_waiting_sheet_subtitle,
            GoodsOutExtraSamples.NOT_FOUND_QUANTITY,
            reference,
            GoodsOutExtraSamples.NOT_FOUND_QUANTITY,
            GoodsOutExtraSamples.NOT_FOUND_LOCATION,
        ),
        onDismiss = onBack,
    ) {
        ChoiceRow(
            title = stringResource(R.string.goodsout_extra_reason_warehouse),
            subtitle = stringResource(R.string.goodsout_extra_reason_warehouse_caption),
            selected = reason == WaitingReason.Warehouse,
            onClick = { reason = WaitingReason.Warehouse },
        )
        ChoiceRow(
            title = stringResource(R.string.goodsout_extra_reason_cs),
            subtitle = stringResource(R.string.goodsout_extra_reason_cs_caption),
            selected = reason == WaitingReason.CustomerService,
            onClick = { reason = WaitingReason.CustomerService },
        )
        Column(verticalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth()) {
            FieldLabel(text = stringResource(R.string.goodsout_extra_note_label))
            PlainTextField(
                value = note,
                onValueChange = { note = it },
                isMultiLine = true,
                minHeight = 66.dp,
                modifier = Modifier.fakeData(cornerRadius = 8.dp),
            )
        }
        HintText(text = stringResource(R.string.goodsout_extra_waiting_sheet_hint))
        SheetButtons {
            if (can.pickAndPack) {
                AikuButton(
                    text = stringResource(R.string.goodsout_extra_set_as_waiting),
                    onClick = {
                        toasts.show(doneMessage, ToastKind.Success)
                        onDone()
                    },
                    icon = R.drawable.ic_ui_pause,
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
