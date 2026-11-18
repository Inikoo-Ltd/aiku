package io.aiku.droid.ui.fulfilment

import androidx.compose.foundation.rememberScrollState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.AppContainer
import io.aiku.droid.R
import io.aiku.droid.data.api.PalletDeliveryRow
import io.aiku.droid.data.api.PalletDeliveryState
import io.aiku.droid.data.api.PalletDeliveryStateFilter
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SearchScanField
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.fulfilment.inbound.PalletDeliveriesUiState
import io.aiku.droid.ui.fulfilment.inbound.PalletDeliveriesViewModel
import io.aiku.droid.ui.fulfilment.inbound.deliveryFilterLabel
import io.aiku.droid.ui.fulfilment.inbound.palletCountsText
import io.aiku.droid.ui.fulfilment.inbound.palletDeliveryRoute
import io.aiku.droid.ui.fulfilment.inbound.palletDeliveryTone
import io.aiku.droid.ui.fulfilment.inbound.quotedReference
import io.aiku.droid.ui.fulfilment.inbound.rememberPalletDeliveries
import io.aiku.droid.ui.goodsin.goodsInDate
import io.aiku.droid.ui.goodsout.LoadMoreOnScroll
import io.aiku.droid.ui.goodsout.LoadingMore
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.inventory.StockLoadProblem
import io.aiku.droid.ui.inventory.StockScanOutcome
import io.aiku.droid.ui.inventory.scanProblemText
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ScanRoute
import io.aiku.droid.ui.scan.ScanEffect
import io.aiku.droid.ui.states.SessionExpiredScreen

@Composable
fun FulfilmentDeliveriesScreen(container: AppContainer, warehouseId: Long) {
    val deliveries = rememberPalletDeliveries(container, warehouseId)
    val state by deliveries.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { deliveries.onStart() }

    RequireFulfilment(section = R.string.section_fulfilment_deliveries) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = deliveries::signInAgain)
        } else {
            FulfilmentDeliveriesContent(state = state, deliveries = deliveries)
        }
    }
}

@Composable
private fun FulfilmentDeliveriesContent(state: PalletDeliveriesUiState, deliveries: PalletDeliveriesViewModel) {
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val scrollState = rememberScrollState()
    val list = state.list
    val search = state.search.trim()
    val filters = PalletDeliveryStateFilter.entries
    val outcome = state.scanOutcome
    val noMatchText = (outcome as? StockScanOutcome.NoMatch)?.let { stringResource(R.string.fulfilment_inbound_scan_no_delivery, it.code) }
    val failedText = (outcome as? StockScanOutcome.Failed)?.let { scanProblemText(it.code, it.problem) }

    ScanEffect { scan -> deliveries.lookUpScan(scan.code) }
    LoadMoreOnScroll(scrollState = scrollState, itemCount = list.rows.size, canLoadMore = list.canLoadMoreOnScroll, onLoadMore = deliveries::loadMore)
    LaunchedEffect(outcome) {
        when (outcome) {
            is StockScanOutcome.Open -> navigator.open(outcome.route)
            is StockScanOutcome.NoMatch -> noMatchText?.let { toasts.show(it, ToastKind.Info) }
            is StockScanOutcome.Failed -> failedText?.let { toasts.show(it, ToastKind.Error) }
            null -> return@LaunchedEffect
        }
        deliveries.scanOutcomeHandled()
    }

    AppScreen(
        header = { ModuleHeader(section = WarehouseSection.FulfilmentDeliveries) },
        activeTab = MainTab.Menu,
        showBar = true,
        scrollState = scrollState,
    ) {
        FilterChips(
            options = filters.map { FilterOption(label = filterChipLabel(label = deliveryFilterLabel(it), count = state.counts?.count(it.key))) },
            selectedIndex = filters.indexOf(state.state),
            onSelect = { index -> deliveries.selectState(filters[index]) },
        )
        SearchScanField(
            placeholder = stringResource(R.string.fulfilment_inbound_search_deliveries),
            value = state.search,
            onValueChange = deliveries::search,
            onScan = { navigator.open(ScanRoute) },
        )
        state.lookingUpCode?.let { code -> InlineAlert(title = stringResource(R.string.scan_looking_up, code), tone = AlertTone.Info) }
        list.firstPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.fulfilment_inbound_deliveries_failed), onRetry = deliveries::retry)
        }
        when {
            list.rows.isNotEmpty() -> ListGroup {
                list.rows.forEachIndexed { index, row ->
                    if (index > 0) {
                        RowDivider()
                    }
                    PalletDeliveryListRow(row = row, onOpen = { navigator.open(palletDeliveryRoute(row.id, row.reference, row.state)) })
                }
            }
            list.isEmpty && search.isNotEmpty() -> FulfilmentFootnote(text = stringResource(R.string.fulfilment_inbound_deliveries_no_match, search))
            list.isEmpty -> FulfilmentFootnote(text = stringResource(R.string.fulfilment_inbound_deliveries_none))
            list.problem == null -> SkeletonListCard(rows = 4)
        }
        if (list.isLoadingMore) {
            LoadingMore()
        }
        list.nextPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.fulfilment_inbound_deliveries_more_failed), onRetry = deliveries::loadMore)
        }
        FulfilmentFootnote(text = stringResource(R.string.fulfilment_deliveries_footnote))
    }
}

@Composable
private fun PalletDeliveryListRow(row: PalletDeliveryRow, onOpen: () -> Unit) {
    val zone = organisationZone()
    val counts = row.counts
    val progress = when (row.state) {
        PalletDeliveryState.BOOKING_IN -> {
            val toBook = (counts.total - counts.notReceived).coerceAtLeast(0)
            pluralStringResource(R.plurals.fulfilment_book_in_progress, toBook, counts.bookedIn, toBook)
        }
        else -> palletCountsText(pallets = counts.pallets, boxes = counts.boxes, oversizes = counts.oversizes)
    }
    val moment = when (row.state) {
        PalletDeliveryState.CONFIRMED -> goodsInDate(row.estimatedDeliveryDate, zone)?.let { stringResource(R.string.fulfilment_inbound_expected_on, it) }
        PalletDeliveryState.BOOKED_IN -> goodsInDate(row.bookedInAt, zone)?.let { stringResource(R.string.fulfilment_inbound_booked_in_on, it) }
        else -> goodsInDate(row.receivedAt, zone)?.let { stringResource(R.string.fulfilment_inbound_received_on, it) }
    }

    ItemRow(
        code = row.reference,
        subtitle = dotJoined(row.customer?.name, quotedReference(row.customerReference), progress, moment),
        chip = row.stateLabel.takeIf { it.isNotBlank() },
        chipTone = palletDeliveryTone(row.state),
        showPhoto = false,
        onClick = onOpen,
    )
}
