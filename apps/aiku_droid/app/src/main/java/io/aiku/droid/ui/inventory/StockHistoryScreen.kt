package io.aiku.droid.ui.inventory

import androidx.compose.foundation.rememberScrollState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.res.stringResource
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.MovementFlow
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.goodsout.LoadMoreOnScroll
import io.aiku.droid.ui.goodsout.LoadingMore
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.states.SessionExpiredScreen

private val flowOptions = listOf(null, MovementFlow.In, MovementFlow.Out, MovementFlow.Audit)

@Composable
fun StockHistoryScreen(movements: StockMovementsViewModel, code: String, isLocation: Boolean) {
    val state by movements.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { movements.onStart() }

    RequireAbility(
        ability = if (isLocation) Ability.ViewLocations else Ability.ViewInventory,
        title = stringResource(if (isLocation) R.string.module_locations else R.string.module_inventory),
        activeTab = MainTab.Menu,
    ) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = movements::signInAgain)
        } else {
            StockHistoryContent(state = state, movements = movements, code = code, isLocation = isLocation)
        }
    }
}

@Composable
private fun StockHistoryContent(state: StockMovementsUiState, movements: StockMovementsViewModel, code: String, isLocation: Boolean) {
    val scrollState = rememberScrollState()
    val list = state.list
    val flowLabels = mapOf(
        null to stringResource(R.string.inventory_all),
        MovementFlow.In to stringResource(R.string.inventory_history_in),
        MovementFlow.Out to stringResource(R.string.inventory_history_out),
        MovementFlow.Audit to stringResource(R.string.inventory_history_audit),
    )

    LoadMoreOnScroll(scrollState = scrollState, itemCount = list.rows.size, canLoadMore = list.canLoadMoreOnScroll, onLoadMore = movements::loadMore)

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(if (isLocation) R.string.inventory_movements_title else R.string.section_stock_history),
                kicker = stringResource(if (isLocation) R.string.inventory_movements_location_kicker else R.string.inventory_movements_sko_kicker, code),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        scrollState = scrollState,
        refresh = PullRefresh(isRefreshing = list.isRefreshing, onRefresh = movements::refresh),
    ) {
        if (movements.canFilterByFlow) {
            FilterChips(
                options = flowOptions.map { FilterOption(label = flowLabels.getValue(it)) },
                selectedIndex = flowOptions.indexOf(state.flow),
                onSelect = { index -> movements.selectFlow(flowOptions[index]) },
            )
        }
        list.firstPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.inventory_movements_failed), onRetry = movements::retry)
        }
        when {
            list.rows.isNotEmpty() -> ListGroup {
                list.rows.forEachIndexed { index, movement ->
                    if (index > 0) {
                        RowDivider()
                    }
                    val title = if (isLocation) movement.orgStockCode ?: movement.orgStockName.orEmpty() else movement.location?.code ?: code
                    MovementRow(movement = movement, title = title, showSko = isLocation)
                }
            }
            list.isEmpty -> BoardFootnote(text = stringResource(R.string.inventory_movements_none))
            list.problem == null -> SkeletonListCard(rows = 4)
        }
        if (list.isLoadingMore) {
            LoadingMore()
        }
        list.nextPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.inventory_movements_more_failed), onRetry = movements::loadMore)
        }
        if (!isLocation) {
            BoardFootnote(text = stringResource(R.string.inventory_movements_footnote))
        }
    }
}
