package io.aiku.droid.ui.goodsout.extra

import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun ShippersScreen() {
    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        AppScreen(
            header = { ModuleHeader(section = WarehouseSection.Shippers) },
            activeTab = MainTab.Menu,
            showBar = true,
        ) {
            ListGroup(modifier = Modifier.fakeData()) {
                GoodsOutExtraSamples.shippers.forEachIndexed { index, shipper ->
                    if (index > 0) {
                        RowDivider()
                    }
                    val hasShipments = shipper.shipmentsToday > 0
                    ExtraRow(
                        title = shipper.name,
                        subtitle = if (hasShipments) {
                            pluralStringResource(R.plurals.goodsout_extra_shipments_today, shipper.shipmentsToday, shipper.shipmentsToday)
                        } else {
                            stringResource(R.string.goodsout_extra_no_shipments_today)
                        },
                        chip = stringResource(R.string.goodsout_extra_shipper_api),
                        chipTone = Tone.Primary,
                        leading = { ExtraIconTile(icon = R.drawable.ic_ui_truck, tone = if (hasShipments) Tone.Primary else Tone.Neutral) },
                    )
                }
            }
            HintText(text = stringResource(R.string.goodsout_extra_shippers_hint))
        }
    }
}
