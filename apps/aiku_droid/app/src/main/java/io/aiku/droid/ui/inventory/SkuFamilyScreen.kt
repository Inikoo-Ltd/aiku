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
import io.aiku.droid.data.api.FamilyState
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.goodsout.LoadMoreOnScroll
import io.aiku.droid.ui.goodsout.LoadingMore
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.SkuRoute
import io.aiku.droid.ui.states.SessionExpiredScreen

@Composable
fun SkuFamilyScreen(family: SkoFamilyViewModel, code: String) {
    val state by family.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { family.onStart() }

    RequireAbility(ability = Ability.ViewInventory, title = stringResource(R.string.module_inventory), activeTab = MainTab.Menu) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = family::signInAgain)
        } else {
            SkuFamilyContent(state = state, family = family, code = code)
        }
    }
}

@Composable
private fun SkuFamilyContent(state: SkoFamilyUiState, family: SkoFamilyViewModel, code: String) {
    val navigator = LocalAppNavigator.current
    val scrollState = rememberScrollState()
    val summary = state.family
    val list = state.list
    val stateLabels = mapOf(
        FamilyState.Active.key to stringResource(R.string.inventory_state_active),
        FamilyState.InProcess.key to stringResource(R.string.inventory_state_in_process),
        FamilyState.Discontinuing.key to stringResource(R.string.inventory_state_discontinuing),
        FamilyState.Discontinued.key to stringResource(R.string.inventory_state_discontinued),
    )

    LoadMoreOnScroll(scrollState = scrollState, itemCount = list.rows.size, canLoadMore = list.canLoadMoreOnScroll, onLoadMore = family::loadMore)

    AppScreen(
        header = {
            AppHeader(
                title = summary?.let { stringResource(R.string.inventory_pair, it.code, it.name) } ?: code,
                kicker = summary?.state?.let { stateLabels[it] ?: it }?.takeIf { it.isNotBlank() }?.let { stringResource(R.string.inventory_family_kicker, it) }
                    ?: stringResource(R.string.inventory_family_kicker_short),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        scrollState = scrollState,
        refresh = PullRefresh(isRefreshing = list.isRefreshing, onRefresh = family::refresh),
    ) {
        if (summary != null) {
            InventoryStatGrid(
                stats = listOf(
                    InventoryStat(value = countText(summary.numberCurrentOrgStocks), label = stringResource(R.string.inventory_stat_skos)),
                    InventoryStat(value = moneyText(summary.stockValue, summary.currencyCode), label = stringResource(R.string.inventory_stat_stock_value)),
                ),
                columns = 2,
            )
            FamilyStatusSections(skoStatus = summary.skoStatus, quantityStatus = summary.quantityStatus)
        }
        InventorySectionHeading(text = stringResource(R.string.inventory_skos_heading), trailingText = list.total?.let { countText(it) })
        list.firstPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.inventory_skos_failed), onRetry = family::retry)
        }
        when {
            list.rows.isNotEmpty() -> ListGroup {
                list.rows.forEachIndexed { index, sko ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ItemRow(
                        code = sko.code,
                        subtitle = sko.name,
                        chip = stateLabels[sko.state]?.takeIf { sko.state != ACTIVE_STATE },
                        done = stockText(sko.quantityAvailable, sko.quantityAvailableLabel),
                        onClick = { navigator.open(SkuRoute(id = sko.id, code = sko.code)) },
                    )
                }
            }
            list.isEmpty -> BoardFootnote(text = stringResource(R.string.inventory_skos_none))
            list.problem == null -> SkeletonListCard(rows = 4)
        }
        if (list.isLoadingMore) {
            LoadingMore()
        }
        list.nextPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.inventory_skos_more_failed), onRetry = family::loadMore)
        }
    }
}
