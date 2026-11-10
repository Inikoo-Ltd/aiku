package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.deliveryNoteSteps
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.DeliveryNotePackingRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun DeliveryNotePickedScreen(reference: String) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    var showBay by remember { mutableStateOf(false) }

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
                    AikuButton(
                        text = stringResource(R.string.goodsout_start_packing),
                        onClick = { navigator.switchSection(DeliveryNotePackingRoute(reference = reference)) },
                        icon = R.drawable.ic_ui_sko,
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            } else {
                null
            },
        ) {
            StateStepper(steps = deliveryNoteSteps(current = DeliveryNoteStage.Picked))
            ListGroup(modifier = Modifier.fakeData()) {
                IconInfoRow(
                    icon = R.drawable.ic_ui_area,
                    title = GoodsOutSamples.PICKED_BAY,
                    subtitle = stringResource(R.string.goodsout_bay_waiting_for_packing),
                    actionLabel = if (can.pickAndPack) stringResource(R.string.goodsout_change) else null,
                    onClick = if (can.pickAndPack) ({ showBay = true }) else null,
                )
                RowDivider()
                IconInfoRow(
                    icon = R.drawable.ic_ui_person,
                    title = stringResource(R.string.goodsout_role_packer),
                    subtitle = GoodsOutSamples.PACKER_DETAIL,
                )
            }
            GoodsOutHeading(title = stringResource(R.string.dn_state_picked))
            DeliveryNoteLines(lines = GoodsOutSamples.handledLines)
            GoodsOutFootNote(text = stringResource(R.string.goodsout_picked_note))
        }
    }
    if (showBay) {
        ChangePickedBaySheet(reference = reference, onDismiss = { showBay = false })
    }
}
