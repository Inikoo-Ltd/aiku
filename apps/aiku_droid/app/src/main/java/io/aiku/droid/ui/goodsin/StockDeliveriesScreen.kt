package io.aiku.droid.ui.goodsin

import androidx.compose.foundation.rememberScrollState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.StockDeliveryRow
import io.aiku.droid.data.api.StockDeliveryState
import io.aiku.droid.data.api.StockDeliveryStateFilter
import io.aiku.droid.ui.components.ActiveFiltersLine
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.RecordRow
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SavedCopyNote
import io.aiku.droid.ui.components.SearchScanField
import io.aiku.droid.ui.components.SearchWithFilter
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.dateRangeText
import io.aiku.droid.ui.components.recordDate
import io.aiku.droid.ui.goodsout.LoadMoreOnScroll
import io.aiku.droid.ui.goodsout.LoadingMore
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.inventory.StockLoadProblem
import io.aiku.droid.ui.inventory.StockScanOutcome
import io.aiku.droid.ui.inventory.countLabel
import io.aiku.droid.ui.inventory.scanProblemText
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ScanRoute
import io.aiku.droid.ui.navigation.StockDeliveryRoute
import io.aiku.droid.ui.scan.LocalScanController
import io.aiku.droid.ui.scan.ScanEffect
import io.aiku.droid.ui.states.SessionExpiredScreen

@Composable
internal fun stateFilterLabel(state: StockDeliveryStateFilter): String = stringResource(
    when (state) {
        StockDeliveryStateFilter.Active -> R.string.goodsin_state_active
        StockDeliveryStateFilter.Dispatched -> R.string.goodsin_state_arriving
        StockDeliveryStateFilter.Received -> R.string.sd_state_received
        StockDeliveryStateFilter.Checked -> R.string.sd_state_checked
        StockDeliveryStateFilter.BookingIn -> R.string.sd_state_booking_in
        StockDeliveryStateFilter.BookedIn -> R.string.sd_state_booked_in
        StockDeliveryStateFilter.Placed -> R.string.goodsin_state_placed
        StockDeliveryStateFilter.NotReceived -> R.string.goodsin_not_received
        StockDeliveryStateFilter.Cancelled -> R.string.goodsin_state_cancelled
    },
)

@Composable
fun StockDeliveriesScreen(deliveries: StockDeliveriesViewModel) {
    val state by deliveries.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { deliveries.onStart() }

    RequireAbility(ability = Ability.ViewGoodsIn, title = stringResource(R.string.module_goods_in), activeTab = MainTab.Menu) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = deliveries::signInAgain)
        } else {
            StockDeliveriesContent(state = state, deliveries = deliveries)
        }
    }
}

@Composable
private fun StockDeliveriesContent(state: StockDeliveriesUiState, deliveries: StockDeliveriesViewModel) {
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val scanFeedback = LocalScanController.current.feedback
    val scrollState = rememberScrollState()
    var showFilter by remember { mutableStateOf(false) }
    val filter = state.filter
    val list = state.list
    val search = filter.search.trim()
    val states = StockDeliveryStateFilter.entries
    val tabs = states.map { FilterOption(label = countLabel(stateFilterLabel(it), state.counts?.count(it.key))) }
    val outcome = state.scanOutcome
    val noMatchText = (outcome as? StockScanOutcome.NoMatch)?.let { stringResource(R.string.goodsin_scan_no_delivery, it.code) }
    val failedText = (outcome as? StockScanOutcome.Failed)?.let { scanProblemText(it.code, it.problem) }
    val summary = listOfNotNull(
        filter.source?.let { sourceLabel(it) },
        filter.dispatched?.let { stringResource(R.string.filter_labelled, stringResource(R.string.goodsin_filter_dispatched), dispatchedLabel(it)) },
        dateRangeText(filter.dates)?.let { stringResource(R.string.filter_labelled, stringResource(R.string.goodsout_filter_date), it) },
        filter.sort?.let { stringResource(R.string.goodsout_sorted_by, stockDeliverySortLabel(it).lowercase()) },
    )

    ScanEffect { scan -> deliveries.lookUpScan(scan.code) }
    LoadMoreOnScroll(scrollState = scrollState, itemCount = list.rows.size, canLoadMore = list.canLoadMoreOnScroll, onLoadMore = deliveries::loadMore)
    LaunchedEffect(outcome) {
        when (outcome) {
            is StockScanOutcome.Open -> navigator.open(outcome.route)
            is StockScanOutcome.NoMatch -> {
                scanFeedback.rejected()
                noMatchText?.let { toasts.show(it, ToastKind.Info) }
            }
            is StockScanOutcome.Failed -> failedText?.let { toasts.show(it, ToastKind.Error) }
            null -> return@LaunchedEffect
        }
        deliveries.scanOutcomeHandled()
    }

    AppScreen(
        header = {
            ModuleHeader(
                section = WarehouseSection.StockDeliveries,
                actions = {
                    HeaderIconButton(
                        icon = R.drawable.ic_ui_search,
                        description = stringResource(R.string.goodsin_search_filter),
                        onClick = { showFilter = true },
                        filled = true,
                        badgeCount = filter.activeFilterCount,
                    )
                },
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        scrollState = scrollState,
        refresh = PullRefresh(isRefreshing = list.isRefreshing, onRefresh = deliveries::refresh),
    ) {
        FilterChips(options = tabs, selectedIndex = filter.state.ordinal, onSelect = { index -> deliveries.selectState(states[index]) })
        SearchWithFilter(filterCount = filter.panelCount, onFilter = { showFilter = true }, buttonSize = 46.dp) { modifier ->
            SearchScanField(
                placeholder = stringResource(R.string.goodsin_search_deliveries),
                value = filter.search,
                onValueChange = deliveries::search,
                onScan = { navigator.open(ScanRoute) },
                modifier = modifier,
            )
        }
        if (filter.isFiltered) {
            ActiveFiltersLine(parts = summary, onClear = deliveries::clearFilters)
        }
        state.lookingUpCode?.let { code -> InlineAlert(title = stringResource(R.string.scan_looking_up, code), tone = AlertTone.Info) }
        list.firstPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.goodsin_deliveries_failed), onRetry = deliveries::retry)
        }
        state.lastUpdated?.let { savedAt -> SavedCopyNote(savedAt = savedAt) }
        when {
            list.rows.isNotEmpty() -> ListGroup {
                list.rows.forEachIndexed { index, row ->
                    if (index > 0) {
                        RowDivider()
                    }
                    DeliveryRow(row = row, onOpen = { navigator.open(StockDeliveryRoute(id = row.id, reference = row.reference)) })
                }
            }
            list.isEmpty && search.isNotEmpty() -> HelperNote(text = stringResource(R.string.goodsin_deliveries_no_match, search))
            list.isEmpty -> HelperNote(text = stringResource(R.string.goodsin_deliveries_none))
            list.problem == null -> SkeletonListCard(rows = 4)
        }
        if (list.isLoadingMore) {
            LoadingMore()
        }
        list.nextPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.goodsin_deliveries_more_failed), onRetry = deliveries::loadMore)
        }
        HelperNote(text = stringResource(R.string.goodsin_deliveries_note))
    }

    if (showFilter) {
        StockDeliveryFilterSheet(
            filter = filter,
            totalFor = state::totalFor,
            onDraftChange = deliveries::previewTotal,
            onApply = { applied ->
                showFilter = false
                deliveries.applyFilter(applied)
            },
            onDismiss = { showFilter = false },
        )
    }
}

@Composable
private fun DeliveryRow(row: StockDeliveryRow, onOpen: () -> Unit) {
    val zone = organisationZone()
    val progress = when (row.state) {
        StockDeliveryState.RECEIVED, StockDeliveryState.CHECKED ->
            pluralStringResource(R.plurals.goodsin_checked_count, row.numberItems, row.numberChecked, row.numberItems)
        StockDeliveryState.BOOKING_IN, StockDeliveryState.BOOKED_IN ->
            pluralStringResource(R.plurals.goodsin_placed_count, row.numberItems, row.numberPlaced, row.numberItems)
        else -> pluralStringResource(R.plurals.goodsin_item_count, row.numberItems, row.numberItems)
    }
    val dispatched = recordDate(row.dispatchedAt, zone)
    val (date, caption) = when {
        row.state !in StockDeliveryState.beforeReceiving -> recordDate(row.receivedAt, zone) to R.string.record_date_received
        dispatched != null -> dispatched to R.string.record_date_dispatched
        else -> recordDate(row.estimatedReceivingDate, zone) to R.string.record_date_expected
    }

    RecordRow(
        reference = row.reference,
        date = date,
        dateCaption = date?.let { stringResource(caption) }?.takeUnless { it.equals(row.stateLabel.trim(), ignoreCase = true) },
        party = row.supplier?.label,
        details = listOfNotNull(row.agentReference?.takeIf { it.isNotBlank() }, progress),
        badges = { row.stateLabel.takeIf { it.isNotBlank() }?.let { StatusBadge(text = it, tone = deliveryStateTone(row.state)) } },
        onClick = onOpen,
    )
}
