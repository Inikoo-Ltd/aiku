package io.aiku.droid.ui.goodsout.extra

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableDoubleStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNoteLine
import io.aiku.droid.data.api.WaitingKind
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.QuantityStepper
import io.aiku.droid.ui.goodsout.ActionProblem
import io.aiku.droid.ui.goodsout.quantityText

@Composable
internal fun WaitingLineSheet(
    reference: String,
    line: DeliveryNoteLine,
    initialKind: WaitingKind,
    isWorking: Boolean,
    problem: String?,
    onConfirm: (WaitingKind, Double, String?) -> Unit,
    onBack: () -> Unit,
) {
    val toPick = line.quantityToPick
    val code = line.orgStock?.code?.takeIf { it.isNotBlank() } ?: stringResource(R.string.goodsout_line_unknown_sko)
    var kind by remember { mutableStateOf(initialKind) }
    var quantity by remember(toPick) { mutableDoubleStateOf(toPick) }
    var note by remember { mutableStateOf("") }

    AikuBottomSheet(
        title = stringResource(R.string.goodsout_extra_waiting_sheet_title, code),
        subtitle = stringResource(R.string.goodsout_waiting_sheet_subtitle, reference, quantityText(toPick, line.quantityToPickLabel)),
        onDismiss = onBack,
        footer = {
            ActionProblem(problem)
            AikuButton(
                text = stringResource(R.string.goodsout_extra_set_as_waiting),
                onClick = { onConfirm(kind, quantity, note) },
                icon = R.drawable.ic_ui_pause,
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
        },
    ) {
        ChoiceRow(
            title = stringResource(R.string.goodsout_extra_reason_warehouse),
            subtitle = stringResource(R.string.goodsout_extra_reason_warehouse_caption),
            selected = kind == WaitingKind.Warehouse,
            onClick = { kind = WaitingKind.Warehouse },
        )
        ChoiceRow(
            title = stringResource(R.string.goodsout_extra_reason_cs),
            subtitle = stringResource(R.string.goodsout_extra_reason_cs_caption),
            selected = kind == WaitingKind.Crm,
            onClick = { kind = WaitingKind.Crm },
        )
        Column(verticalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth()) {
            FieldLabel(text = stringResource(R.string.goodsout_waiting_quantity))
            QuantityStepper(
                value = quantity,
                valueText = quantityText(quantity),
                onValueChange = { quantity = it },
                min = minOf(1.0, toPick),
                max = toPick,
            )
        }
        Column(verticalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth()) {
            FieldLabel(text = stringResource(R.string.goodsout_extra_note_label))
            PlainTextField(
                value = note,
                onValueChange = { note = it },
                placeholder = stringResource(R.string.goodsout_note_optional),
                isMultiLine = true,
                minHeight = 66.dp,
            )
        }
        HintText(text = stringResource(R.string.goodsout_extra_waiting_sheet_hint))
    }
}
