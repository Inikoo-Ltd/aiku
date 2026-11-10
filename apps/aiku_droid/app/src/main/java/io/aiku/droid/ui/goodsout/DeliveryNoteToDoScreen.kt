package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.NoteCard
import io.aiku.droid.ui.components.PlatformChip
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.deliveryNoteSteps
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.goodsout.extra.EditAddressSheet
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun DeliveryNoteToDoScreen(reference: String) {
    val can = LocalWarehouse.current.can
    var showTrolley by remember { mutableStateOf(false) }
    var showAddress by remember { mutableStateOf(false) }

    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        AppScreen(
            header = {
                DeliveryNoteHeader(
                    reference = reference,
                    kicker = stringResource(R.string.goodsout_kicker_delivery_note, stringResource(R.string.dn_state_to_do)),
                )
            },
            activeTab = MainTab.Menu,
            showBar = true,
            footer = if (can.pickAndPack) {
                {
                    AikuButton(
                        text = stringResource(R.string.goodsout_start_picking),
                        onClick = { showTrolley = true },
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                    FooterHint(text = stringResource(R.string.goodsout_start_picking_hint))
                }
            } else {
                null
            },
        ) {
            StateStepper(steps = deliveryNoteSteps(current = DeliveryNoteStage.ToDo))
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                modifier = Modifier.fakeData(cornerRadius = 8.dp),
            ) {
                StatusBadge(text = GoodsOutSamples.CHANNEL_DROPSHIPPING, tone = Tone.Primary)
                PlatformChip(name = GoodsOutSamples.PLATFORM_SHOPIFY)
            }
            DeliveryNoteQuickActions(reference = reference)
            NoteCard(
                title = stringResource(R.string.goodsout_note_shipping_label),
                text = GoodsOutSamples.TO_DO_SHIPPING_LABEL_NOTE,
                icon = R.drawable.ic_ui_edit,
                modifier = Modifier.fakeData(),
            )
            NoteCard(
                title = stringResource(R.string.goodsout_note_customer),
                text = GoodsOutSamples.TO_DO_CUSTOMER_NOTE,
                icon = R.drawable.ic_ui_lock,
                modifier = Modifier.fakeData(),
            )
            NoteCard(
                title = stringResource(R.string.goodsout_note_warehouse),
                text = GoodsOutSamples.TO_DO_WAREHOUSE_NOTE,
                icon = R.drawable.ic_ui_edit,
                isWarning = true,
                modifier = Modifier.fakeData(),
            )
            SampleTimeline(entries = GoodsOutSamples.toDoTimeline)
            SampleDetailCard(title = stringResource(R.string.goodsout_card_order), lines = GoodsOutSamples.toDoOrder)
            SampleDetailCard(
                title = stringResource(R.string.goodsout_card_shipping),
                lines = listOf(SampleDetailLine(R.drawable.ic_ui_location, GoodsOutSamples.SHIPPING_ADDRESS)),
                actionLabel = if (can.pickAndPack) stringResource(R.string.goodsout_edit_delivery_address) else null,
                onAction = { showAddress = true },
            )
            SampleDetailCard(title = stringResource(R.string.goodsout_card_delivery_note), lines = GoodsOutSamples.toDoSummary)
            GoodsOutHeading(title = stringResource(R.string.goodsout_items_by_location))
            DeliveryNoteLines(lines = GoodsOutSamples.toDoLines)
        }
    }
    if (showTrolley) {
        TrolleySheet(reference = reference, onDismiss = { showTrolley = false })
    }
    if (showAddress) {
        EditAddressSheet(reference = reference, onDismiss = { showAddress = false })
    }
}
