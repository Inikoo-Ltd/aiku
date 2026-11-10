package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.deliveryNoteSteps
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.goodsout.extra.EditAddressSheet
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ManualShipmentRoute
import io.aiku.droid.ui.navigation.ShipmentsRoute
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
internal fun ShipmentHeader(reference: String, title: String) {
    AppHeader(
        isSample = true,
        title = title,
        kicker = stringResource(R.string.goodsout_kicker_reference_state, reference, stringResource(R.string.dn_state_packed)),
    )
}

@Composable
fun AddShipmentScreen(reference: String) {
    val navigator = LocalAppNavigator.current
    var selectedShipper by remember { mutableIntStateOf(0) }
    var showAddress by remember { mutableStateOf(false) }

    RequireAbility(ability = Ability.PickAndPack, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        AppScreen(
            header = { ShipmentHeader(reference = reference, title = stringResource(R.string.goodsout_add_shipment)) },
            activeTab = MainTab.Menu,
            showBar = true,
            footer = {
                AikuButton(
                    text = stringResource(R.string.goodsout_add_shipment_create_label),
                    onClick = { navigator.open(ShipmentsRoute(reference = reference)) },
                    icon = R.drawable.ic_ui_truck,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            },
        ) {
            StateStepper(steps = deliveryNoteSteps(current = DeliveryNoteStage.Packed))
            ListGroup(modifier = Modifier.fakeData()) {
                IconInfoRow(
                    icon = R.drawable.ic_ui_location,
                    title = stringResource(R.string.goodsout_delivery_address),
                    subtitle = GoodsOutSamples.SHIPMENT_ADDRESS,
                    actionLabel = stringResource(R.string.goodsout_edit),
                    onClick = { showAddress = true },
                )
            }
            GoodsOutHeading(title = stringResource(R.string.goodsout_api_shippers))
            Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fakeData()) {
                GoodsOutSamples.apiShippers.forEachIndexed { index, shipper ->
                    ShipperChoiceRow(
                        name = shipper.name,
                        detail = shipper.detail,
                        selected = index == selectedShipper,
                        onClick = { selectedShipper = index },
                    )
                }
            }
            GoodsOutHeading(title = stringResource(R.string.goodsout_other_shippers))
            ListGroup {
                IconInfoRow(
                    icon = R.drawable.ic_ui_search,
                    title = stringResource(R.string.goodsout_choose_other_shipper),
                    subtitle = stringResource(R.string.goodsout_choose_other_shipper_hint),
                    tone = Tone.Neutral,
                    onClick = { navigator.open(ManualShipmentRoute(reference = reference)) },
                )
            }
            GoodsOutHeading(title = stringResource(R.string.goodsout_parcels))
            SampleInfoGroup(rows = GoodsOutSamples.shipmentParcels, icon = R.drawable.ic_ui_sko)
            GoodsOutFootNote(text = stringResource(R.string.goodsout_add_shipment_note))
        }
    }
    if (showAddress) {
        EditAddressSheet(reference = reference, onDismiss = { showAddress = false })
    }
}

@Composable
private fun ShipperChoiceRow(name: String, detail: String, selected: Boolean, onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val shape = RoundedCornerShape(10.dp)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 64.dp)
            .clip(shape)
            .background(if (selected) colors.soft else colors.surface)
            .border(if (selected) 2.dp else 1.dp, if (selected) colors.primary else colors.border, shape)
            .selectable(selected = selected, role = Role.RadioButton, onClick = onClick)
            .padding(horizontal = 14.dp, vertical = 10.dp),
    ) {
        IconTile40(icon = R.drawable.ic_ui_truck, tone = if (selected) Tone.Primary else Tone.Neutral)
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = name, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = detail, fontSize = 12.sp, color = colors.textTertiary)
        }
        if (selected) {
            Icon(painter = painterResource(R.drawable.ic_ui_check), contentDescription = null, tint = colors.link, modifier = Modifier.size(20.dp))
        }
    }
}
