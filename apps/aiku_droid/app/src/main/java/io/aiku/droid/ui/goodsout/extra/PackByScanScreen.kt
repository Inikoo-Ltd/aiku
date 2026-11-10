package io.aiku.droid.ui.goodsout.extra

import androidx.compose.runtime.Composable
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun PackByScanScreen(reference: String) {
    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        PackByScanContent(reference = reference)
    }
}

@Composable
private fun PackByScanContent(reference: String) {
    val can = LocalWarehouse.current.can
    val toasts = LocalToasts.current
    val notes = GoodsOutExtraSamples.packNotes
    val packedPerNote = remember { mutableStateListOf(*notes.map { it.packed }.toTypedArray()) }
    val currentIndex = notes.indices.firstOrNull { packedPerNote[it] < notes[it].items }
    val allPackedMessage = stringResource(R.string.goodsout_extra_all_packed)
    val packOne: () -> Unit = {
        if (currentIndex == null) {
            toasts.show(allPackedMessage, ToastKind.Success)
        } else {
            packedPerNote[currentIndex] = packedPerNote[currentIndex] + 1
        }
    }

    AppScreen(
        header = {
            AppHeader(
                isSample = true,
                title = stringResource(R.string.goodsout_extra_pack_by_scan),
                kicker = listOf(
                    reference,
                    stringResource(SessionState.PickingFinished.label),
                    stringResource(R.string.goodsout_extra_packing_percent, GoodsOutExtraSamples.PACKING_PERCENT),
                ).joinToString(KICKER_SEPARATOR),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        ScanTargetCard(
            title = stringResource(R.string.goodsout_extra_scan_to_pack),
            caption = stringResource(R.string.goodsout_extra_put_in_box),
            target = if (currentIndex == null) {
                stringResource(R.string.goodsout_extra_all_packed)
            } else {
                stringResource(
                    R.string.goodsout_extra_delivery_note_box,
                    notes[currentIndex].deliveryNote,
                    stringResource(R.string.goodsout_extra_box_number, GoodsOutExtraSamples.PACK_BOX),
                )
            },
            onScan = packOne.takeIf { can.pickAndPack },
            modifier = Modifier.fakeData(),
        )
        SectionHeading(text = stringResource(R.string.section_delivery_notes))
        ListGroup(modifier = Modifier.fakeData()) {
            notes.forEachIndexed { index, note ->
                if (index > 0) {
                    RowDivider()
                }
                val packed = packedPerNote[index]
                val isPacked = packed >= note.items
                ItemRow(
                    code = note.deliveryNote,
                    subtitle = listOf(
                        note.trolley,
                        pluralStringResource(R.plurals.goodsout_extra_items, note.items, note.items),
                    ).joinToString(KICKER_SEPARATOR),
                    chip = when {
                        isPacked -> stringResource(R.string.dn_state_packed)
                        packed > 0 -> stringResource(R.string.dn_state_packing)
                        else -> null
                    },
                    chipTone = progressTone(packed, note.items) ?: Tone.Neutral,
                    done = packed.toString(),
                    required = note.items.toString(),
                    quantityTone = progressTone(packed, note.items),
                    showPhoto = false,
                )
            }
        }
        HintText(text = stringResource(R.string.goodsout_extra_pack_by_scan_hint))
    }
}
