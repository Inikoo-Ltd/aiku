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
fun PickedBaysScreen() {
    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        AppScreen(
            header = { ModuleHeader(section = WarehouseSection.PickedBays) },
            activeTab = MainTab.Menu,
            showBar = true,
        ) {
            ListGroup(modifier = Modifier.fakeData()) {
                GoodsOutExtraSamples.bays.forEachIndexed { index, bay ->
                    if (index > 0) {
                        RowDivider()
                    }
                    val isFree = bay.deliveryNotes == 0
                    val tone = if (isFree) Tone.Success else Tone.Primary
                    ExtraRow(
                        title = bay.name,
                        subtitle = if (isFree) {
                            null
                        } else {
                            listOfNotNull(
                                pluralStringResource(R.plurals.goodsout_extra_delivery_notes, bay.deliveryNotes, bay.deliveryNotes),
                                if (bay.waiting > 0) pluralStringResource(R.plurals.goodsout_extra_waiting_count, bay.waiting, bay.waiting) else null,
                            ).joinToString(KICKER_SEPARATOR)
                        },
                        chip = stringResource(if (isFree) R.string.goodsout_extra_free else R.string.goodsout_extra_in_use),
                        chipTone = tone,
                        leading = { ExtraIconTile(icon = R.drawable.ic_ui_area, tone = tone) },
                    )
                }
            }
            HintText(text = stringResource(R.string.goodsout_extra_bays_hint))
        }
    }
}
