package io.aiku.droid.ui.locations

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.WarehouseArea
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SearchScanField
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.inventory.StockLoadProblem
import io.aiku.droid.ui.inventory.moneyText
import io.aiku.droid.ui.inventory.stockText
import io.aiku.droid.ui.navigation.AreaRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ScanRoute
import io.aiku.droid.ui.states.SessionExpiredScreen

@Composable
fun AreasScreen(areas: AreasViewModel) {
    val state by areas.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { areas.onStart() }

    RequireAbility(ability = Ability.ViewLocations, title = stringResource(R.string.module_locations), activeTab = MainTab.Menu) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = areas::signInAgain)
        } else {
            AreasContent(state = state, areas = areas)
        }
    }
}

@Composable
private fun AreasContent(state: AreasUiState, areas: AreasViewModel) {
    val navigator = LocalAppNavigator.current
    val loaded = state.areas
    val search = state.search.trim()

    AppScreen(
        header = { ModuleHeader(WarehouseSection.Areas) },
        activeTab = MainTab.Menu,
        showBar = true,
        refresh = PullRefresh(isRefreshing = state.isRefreshing, onRefresh = areas::refresh),
    ) {
        SearchScanField(
            placeholder = stringResource(R.string.locations_areas_search),
            value = state.search,
            onValueChange = areas::search,
            onScan = { navigator.open(ScanRoute) },
        )
        when {
            state.problem != null -> StockLoadProblem(problem = state.problem, title = stringResource(R.string.locations_areas_failed), onRetry = areas::retry)
            loaded == null -> SkeletonListCard(rows = 3)
            loaded.isEmpty() && search.isNotEmpty() -> LocationsFootnote(text = stringResource(R.string.locations_areas_no_match, search))
            loaded.isEmpty() -> LocationsFootnote(text = stringResource(R.string.locations_areas_none))
            else -> ListGroup {
                loaded.forEachIndexed { index, area ->
                    if (index > 0) {
                        RowDivider()
                    }
                    LocationsTileRow(
                        icon = R.drawable.ic_ui_area,
                        title = area.label,
                        subtitle = areaSubtitle(area, state.currencyCode),
                        onClick = { navigator.open(AreaRoute(id = area.id, code = area.code)) },
                    )
                }
            }
        }
    }
}

@Composable
private fun areaSubtitle(area: WarehouseArea, currencyCode: String?): String = listOfNotNull(
    area.pickingPosition?.let { stringResource(R.string.locations_picking_order, stockText(it)) },
    pluralStringResource(R.plurals.locations_location_count, area.numberLocations, area.numberLocations),
    pluralStringResource(R.plurals.locations_empty_count, area.numberEmptyLocations, area.numberEmptyLocations),
    moneyText(area.stockValue, currencyCode),
).joinToString(LIST_SEPARATOR)
