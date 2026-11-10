package io.aiku.droid.ui.goodsin

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.StockDeliveryStage
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.stockDeliverySteps
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.StockDeliveriesRoute

@Composable
fun BookedInScreen(reference: String) {
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val can = LocalWarehouse.current.can
    val placedToast = stringResource(R.string.goodsin_toast_delivery_placed, reference)

    RequireAbility(ability = Ability.ViewGoodsIn, title = stringResource(R.string.module_goods_in), activeTab = MainTab.Menu) {
        AppScreen(
            header = { StockDeliveryHeader(reference = reference) },
            activeTab = MainTab.Menu,
            showBar = true,
            footer = {
                AikuButton(
                    text = stringResource(R.string.goodsin_next_delivery),
                    onClick = { navigator.switchSection(StockDeliveriesRoute) },
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            },
        ) {
            StateStepper(steps = stockDeliverySteps(current = StockDeliveryStage.BookedIn))
            DeliveryViewTabs(active = DeliveryView.Done, reference = reference)
            ListGroup(modifier = Modifier.fakeData()) {
                GoodsInSamples.bookedInItems.forEachIndexed { index, item ->
                    if (index > 0) {
                        RowDivider()
                    }
                    val placements = item.placements.map { stringResource(R.string.goodsin_placed_quantity, it.quantity, it.location) }
                    ItemRow(
                        code = item.code,
                        subtitle = placements.joinToString(" · "),
                        chip = stringResource(R.string.goodsin_chip_placed),
                        chipTone = Tone.Success,
                        done = item.placedQuantity.toString(),
                        required = item.checked.toString(),
                        quantityTone = Tone.Success,
                    )
                }
            }
            HelperNote(text = stringResource(R.string.goodsin_booked_in_note))
            if (can.placeStockDeliveries) {
                SectionHeading(title = stringResource(R.string.goodsin_procurement_accounting_only))
                ListGroup {
                    ActionLinkRow(
                        icon = R.drawable.ic_ui_check,
                        title = stringResource(R.string.goodsin_place_delivery),
                        subtitle = stringResource(R.string.goodsin_place_delivery_body),
                        tone = ActionTone.Primary,
                        onClick = {
                            toasts.show(placedToast, kind = ToastKind.Success)
                            navigator.switchSection(StockDeliveriesRoute)
                        },
                    )
                }
            }
        }
    }
}
