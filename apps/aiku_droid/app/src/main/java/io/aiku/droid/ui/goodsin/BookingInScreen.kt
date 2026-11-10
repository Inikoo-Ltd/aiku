package io.aiku.droid.ui.goodsin

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.StockDeliveryStage
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.stockDeliverySteps
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.PlaceItemNoPickingLocationRoute
import io.aiku.droid.ui.navigation.PlaceItemRoute

internal val BookingItem.placedQuantity: Int get() = placements.sumOf { it.quantity }

internal val BookingItem.isPlaced: Boolean get() = placedQuantity >= checked

private fun BookingItem.placeRoute(reference: String): Any =
    if (pickingLocation == null) PlaceItemNoPickingLocationRoute(reference = reference, skuCode = code) else PlaceItemRoute(reference = reference, skuCode = code)

@Composable
fun BookingInScreen(reference: String) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val items = GoodsInSamples.bookingItems
    val nextToPlace = items.firstOrNull { !it.isPlaced }

    RequireAbility(ability = Ability.ViewGoodsIn, title = stringResource(R.string.module_goods_in), activeTab = MainTab.Menu) {
        AppScreen(
            header = { StockDeliveryHeader(reference = reference) },
            activeTab = MainTab.Menu,
            showBar = true,
            footer = if (can.bookIn && nextToPlace != null) {
                {
                    AikuButton(
                        text = stringResource(R.string.goodsin_place_item, nextToPlace.code),
                        onClick = { navigator.open(nextToPlace.placeRoute(reference)) },
                        icon = R.drawable.ic_ui_location,
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            } else {
                null
            },
        ) {
            StateStepper(steps = stockDeliverySteps(current = StockDeliveryStage.BookingIn))
            SectionHeading(
                title = stringResource(R.string.goodsin_place_checked),
                caption = pluralStringResource(R.plurals.goodsin_placed_count, items.size, items.count { it.isPlaced }, items.size),
                isCaptionSample = true,
            )
            ListGroup(modifier = Modifier.fakeData()) {
                items.forEachIndexed { index, item ->
                    if (index > 0) {
                        RowDivider()
                    }
                    BookingItemRow(
                        item = item,
                        onClick = if (can.bookIn && !item.isPlaced) {
                            { navigator.open(item.placeRoute(reference)) }
                        } else {
                            null
                        },
                    )
                }
            }
            HelperNote(text = stringResource(R.string.goodsin_booking_in_note))
        }
    }
}

@Composable
private fun BookingItemRow(item: BookingItem, onClick: (() -> Unit)?) {
    val placedIn = item.placements.joinToString(", ") { it.location }
    when {
        item.isPlaced -> ItemRow(
            code = item.code,
            subtitle = stringResource(R.string.goodsin_item_in_location, item.name, placedIn),
            chip = stringResource(R.string.goodsin_chip_placed),
            chipTone = Tone.Success,
            done = item.placedQuantity.toString(),
            required = item.checked.toString(),
            quantityTone = Tone.Success,
            onClick = onClick,
        )
        item.pickingLocation == null -> ItemRow(
            code = item.code,
            subtitle = item.name,
            chip = stringResource(R.string.goodsin_no_picking_location),
            chipTone = Tone.Warn,
            done = item.placedQuantity.toString(),
            required = item.checked.toString(),
            onClick = onClick,
        )
        else -> ItemRow(
            code = item.code,
            subtitle = stringResource(R.string.goodsin_item_picking_location, item.name, item.pickingLocation),
            done = item.placedQuantity.toString(),
            required = item.checked.toString(),
            onClick = onClick,
        )
    }
}
