package io.aiku.droid.ui.inventory

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData

private val moveReasons = listOf(
    R.string.inventory_reason_warehouse_transfer,
    R.string.inventory_reason_wrong_bin,
)

internal fun stockLocationsFor(locationCode: String?): List<SampleSkoLocation> {
    if (locationCode == null || sampleSkoLocations.any { it.code == locationCode }) return sampleSkoLocations
    return listOf(SampleSkoLocation(locationCode, LocationKind.Picking, stock = SAMPLE_NEW_LOCATION_STOCK)) + sampleSkoLocations
}

internal fun nextIndex(current: Int, size: Int, isAllowed: (Int) -> Boolean): Int {
    for (step in 1 until size) {
        val candidate = (current + step) % size
        if (isAllowed(candidate)) return candidate
    }
    return current
}

@Composable
fun MoveStockSheet(skuCode: String, onDismiss: () -> Unit, fromLocationCode: String? = null) {
    val toasts = LocalToasts.current
    val locations = remember(fromLocationCode) { stockLocationsFor(fromLocationCode) }
    var fromIndex by remember(locations) { mutableIntStateOf(locations.indexOfFirst { it.code == fromLocationCode }.coerceAtLeast(0)) }
    var toIndex by remember(locations) { mutableIntStateOf(locations.indices.last { it != fromIndex }) }
    var quantity by remember(locations) { mutableIntStateOf(SAMPLE_MOVE_QUANTITY.coerceAtMost(locations[fromIndex].stock).coerceAtLeast(1)) }
    var reason by remember { mutableIntStateOf(0) }
    val from = locations[fromIndex]
    val to = locations[toIndex]
    val movedMessage = pluralStringResource(R.plurals.inventory_moved, quantity, quantity, from.code, to.code)

    AikuBottomSheet(
        title = stringResource(R.string.inventory_move_title, skuCode),
        subtitle = stringResource(R.string.inventory_move_subtitle),
        onDismiss = onDismiss,
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(14.dp), modifier = Modifier.fakeData(10.dp)) {
            ChoiceRow(
                title = stringResource(R.string.inventory_move_from, from.code),
                subtitle = stringResource(R.string.inventory_in_location, from.stock),
                selected = true,
                onClick = {
                    fromIndex = nextIndex(current = fromIndex, size = locations.size, isAllowed = { it != toIndex && locations[it].stock > 0 })
                    quantity = quantity.coerceAtMost(locations[fromIndex].stock).coerceAtLeast(1)
                },
            )
            ChoiceRow(
                title = stringResource(R.string.inventory_move_to, to.code),
                subtitle = stringResource(R.string.inventory_in_location, to.stock),
                selected = true,
                onClick = { toIndex = nextIndex(current = toIndex, size = locations.size, isAllowed = { it != fromIndex }) },
            )
        }
        SheetQuantityRow(
            value = quantity,
            onValueChange = { quantity = it },
            max = from.stock.coerceAtLeast(1),
        )
        SheetGroupLabel(text = stringResource(R.string.inventory_reason))
        moveReasons.forEachIndexed { index, label ->
            ChoiceRow(
                title = stringResource(label),
                selected = index == reason,
                onClick = { reason = index },
            )
        }
        SheetButtons(
            confirmLabel = stringResource(R.string.inventory_move_confirm, quantity),
            onConfirm = {
                toasts.show(movedMessage, ToastKind.Success)
                onDismiss()
            },
            onCancel = onDismiss,
        )
    }
}
