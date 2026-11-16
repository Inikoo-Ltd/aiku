package io.aiku.droid.ui.locations

import androidx.compose.foundation.rememberScrollState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.goodsout.LoadMoreOnScroll
import io.aiku.droid.ui.goodsout.LoadingMore
import io.aiku.droid.ui.inventory.StockLoadProblem
import io.aiku.droid.ui.inventory.countText
import io.aiku.droid.ui.inventory.stockText
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocationRoute
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun AreaScreen(areas: AreasViewModel, locations: LocationsViewModel, areaId: Long, code: String) {
    val areasState by areas.state.collectAsStateWithLifecycle()
    val state by locations.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) {
        areas.onStart()
        locations.onStart()
    }

    RequireAbility(ability = Ability.ViewLocations, title = stringResource(R.string.module_locations), activeTab = MainTab.Menu) {
        if (areasState.isSessionExpired || state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = locations::signInAgain)
        } else {
            AreaContent(areasState = areasState, state = state, locations = locations, areaId = areaId, code = code)
        }
    }
}

@Composable
private fun AreaContent(areasState: AreasUiState, state: LocationsUiState, locations: LocationsViewModel, areaId: Long, code: String) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val scrollState = rememberScrollState()
    val area = areasState.area(areaId)
    val list = state.list

    LoadMoreOnScroll(scrollState = scrollState, itemCount = list.rows.size, canLoadMore = list.canLoadMoreOnScroll, onLoadMore = locations::loadMore)

    AppScreen(
        header = { AppHeader(title = area?.label ?: code, kicker = stringResource(R.string.locations_area_kicker, area?.code ?: code)) },
        activeTab = MainTab.Menu,
        showBar = true,
        scrollState = scrollState,
    ) {
        if (area != null) {
            LocationsStatRow {
                LocationsStatTile(
                    value = countText(area.numberLocations),
                    label = stringResource(R.string.locations_stat_locations),
                    modifier = Modifier.weight(1f),
                )
                LocationsStatTile(
                    value = countText(area.numberEmptyLocations),
                    label = stringResource(R.string.locations_stat_empty),
                    modifier = Modifier.weight(1f),
                    valueColor = colors.warn,
                )
                area.pickingPosition?.let { position ->
                    LocationsStatTile(
                        value = stockText(position),
                        label = stringResource(R.string.locations_stat_picking_order),
                        modifier = Modifier.weight(1f),
                    )
                }
            }
        }
        LocationsHeading(text = stringResource(R.string.locations_heading_locations), count = list.total?.let { countText(it) })
        list.firstPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.locations_list_failed), onRetry = locations::retry)
        }
        when {
            list.rows.isNotEmpty() -> LocationsList(
                locations = list.rows,
                onOpen = { location -> navigator.open(LocationRoute(id = location.id, code = location.code)) },
                showArea = false,
            )
            list.isEmpty -> LocationsFootnote(text = stringResource(R.string.locations_area_none))
            list.problem == null -> SkeletonListCard(rows = 4)
        }
        if (list.isLoadingMore) {
            LoadingMore()
        }
        list.nextPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.locations_list_more_failed), onRetry = locations::loadMore)
        }
    }
}
