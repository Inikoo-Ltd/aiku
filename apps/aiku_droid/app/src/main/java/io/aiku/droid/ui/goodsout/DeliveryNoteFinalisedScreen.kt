package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.deliveryNoteSteps
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.DeliveryNoteDispatchedRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun DeliveryNoteFinalisedScreen(reference: String) {
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val can = LocalWarehouse.current.can
    val invoiceText = stringResource(R.string.goodsout_invoice_generated, reference)

    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        AppScreen(
            header = {
                DeliveryNoteHeader(
                    reference = reference,
                    kicker = stringResource(R.string.goodsout_kicker_delivery_note, GoodsOutSamples.CHANNEL_WHOLESALE),
                )
            },
            activeTab = MainTab.Menu,
            showBar = true,
            footer = if (can.pickAndPack) {
                {
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                        AikuButton(
                            text = stringResource(R.string.goodsout_generate_invoice),
                            onClick = { toasts.show(invoiceText, ToastKind.Success) },
                            style = ButtonStyle.Outline,
                            icon = R.drawable.ic_ui_document,
                            height = 52.dp,
                            modifier = Modifier.weight(1f),
                        )
                        AikuButton(
                            text = stringResource(R.string.goodsout_dispatch),
                            onClick = { navigator.switchSection(DeliveryNoteDispatchedRoute(reference = reference)) },
                            icon = R.drawable.ic_ui_truck,
                            height = 52.dp,
                            modifier = Modifier.weight(1f),
                        )
                    }
                }
            } else {
                null
            },
        ) {
            StateStepper(steps = deliveryNoteSteps(current = DeliveryNoteStage.Finalised))
            SummaryCard(
                title = stringResource(R.string.goodsout_ready_to_dispatch),
                body = GoodsOutSamples.DISPATCH_SUMMARY,
                bodyModifier = Modifier.fakeData(cornerRadius = 4.dp),
            )
            ListGroup(modifier = Modifier.fakeData()) {
                IconInfoRow(
                    icon = R.drawable.ic_ui_check,
                    title = GoodsOutSamples.DISPATCHED_ITEMS,
                    subtitle = GoodsOutSamples.DISPATCHED_ITEMS_DETAIL,
                    tone = Tone.Success,
                )
            }
            GoodsOutFootNote(text = stringResource(R.string.goodsout_dispatch_note))
        }
    }
}
