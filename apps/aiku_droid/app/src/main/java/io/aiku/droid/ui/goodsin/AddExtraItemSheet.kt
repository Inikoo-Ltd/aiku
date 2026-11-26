package io.aiku.droid.ui.goodsin

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableDoubleStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.AddableItem
import io.aiku.droid.data.api.StockDelivery
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.QuantityStepper
import io.aiku.droid.ui.components.SearchField
import io.aiku.droid.ui.goodsout.ActionProblem
import io.aiku.droid.ui.goodsout.Choices
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.pluralQuantity
import io.aiku.droid.ui.goodsout.quantityText
import io.aiku.droid.ui.inventory.SheetGroupLabel
import io.aiku.droid.ui.theme.LocalAikuColors
import kotlinx.coroutines.delay

private const val SEARCH_DELAY_MILLIS = 300L
private const val FIRST_QUANTITY = 1.0
private const val NAME_SEPARATOR = " · "

data class ScannedExtra(val code: String, val skoId: Long)

@Composable
internal fun AddExtraItemSheet(
    delivery: StockDelivery,
    scanned: ScannedExtra?,
    addable: Choices<AddableItem>,
    isWorking: Boolean,
    problem: String?,
    onSearch: (search: String?, orgStockId: Long?) -> Unit,
    onAdd: (itemId: Long, skoQuantity: Double) -> Unit,
    onDismiss: () -> Unit,
) {
    val colors = LocalAikuColors.current
    var search by rememberSaveable { mutableStateOf("") }
    var chosenId by rememberSaveable { mutableStateOf<Long?>(null) }
    var quantity by rememberSaveable { mutableDoubleStateOf(FIRST_QUANTITY) }
    val chosen = addable.items.firstOrNull { it.id == chosenId } ?: addable.items.singleOrNull().takeIf { scanned != null }

    LaunchedEffect(search, scanned) {
        if (scanned == null) {
            delay(SEARCH_DELAY_MILLIS)
        }
        onSearch(search.takeIf { scanned == null }, scanned?.skoId)
    }

    AikuBottomSheet(
        title = stringResource(if (scanned != null) R.string.goodsin_add_extra_question else R.string.goodsin_add_extra),
        subtitle = scanned?.let { stringResource(R.string.goodsin_scan_not_on_delivery, it.code, delivery.reference) } ?: delivery.reference,
        onDismiss = onDismiss,
        footer = {
            ActionProblem(problem)
            if (chosen != null) {
                AikuButton(
                    text = pluralStringResource(R.plurals.goodsin_add_extra_button, pluralQuantity(quantity), quantityText(quantity), chosen.sko?.code ?: chosen.code.orEmpty()),
                    onClick = { onAdd(chosen.id, quantity) },
                    icon = R.drawable.ic_ui_plus,
                    isLoading = isWorking,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
            AikuButton(
                text = stringResource(R.string.dialog_cancel),
                onClick = onDismiss,
                style = ButtonStyle.Outline,
                modifier = Modifier.fillMaxWidth(),
            )
        },
    ) {
        if (scanned == null) {
            SearchField(value = search, onValueChange = { search = it }, placeholder = stringResource(R.string.goodsin_add_extra_search), modifier = Modifier.fillMaxWidth())
        }
        when {
            addable.problem != null -> GoodsOutLoadProblem(
                problem = addable.problem,
                title = stringResource(R.string.goodsin_add_extra_failed),
                onRetry = { onSearch(search.takeIf { scanned == null }, scanned?.skoId) },
            )
            addable.isLoading && addable.items.isEmpty() -> CircularProgressIndicator(color = colors.link, strokeWidth = 2.dp)
            addable.items.isEmpty() -> Text(
                text = stringResource(if (scanned != null) R.string.goodsin_add_extra_not_supplied else R.string.goodsin_add_extra_none),
                fontSize = 13.sp,
                color = colors.textTertiary,
            )
            else -> Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                addable.items.forEach { option ->
                    ChoiceRow(
                        title = listOfNotNull(option.code, option.sko?.code?.takeIf { it != option.code }).joinToString(NAME_SEPARATOR),
                        subtitle = option.name ?: option.sko?.name,
                        selected = option.id == chosen?.id,
                        onClick = { chosenId = option.id },
                    )
                }
            }
        }
        if (chosen != null) {
            SheetGroupLabel(text = stringResource(R.string.goodsin_add_extra_quantity))
            QuantityStepper(
                value = quantity,
                valueText = quantityText(quantity),
                onValueChange = { quantity = it },
                min = FIRST_QUANTITY,
                max = Double.MAX_VALUE,
                helper = stringResource(R.string.goodsin_add_extra_note),
            )
        }
    }
}
