package io.aiku.droid.ui.goodsout.extra

import androidx.compose.runtime.Composable
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun PickByLocationScreen(reference: String) {
    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        PickByLocationContent(reference = reference)
    }
}

@Composable
private fun PickByLocationContent(reference: String) {
    val can = LocalWarehouse.current.can
    val toasts = LocalToasts.current
    val location = GoodsOutExtraSamples.pickLocation
    val pickedPerNote = remember { mutableStateListOf(*GoodsOutExtraSamples.putInto.map { it.picked }.toTypedArray()) }
    val scannedSinceOpen = pickedPerNote.sum() - GoodsOutExtraSamples.putInto.sumOf { it.picked }
    val locationDoneMessage = stringResource(R.string.goodsout_extra_location_done, location.location)
    val pickOne: () -> Unit = {
        val nextIndex = GoodsOutExtraSamples.putInto.indices.firstOrNull { pickedPerNote[it] < GoodsOutExtraSamples.putInto[it].required }
        if (nextIndex == null) {
            toasts.show(locationDoneMessage, ToastKind.Success)
        } else {
            pickedPerNote[nextIndex] = pickedPerNote[nextIndex] + 1
        }
    }

    AppScreen(
        header = {
            AppHeader(
                isSample = true,
                title = stringResource(R.string.goodsout_extra_pick_by_location),
                kicker = listOf(
                    reference,
                    stringResource(SessionState.Picking.label),
                    stringResource(
                        R.string.goodsout_extra_count_of_total,
                        GoodsOutExtraSamples.SESSION_PICKED + scannedSinceOpen,
                        GoodsOutExtraSamples.SESSION_ITEMS,
                    ),
                ).joinToString(KICKER_SEPARATOR),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        ScanTargetCard(
            title = stringResource(R.string.goodsout_extra_scan_to_pick),
            caption = stringResource(R.string.goodsout_extra_take_from_location),
            target = location.location,
            detail = stringResource(R.string.goodsout_extra_to_pick_detail, location.skoCode, location.name, location.required),
            onScan = pickOne.takeIf { can.pickAndPack },
            modifier = Modifier.fakeData(),
        )
        SectionHeading(text = stringResource(R.string.goodsout_extra_put_into))
        ListGroup(modifier = Modifier.fakeData()) {
            GoodsOutExtraSamples.putInto.forEachIndexed { index, target ->
                if (index > 0) {
                    RowDivider()
                }
                val picked = pickedPerNote[index]
                val tone = progressTone(picked, target.required)
                ExtraRow(
                    title = stringResource(R.string.goodsout_extra_trolley_delivery_note, target.trolley, target.deliveryNote),
                    subtitle = location.skoCode,
                    leading = { ExtraIconTile(icon = R.drawable.ic_ui_trolley, tone = tone ?: Tone.Neutral) },
                    trailing = { QuantityText(done = picked, required = target.required, tone = tone) },
                )
            }
        }
        SectionHeading(text = stringResource(R.string.goodsout_extra_next_location))
        ListGroup(modifier = Modifier.fakeData()) {
            LocationPickRow(pick = GoodsOutExtraSamples.nextLocation, tone = Tone.Neutral)
        }
        HintText(text = stringResource(R.string.goodsout_extra_pick_by_location_hint))
    }
}
