package io.aiku.droid.ui.goodsin

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
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
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
import io.aiku.droid.ui.navigation.BookingInRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ScanCameraRoute
import io.aiku.droid.ui.navigation.StockDeliveriesRoute
import io.aiku.droid.ui.navigation.StockDeliveryArrivingRoute

@Composable
fun StockDeliveryCheckingScreen(reference: String) {
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val can = LocalWarehouse.current.can
    var sheetSkuCode by remember { mutableStateOf<String?>(null) }
    val items = GoodsInSamples.checkItems
    val checkedCount = items.count { it.checked != null }
    val nextToCheck = items.firstOrNull { it.checked == null }
    val backToDispatched = stringResource(R.string.goodsin_toast_unmarked_received, reference)
    val cancelled = stringResource(R.string.goodsin_toast_cancelled, reference)

    RequireAbility(ability = Ability.ViewGoodsIn, title = stringResource(R.string.module_goods_in), activeTab = MainTab.Menu) {
        AppScreen(
            header = { StockDeliveryHeader(reference = reference) },
            activeTab = MainTab.Menu,
            showBar = true,
            footer = if (can.bookIn) {
                {
                    AikuButton(
                        text = stringResource(R.string.goodsin_place_checked),
                        onClick = { navigator.open(BookingInRoute(reference)) },
                        style = ButtonStyle.Soft,
                        icon = R.drawable.ic_ui_location,
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            } else {
                null
            },
        ) {
            StateStepper(steps = stockDeliverySteps(current = StockDeliveryStage.Received))
            OrdererCard()
            FieldGridCard(title = stringResource(R.string.goodsin_delivery), fields = GoodsInSamples.deliveryFields, modifier = Modifier.fakeData())
            FieldGridCard(title = stringResource(R.string.goodsin_items), fields = GoodsInSamples.checkingItemFields, modifier = Modifier.fakeData())
            DeliveryViewTabs(active = DeliveryView.Pending, reference = reference)
            if (can.bookIn && nextToCheck != null) {
                ScanPromptCard(
                    title = stringResource(R.string.goodsin_scan_to_check),
                    body = stringResource(R.string.goodsin_scan_to_check_body),
                    code = nextToCheck.code,
                    detail = pluralStringResource(R.plurals.goodsin_item_checked_progress, nextToCheck.delivered, nextToCheck.name, nextToCheck.checked ?: 0, nextToCheck.delivered),
                    modifier = Modifier.fakeData(),
                    onClick = { navigator.open(ScanCameraRoute) },
                )
            }
            SectionHeading(
                title = stringResource(R.string.goodsin_tab_pending),
                caption = pluralStringResource(R.plurals.goodsin_checked_count, items.size, checkedCount, items.size),
                isCaptionSample = true,
            )
            ListGroup(modifier = Modifier.fakeData()) {
                items.forEachIndexed { index, item ->
                    if (index > 0) {
                        RowDivider()
                    }
                    val look = checkItemLook(item)
                    ItemRow(
                        code = item.code,
                        subtitle = look.subtitle,
                        chip = look.chip,
                        chipTone = look.tone ?: Tone.Neutral,
                        done = (item.checked ?: 0).toString(),
                        required = item.delivered.toString(),
                        quantityTone = look.tone,
                        onClick = if (can.bookIn) {
                            { sheetSkuCode = item.code }
                        } else {
                            null
                        },
                    )
                }
            }
            HelperNote(text = stringResource(R.string.goodsin_checking_note))
            if (can.superviseGoodsIn) {
                SectionHeading(title = stringResource(R.string.goodsin_supervisor))
                ListGroup {
                    ActionLinkRow(
                        icon = R.drawable.ic_ui_undo,
                        title = stringResource(R.string.goodsin_unmark_received),
                        subtitle = stringResource(R.string.goodsin_unmark_received_body),
                        onClick = {
                            toasts.show(backToDispatched, kind = ToastKind.Success)
                            navigator.switchSection(StockDeliveryArrivingRoute(reference))
                        },
                    )
                }
            }
            if (can.editProcurement) {
                SectionHeading(title = stringResource(R.string.goodsin_procurement_only))
                ListGroup {
                    ActionLinkRow(
                        icon = R.drawable.ic_ui_close,
                        title = stringResource(R.string.goodsin_cancel_delivery),
                        subtitle = stringResource(R.string.goodsin_cancel_delivery_body),
                        tone = ActionTone.Danger,
                        onClick = {
                            toasts.show(cancelled, kind = ToastKind.Success)
                            navigator.switchSection(StockDeliveriesRoute)
                        },
                    )
                }
            }
        }
    }

    sheetSkuCode?.let { skuCode ->
        CheckedQuantitySheet(reference = reference, skuCode = skuCode, onDismiss = { sheetSkuCode = null })
    }
}
