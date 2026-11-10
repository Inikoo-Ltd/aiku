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
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.FulfilmentBookInRoute
import io.aiku.droid.ui.navigation.FulfilmentDeliveriesRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun FulfilmentDeliveryScreen(reference: String) {
    RequireFulfilment(section = R.string.section_fulfilment_deliveries) {
        ReceivedDeliveryContent(reference = reference)
    }
}

@Composable
private fun ReceivedDeliveryContent(reference: String) {
    val can = LocalWarehouse.current.can
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val notReceivedMessage = stringResource(R.string.fulfilment_not_received_toast, reference)

    AppScreen(
        header = {
            FulfilmentHeader(
                title = reference,
                kicker = dotJoined(stringResource(R.string.fulfilment_delivery_kicker), RECEIVED_DELIVERY_CUSTOMER_SAMPLE),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = {
            if (can.editFulfilment) {
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    AikuButton(
                        text = stringResource(R.string.fulfilment_not_received),
                        onClick = {
                            toasts.show(notReceivedMessage, ToastKind.Success)
                            navigator.switchSection(FulfilmentDeliveriesRoute)
                        },
                        style = ButtonStyle.Danger,
                        height = 52.dp,
                        modifier = Modifier.weight(1f),
                    )
                    AikuButton(
                        text = stringResource(R.string.fulfilment_delivery_start_booking_in),
                        onClick = { navigator.switchSection(FulfilmentBookInRoute(reference)) },
                        height = 52.dp,
                        modifier = Modifier.weight(1f),
                    )
                }
            } else {
                FooterStatus(title = stringResource(R.string.fulfilment_delivery_waiting_to_book_in))
            }
        },
    ) {
        StateStepper(
            steps = fulfilmentDeliverySteps(current = FulfilmentDeliveryState.Received),
            modifier = Modifier.fakeData(cornerRadius = 14.dp),
        )
        FulfilmentSectionHeading(
            text = stringResource(R.string.fulfilment_pallets),
            sampleCaption = pluralStringResource(R.plurals.fulfilment_delivery_received_count, RECEIVED_PALLET_COUNT_SAMPLE, RECEIVED_PALLET_COUNT_SAMPLE),
        )
        ListGroup(modifier = Modifier.fakeData()) {
            receivedLinesSample.forEachIndexed { index, line ->
                if (index > 0) {
                    RowDivider()
                }
                FulfilmentRow(
                    title = line.reference,
                    subtitle = line.description,
                    chip = stringResource(line.type.label),
                    trailing = { StatusBadge(text = stringResource(line.state.label), tone = line.state.tone) },
                )
            }
        }
        FulfilmentFootnote(text = stringResource(R.string.fulfilment_delivery_footnote))
    }
}
