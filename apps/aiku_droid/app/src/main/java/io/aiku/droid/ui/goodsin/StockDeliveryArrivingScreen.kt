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
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.StockDeliveryStage
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.stockDeliverySteps
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.StockDeliveriesRoute
import io.aiku.droid.ui.navigation.StockDeliveryCheckingRoute

@Composable
fun StockDeliveryArrivingScreen(reference: String) {
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val can = LocalWarehouse.current.can
    val markedReceived = stringResource(R.string.goodsin_toast_marked_received, reference)
    val unmarkedDispatched = stringResource(R.string.goodsin_toast_unmarked_dispatched, reference)

    RequireAbility(ability = Ability.ViewGoodsIn, title = stringResource(R.string.module_goods_in), activeTab = MainTab.Menu) {
        AppScreen(
            header = { StockDeliveryHeader(reference = reference) },
            activeTab = MainTab.Menu,
            showBar = true,
            footer = if (can.bookIn) {
                {
                    AikuButton(
                        text = stringResource(R.string.goodsin_mark_received),
                        onClick = {
                            toasts.show(markedReceived, kind = ToastKind.Success)
                            navigator.switchSection(StockDeliveryCheckingRoute(reference))
                        },
                        icon = R.drawable.ic_ui_check,
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            } else {
                null
            },
        ) {
            StateStepper(steps = stockDeliverySteps(current = StockDeliveryStage.Dispatched))
            OrdererCard()
            FieldGridCard(title = stringResource(R.string.goodsin_delivery), fields = GoodsInSamples.deliveryFields, modifier = Modifier.fakeData())
            FieldGridCard(title = stringResource(R.string.goodsin_items), fields = GoodsInSamples.arrivingItemFields, modifier = Modifier.fakeData())
            HelperNote(text = stringResource(R.string.goodsin_arriving_note))
            if (can.editProcurement) {
                SectionHeading(title = stringResource(R.string.goodsin_procurement_only))
                ListGroup {
                    ActionLinkRow(
                        icon = R.drawable.ic_ui_undo,
                        title = stringResource(R.string.goodsin_unmark_dispatched),
                        subtitle = stringResource(R.string.goodsin_unmark_dispatched_body),
                        onClick = {
                            toasts.show(unmarkedDispatched, kind = ToastKind.Success)
                            navigator.switchSection(StockDeliveriesRoute)
                        },
                    )
                }
            }
        }
    }
}
