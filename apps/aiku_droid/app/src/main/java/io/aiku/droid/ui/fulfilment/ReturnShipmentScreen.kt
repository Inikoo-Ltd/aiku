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
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.IconTile
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ReturnDispatchedRoute

@Composable
fun ReturnShipmentScreen(reference: String) {
    RequireFulfilment(section = R.string.section_fulfilment_returns) {
        val can = LocalWarehouse.current.can
        val navigator = LocalAppNavigator.current

        AppScreen(
            header = {
                FulfilmentHeader(
                    title = reference,
                    kicker = dotJoined(stringResource(R.string.fulfilment_return_kicker), stringResource(FulfilmentReturnState.Picked.label)),
                )
            },
            activeTab = MainTab.Menu,
            showBar = true,
            footer = {
                if (can.editFulfilment) {
                    AikuButton(
                        text = stringResource(R.string.fulfilment_return_dispatch),
                        onClick = { navigator.switchSection(ReturnDispatchedRoute(reference)) },
                        icon = R.drawable.ic_ui_truck,
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                } else {
                    FooterStatus(
                        title = stringResource(R.string.fulfilment_return_label_printed),
                        body = stringResource(R.string.fulfilment_return_ready_to_dispatch),
                    )
                }
            },
        ) {
            StateStepper(
                steps = fulfilmentReturnSteps(current = FulfilmentReturnState.Picked),
                modifier = Modifier.fakeData(cornerRadius = 14.dp),
            )
            ListGroup(modifier = Modifier.fakeData()) {
                FulfilmentRow(
                    title = RETURN_SHIPPER_SAMPLE,
                    subtitle = stringResource(
                        R.string.fulfilment_return_tracking,
                        RETURN_TRACKING_SAMPLE,
                        pluralStringResource(R.plurals.fulfilment_pallet_count, RETURN_SHIPMENT_PALLET_COUNT_SAMPLE, RETURN_SHIPMENT_PALLET_COUNT_SAMPLE),
                    ),
                    chip = stringResource(R.string.fulfilment_return_label_printed),
                    chipTone = Tone.Success,
                    leading = { IconTile(icon = R.drawable.ic_ui_truck, tone = Tone.Success) },
                )
            }
            FulfilmentFootnote(text = stringResource(R.string.fulfilment_return_shipment_footnote))
        }
    }
}
