package io.aiku.droid.ui.inventory

import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableDoubleStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.OrgStockDetail
import io.aiku.droid.data.api.OrgStockWrite
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.ButtonLook
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun ConsumeStockSheet(detail: OrgStockDetail, isWorking: Boolean, errorText: String?, onConsume: (OrgStockWrite) -> Unit, onDismiss: () -> Unit) {
    val colors = LocalAikuColors.current
    val locations = detail.locations.filter { it.quantity > 0.0 }
    var locationId by remember { mutableStateOf(locations.firstOrNull()?.locationOrgStockId) }
    val location = locations.firstOrNull { it.locationOrgStockId == locationId }
    var quantity by remember { mutableDoubleStateOf(location?.quantity?.coerceAtMost(1.0) ?: 0.0) }
    var note by remember { mutableStateOf("") }
    var isMissing by remember { mutableStateOf(false) }

    AikuBottomSheet(
        title = stringResource(R.string.inventory_consume_title, detail.code),
        subtitle = stringResource(R.string.inventory_consume_subtitle),
        onDismiss = onDismiss,
    ) {
        if (locations.isEmpty()) {
            Text(text = stringResource(R.string.inventory_consume_nothing), fontSize = 13.sp, color = colors.textTertiary)
        }
        locations.forEach { option ->
            ChoiceRow(
                title = option.location.code,
                subtitle = stringResource(R.string.inventory_in_location, stockText(option.quantity, option.quantityLabel)),
                selected = option.locationOrgStockId == locationId,
                onClick = {
                    locationId = option.locationOrgStockId
                    quantity = quantity.coerceAtMost(option.quantity)
                },
            )
        }
        SheetGroupLabel(text = stringResource(R.string.inventory_quantity))
        DecimalStepper(value = quantity, onValueChange = { quantity = it }, max = location?.quantity ?: 0.0)
        SheetTextField(value = note, onValueChange = { note = it }, label = stringResource(R.string.inventory_note_optional))
        if (isMissing) {
            Text(text = stringResource(R.string.inventory_quantity_missing), fontSize = 13.sp, color = colors.danger)
        }
        errorText?.let { Text(text = it, fontSize = 13.sp, color = colors.danger) }
        SheetButtons(
            confirmLabel = stringResource(R.string.inventory_consume_confirm),
            onConfirm = {
                val chosen = location
                if (chosen == null || quantity <= 0.0) {
                    isMissing = true
                } else {
                    onConsume(OrgStockWrite.Consume(locationOrgStockId = chosen.locationOrgStockId, quantity = quantity, note = note.trim().takeIf { it.isNotEmpty() }))
                }
            },
            onCancel = onDismiss,
            confirmLook = ButtonLook(ButtonStyle.Caution, R.drawable.ic_ui_sko),
            isWorking = isWorking,
            showConfirm = locations.isNotEmpty(),
        )
    }
}
