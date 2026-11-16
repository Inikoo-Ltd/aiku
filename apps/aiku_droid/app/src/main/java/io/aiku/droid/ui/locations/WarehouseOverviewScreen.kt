package io.aiku.droid.ui.locations

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.LocationTab
import io.aiku.droid.data.api.LocationsOverview
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.inventory.StockEvent
import io.aiku.droid.ui.inventory.StockLoadProblem
import io.aiku.droid.ui.inventory.countText
import io.aiku.droid.ui.inventory.dayText
import io.aiku.droid.ui.inventory.moneyText
import io.aiku.droid.ui.inventory.percentOf
import io.aiku.droid.ui.inventory.refusalText
import io.aiku.droid.ui.navigation.AreasRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocationsRoute
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun WarehouseOverviewScreen(overview: WarehouseOverviewViewModel) {
    val state by overview.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { overview.onStart() }

    RequireAbility(ability = Ability.ViewLocations, title = stringResource(R.string.module_locations), activeTab = MainTab.Menu) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = overview::signInAgain)
        } else {
            WarehouseOverviewContent(state = state, overview = overview)
        }
    }
}

@Composable
private fun WarehouseOverviewContent(state: WarehouseOverviewUiState, overview: WarehouseOverviewViewModel) {
    val warehouse = LocalWarehouse.current
    val toasts = LocalToasts.current
    val event = state.event
    val doneText = (event as? StockEvent.Done)?.let { done ->
        stringResource(if ((done.action as? AutoRemoveChange)?.enabled == true) R.string.locations_auto_remove_on else R.string.locations_auto_remove_off)
    }
    val refusedText = (event as? StockEvent.Refused)?.let { refusalText(it) }

    LaunchedEffect(event) {
        when (event) {
            is StockEvent.Done -> doneText?.let { toasts.show(it, ToastKind.Success) }
            is StockEvent.Refused -> refusedText?.let { toasts.show(it, ToastKind.Error) }
            null -> return@LaunchedEffect
        }
        overview.eventHandled()
    }

    AppScreen(
        header = { ModuleHeader(WarehouseSection.WarehouseOverview, title = warehouse.name) },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        val loaded = state.overview
        when {
            loaded != null -> OverviewBlocks(overview = loaded, onToggle = overview::setAutoRemove)
            state.problem != null -> StockLoadProblem(problem = state.problem, title = stringResource(R.string.locations_overview_failed), onRetry = overview::retry)
            else -> SkeletonListCard(rows = 3)
        }
    }
}

@Composable
private fun OverviewBlocks(overview: LocationsOverview, onToggle: (Boolean) -> Unit) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val removal = overview.emptyLocationsRemoval
    val zone = organisationZone()
    val lastChange = removal.lastChange?.let { change ->
        val by = change.by?.takeIf { it.isNotBlank() }
        val at = dayText(change.at, zone)
        when {
            by != null && at != null -> stringResource(R.string.locations_auto_remove_changed, by, at)
            by != null -> stringResource(R.string.locations_auto_remove_changed_by, by)
            at != null -> stringResource(R.string.locations_auto_remove_changed_on, at)
            else -> null
        }
    }

    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        LocationsStatRow {
            LocationsStatTile(
                value = countText(overview.numberAreas),
                label = stringResource(R.string.locations_stat_areas),
                modifier = Modifier.weight(1f),
                onClick = { navigator.switchSection(AreasRoute) },
            )
            LocationsStatTile(
                value = countText(overview.numberLocations),
                label = stringResource(R.string.locations_stat_locations),
                modifier = Modifier.weight(1f),
                onClick = { navigator.switchSection(LocationsRoute()) },
            )
        }
        LocationsStatRow {
            LocationsStatTile(
                value = countText(overview.numberLocationsEmpty),
                label = stringResource(R.string.locations_stat_empty_locations),
                modifier = Modifier.weight(1f),
                valueColor = colors.warn,
                onClick = { navigator.switchSection(LocationsRoute(tab = LocationTab.Empty.key)) },
            )
            overview.utilisation?.let { utilisation ->
                LocationsStatTile(
                    value = stringResource(R.string.locations_percent, percentOf(utilisation)),
                    label = stringResource(R.string.locations_stat_utilisation),
                    modifier = Modifier.weight(1f),
                )
            }
        }
        LocationsStatTile(
            value = moneyText(overview.stockValue, overview.currencyCode),
            label = stringResource(R.string.locations_stat_stock_value),
        )
    }
    LocationsHeading(text = stringResource(R.string.locations_heading_empty_locations))
    AikuCard(padding = 16.dp, spacing = 12.dp) {
        TrailingCheckboxRow(
            label = stringResource(R.string.locations_auto_remove),
            checked = removal.enabled,
            onCheckedChange = onToggle.takeIf { removal.canEdit },
            caption = lastChange,
        )
        LocationsStatRow {
            LocationsStatTile(
                value = countText(removal.toRemove),
                label = stringResource(R.string.locations_to_remove_now),
                modifier = Modifier.weight(1f),
            )
            LocationsStatTile(
                value = countText(removal.removedLastSevenDays),
                label = stringResource(R.string.locations_removed_last_week),
                modifier = Modifier.weight(1f),
            )
        }
    }
    LocationsFootnote(text = stringResource(R.string.locations_auto_remove_help))
}
