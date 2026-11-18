package io.aiku.droid.ui.fulfilment.inbound

import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.FulfilmentInboundActionKey
import io.aiku.droid.data.api.FulfilmentLocation
import io.aiku.droid.data.api.Pallet
import io.aiku.droid.data.api.PalletDelivery
import io.aiku.droid.data.api.PalletDeliveryState
import io.aiku.droid.data.api.PalletDeliveryStateFilter
import io.aiku.droid.data.api.PalletFilter
import io.aiku.droid.data.api.PalletIncidentState
import io.aiku.droid.data.api.PalletState
import io.aiku.droid.data.api.PalletStatus
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonLook
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.SearchScanField
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.Step
import io.aiku.droid.ui.components.StepStatus
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.fulfilment.FulfilmentDeliveryState
import io.aiku.droid.ui.fulfilment.FulfilmentPalletType
import io.aiku.droid.ui.fulfilment.PalletIncidentKind
import io.aiku.droid.ui.fulfilment.StoredPalletFilter
import io.aiku.droid.ui.fulfilment.dotJoined
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.LoadingMore
import io.aiku.droid.ui.goodsout.problemText
import io.aiku.droid.ui.inventory.InlineScanCamera
import io.aiku.droid.ui.navigation.FulfilmentBookInRoute
import io.aiku.droid.ui.navigation.FulfilmentBookedInRoute
import io.aiku.droid.ui.navigation.FulfilmentDeliveryRoute
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.LocalAikuColors

internal val fulfilmentInboundActionLooks: Map<String, ButtonLook> = mapOf(
    FulfilmentInboundActionKey.RECEIVE to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_next),
    FulfilmentInboundActionKey.START_BOOKING to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_pallet),
    FulfilmentInboundActionKey.BOOK_IN to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_location),
    FulfilmentInboundActionKey.FINISH_BOOKING to ButtonLook(ButtonStyle.Finish, R.drawable.ic_ui_check),
    FulfilmentInboundActionKey.UNDO_NOT_RECEIVED to ButtonLook(ButtonStyle.Caution, R.drawable.ic_ui_undo),
    FulfilmentInboundActionKey.NOT_RECEIVED to ButtonLook(ButtonStyle.Danger, R.drawable.ic_ui_x_circle),
    FulfilmentInboundActionKey.DAMAGED to ButtonLook(ButtonStyle.Danger, R.drawable.ic_ui_warning),
    FulfilmentInboundActionKey.LOST to ButtonLook(ButtonStyle.Danger, R.drawable.ic_ui_warning),
    FulfilmentInboundActionKey.MOVE to ButtonLook(ButtonStyle.Outline),
    FulfilmentInboundActionKey.AUDIT to ButtonLook(ButtonStyle.Outline),
)

internal fun fulfilmentInboundActionLook(key: String): ButtonLook = fulfilmentInboundActionLooks[key] ?: ButtonLook(ButtonStyle.Outline)

internal fun palletDeliveryRoute(id: Long, reference: String, state: String): Any = when (state) {
    PalletDeliveryState.BOOKING_IN -> FulfilmentBookInRoute(id = id, reference = reference)
    PalletDeliveryState.BOOKED_IN -> FulfilmentBookedInRoute(id = id, reference = reference)
    else -> FulfilmentDeliveryRoute(id = id, reference = reference)
}

internal fun palletDeliveryTone(state: String): Tone = when (state) {
    PalletDeliveryState.RECEIVED -> Tone.Primary
    PalletDeliveryState.BOOKING_IN -> Tone.Lime
    PalletDeliveryState.BOOKED_IN -> Tone.Success
    PalletDeliveryState.NOT_RECEIVED -> Tone.Danger
    else -> Tone.Neutral
}

internal fun incidentTone(state: String?): Tone = if (state == PalletIncidentState.OtherIncident.key) Tone.Warn else Tone.Danger

internal fun palletTone(pallet: Pallet): Tone = when {
    pallet.status == PalletStatus.INCIDENT -> incidentTone(pallet.incident?.state ?: pallet.state)
    pallet.state == PalletState.NOT_RECEIVED -> Tone.Danger
    pallet.state == PalletState.STORING || pallet.state == PalletState.BOOKED_IN -> Tone.Success
    pallet.state == PalletState.RECEIVED || pallet.state == PalletState.BOOKING_IN -> Tone.Primary
    pallet.status == PalletStatus.RETURNING -> Tone.Primary
    else -> Tone.Neutral
}

internal fun PalletDeliveryStateFilter.deliveryState(): FulfilmentDeliveryState? = when (this) {
    PalletDeliveryStateFilter.Active -> null
    PalletDeliveryStateFilter.Confirmed -> FulfilmentDeliveryState.Confirmed
    PalletDeliveryStateFilter.Received -> FulfilmentDeliveryState.Received
    PalletDeliveryStateFilter.BookingIn -> FulfilmentDeliveryState.BookingIn
    PalletDeliveryStateFilter.BookedIn -> FulfilmentDeliveryState.BookedIn
    PalletDeliveryStateFilter.NotReceived -> FulfilmentDeliveryState.NotReceived
}

internal fun PalletFilter.chip(): StoredPalletFilter = when (this) {
    PalletFilter.Storing -> StoredPalletFilter.Storing
    PalletFilter.Receiving -> StoredPalletFilter.Receiving
    PalletFilter.Returning -> StoredPalletFilter.Returning
    PalletFilter.Incidents -> StoredPalletFilter.Incidents
    PalletFilter.Returned -> StoredPalletFilter.Returned
}

internal fun PalletIncidentState.kind(): PalletIncidentKind = when (this) {
    PalletIncidentState.Damaged -> PalletIncidentKind.Damaged
    PalletIncidentState.Lost -> PalletIncidentKind.Lost
    PalletIncidentState.OtherIncident -> PalletIncidentKind.Other
}

@Composable
internal fun deliveryFilterLabel(filter: PalletDeliveryStateFilter): String =
    stringResource(filter.deliveryState()?.label ?: R.string.fulfilment_state_active)

@Composable
internal fun palletTypeText(pallet: Pallet): String? = pallet.typeLabel.takeIf { it.isNotBlank() }
    ?: FulfilmentPalletType.entries.firstOrNull { it.name.equals(pallet.type, ignoreCase = true) }?.let { stringResource(it.label) }
    ?: pallet.type.takeIf { it.isNotBlank() }

@Composable
internal fun quotedReference(reference: String?): String? =
    reference?.takeIf { it.isNotBlank() }?.let { stringResource(R.string.fulfilment_inbound_quoted, it) }

@Composable
internal fun palletLocationText(pallet: Pallet): String = pallet.locationCode ?: stringResource(R.string.fulfilment_no_location_yet)

@Composable
internal fun storedItemsText(count: Int): String? =
    count.takeIf { it > 0 }?.let { pluralStringResource(R.plurals.fulfilment_inbound_stored_items, it, it) }

@Composable
internal fun palletSubtitle(pallet: Pallet, showCustomer: Boolean): String = dotJoined(
    pallet.customer?.name?.takeIf { showCustomer },
    palletTypeText(pallet),
    quotedReference(pallet.customerReference),
    palletLocationText(pallet),
    storedItemsText(pallet.numberStoredItems.takeIf { it > 0 } ?: pallet.storedItems.size),
)

internal fun palletChip(pallet: Pallet): String? =
    pallet.incident?.stateLabel?.takeIf { it.isNotBlank() } ?: pallet.stateLabel.takeIf { it.isNotBlank() } ?: pallet.statusLabel.takeIf { it.isNotBlank() }

@Composable
internal fun deliveryKicker(delivery: PalletDelivery): String =
    dotJoined(stringResource(R.string.fulfilment_delivery_kicker), delivery.customer?.name ?: delivery.stateLabel)

internal fun palletDeliverySteps(delivery: PalletDelivery): List<Step> {
    val current = delivery.timeline.indexOfFirst { it.state == delivery.state }
    val isComplete = delivery.state == PalletDeliveryState.BOOKED_IN
    return delivery.timeline.mapIndexed { index, entry ->
        Step(
            label = entry.label.ifBlank { entry.state },
            status = when {
                current < 0 -> if (entry.at != null) StepStatus.Done else StepStatus.Upcoming
                index < current || (index == current && isComplete) -> StepStatus.Done
                index == current -> StepStatus.Current
                else -> StepStatus.Upcoming
            },
        )
    }
}

@Composable
internal fun PalletDeliveryStatus(delivery: PalletDelivery) {
    when {
        delivery.state == PalletDeliveryState.NOT_RECEIVED -> InlineAlert(
            title = delivery.stateLabel.ifBlank { stringResource(R.string.fulfilment_state_not_received) },
            body = stringResource(R.string.fulfilment_inbound_delivery_not_received_body),
            tone = AlertTone.Danger,
        )
        delivery.timeline.isNotEmpty() -> StateStepper(steps = palletDeliverySteps(delivery))
    }
}

@Composable
internal fun InboundLoading(title: String, kicker: String, problem: GoodsOutProblem?, failedTitle: String, onRetry: () -> Unit) {
    AppScreen(
        header = { AppHeader(title = title, kicker = kicker) },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        if (problem == null) {
            SkeletonListCard(rows = 2)
            SkeletonListCard(rows = 4)
        } else {
            GoodsOutLoadProblem(problem = problem, title = failedTitle, onRetry = onRetry)
        }
    }
}

@Composable
internal fun PalletLocationPicker(
    state: FulfilmentLocationFinderState,
    onQueryChange: (String) -> Unit,
    onPick: (FulfilmentLocation) -> Unit,
    onScanned: (String) -> Unit,
) {
    val colors = LocalAikuColors.current
    var isCameraOn by remember { mutableStateOf(false) }
    val picked = state.picked

    SearchScanField(
        placeholder = stringResource(R.string.fulfilment_inbound_location_search),
        value = state.query,
        onValueChange = onQueryChange,
        onScan = { isCameraOn = !isCameraOn },
    )
    if (isCameraOn) {
        InlineScanCamera(onScanned = { code ->
            isCameraOn = false
            onScanned(code)
        })
    }
    state.problem?.let { Text(text = problemText(it), fontSize = 13.sp, color = colors.danger) }
    if (picked != null && state.results.none { it.id == picked.id }) {
        LocationChoice(location = picked, selected = true, onClick = {})
    }
    state.results.forEach { location ->
        LocationChoice(location = location, selected = location.id == picked?.id, onClick = { onPick(location) })
    }
    if (state.isSearching) {
        LoadingMore()
    } else if (state.query.isNotBlank() && state.results.isEmpty() && state.problem == null && picked == null) {
        Text(text = stringResource(R.string.fulfilment_inbound_no_location, state.query.trim()), fontSize = 13.sp, color = colors.textTertiary)
    }
}

@Composable
private fun LocationChoice(location: FulfilmentLocation, selected: Boolean, onClick: () -> Unit) {
    ChoiceRow(
        title = location.code,
        subtitle = pluralStringResource(R.plurals.fulfilment_pallet_count, location.numberPallets, location.numberPallets),
        selected = selected,
        onClick = onClick,
    )
}

@Composable
internal fun palletCountsText(pallets: Int, boxes: Int, oversizes: Int): String? = listOfNotNull(
    pallets.takeIf { it > 0 }?.let { pluralStringResource(R.plurals.fulfilment_pallet_count, it, it) },
    boxes.takeIf { it > 0 }?.let { pluralStringResource(R.plurals.fulfilment_inbound_box_count, it, it) },
    oversizes.takeIf { it > 0 }?.let { pluralStringResource(R.plurals.fulfilment_inbound_oversize_count, it, it) },
).joinToString(", ").takeIf { it.isNotBlank() }
