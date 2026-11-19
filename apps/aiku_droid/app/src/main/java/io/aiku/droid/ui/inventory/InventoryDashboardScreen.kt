package io.aiku.droid.ui.inventory

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.InventoryDashboard
import io.aiku.droid.data.api.SkoBucket
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.DetailCard
import io.aiku.droid.ui.components.DetailLine
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.warehouseKicker
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ScanRoute
import io.aiku.droid.ui.navigation.SkusRoute
import io.aiku.droid.ui.states.SessionExpiredScreen

@Composable
fun InventoryDashboardScreen(dashboard: InventoryDashboardViewModel) {
    val state by dashboard.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { dashboard.onStart() }

    RequireAbility(ability = Ability.ViewInventory, title = stringResource(R.string.module_inventory), activeTab = MainTab.Menu) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = dashboard::signInAgain)
        } else {
            InventoryDashboardContent(state = state, onRetry = dashboard::retry, onRefresh = dashboard::refresh)
        }
    }
}

@Composable
private fun InventoryDashboardContent(state: InventoryDashboardUiState, onRetry: () -> Unit, onRefresh: () -> Unit) {
    val navigator = LocalAppNavigator.current

    AppScreen(
        header = {
            ModuleHeader(
                section = WarehouseSection.InventoryDashboard,
                title = stringResource(R.string.inventory_dashboard_title),
                actions = {
                    HeaderIconButton(
                        icon = R.drawable.ic_ui_scan,
                        description = stringResource(R.string.inventory_manage_barcodes),
                        onClick = { navigator.open(ScanRoute) },
                        filled = true,
                    )
                },
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        refresh = PullRefresh(isRefreshing = state.isRefreshing, onRefresh = onRefresh),
    ) {
        val dashboard = state.dashboard
        when {
            dashboard != null -> DashboardBlocks(dashboard = dashboard)
            state.problem != null -> StockLoadProblem(problem = state.problem, title = stringResource(R.string.inventory_dashboard_failed), onRetry = onRetry)
            else -> SkeletonListCard(rows = 4)
        }
    }
}

@Composable
private fun DashboardBlocks(dashboard: InventoryDashboard) {
    val navigator = LocalAppNavigator.current

    InventoryStatGrid(
        stats = listOf(
            InventoryStat(
                value = countText(dashboard.numberCurrentOrgStocks),
                label = stringResource(R.string.inventory_tile_current_skos),
                onClick = { navigator.open(SkusRoute()) },
            ),
            InventoryStat(
                value = countText(dashboard.numberAbnormalities),
                label = stringResource(R.string.inventory_tile_abnormalities),
                tone = if (dashboard.numberAbnormalities > 0) Tone.Danger else null,
                onClick = { navigator.open(SkusRoute(bucket = SkoBucket.Abnormality.key)) },
            ),
            InventoryStat(
                value = countText(dashboard.replenishmentsWholesale),
                label = stringResource(R.string.inventory_tile_replenishments, dashboard.replenishmentsDropshipping),
            ),
            InventoryStat(value = countText(dashboard.lowStockAudits), label = stringResource(R.string.inventory_tile_low_stock_audits)),
            InventoryStat(
                value = countText(dashboard.belowMinimumStock),
                label = pluralStringResource(R.plurals.inventory_tile_below_minimum, dashboard.numberWithMinimumStock, dashboard.numberWithMinimumStock),
            ),
            InventoryStat(
                value = countText(dashboard.negativeStocks),
                label = stringResource(R.string.inventory_tile_negative_stocks),
                tone = if (dashboard.negativeStocks > 0) Tone.Danger else null,
            ),
            InventoryStat(
                value = countText(dashboard.wrongWeights),
                label = stringResource(R.string.inventory_tile_wrong_weights),
                tone = if (dashboard.wrongWeights > 0) Tone.Warn else null,
            ),
            InventoryStat(
                value = countText(dashboard.withoutProducts),
                label = stringResource(R.string.inventory_tile_without_product),
                tone = if (dashboard.withoutProducts > 0) Tone.Warn else null,
            ),
        ),
        columns = 2,
    )
    DetailCard(
        title = warehouseKicker(),
        lines = listOfNotNull(
            DetailLine(icon = R.drawable.ic_ui_sko, text = stringResource(R.string.inventory_stock_value_line, moneyText(dashboard.stockValue, dashboard.currencyCode))),
            dashboard.utilisation?.let { utilisation ->
                DetailLine(
                    icon = R.drawable.ic_ui_location,
                    text = pluralStringResource(
                        R.plurals.inventory_location_utilisation,
                        dashboard.numberLocations,
                        percentOf(utilisation),
                        dashboard.usedLocations,
                        dashboard.numberLocations,
                    ),
                )
            },
        ),
    )
    BoardFootnote(text = stringResource(R.string.inventory_dashboard_footnote))
}
