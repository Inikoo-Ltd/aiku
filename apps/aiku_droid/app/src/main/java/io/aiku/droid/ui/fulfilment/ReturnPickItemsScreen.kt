package io.aiku.droid.ui.fulfilment

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun ReturnPickItemsScreen(reference: String) {
    RequireFulfilment(section = R.string.section_fulfilment_returns) {
        ReturnPickItemsContent(reference = reference)
    }
}

@Composable
private fun ReturnPickItemsContent(reference: String) {
    val can = LocalWarehouse.current.can
    val toasts = LocalToasts.current
    val pallets = returnItemPalletsSample
    val items = pallets.flatMap { it.items }
    val shortItem = items.firstOrNull { it.onPallet < it.required && it.picked < it.required }
    val scanPallet = pallets.first()

    AppScreen(
        header = {
            FulfilmentHeader(
                title = reference,
                kicker = dotJoined(
                    stringResource(R.string.fulfilment_return_kicker),
                    stringResource(FulfilmentReturnType.StoredItems.label),
                    RETURN_ITEMS_CUSTOMER_SAMPLE,
                ),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = {
            if (can.editFulfilment && shortItem != null) {
                val pickElsewhereMessage = stringResource(R.string.fulfilment_return_pick_from_other_toast, shortItem.code)
                AikuButton(
                    text = stringResource(R.string.fulfilment_return_pick_from_other, shortItem.code),
                    onClick = { toasts.show(pickElsewhereMessage, ToastKind.Info) },
                    style = ButtonStyle.Soft,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth().fakeData(cornerRadius = 8.dp),
                )
            } else {
                val required = items.sumOf { it.required }
                FooterStatus(
                    title = stringResource(FulfilmentReturnState.Picking.label),
                    body = pluralStringResource(R.plurals.fulfilment_return_items_progress, required, items.sumOf { it.picked }, required),
                    modifier = Modifier.fakeData(cornerRadius = 8.dp),
                )
            }
        },
    ) {
        StateStepper(
            steps = fulfilmentReturnSteps(current = FulfilmentReturnState.Picking),
            modifier = Modifier.fakeData(cornerRadius = 14.dp),
        )
        if (can.editFulfilment) {
            FulfilmentScanPrompt(
                title = stringResource(R.string.fulfilment_return_scan_title),
                instruction = stringResource(R.string.fulfilment_return_scan_pallet_items),
                target = dotJoined(scanPallet.reference, scanPallet.location),
                modifier = Modifier.fakeData(),
            )
        }
        pallets.forEach { pallet ->
            FulfilmentSectionHeading(
                text = stringResource(R.string.fulfilment_return_from_pallet, dotJoined(pallet.reference, pallet.location)),
                modifier = Modifier.fakeData(cornerRadius = 4.dp),
            )
            ListGroup(modifier = Modifier.fakeData()) {
                pallet.items.forEachIndexed { index, item ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ReturnItemRow(item = item)
                }
            }
        }
        FulfilmentFootnote(text = stringResource(R.string.fulfilment_return_items_footnote))
    }
}

@Composable
private fun ReturnItemRow(item: ReturnItemSample) {
    val quantityTone = when {
        item.picked >= item.required -> Tone.Success
        item.picked > 0 -> Tone.Warn
        else -> null
    }

    ItemRow(
        code = item.code,
        subtitle = if (item.onPallet < item.required) {
            dotJoined(item.name, pluralStringResource(R.plurals.fulfilment_return_only_on_pallet, item.onPallet, item.onPallet))
        } else {
            item.name
        },
        done = item.picked.toString(),
        required = item.required.toString(),
        quantityTone = quantityTone,
    )
}
