package io.aiku.droid.ui.goodsout

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.ScanPanel
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun ScanToPickScreen(reference: String) {
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    var remaining by remember { mutableIntStateOf(GoodsOutSamples.SCAN_REMAINING) }
    var picked by remember { mutableIntStateOf(GoodsOutSamples.SCAN_PICKED) }
    var amount by remember { mutableIntStateOf(GoodsOutSamples.SCAN_AMOUNT) }
    val skuCode = GoodsOutSamples.SCAN_SKO
    val lineDoneText = stringResource(R.string.goodsout_scan_line_done_toast, skuCode)
    val pickedAmountText = pluralStringResource(R.plurals.goodsout_scan_picked_toast, amount, amount, skuCode)

    fun pick(count: Int) {
        picked += count
        remaining -= count
        if (remaining <= 0) {
            toasts.show(lineDoneText, ToastKind.Success)
            navigator.back()
        } else {
            toasts.show(pickedAmountText, ToastKind.Success)
            amount = amount.coerceAtMost(remaining)
        }
    }

    RequireAbility(ability = Ability.PickAndPack, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        AppScreen(
            header = {
                AppHeader(
                    isSample = true,
                    title = stringResource(R.string.goodsout_scan_to_pick),
                    kicker = pluralStringResource(R.plurals.goodsout_scan_kicker, GoodsOutSamples.SCAN_TOTAL_COUNT, reference, GoodsOutSamples.SCAN_PICKED_COUNT, GoodsOutSamples.SCAN_TOTAL_COUNT),
                )
            },
            activeTab = MainTab.Menu,
            showBar = true,
            bottomPanel = {
                ScanPanel(
                    code = skuCode,
                    chip = stringResource(R.string.goodsout_scan_picked_chip, picked),
                    subtitle = GoodsOutSamples.SCAN_SKO_DETAIL,
                    remaining = pluralStringResource(R.plurals.goodsout_left_on_item, remaining, remaining),
                    allLabel = pluralStringResource(R.plurals.goodsout_pick_all, remaining, remaining),
                    onAll = { pick(remaining) },
                    amount = amount,
                    onAmountChange = { amount = it.coerceIn(1, remaining) },
                    amountLabel = stringResource(R.string.goodsout_pick_amount, amount),
                    onAmount = { pick(amount) },
                    hint = stringResource(R.string.goodsout_scan_panel_hint),
                    maxAmount = remaining,
                    modifier = Modifier.fakeData(cornerRadius = 16.dp),
                )
            },
        ) {
            ScanHintCard(
                title = stringResource(R.string.goodsout_scan_listening),
                caption = stringResource(R.string.goodsout_scan_take_from),
                value = GoodsOutSamples.PICKING_LOCATION,
                detail = GoodsOutSamples.SCAN_QUEUED,
                modifier = Modifier.fakeData(),
            )
            GoodsOutHeading(title = stringResource(R.string.goodsout_scanned))
            ListGroup(modifier = Modifier.fakeData()) {
                GoodsOutSamples.scannedEntries.forEachIndexed { index, entry ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ItemRow(
                        code = entry.skuCode,
                        subtitle = entry.detail,
                        chip = if (entry.isPicked) stringResource(R.string.goodsout_scan_plus_one) else stringResource(R.string.goodsout_scan_could_not_pick),
                        chipTone = if (entry.isPicked) Tone.Success else Tone.Danger,
                    )
                }
            }
            GoodsOutFootNote(text = stringResource(R.string.goodsout_scan_bluetooth_note))
        }
    }
}
