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
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.deliveryNoteSteps
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun DeliveryNoteAllPickedScreen(reference: String) {
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
                        text = stringResource(R.string.goodsout_set_as_picked),
                        onClick = { showBay = true },
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            } else {
                null
            },
        ) {
            StateStepper(steps = deliveryNoteSteps(current = DeliveryNoteStage.Picking))
            GoodsOutHeading(
                title = stringResource(R.string.goodsout_items_by_location),
                trailing = stringResource(R.string.goodsout_every_line_handled),
            )
            DeliveryNoteLines(lines = GoodsOutSamples.handledLines)
            GoodsOutFootNote(text = stringResource(R.string.goodsout_all_picked_note))
        }
    }
    if (showBay) {
        PickedBaySheet(reference = reference, onDismiss = { showBay = false })
    }
}
