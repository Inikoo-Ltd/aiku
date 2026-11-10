package io.aiku.droid.ui.goodsout.extra

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.deliveryNoteSteps
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ShipmentsRoute
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun ExternalShipmentScreen(reference: String) {
    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        ExternalShipmentContent(reference = reference)
    }
}

@Composable
private fun ExternalShipmentContent(reference: String) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val platform = GoodsOutExtraSamples.TIKTOK

    AppScreen(
        header = {
            AppHeader(
                isSample = true,
                title = reference,
                kicker = deliveryNoteKicker(GoodsOutExtraSamples.MARKETPLACES, platform),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = {
            if (can.pickAndPack) {
                AikuButton(
                    text = stringResource(R.string.goodsout_extra_get_shipment_from, platform),
                    onClick = { navigator.open(ShipmentsRoute(reference)) },
                    icon = R.drawable.ic_ui_truck,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            } else {
                FooterStatus(
                    title = stringResource(R.string.goodsout_extra_shipped_by_platform),
                    body = stringResource(R.string.goodsout_extra_label_from_platform, platform),
                )
            }
        },
    ) {
        StateStepper(steps = deliveryNoteSteps(DeliveryNoteStage.Packed))
        PlatformShipmentCard(platform = platform, modifier = Modifier.fakeData())
        ListGroup(modifier = Modifier.fakeData()) {
            GoodsOutExtraSamples.externalParcels.forEachIndexed { index, parcel ->
                if (index > 0) {
                    RowDivider()
                }
                ExtraRow(
                    title = stringResource(R.string.goodsout_extra_parcel_number, parcel.number),
                    subtitle = stringResource(R.string.goodsout_extra_parcel_size, parcel.weightKg, parcel.length, parcel.width, parcel.height),
                    leading = { ExtraIconTile(icon = R.drawable.ic_ui_sko, tone = Tone.Primary) },
                )
            }
        }
        HintText(text = stringResource(R.string.goodsout_extra_external_shipment_hint))
    }
}

@Composable
private fun PlatformShipmentCard(platform: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .padding(16.dp),
    ) {
        ExtraIconTile(icon = R.drawable.ic_ui_truck, tone = Tone.Primary)
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(
                text = stringResource(R.string.goodsout_extra_shipped_by_platform),
                fontSize = 16.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
            )
            Text(text = stringResource(R.string.goodsout_extra_label_from_platform, platform), fontSize = 13.sp, color = colors.textTertiary)
        }
    }
}
