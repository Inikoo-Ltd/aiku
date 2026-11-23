package io.aiku.droid.ui.fulfilment.outbound

import androidx.compose.foundation.rememberScrollState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.PalletReturnRow
import io.aiku.droid.data.api.PalletReturnSort
import io.aiku.droid.data.api.PalletReturnStateFilter
import io.aiku.droid.data.api.PalletReturnTypeFilter
import io.aiku.droid.data.api.PalletReturnTypeGroup
import io.aiku.droid.ui.components.ActiveFiltersLine
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.ListFilterPanel
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.RecordRow
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SavedCopyNote
import io.aiku.droid.ui.components.SearchField
import io.aiku.droid.ui.components.SearchWithFilter
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.SortOption
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.listFilterSummary
import io.aiku.droid.ui.components.recordDate
import io.aiku.droid.ui.fulfilment.filterChipLabel
import io.aiku.droid.ui.goodsin.HelperNote
import io.aiku.droid.ui.goodsout.LoadMoreOnScroll
import io.aiku.droid.ui.goodsout.LoadingMore
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.inventory.StockLoadProblem
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.states.SessionExpiredScreen

private val typeFilters: List<PalletReturnTypeFilter?> = listOf<PalletReturnTypeFilter?>(null) + PalletReturnTypeFilter.entries

@Composable
private fun stateFilterLabel(filter: PalletReturnStateFilter): String = stringResource(
    when (filter) {
        PalletReturnStateFilter.Active -> R.string.fulfilment_out_state_active
        PalletReturnStateFilter.Confirmed -> R.string.fulfilment_state_confirmed
        PalletReturnStateFilter.Picking -> R.string.fulfilment_state_picking
        PalletReturnStateFilter.Picked -> R.string.fulfilment_state_picked
        PalletReturnStateFilter.Dispatched -> R.string.fulfilment_state_dispatched
        PalletReturnStateFilter.Cancelled -> R.string.fulfilment_out_state_cancelled
    },
)

@Composable
private fun typeFilterLabel(filter: PalletReturnTypeFilter?): String = stringResource(
    when (filter) {
        null -> R.string.fulfilment_filter_all
        PalletReturnTypeFilter.Pallets -> R.string.fulfilment_return_type_pallets
        PalletReturnTypeFilter.Items -> R.string.fulfilment_return_type_stored_items
    },
)

@Composable
private fun palletReturnSorts(): List<SortOption<PalletReturnSort>> = listOf(
    SortOption(null, stringResource(R.string.goodsin_sort_newest)),
    SortOption(PalletReturnSort.Oldest, stringResource(R.string.filter_sort_oldest)),
    SortOption(PalletReturnSort.Reference, stringResource(R.string.goodsin_sort_reference)),
    SortOption(PalletReturnSort.Customer, stringResource(R.string.goodsout_filter_sort_customer)),
    SortOption(PalletReturnSort.Dispatched, stringResource(R.string.record_date_dispatched)),
)

@Composable
internal fun PalletReturnsContent(returns: PalletReturnsViewModel) {
    val state by returns.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { returns.onStart() }

    if (state.isSessionExpired) {
        SessionExpiredScreen(onSignIn = returns::signInAgain)
        return
    }

    val navigator = LocalAppNavigator.current
    val scrollState = rememberScrollState()
    var showFilter by remember { mutableStateOf(false) }
    val sorts = palletReturnSorts()
    val list = state.list
    val search = state.search.trim()
    val states = PalletReturnStateFilter.entries
    val typeCounts = state.counts?.types
    val allTypes = typeCounts?.let { counts -> PalletReturnTypeFilter.entries.mapNotNull { counts.count(it.key) }.takeIf { it.isNotEmpty() }?.sum() }

    LoadMoreOnScroll(scrollState = scrollState, itemCount = list.rows.size, canLoadMore = list.canLoadMoreOnScroll, onLoadMore = returns::loadMore)

    AppScreen(
        header = { ModuleHeader(section = WarehouseSection.FulfilmentReturns) },
        activeTab = MainTab.Menu,
        showBar = true,
        scrollState = scrollState,
        refresh = PullRefresh(isRefreshing = list.isRefreshing, onRefresh = returns::refresh),
    ) {
        FilterChips(
            options = states.map { FilterOption(label = filterChipLabel(stateFilterLabel(it), state.counts?.states?.count(it.key))) },
            selectedIndex = states.indexOf(state.state),
            onSelect = { index -> returns.selectState(states[index]) },
        )
        FilterChips(
            options = typeFilters.map { FilterOption(label = filterChipLabel(typeFilterLabel(it), if (it == null) allTypes else typeCounts?.count(it.key))) },
            selectedIndex = typeFilters.indexOf(state.type),
            onSelect = { index -> returns.selectType(typeFilters[index]) },
        )
        SearchWithFilter(filterCount = state.filter.activeCount, onFilter = { showFilter = true }) { modifier ->
            SearchField(value = state.search, onValueChange = returns::search, placeholder = stringResource(R.string.fulfilment_out_search_returns), modifier = modifier)
        }
        if (state.filter.isFiltered) {
            ActiveFiltersLine(
                parts = listFilterSummary(state.filter, sorts, onlineOnlySorts = if (state.offline != null) onlineOnlyPalletReturnSorts else emptySet()),
                onClear = returns::clearFilters,
            )
        }
        list.firstPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.fulfilment_out_returns_failed), onRetry = returns::retry)
        }
        state.lastUpdated?.let { savedAt -> SavedCopyNote(savedAt = savedAt) }
        when {
            list.rows.isNotEmpty() -> ListGroup {
                list.rows.forEachIndexed { index, row ->
                    if (index > 0) {
                        RowDivider()
                    }
                    PalletReturnRowItem(row = row, onOpen = { navigator.open(row.route()) })
                }
            }
            list.isEmpty && search.isNotEmpty() -> HelperNote(text = stringResource(R.string.fulfilment_out_returns_no_match, search))
            list.isEmpty -> HelperNote(text = stringResource(R.string.fulfilment_out_returns_none))
            list.problem == null -> SkeletonListCard(rows = 4)
        }
        if (list.isLoadingMore) {
            LoadingMore()
        }
        list.nextPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.fulfilment_out_returns_more_failed), onRetry = returns::loadMore)
        }
        HelperNote(text = stringResource(R.string.fulfilment_out_returns_note))
    }
    if (showFilter) {
        ListFilterPanel(
            title = stringResource(R.string.goodsin_returns_filter_title),
            filter = state.filter,
            sorts = sorts,
            total = list.total?.takeIf { list.hasLoaded && list.loadingPage == null },
            onApply = { applied ->
                showFilter = false
                returns.applyFilter(applied)
            },
            onDismiss = { showFilter = false },
            totalFor = state::offlineTotalFor,
        )
    }
}

@Composable
private fun PalletReturnRowItem(row: PalletReturnRow, onOpen: () -> Unit) {
    val zone = organisationZone()
    val size = if (row.typeGroup == PalletReturnTypeGroup.ITEMS) {
        pluralStringResource(R.plurals.fulfilment_out_item_lines, row.numberItems, row.numberItems)
    } else {
        pluralStringResource(R.plurals.fulfilment_pallet_count, row.numberPallets, row.numberPallets)
    }

    RecordRow(
        reference = row.reference,
        date = recordDate(row.date, zone),
        party = row.customer?.name,
        details = listOfNotNull(
            row.typeLabel.takeIf { it.isNotBlank() },
            row.platform?.label?.takeIf { it.isNotBlank() }?.let { stringResource(R.string.fulfilment_out_from_platform, it) },
            size,
        ),
        badges = { row.stateLabel.takeIf { it.isNotBlank() }?.let { StatusBadge(text = it, tone = returnStateTone(row.state)) } },
        onClick = onOpen,
    )
}
