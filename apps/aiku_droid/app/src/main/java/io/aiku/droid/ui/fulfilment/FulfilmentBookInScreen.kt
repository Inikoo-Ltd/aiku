package io.aiku.droid.ui.fulfilment

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
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
import io.aiku.droid.ui.navigation.FulfilmentBookedInRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun FulfilmentBookInScreen(reference: String) {
    RequireFulfilment(section = R.string.section_fulfilment_deliveries) {
        BookInContent(reference = reference)
    }
}

@Composable
private fun BookInContent(reference: String) {
    val can = LocalWarehouse.current.can
    val navigator = LocalAppNavigator.current
    var pallets by remember { mutableStateOf(bookInPalletsSample) }
    val current = pallets.firstOrNull { it.state == FulfilmentDeliveryState.BookingIn }
    val bookedIn = pallets.count { it.state == FulfilmentDeliveryState.BookedIn }
    val progress = pluralStringResource(R.plurals.fulfilment_book_in_progress, pallets.size, bookedIn, pallets.size)

    AppScreen(
        header = {
            FulfilmentHeader(
                title = reference,
                kicker = dotJoined(stringResource(R.string.fulfilment_delivery_kicker), stringResource(FulfilmentDeliveryState.BookingIn.label)),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = {
            if (can.editFulfilment) {
                AikuButton(
                    text = stringResource(
                        if (current != null) R.string.fulfilment_book_in_set_booked_in_after_last else R.string.fulfilment_book_in_set_booked_in,
                    ),
                    onClick = { navigator.switchSection(FulfilmentBookedInRoute(reference)) },
                    style = if (current != null) ButtonStyle.Soft else ButtonStyle.Primary,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            } else {
                FooterStatus(
                    title = stringResource(FulfilmentDeliveryState.BookingIn.label),
                    body = progress,
                    modifier = Modifier.fakeData(cornerRadius = 8.dp),
                )
            }
        },
    ) {
        StateStepper(
            steps = fulfilmentDeliverySteps(current = FulfilmentDeliveryState.BookingIn),
            modifier = Modifier.fakeData(cornerRadius = 14.dp),
        )
        if (can.editFulfilment && current != null) {
            BookInCurrentPallet(
                pallet = current,
                onBookedIn = { location ->
                    pallets = pallets.map { if (it.reference == current.reference) it.copy(state = FulfilmentDeliveryState.BookedIn, location = location) else it }
                },
                onNotReceived = {
                    pallets = pallets.map { if (it.reference == current.reference) it.copy(state = FulfilmentDeliveryState.NotReceived) else it }
                },
            )
        }
        FulfilmentSectionHeading(text = stringResource(R.string.fulfilment_pallets), sampleCaption = progress)
        ListGroup(modifier = Modifier.fakeData()) {
            pallets.forEachIndexed { index, pallet ->
                if (index > 0) {
                    RowDivider()
                }
                ItemRow(
                    code = pallet.reference,
                    subtitle = dotJoined(stringResource(pallet.type.label), pallet.location ?: stringResource(R.string.fulfilment_no_location_yet)),
                    chip = stringResource(pallet.state.label),
                    chipTone = pallet.state.tone,
                    showPhoto = false,
                )
            }
        }
        FulfilmentFootnote(text = stringResource(R.string.fulfilment_book_in_footnote))
    }
}

@Composable
private fun BookInCurrentPallet(pallet: BookInPalletSample, onBookedIn: (String) -> Unit, onNotReceived: () -> Unit) {
    val toasts = LocalToasts.current
    val location = pallet.suggestedLocation
    val bookedInMessage = stringResource(R.string.fulfilment_book_in_booked_toast, pallet.reference, location.orEmpty())
    val notReceivedMessage = stringResource(R.string.fulfilment_not_received_toast, pallet.reference)

    if (location != null) {
        FulfilmentScanPrompt(
            title = stringResource(R.string.fulfilment_book_in_scan_title),
            instruction = dotJoined(pallet.reference, stringResource(pallet.type.label), pallet.customerReference),
            target = location,
            detail = pallet.suggestedArea,
            modifier = Modifier.fakeData(),
        )
    }
    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        AikuButton(
            text = stringResource(R.string.fulfilment_not_received),
            onClick = {
                onNotReceived()
                toasts.show(notReceivedMessage, ToastKind.Success)
            },
            style = ButtonStyle.Outline,
            height = 52.dp,
            modifier = Modifier.weight(1f),
        )
        if (location != null) {
            AikuButton(
                text = stringResource(R.string.fulfilment_book_in_at, location),
                onClick = {
                    onBookedIn(location)
                    toasts.show(bookedInMessage, ToastKind.Success)
                },
                height = 52.dp,
                modifier = Modifier.weight(1f).fakeData(cornerRadius = 8.dp),
            )
        }
    }
}
