package io.aiku.droid.ui.fulfilment

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
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
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.PalletIncidentsRoute
import io.aiku.droid.ui.navigation.ReturnPickedRoute

@Composable
fun ReturnPickPalletsScreen(reference: String) {
    RequireFulfilment(section = R.string.section_fulfilment_returns) {
        ReturnPickPalletsContent(reference = reference)
    }
}

@Composable
private fun ReturnPickPalletsContent(reference: String) {
    val can = LocalWarehouse.current.can
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val pallets = returnPalletsSample
    val nextPallet = pallets.firstOrNull { it.state == ReturnPalletState.ToPick }
    val pickedCount = pallets.count { it.state == ReturnPalletState.Picked }

    AppScreen(
        header = {
            FulfilmentHeader(
                title = reference,
                kicker = dotJoined(
                    stringResource(R.string.fulfilment_return_kicker),
                    stringResource(FulfilmentReturnType.Pallets.label),
                    RETURN_PALLETS_CUSTOMER_SAMPLE,
                ),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = {
            if (can.editFulfilment && nextPallet != null) {
                val pickedMessage = stringResource(R.string.fulfilment_return_picked_toast, nextPallet.reference)
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    AikuButton(
                        text = stringResource(R.string.fulfilment_return_not_picked),
                        onClick = { navigator.open(PalletIncidentsRoute) },
                        style = ButtonStyle.Outline,
                        height = 52.dp,
                        modifier = Modifier.weight(1f),
                    )
                    AikuButton(
                        text = stringResource(R.string.fulfilment_return_pick_pallet, nextPallet.reference),
                        onClick = {
                            toasts.show(pickedMessage, ToastKind.Success)
                            navigator.switchSection(ReturnPickedRoute(reference))
                        },
                        height = 52.dp,
                        modifier = Modifier.weight(1f).fakeData(cornerRadius = 8.dp),
                    )
                }
            } else {
                FooterStatus(
                    title = stringResource(FulfilmentReturnState.Picking.label),
                    body = pluralStringResource(R.plurals.fulfilment_return_pallets_progress, pallets.size, pickedCount, pallets.size),
                    modifier = Modifier.fakeData(cornerRadius = 8.dp),
                )
            }
        },
    ) {
        StateStepper(
            steps = fulfilmentReturnSteps(current = FulfilmentReturnState.Picking),
            modifier = Modifier.fakeData(cornerRadius = 14.dp),
        )
        if (can.editFulfilment && nextPallet != null) {
            FulfilmentScanPrompt(
                title = stringResource(R.string.fulfilment_return_scan_title),
                instruction = stringResource(R.string.fulfilment_return_scan_pallet),
                target = nextPallet.reference,
                detail = nextPallet.location,
                modifier = Modifier.fakeData(),
            )
        }
        ListGroup(modifier = Modifier.fakeData()) {
            pallets.forEachIndexed { index, pallet ->
                if (index > 0) {
                    RowDivider()
                }
                val type = stringResource(pallet.type.label)
                ItemRow(
                    code = pallet.reference,
                    subtitle = if (pallet.state == ReturnPalletState.Picked) {
                        stringResource(R.string.fulfilment_return_was_in, type, pallet.location)
                    } else {
                        dotJoined(type, pallet.location)
                    },
                    chip = stringResource(pallet.state.label),
                    chipTone = pallet.state.tone,
                    showPhoto = false,
                )
            }
        }
        FulfilmentFootnote(text = stringResource(R.string.fulfilment_return_pick_footnote))
    }
}
