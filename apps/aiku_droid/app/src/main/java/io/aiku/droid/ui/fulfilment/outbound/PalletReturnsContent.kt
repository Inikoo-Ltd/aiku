package io.aiku.droid.ui.fulfilment.outbound

import androidx.compose.foundation.rememberScrollState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.PalletReturnRow
import io.aiku.droid.data.api.PalletReturnStateFilter
import io.aiku.droid.data.api.PalletReturnTypeFilter
import io.aiku.droid.data.api.PalletReturnTypeGroup
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SearchField
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.fulfilment.dotJoined
import io.aiku.droid.ui.fulfilment.filterChipLabel
import io.aiku.droid.ui.goodsin.HelperNote
import io.aiku.droid.ui.goodsin.goodsInDate
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
internal fun PalletReturnsContent(returns: PalletReturnsViewModel) {
    val state by returns.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { returns.onStart() }

    if (state.isSessionExpired) {
        SessionExpiredScreen(onSignIn = returns::signInAgain)
        return
    }

    val navigator = LocalAppNavigator.current
    val scrollState = rememberScrollState()
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
        SearchField(value = state.search, onValueChange = returns::search, placeholder = stringResource(R.string.fulfilment_out_search_returns))
        list.firstPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.fulfilment_out_returns_failed), onRetry = returns::retry)
        }
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
}

@Composable
private fun PalletReturnRowItem(row: PalletReturnRow, onOpen: () -> Unit) {
    val zone = organisationZone()
    val size = if (row.typeGroup == PalletReturnTypeGroup.ITEMS) {
        pluralStringResource(R.plurals.fulfilment_out_item_lines, row.numberItems, row.numberItems)
    } else {
        pluralStringResource(R.plurals.fulfilment_pallet_count, row.numberPallets, row.numberPallets)
    }

    ItemRow(
        code = row.reference,
        subtitle = dotJoined(
            row.customer?.name,
            row.typeLabel,
            row.platform?.label?.takeIf { it.isNotBlank() }?.let { stringResource(R.string.fulfilment_out_from_platform, it) },
            size,
            goodsInDate(row.date, zone),
        ),
        chip = row.stateLabel.takeIf { it.isNotBlank() },
        chipTone = returnStateTone(row.state),
        showPhoto = false,
        onClick = onOpen,
    )
}
