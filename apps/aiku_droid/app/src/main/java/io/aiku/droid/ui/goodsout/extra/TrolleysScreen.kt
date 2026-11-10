package io.aiku.droid.ui.goodsout.extra

import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
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
fun TrolleysScreen() {
    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        AppScreen(
            header = { ModuleHeader(section = WarehouseSection.Trolleys) },
            activeTab = MainTab.Menu,
            showBar = true,
        ) {
            ListGroup(modifier = Modifier.fakeData()) {
                GoodsOutExtraSamples.trolleys.forEachIndexed { index, trolley ->
                    if (index > 0) {
                        RowDivider()
                    }
                    val tone = if (trolley.use == null) Tone.Success else Tone.Primary
                    ExtraRow(
                        title = trolley.code,
                        subtitle = when (val use = trolley.use) {
                            is TrolleyUse.DeliveryNote -> stringResource(R.string.goodsout_extra_trolley_delivery_note_use, use.reference)
                            is TrolleyUse.Session -> stringResource(R.string.goodsout_extra_trolley_session_use, use.reference)
                            null -> null
                        },
                        chip = stringResource(if (trolley.use == null) R.string.goodsout_extra_free else R.string.goodsout_extra_in_use),
                        chipTone = tone,
                        leading = { ExtraIconTile(icon = R.drawable.ic_ui_trolley, tone = tone) },
                    )
                }
            }
            HintText(text = stringResource(R.string.goodsout_extra_trolleys_hint))
        }
    }
}
