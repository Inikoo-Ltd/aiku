package io.aiku.droid.ui.goodsin

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
import io.aiku.droid.data.api.CustomerReturnItem
import io.aiku.droid.data.api.ItemLocation
import io.aiku.droid.data.api.LocationStockType
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.QuantityStepper
import io.aiku.droid.ui.goodsout.ActionProblem
import io.aiku.droid.ui.goodsout.quantityText
import io.aiku.droid.ui.inventory.SheetDangerNote
import io.aiku.droid.ui.inventory.SheetGroupLabel
import io.aiku.droid.ui.locations.LIST_SEPARATOR

enum class ReturnItemAction { BackToStock, Damaged, NotReturned }

@Composable
private fun returnLocationDetail(location: ItemLocation): String = listOfNotNull(
    when (location.stockType) {
        LocationStockType.Picking -> stringResource(R.string.goodsin_location_picking)
        LocationStockType.Storing -> stringResource(R.string.goodsin_location_storing)
        null -> null
    },
    stringResource(R.string.goodsin_in_location, quantityText(location.quantity, location.quantityLabel)),
).joinToString(LIST_SEPARATOR)

@Composable
internal fun ReturnItemSheet(
    item: CustomerReturnItem,
    action: ReturnItemAction,
    isWorking: Boolean,
    problem: String?,
    onConfirm: (Double, Long?) -> Unit,
    onDismiss: () -> Unit,
) {
    val remaining = item.remaining
    var quantity by remember(item.id, action) { mutableDoubleStateOf(remaining) }
    var locationOrgStockId by remember(item.id, action) { mutableStateOf(item.locations.firstOrNull()?.locationOrgStockId) }
    val shownQuantity = quantity.coerceIn(0.0, remaining.coerceAtLeast(0.0))
    val location = item.locations.firstOrNull { it.locationOrgStockId == locationOrgStockId }
    val hasNoLocation = action == ReturnItemAction.BackToStock && item.locations.isEmpty()
    val title = stringResource(
        when (action) {
            ReturnItemAction.BackToStock -> R.string.goodsin_return_sheet_back_to_stock
            ReturnItemAction.Damaged -> R.string.goodsin_return_sheet_damaged
            ReturnItemAction.NotReturned -> R.string.goodsin_return_sheet_not_returned
        },
        item.code,
    )
    val confirmLabel = when (action) {
        ReturnItemAction.BackToStock -> stringResource(R.string.goodsin_return_confirm_back_to_stock, quantityText(shownQuantity), location?.location?.code.orEmpty())
        ReturnItemAction.Damaged -> stringResource(R.string.goodsin_return_confirm_damaged, quantityText(shownQuantity))
        ReturnItemAction.NotReturned -> stringResource(R.string.goodsin_return_confirm_not_returned, quantityText(shownQuantity))
    }

    AikuBottomSheet(
        title = title,
        subtitle = stringResource(R.string.goodsin_return_remaining, item.sko?.name.orEmpty(), quantityText(remaining, item.remainingLabel)),
        onDismiss = onDismiss,
        footer = {
            ActionProblem(problem)
            if (!hasNoLocation && shownQuantity > 0.0) {
                AikuButton(
                    text = confirmLabel,
                    onClick = { onConfirm(shownQuantity, locationOrgStockId) },
                    style = if (action == ReturnItemAction.BackToStock) ButtonStyle.Primary else ButtonStyle.Danger,
                    isLoading = isWorking,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
            AikuButton(text = stringResource(R.string.dialog_cancel), onClick = onDismiss, style = ButtonStyle.Outline, height = 52.dp, modifier = Modifier.fillMaxWidth())
        },
    ) {
        when (action) {
            ReturnItemAction.BackToStock -> {
                SheetGroupLabel(text = stringResource(R.string.goodsin_return_location))
                if (hasNoLocation) {
                    InlineAlert(title = stringResource(R.string.goodsin_return_no_location), tone = AlertTone.Warn)
                }
                item.locations.forEach { option ->
                    ChoiceRow(
                        title = option.location.code,
                        subtitle = returnLocationDetail(option),
                        selected = option.locationOrgStockId == locationOrgStockId,
                        onClick = { locationOrgStockId = option.locationOrgStockId },
                    )
                }
            }
            ReturnItemAction.Damaged -> SheetDangerNote(text = stringResource(R.string.goodsin_return_damaged_note))
            ReturnItemAction.NotReturned -> HelperNote(text = stringResource(R.string.goodsin_return_not_returned_note))
        }
        if (remaining > 0.0) {
            SheetGroupLabel(text = stringResource(R.string.goodsin_quantity))
            QuantityStepper(
                value = shownQuantity,
                valueText = quantityText(shownQuantity),
                onValueChange = { quantity = it },
                min = minOf(1.0, remaining),
                max = remaining,
                helper = stringResource(R.string.goodsin_up_to, quantityText(remaining, item.remainingLabel)),
            )
        }
    }
}
