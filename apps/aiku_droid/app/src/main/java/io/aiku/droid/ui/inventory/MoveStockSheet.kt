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
import io.aiku.droid.data.api.MoveTarget
import io.aiku.droid.data.api.OrgStockDetail
import io.aiku.droid.data.api.OrgStockWrite
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.ConfirmDialog
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun MoveStockSheet(detail: OrgStockDetail, isWorking: Boolean, errorText: String?, onMove: (OrgStockWrite) -> Unit, onDismiss: () -> Unit) {
    val colors = LocalAikuColors.current
    val sources = detail.locations.filter { it.quantity > 0.0 }
    var fromId by remember { mutableStateOf(sources.firstOrNull()?.locationOrgStockId) }
    val from = detail.locations.firstOrNull { it.locationOrgStockId == fromId }
    val targets = detail.locations.filter { it.locationOrgStockId != fromId }
    var toId by remember { mutableStateOf(targets.firstOrNull()?.locationOrgStockId) }
    val to = targets.firstOrNull { it.locationOrgStockId == toId }
    var quantity by remember { mutableDoubleStateOf(from?.quantity?.coerceAtMost(1.0) ?: 0.0) }
    var missing by remember { mutableStateOf<Int?>(null) }
    var pending by remember { mutableStateOf<OrgStockWrite.Move?>(null) }

    AikuBottomSheet(
        title = stringResource(R.string.inventory_move_title, detail.code),
        subtitle = stringResource(R.string.inventory_move_subtitle),
        onDismiss = onDismiss,
    ) {
        SheetGroupLabel(text = stringResource(R.string.inventory_move_from_label))
        if (sources.isEmpty()) {
            Text(text = stringResource(R.string.inventory_move_nothing), fontSize = 13.sp, color = colors.textTertiary)
        }
        sources.forEach { location ->
            ChoiceRow(
                title = location.location.code,
                subtitle = stringResource(R.string.inventory_in_location, stockText(location.quantity, location.quantityLabel)),
                selected = location.locationOrgStockId == fromId,
                onClick = {
                    fromId = location.locationOrgStockId
                    if (toId == location.locationOrgStockId) {
                        toId = detail.locations.firstOrNull { it.locationOrgStockId != location.locationOrgStockId }?.locationOrgStockId
                    }
                    quantity = quantity.coerceAtMost(location.quantity)
                },
            )
        }
        SheetGroupLabel(text = stringResource(R.string.inventory_move_to_label))
        targets.forEach { location ->
            ChoiceRow(
                title = location.location.code,
                subtitle = stringResource(R.string.inventory_in_location, stockText(location.quantity, location.quantityLabel)),
                selected = location.locationOrgStockId == toId,
                onClick = { toId = location.locationOrgStockId },
            )
        }
        SheetGroupLabel(text = stringResource(R.string.inventory_quantity))
        DecimalStepper(value = quantity, onValueChange = { quantity = it }, max = from?.quantity ?: 0.0)
        missing?.let { Text(text = stringResource(it), fontSize = 13.sp, color = colors.danger) }
        errorText?.let { Text(text = it, fontSize = 13.sp, color = colors.danger) }
        SheetButtons(
            confirmLabel = stringResource(R.string.inventory_move_confirm, stockText(quantity)),
            onConfirm = {
                when {
                    from == null || to == null -> missing = R.string.inventory_move_locations_missing
                    quantity <= 0.0 -> missing = R.string.inventory_quantity_missing
                    else -> pending = OrgStockWrite.Move(fromLocationOrgStockId = from.locationOrgStockId, targets = listOf(MoveTarget(to.locationOrgStockId, quantity)))
                }
            },
            onCancel = onDismiss,
            isWorking = isWorking,
        )
    }

    pending?.let { move ->
        ConfirmDialog(
            title = stringResource(R.string.inventory_move_confirm_title),
            body = stringResource(R.string.inventory_move_confirm_body, stockText(quantity), from?.location?.code.orEmpty(), to?.location?.code.orEmpty()),
            confirmLabel = stringResource(R.string.inventory_move_confirm, stockText(quantity)),
            onConfirm = {
                pending = null
                onMove(move)
            },
            onDismiss = { pending = null },
        )
    }
}
