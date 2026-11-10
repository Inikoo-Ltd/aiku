package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.NoteCard
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.deliveryNoteSteps
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.goodsout.extra.EditAddressSheet
import io.aiku.droid.ui.navigation.DeliveryNoteAllPickedRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ScanToPickRoute
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun DeliveryNotePickingScreen(reference: String) {
    val navigator = LocalAppNavigator.current
    val colors = LocalAikuColors.current
    val can = LocalWarehouse.current.can
    var showPicker by remember { mutableStateOf(false) }
    var showTrolley by remember { mutableStateOf(false) }
    var showAddress by remember { mutableStateOf(false) }
    var lineSkuCode by remember { mutableStateOf<String?>(null) }
    val changeLabel = stringResource(R.string.goodsout_change)

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
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(10.dp),
                        modifier = Modifier.fillMaxWidth().heightIn(min = 52.dp),
                    ) {
                        FooterStatus(
                            title = pluralStringResource(R.plurals.goodsout_lines_to_handle, GoodsOutSamples.LINES_TO_HANDLE, GoodsOutSamples.LINES_TO_HANDLE),
                            body = stringResource(R.string.goodsout_lines_to_handle_hint),
                            modifier = Modifier.weight(1f).fakeData(cornerRadius = 6.dp),
                        )
                        Text(
                            text = stringResource(R.string.goodsout_preview),
                            fontSize = 14.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = colors.link,
                            modifier = Modifier
                                .clip(ChipShape)
                                .clickable(role = Role.Button) { navigator.open(DeliveryNoteAllPickedRoute(reference = reference)) }
                                .padding(horizontal = 6.dp, vertical = 12.dp),
                        )
                    }
                }
            } else {
                null
            },
        ) {
            StateStepper(steps = deliveryNoteSteps(current = DeliveryNoteStage.Picking))
            ListGroup(modifier = Modifier.fakeData()) {
                IconInfoRow(
                    icon = R.drawable.ic_ui_person,
                    title = GoodsOutSamples.PICKER_ME,
                    subtitle = stringResource(R.string.goodsout_role_picker),
                    actionLabel = if (can.pickAndPack) changeLabel else null,
                    onClick = if (can.pickAndPack) ({ showPicker = true }) else null,
                )
                RowDivider()
                IconInfoRow(
                    icon = R.drawable.ic_ui_trolley,
                    title = GoodsOutSamples.TROLLEY,
                    subtitle = stringResource(R.string.goodsout_picking_into),
                    actionLabel = if (can.pickAndPack) changeLabel else null,
                    onClick = if (can.pickAndPack) ({ showTrolley = true }) else null,
                )
            }
            StatusBadge(text = GoodsOutSamples.CHANNEL_WHOLESALE, tone = Tone.Primary, modifier = Modifier.fakeData(cornerRadius = 6.dp))
            DeliveryNoteQuickActions(reference = reference)
            NoteCard(
                title = stringResource(R.string.goodsout_note_customer),
                text = GoodsOutSamples.PICKING_CUSTOMER_NOTE,
                icon = R.drawable.ic_ui_lock,
                modifier = Modifier.fakeData(),
            )
            NoteCard(
                title = stringResource(R.string.goodsout_note_warehouse),
                text = GoodsOutSamples.PICKING_WAREHOUSE_NOTE,
                icon = R.drawable.ic_ui_edit,
                isWarning = true,
                modifier = Modifier.fakeData(),
            )
            SampleTimeline(entries = GoodsOutSamples.pickingTimeline)
            SampleDetailCard(title = stringResource(R.string.goodsout_card_order), lines = GoodsOutSamples.pickingOrder)
            SampleDetailCard(
                title = stringResource(R.string.goodsout_card_shipping),
                lines = listOf(SampleDetailLine(R.drawable.ic_ui_location, GoodsOutSamples.SHIPPING_ADDRESS)),
                actionLabel = if (can.pickAndPack) stringResource(R.string.goodsout_edit_delivery_address) else null,
                onAction = { showAddress = true },
            )
            SampleDetailCard(title = stringResource(R.string.goodsout_card_delivery_note), lines = GoodsOutSamples.pickingSummary)
            if (can.pickAndPack) {
                ScanHintCard(
                    title = stringResource(R.string.goodsout_scan_to_pick),
                    caption = stringResource(R.string.goodsout_scan_take_from),
                    value = GoodsOutSamples.PICKING_LOCATION,
                    detail = GoodsOutSamples.PICKING_LOCATION_DETAIL,
                    modifier = Modifier.fakeData(),
                    onClick = { navigator.open(ScanToPickRoute(reference = reference)) },
                )
            }
            GoodsOutHeading(
                title = stringResource(R.string.goodsout_items_by_location),
                trailing = GoodsOutSamples.PICKED_SUMMARY,
                isTrailingSample = true,
            )
            DeliveryNoteLines(
                lines = GoodsOutSamples.pickingLines,
                onLineClick = if (can.pickAndPack) ({ line: SampleLine -> lineSkuCode = line.skuCode }) else null,
            )
            GoodsOutFootNote(text = stringResource(R.string.goodsout_picking_note))
        }
    }
    if (showPicker) {
        PickerSheet(reference = reference, onDismiss = { showPicker = false })
    }
    if (showTrolley) {
        TrolleySheet(reference = reference, onDismiss = { showTrolley = false })
    }
    if (showAddress) {
        EditAddressSheet(reference = reference, onDismiss = { showAddress = false })
    }
    lineSkuCode?.let { skuCode ->
        LineActionsSheet(reference = reference, skuCode = skuCode, onDismiss = { lineSkuCode = null })
    }
}
