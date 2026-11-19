package io.aiku.droid.ui.fulfilment

import androidx.compose.foundation.ScrollState
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.rememberScrollState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
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
import io.aiku.droid.data.api.FulfilmentInboundActionKey
import io.aiku.droid.data.api.FulfilmentInboundErrorKey
import io.aiku.droid.data.api.Pallet
import io.aiku.droid.data.api.PalletDelivery
import io.aiku.droid.data.api.PalletDeliveryState
import io.aiku.droid.data.api.PalletDeliveryWrite
import io.aiku.droid.data.api.PalletState
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ConfirmDialog
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.forConfirm
import io.aiku.droid.ui.fulfilment.inbound.BookInPanel
import io.aiku.droid.ui.fulfilment.inbound.DeliveryPalletList
import io.aiku.droid.ui.fulfilment.inbound.InboundLoading
import io.aiku.droid.ui.fulfilment.inbound.PalletDeliveryStatus
import io.aiku.droid.ui.fulfilment.inbound.PalletDeliveryUiState
import io.aiku.droid.ui.fulfilment.inbound.PalletDeliveryViewModel
import io.aiku.droid.ui.fulfilment.inbound.fulfilmentInboundActionLook
import io.aiku.droid.ui.fulfilment.inbound.palletDeliveryDoneText
import io.aiku.droid.ui.fulfilment.inbound.palletTypeText
import io.aiku.droid.ui.fulfilment.inbound.quotedReference
import io.aiku.droid.ui.fulfilment.inbound.rememberPalletDelivery
import io.aiku.droid.ui.goodsin.GoodsInEvents
import io.aiku.droid.ui.goodsin.ScanPromptCard
import io.aiku.droid.ui.goodsin.SectionHeading
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.navigation.FulfilmentBookedInRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.scan.LocalScanController
import io.aiku.droid.ui.scan.ScanEffect
import io.aiku.droid.ui.states.SessionExpiredScreen

private sealed interface BookInConfirm {
    data object Finish : BookInConfirm
    data class NotReceived(val palletId: Long, val reference: String) : BookInConfirm
}

private fun Pallet.isWorkable(): Boolean = can(FulfilmentInboundActionKey.BOOK_IN) || can(FulfilmentInboundActionKey.NOT_RECEIVED)

@Composable
fun FulfilmentBookInScreen(container: AppContainer, warehouseId: Long, id: Long, reference: String) {
    val delivery = rememberPalletDelivery(container, warehouseId, id)
    val state by delivery.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { delivery.onStart() }

    RequireFulfilment(section = R.string.section_fulfilment_deliveries) {
        val loaded = state.delivery
        when {
            state.isSessionExpired -> SessionExpiredScreen(onSignIn = delivery::signInAgain)
            loaded != null -> BookInContent(viewModel = delivery, state = state, delivery = loaded)
            else -> InboundLoading(
                title = reference,
                kicker = dotJoined(stringResource(R.string.fulfilment_delivery_kicker), stringResource(R.string.fulfilment_state_booking_in)),
                problem = state.problem,
                failedTitle = stringResource(R.string.fulfilment_inbound_delivery_failed, reference),
                onRetry = delivery::retry,
                refresh = PullRefresh(isRefreshing = state.isRefreshing, onRefresh = delivery::refresh),
            )
        }
    }
}

@Composable
private fun BookInContent(viewModel: PalletDeliveryViewModel, state: PalletDeliveryUiState, delivery: PalletDelivery) {
    val navigator = LocalAppNavigator.current
    val resources = LocalResources.current
    val toasts = LocalToasts.current
    val scrollState = rememberScrollState()
    val finderState by viewModel.finder.state.collectAsStateWithLifecycle()
    var confirm by remember { mutableStateOf<BookInConfirm?>(null) }
    val openPallet = state.openPallet
    val toBook = delivery.pallets.count { it.state != PalletState.NOT_RECEIVED }
    val bookedIn = delivery.pallets.count { it.state == PalletState.BOOKED_IN }
    val progress = pluralStringResource(R.plurals.fulfilment_book_in_progress, toBook, bookedIn, toBook)
    val next = delivery.pallets.firstOrNull { it.can(FulfilmentInboundActionKey.BOOK_IN) && it.locationCode == null }
    val canScan = delivery.state == PalletDeliveryState.BOOKING_IN && delivery.pallets.any { it.isWorkable() }
    val fieldError = listOf(FulfilmentInboundErrorKey.LOCATION_ID, FulfilmentInboundErrorKey.LOCATION_CODE)
        .firstNotNullOfOrNull { state.writeErrors[it]?.firstOrNull() }
    val scanMiss = state.scanMiss
    val scanFeedback = LocalScanController.current.feedback
    val missText = scanMiss?.let { stringResource(R.string.fulfilment_inbound_scan_not_on_delivery, it.code, delivery.reference) }

    GoodsInEvents(
        event = state.event,
        onHandled = viewModel::eventHandled,
        doneText = { done -> palletDeliveryDoneText(done, state.delivery ?: delivery) { id, args -> resources.getString(id, *args) } },
        onDone = { done ->
            if (done.action == PalletDeliveryWrite.FinishBooking) {
                navigator.switchSection(FulfilmentBookedInRoute(id = delivery.id, reference = delivery.reference))
            }
        },
    )
    ScanEffect(enabled = canScan && confirm == null) { scan -> viewModel.scanForBookIn(scan.code) }
    LaunchedEffect(scanMiss) {
        if (scanMiss != null) {
            scanFeedback.rejected()
            missText?.let { toasts.show(it, ToastKind.Info) }
            viewModel.scanMissHandled()
        }
    }
    ScrollToTopOnSelect(palletId = state.openPalletId, scrollState = scrollState)

    AppScreen(
        header = {
            AppHeader(
                title = delivery.reference,
                kicker = dotJoined(stringResource(R.string.fulfilment_delivery_kicker), delivery.customer?.name ?: delivery.stateLabel),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        scrollState = scrollState,
        footer = bookInFooter(delivery = delivery, progress = progress, working = state.working, onFinish = { confirm = BookInConfirm.Finish }),
        refresh = PullRefresh(isRefreshing = state.isRefreshing, onRefresh = viewModel::refresh, isEnabled = !state.isWorking),
    ) {
        state.problem?.let { GoodsOutLoadProblem(problem = it, title = stringResource(R.string.fulfilment_inbound_delivery_refresh_failed), onRetry = viewModel::retry) }
        PalletDeliveryStatus(delivery = delivery)
        when {
            openPallet != null -> BookInPanel(
                pallet = openPallet,
                finderState = finderState,
                fieldError = fieldError,
                working = state.working,
                onQueryChange = viewModel.finder::search,
                onPick = viewModel.finder::pick,
                onScanned = viewModel::scanForBookIn,
                onBookIn = { target -> viewModel.bookIn(openPallet.id, target) },
                onNotReceived = { confirm = BookInConfirm.NotReceived(openPallet.id, openPallet.reference) },
                onClose = viewModel::closePallet,
            )
            next != null -> ScanPromptCard(
                title = stringResource(R.string.fulfilment_book_in_scan_title),
                body = stringResource(R.string.fulfilment_inbound_next_pallet, dotJoined(next.reference, palletTypeText(next), quotedReference(next.customerReference))),
                onClick = { viewModel.openPallet(next.id) },
            )
        }
        SectionHeading(title = stringResource(R.string.fulfilment_pallets), caption = progress)
        DeliveryPalletList(pallets = delivery.pallets, onOpen = { viewModel.openPallet(it.id) }, isOpenable = { it.isWorkable() })
        FulfilmentFootnote(text = stringResource(R.string.fulfilment_inbound_book_in_footnote))
    }

    when (val current = confirm) {
        BookInConfirm.Finish -> ConfirmDialog(
            title = stringResource(R.string.fulfilment_inbound_finish_title, delivery.reference),
            body = stringResource(R.string.fulfilment_inbound_finish_body),
            confirmLabel = delivery.action(FulfilmentInboundActionKey.FINISH_BOOKING)?.label?.takeIf { it.isNotBlank() }
                ?: stringResource(R.string.fulfilment_book_in_set_booked_in),
            confirmLook = fulfilmentInboundActionLook(FulfilmentInboundActionKey.FINISH_BOOKING).forConfirm(),
            onConfirm = {
                confirm = null
                viewModel.finishBooking()
            },
            onDismiss = { confirm = null },
        )
        is BookInConfirm.NotReceived -> ConfirmDialog(
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
        null -> Unit
    }
}

@Composable
private fun ScrollToTopOnSelect(palletId: Long?, scrollState: ScrollState) {
    LaunchedEffect(palletId) {
        if (palletId != null) {
            scrollState.animateScrollTo(0)
        }
    }
}

private fun bookInFooter(
    delivery: PalletDelivery,
    progress: String,
    working: PalletDeliveryWrite?,
    onFinish: () -> Unit,
): (@Composable ColumnScope.() -> Unit)? {
    val finish = delivery.action(FulfilmentInboundActionKey.FINISH_BOOKING)
    val blocked = delivery.finishBookingBlocked?.takeIf { it.isNotBlank() }
    return when {
        finish != null -> ({
            AikuButton(
                text = finish.label.ifBlank { stringResource(R.string.fulfilment_book_in_set_booked_in) },
                onClick = onFinish,
                style = fulfilmentInboundActionLook(finish.key).style,
                icon = fulfilmentInboundActionLook(finish.key).icon,
                isLoading = working == PalletDeliveryWrite.FinishBooking,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        })
        blocked != null -> ({ FooterStatus(title = progress, body = blocked) })
        delivery.state == PalletDeliveryState.BOOKING_IN -> ({
            FooterStatus(title = progress, body = stringResource(R.string.fulfilment_inbound_finish_needs_shop_edit))
        })
        else -> null
    }
}
