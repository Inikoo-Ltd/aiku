package io.aiku.droid.ui.fulfilment

import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalResources
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.AppContainer
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryCharge
import io.aiku.droid.data.api.FulfilmentInboundActionKey
import io.aiku.droid.data.api.PalletDelivery
import io.aiku.droid.data.api.PalletDeliveryState
import io.aiku.droid.data.api.PalletDeliveryWrite
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ConfirmDialog
import io.aiku.droid.ui.components.DetailCard
import io.aiku.droid.ui.components.DetailLine
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.NoteCard
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.forConfirm
import io.aiku.droid.ui.fulfilment.inbound.DeliveryPalletList
import io.aiku.droid.ui.fulfilment.inbound.DeliveryPalletSheet
import io.aiku.droid.ui.fulfilment.inbound.InboundLoading
import io.aiku.droid.ui.fulfilment.inbound.PalletDeliveryStatus
import io.aiku.droid.ui.fulfilment.inbound.PalletDeliveryUiState
import io.aiku.droid.ui.fulfilment.inbound.PalletDeliveryViewModel
import io.aiku.droid.ui.fulfilment.inbound.deliveryKicker
import io.aiku.droid.ui.fulfilment.inbound.fulfilmentInboundActionLook
import io.aiku.droid.ui.fulfilment.inbound.palletDeliveryDoneText
import io.aiku.droid.ui.fulfilment.inbound.quotedReference
import io.aiku.droid.ui.fulfilment.inbound.rememberPalletDelivery
import io.aiku.droid.ui.goodsin.DeliveryField
import io.aiku.droid.ui.goodsin.FieldGridCard
import io.aiku.droid.ui.goodsin.GoodsInEvents
import io.aiku.droid.ui.goodsin.SectionHeading
import io.aiku.droid.ui.goodsin.goodsInDate
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.goodsout.quantityText
import io.aiku.droid.ui.inventory.countText
import io.aiku.droid.ui.navigation.FulfilmentBookInRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.states.SessionExpiredScreen

private sealed interface DeliveryConfirm {
    data object Receive : DeliveryConfirm
    data class NotReceived(val palletId: Long, val reference: String) : DeliveryConfirm
    data class UndoNotReceived(val palletId: Long, val reference: String) : DeliveryConfirm
}

@Composable
fun FulfilmentDeliveryScreen(container: AppContainer, warehouseId: Long, id: Long, reference: String) {
    val delivery = rememberPalletDelivery(container, warehouseId, id)
    val state by delivery.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { delivery.onStart() }

    RequireFulfilment(section = R.string.section_fulfilment_deliveries) {
        val loaded = state.delivery
        when {
            state.isSessionExpired -> SessionExpiredScreen(onSignIn = delivery::signInAgain)
            loaded != null -> PalletDeliveryContent(viewModel = delivery, state = state, delivery = loaded)
            else -> InboundLoading(
                title = reference,
                kicker = stringResource(R.string.fulfilment_delivery_kicker),
                problem = state.problem,
                failedTitle = stringResource(R.string.fulfilment_inbound_delivery_failed, reference),
                onRetry = delivery::retry,
                refresh = PullRefresh(isRefreshing = state.isRefreshing, onRefresh = delivery::refresh),
            )
        }
    }
}

@Composable
private fun PalletDeliveryContent(viewModel: PalletDeliveryViewModel, state: PalletDeliveryUiState, delivery: PalletDelivery) {
    val navigator = LocalAppNavigator.current
    val resources = LocalResources.current
    var confirm by remember { mutableStateOf<DeliveryConfirm?>(null) }
    val openPallet = state.openPallet

    GoodsInEvents(
        event = state.event,
        onHandled = viewModel::eventHandled,
        doneText = { done -> palletDeliveryDoneText(done, state.delivery ?: delivery) { id, args -> resources.getString(id, *args) } },
        onDone = { done ->
            if (done.action == PalletDeliveryWrite.StartBooking) {
                navigator.switchSection(FulfilmentBookInRoute(id = delivery.id, reference = delivery.reference))
            }
        },
    )

    AppScreen(
        header = { AppHeader(title = delivery.reference, kicker = deliveryKicker(delivery)) },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = deliveryFooter(
            delivery = delivery,
            working = state.working,
            onReceive = { confirm = DeliveryConfirm.Receive },
            onStartBooking = viewModel::startBooking,
            onBookIn = { navigator.switchSection(FulfilmentBookInRoute(id = delivery.id, reference = delivery.reference)) },
        ),
        refresh = PullRefresh(isRefreshing = state.isRefreshing, onRefresh = viewModel::refresh, isEnabled = !state.isWorking),
    ) {
        state.problem?.let { GoodsOutLoadProblem(problem = it, title = stringResource(R.string.fulfilment_inbound_delivery_refresh_failed), onRetry = viewModel::retry) }
        PalletDeliveryStatus(delivery = delivery)
        DeliveryDetailCard(delivery = delivery)
        DeliveryNotes(delivery = delivery)
        DeliveryCountsCard(delivery = delivery)
        SectionHeading(
            title = stringResource(R.string.fulfilment_pallets),
            caption = if (delivery.state == PalletDeliveryState.RECEIVED) {
                pluralStringResource(R.plurals.fulfilment_delivery_received_count, delivery.counts.received, delivery.counts.received)
            } else {
                countText(delivery.pallets.size)
            },
        )
        DeliveryPalletList(pallets = delivery.pallets, onOpen = { viewModel.openPallet(it.id) })
        ChargeSection(title = stringResource(R.string.fulfilment_inbound_services), charges = delivery.services)
        ChargeSection(title = stringResource(R.string.fulfilment_inbound_physical_goods), charges = delivery.physicalGoods)
        FulfilmentFootnote(text = stringResource(R.string.fulfilment_inbound_delivery_footnote))
    }

    if (openPallet != null) {
        DeliveryPalletSheet(
            pallet = openPallet,
            working = state.working,
            onNotReceived = {
                viewModel.closePallet()
                confirm = DeliveryConfirm.NotReceived(openPallet.id, openPallet.reference)
            },
            onUndoNotReceived = {
                viewModel.closePallet()
                confirm = DeliveryConfirm.UndoNotReceived(openPallet.id, openPallet.reference)
            },
            onDismiss = viewModel::closePallet,
        )
    }

    when (val current = confirm) {
        DeliveryConfirm.Receive -> ConfirmDialog(
            title = stringResource(R.string.fulfilment_inbound_receive_title, delivery.reference),
            body = stringResource(R.string.fulfilment_inbound_receive_body),
            confirmLabel = delivery.action(FulfilmentInboundActionKey.RECEIVE)?.label?.takeIf { it.isNotBlank() }
                ?: stringResource(R.string.fulfilment_inbound_mark_received),
            confirmLook = fulfilmentInboundActionLook(FulfilmentInboundActionKey.RECEIVE).forConfirm(),
            onConfirm = {
                confirm = null
                viewModel.receive()
            },
            onDismiss = { confirm = null },
        )
        is DeliveryConfirm.NotReceived -> ConfirmDialog(
            title = stringResource(R.string.fulfilment_inbound_not_received_title, current.reference),
            body = stringResource(R.string.fulfilment_inbound_not_received_body),
            confirmLabel = stringResource(R.string.fulfilment_not_received),
            onConfirm = {
                confirm = null
                viewModel.notReceived(current.palletId)
            },
            onDismiss = { confirm = null },
            confirmLook = fulfilmentInboundActionLook(FulfilmentInboundActionKey.NOT_RECEIVED).forConfirm(),
        )
        is DeliveryConfirm.UndoNotReceived -> ConfirmDialog(
            title = stringResource(R.string.fulfilment_inbound_undo_not_received_title, current.reference),
            body = stringResource(R.string.fulfilment_inbound_undo_not_received_body),
            confirmLabel = stringResource(R.string.fulfilment_inbound_undo_not_received),
            onConfirm = {
                confirm = null
                viewModel.undoNotReceived(current.palletId)
            },
            onDismiss = { confirm = null },
            confirmLook = fulfilmentInboundActionLook(FulfilmentInboundActionKey.UNDO_NOT_RECEIVED).forConfirm(),
        )
        null -> Unit
    }
}

private fun deliveryFooter(
    delivery: PalletDelivery,
    working: PalletDeliveryWrite?,
    onReceive: () -> Unit,
    onStartBooking: () -> Unit,
    onBookIn: () -> Unit,
): (@Composable ColumnScope.() -> Unit)? {
    val receive = delivery.action(FulfilmentInboundActionKey.RECEIVE)
    val startBooking = delivery.action(FulfilmentInboundActionKey.START_BOOKING)
    return when {
        receive != null -> ({
            AikuButton(
                text = receive.label.ifBlank { stringResource(R.string.fulfilment_inbound_mark_received) },
                onClick = onReceive,
                style = fulfilmentInboundActionLook(receive.key).style,
                icon = fulfilmentInboundActionLook(receive.key).icon,
                isLoading = working == PalletDeliveryWrite.Receive,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        })
        startBooking != null -> ({
            AikuButton(
                text = startBooking.label.ifBlank { stringResource(R.string.fulfilment_delivery_start_booking_in) },
                onClick = onStartBooking,
                style = fulfilmentInboundActionLook(startBooking.key).style,
                icon = fulfilmentInboundActionLook(startBooking.key).icon,
                isLoading = working == PalletDeliveryWrite.StartBooking,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        })
        delivery.state == PalletDeliveryState.BOOKING_IN -> ({
            AikuButton(
                text = stringResource(R.string.fulfilment_inbound_book_in_pallets),
                onClick = onBookIn,
                icon = R.drawable.ic_ui_pallet,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        })
        delivery.state == PalletDeliveryState.CONFIRMED -> ({
            FooterStatus(
                title = stringResource(R.string.fulfilment_inbound_waiting_to_be_received),
                body = stringResource(R.string.fulfilment_inbound_receive_needs_shop_edit),
            )
        })
        delivery.state == PalletDeliveryState.RECEIVED -> ({
            FooterStatus(
                title = stringResource(R.string.fulfilment_delivery_waiting_to_book_in),
                body = stringResource(R.string.fulfilment_inbound_start_booking_needs_shop_edit),
            )
        })
        else -> null
    }
}

@Composable
private fun DeliveryDetailCard(delivery: PalletDelivery) {
    val zone = organisationZone()
    val lines = listOfNotNull(
        delivery.customer?.name?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_person, text = it) },
        quotedReference(delivery.customerReference)?.let { DetailLine(icon = R.drawable.ic_ui_document, text = stringResource(R.string.fulfilment_inbound_customer_reference, it)) },
        goodsInDate(delivery.estimatedDeliveryDate, zone)?.let { DetailLine(icon = R.drawable.ic_ui_clock, text = stringResource(R.string.fulfilment_inbound_expected_on, it)) },
        goodsInDate(delivery.at(PalletDeliveryState.RECEIVED), zone)?.let { DetailLine(icon = R.drawable.ic_ui_check, text = stringResource(R.string.fulfilment_inbound_received_on, it)) },
        goodsInDate(delivery.at(PalletDeliveryState.BOOKED_IN), zone)?.let { DetailLine(icon = R.drawable.ic_ui_pallet, text = stringResource(R.string.fulfilment_inbound_booked_in_on, it)) },
    )
    if (lines.isNotEmpty()) {
        DetailCard(title = stringResource(R.string.fulfilment_inbound_details), lines = lines)
    }
}

@Composable
private fun DeliveryNotes(delivery: PalletDelivery) {
    delivery.customerNotes?.takeIf { it.isNotBlank() }?.let {
        NoteCard(title = stringResource(R.string.fulfilment_inbound_customer_notes), text = it, icon = R.drawable.ic_ui_lock)
    }
    delivery.publicNotes?.takeIf { it.isNotBlank() }?.let {
        NoteCard(title = stringResource(R.string.fulfilment_inbound_public_notes), text = it, icon = R.drawable.ic_ui_lock)
    }
    delivery.internalNotes?.takeIf { it.isNotBlank() }?.let {
        NoteCard(title = stringResource(R.string.fulfilment_inbound_internal_notes), text = it, icon = R.drawable.ic_ui_lock, isWarning = true)
    }
}

@Composable
private fun DeliveryCountsCard(delivery: PalletDelivery) {
    val counts = delivery.counts
    val isArrived = delivery.state != PalletDeliveryState.CONFIRMED
    val fields = listOfNotNull(
        DeliveryField(R.string.fulfilment_pallets, countText(counts.pallets)),
        DeliveryField(R.string.fulfilment_inbound_boxes, countText(counts.boxes)),
        DeliveryField(R.string.fulfilment_inbound_oversizes, countText(counts.oversizes)),
        DeliveryField(R.string.fulfilment_inbound_total, countText(counts.total)),
        DeliveryField(R.string.fulfilment_state_received, countText(counts.received)).takeIf { isArrived },
        DeliveryField(R.string.fulfilment_state_booking_in, countText(counts.bookingIn)).takeIf { isArrived },
        DeliveryField(R.string.fulfilment_state_booked_in, countText(counts.bookedIn)).takeIf { isArrived },
        DeliveryField(R.string.fulfilment_state_not_received, countText(counts.notReceived)).takeIf { isArrived },
    )
    FieldGridCard(title = stringResource(R.string.fulfilment_inbound_counts), fields = fields)
}

@Composable
private fun ChargeSection(title: String, charges: List<DeliveryCharge>) {
    if (charges.isEmpty()) {
        return
    }
    SectionHeading(title = title, caption = countText(charges.size))
    ListGroup {
        charges.forEachIndexed { index, charge ->
            if (index > 0) {
                RowDivider()
            }
            ItemRow(
                code = charge.name.ifBlank { charge.code },
                subtitle = dotJoined(charge.code.takeIf { charge.name.isNotBlank() }, charge.unitLabel),
                done = quantityText(charge.quantity),
                showPhoto = false,
            )
        }
    }
}
