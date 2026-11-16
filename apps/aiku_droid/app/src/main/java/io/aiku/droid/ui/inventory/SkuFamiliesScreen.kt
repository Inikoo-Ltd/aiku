package io.aiku.droid.ui.inventory

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.rememberScrollState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.FamilyState
import io.aiku.droid.data.api.OrgStockFamily
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SearchField
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.goodsout.LoadMoreOnScroll
import io.aiku.droid.ui.goodsout.LoadingMore
import io.aiku.droid.ui.locations.LIST_SEPARATOR
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.SkuFamilyRoute
import io.aiku.droid.ui.states.SessionExpiredScreen

private val familyStates = listOf(FamilyState.Active, FamilyState.InProcess, FamilyState.Discontinuing, FamilyState.Discontinued)

@Composable
fun SkuFamiliesScreen(families: SkoFamiliesViewModel) {
    val state by families.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { families.onStart() }

    RequireAbility(ability = Ability.ViewInventory, title = stringResource(R.string.module_inventory), activeTab = MainTab.Menu) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = families::signInAgain)
        } else {
            SkuFamiliesContent(state = state, families = families)
        }
    }
}

@Composable
private fun SkuFamiliesContent(state: SkoFamiliesUiState, families: SkoFamiliesViewModel) {
    val navigator = LocalAppNavigator.current
    val scrollState = rememberScrollState()
    val list = state.list
    val search = state.search.trim()
    val stateLabels = mapOf(
        FamilyState.Active to stringResource(R.string.inventory_state_active),
        FamilyState.InProcess to stringResource(R.string.inventory_state_in_process),
        FamilyState.Discontinuing to stringResource(R.string.inventory_state_discontinuing),
        FamilyState.Discontinued to stringResource(R.string.inventory_state_discontinued),
    )

    LoadMoreOnScroll(scrollState = scrollState, itemCount = list.rows.size, canLoadMore = list.canLoadMoreOnScroll, onLoadMore = families::loadMore)

    AppScreen(
        header = { ModuleHeader(section = WarehouseSection.SkuFamilies) },
        activeTab = MainTab.Menu,
        showBar = true,
        scrollState = scrollState,
    ) {
        FilterChips(
            options = familyStates.map { FilterOption(label = countLabel(stateLabels.getValue(it), state.counts?.count(it))) },
            selectedIndex = familyStates.indexOf(state.state),
            onSelect = { index -> families.selectState(familyStates[index]) },
        )
        SearchField(
            value = state.search,
            onValueChange = families::search,
            placeholder = stringResource(R.string.inventory_families_search),
            modifier = Modifier.fillMaxWidth(),
        )
        list.firstPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.inventory_families_failed), onRetry = families::retry)
        }
        when {
            list.rows.isNotEmpty() -> ListGroup {
                list.rows.forEachIndexed { index, family ->
                    if (index > 0) {
                        RowDivider()
                    }
                    IconItemRow(
                        icon = R.drawable.ic_ui_sko,
                        title = stringResource(R.string.inventory_pair, family.code, family.name),
                        subtitle = familySubtitle(family),
                        onClick = { navigator.open(SkuFamilyRoute(id = family.id, code = family.code)) },
                    )
                }
            }
            list.isEmpty && search.isNotEmpty() -> BoardFootnote(text = stringResource(R.string.inventory_families_no_match, search))
            list.isEmpty -> BoardFootnote(text = stringResource(R.string.inventory_families_none))
            list.problem == null -> SkeletonListCard(rows = 4)
        }
        if (list.isLoadingMore) {
            LoadingMore()
        }
        list.nextPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.inventory_families_more_failed), onRetry = families::loadMore)
        }
    }
}

@Composable
private fun familySubtitle(family: OrgStockFamily): String = listOfNotNull(
    pluralStringResource(R.plurals.inventory_sko_count, family.numberCurrentOrgStocks, family.numberCurrentOrgStocks),
    stringResource(R.string.inventory_family_stock_value, moneyText(family.stockValue, family.currencyCode)),
    family.stockCover?.let { stringResource(R.string.inventory_cover_months, stockText(it)) },
).joinToString(LIST_SEPARATOR)
