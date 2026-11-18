package io.aiku.droid.ui.goodsin

import androidx.annotation.StringRes
import androidx.compose.foundation.rememberScrollState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.CustomerReturnRow
import io.aiku.droid.data.api.ReturnStateFilter
import io.aiku.droid.data.api.ReturnType
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SearchField
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.goodsout.LoadMoreOnScroll
import io.aiku.droid.ui.goodsout.LoadingMore
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.inventory.StockLoadProblem
import io.aiku.droid.ui.inventory.countLabel
import io.aiku.droid.ui.locations.LIST_SEPARATOR
import io.aiku.droid.ui.navigation.GoodsInReturnRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.states.SessionExpiredScreen

internal val returnChipStates = listOf(
    ReturnStateFilter.All to R.string.goodsin_filter_all,
    ReturnStateFilter.Received to R.string.goodsin_return_step_received,
    ReturnStateFilter.Returning to R.string.goodsin_return_step_returning,
    ReturnStateFilter.Returned to R.string.goodsin_return_step_returned,
    ReturnStateFilter.Done to R.string.goodsin_return_step_done,
    ReturnStateFilter.Cancelled to R.string.goodsin_state_cancelled,
)

@StringRes
internal fun returnsListTitle(state: ReturnStateFilter): Int =
    if (state == ReturnStateFilter.ToProcess) R.string.goodsin_returns_to_process_title else R.string.section_returns

@Composable
fun GoodsInReturnsScreen(returns: ReturnsViewModel) {
    val state by returns.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { returns.onStart() }

    RequireAbility(ability = Ability.ViewReturns, title = stringResource(R.string.module_goods_in), activeTab = MainTab.Menu) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = returns::signInAgain)
        } else {
            GoodsInReturnsContent(state = state, returns = returns)
        }
    }
}

@Composable
private fun GoodsInReturnsContent(state: ReturnsUiState, returns: ReturnsViewModel) {
    val navigator = LocalAppNavigator.current
    val scrollState = rememberScrollState()
    val list = state.list
    val search = state.search.trim()
    val tabs = returnChipStates.map { (chipState, label) -> FilterOption(label = countLabel(stringResource(label), state.counts?.count(chipState.key))) }

    LoadMoreOnScroll(scrollState = scrollState, itemCount = list.rows.size, canLoadMore = list.canLoadMoreOnScroll, onLoadMore = returns::loadMore)

    AppScreen(
        header = { ModuleHeader(section = WarehouseSection.GoodsInReturns, title = stringResource(returnsListTitle(state.state))) },
        activeTab = MainTab.Menu,
        showBar = true,
        scrollState = scrollState,
    ) {
        FilterChips(
            options = tabs,
            selectedIndex = returnChipStates.indexOfFirst { it.first == state.state },
            onSelect = { index -> returns.selectState(returnChipStates[index].first) },
        )
        SearchField(placeholder = stringResource(R.string.goodsin_search_returns), value = state.search, onValueChange = returns::search)
        list.firstPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.goodsin_returns_failed), onRetry = returns::retry)
        }
        when {
            list.rows.isNotEmpty() -> ListGroup {
                list.rows.forEachIndexed { index, row ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ReturnRow(row = row, onOpen = { navigator.open(GoodsInReturnRoute(id = row.id, reference = row.reference)) })
                }
            }
            list.isEmpty && search.isNotEmpty() -> HelperNote(text = stringResource(R.string.goodsin_returns_no_match, search))
            list.isEmpty -> HelperNote(text = stringResource(R.string.goodsin_returns_none))
            list.problem == null -> SkeletonListCard(rows = 4)
        }
        if (list.isLoadingMore) {
            LoadingMore()
        }
        list.nextPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.goodsin_returns_more_failed), onRetry = returns::loadMore)
        }
        HelperNote(text = stringResource(R.string.goodsin_returns_note))
    }
}

@Composable
private fun ReturnRow(row: CustomerReturnRow, onOpen: () -> Unit) {
    val zone = organisationZone()
    val progress = if (row.numberHandled > 0) {
        pluralStringResource(R.plurals.goodsin_return_handled_count, row.numberItems, row.numberHandled, row.numberItems)
    } else {
        pluralStringResource(R.plurals.goodsin_item_count, row.numberItems, row.numberItems)
    }
    val subtitle = listOfNotNull(
        row.typeLabel.takeIf { row.type == ReturnType.CANCELLATION && it.isNotBlank() },
        row.customer?.name?.takeIf { it.isNotBlank() },
        progress,
        row.deliveryNote?.reference?.takeIf { it.isNotBlank() },
        goodsInDate(row.createdAt, zone),
    ).joinToString(LIST_SEPARATOR)

    ItemRow(
        code = row.reference,
        subtitle = subtitle,
        chip = row.stateLabel.takeIf { it.isNotBlank() },
        chipTone = returnStateTone(row.state),
        showPhoto = false,
        onClick = onOpen,
    )
}
