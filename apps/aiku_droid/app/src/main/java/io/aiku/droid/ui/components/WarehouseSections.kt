package io.aiku.droid.ui.components

import androidx.annotation.StringRes
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.RowScope
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.WarehouseAbilities
import io.aiku.droid.ui.navigation.AreasRoute
import io.aiku.droid.ui.navigation.DeliveryNotesRoute
import io.aiku.droid.ui.navigation.FulfilmentDeliveriesRoute
import io.aiku.droid.ui.navigation.FulfilmentReturnsRoute
import io.aiku.droid.ui.navigation.GoodsInBacklogRoute
import io.aiku.droid.ui.navigation.GoodsInReturnsRoute
import io.aiku.droid.ui.navigation.GoodsOutBacklogRoute
import io.aiku.droid.ui.navigation.InventoryDashboardRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocationsRoute
import io.aiku.droid.ui.navigation.PickedBaysRoute
import io.aiku.droid.ui.navigation.PickingSessionsRoute
import io.aiku.droid.ui.navigation.ShippersRoute
import io.aiku.droid.ui.navigation.SkuFamiliesRoute
import io.aiku.droid.ui.navigation.SkusRoute
import io.aiku.droid.ui.navigation.StockDeliveriesRoute
import io.aiku.droid.ui.navigation.StockHistoryRoute
import io.aiku.droid.ui.navigation.StoredItemsRoute
import io.aiku.droid.ui.navigation.StoredPalletsRoute
import io.aiku.droid.ui.navigation.TrolleysRoute
import io.aiku.droid.ui.navigation.WarehouseOverviewRoute

enum class WarehouseModule(@StringRes val label: Int, private val canView: (WarehouseAbilities) -> Boolean) {
    Inventory(R.string.module_inventory, { it.viewInventory }),
    Locations(R.string.module_locations, { it.viewLocations }),
    GoodsIn(R.string.module_goods_in, { it.viewGoodsIn }),
    GoodsOut(R.string.module_goods_out, { it.viewGoodsOut }),
    ;

    fun allowsCoreSections(can: WarehouseAbilities): Boolean = canView(can)

    fun visibleSections(can: WarehouseAbilities): List<WarehouseSection> =
        WarehouseSection.entries.filter { it.module == this && it.isVisible(can) }
}

enum class WarehouseSection(
    val module: WarehouseModule,
    @StringRes val label: Int,
    val route: Any,
    val isFulfilment: Boolean = false,
) {
    InventoryDashboard(WarehouseModule.Inventory, R.string.section_dashboard, InventoryDashboardRoute),
    SkuFamilies(WarehouseModule.Inventory, R.string.section_sko_families, SkuFamiliesRoute),
    Skus(WarehouseModule.Inventory, R.string.section_skos, SkusRoute),
    StoredPallets(WarehouseModule.Inventory, R.string.section_stored_pallets, StoredPalletsRoute, isFulfilment = true),
    StoredItems(WarehouseModule.Inventory, R.string.section_stored_items, StoredItemsRoute, isFulfilment = true),
    StockHistory(WarehouseModule.Inventory, R.string.section_stock_history, StockHistoryRoute),
    WarehouseOverview(WarehouseModule.Locations, R.string.section_warehouse, WarehouseOverviewRoute),
    Areas(WarehouseModule.Locations, R.string.section_areas, AreasRoute),
    Locations(WarehouseModule.Locations, R.string.section_locations, LocationsRoute),
    GoodsInBacklog(WarehouseModule.GoodsIn, R.string.section_backlog, GoodsInBacklogRoute),
    StockDeliveries(WarehouseModule.GoodsIn, R.string.section_stock_deliveries, StockDeliveriesRoute),
    FulfilmentDeliveries(WarehouseModule.GoodsIn, R.string.section_fulfilment_deliveries, FulfilmentDeliveriesRoute, isFulfilment = true),
    GoodsInReturns(WarehouseModule.GoodsIn, R.string.section_returns, GoodsInReturnsRoute),
    GoodsOutBacklog(WarehouseModule.GoodsOut, R.string.section_backlog, GoodsOutBacklogRoute),
    DeliveryNotes(WarehouseModule.GoodsOut, R.string.section_delivery_notes, DeliveryNotesRoute),
    PickingSessions(WarehouseModule.GoodsOut, R.string.section_picking_sessions, PickingSessionsRoute),
    FulfilmentReturns(WarehouseModule.GoodsOut, R.string.section_fulfilment_returns, FulfilmentReturnsRoute, isFulfilment = true),
    Shippers(WarehouseModule.GoodsOut, R.string.section_shippers, ShippersRoute),
    Trolleys(WarehouseModule.GoodsOut, R.string.section_trolleys, TrolleysRoute),
    PickedBays(WarehouseModule.GoodsOut, R.string.section_picked_bays, PickedBaysRoute),
    ;

    fun isVisible(can: WarehouseAbilities): Boolean = if (isFulfilment) can.viewFulfilment else module.allowsCoreSections(can)
}

@Composable
fun sectionTabsOf(active: WarehouseSection): List<SectionTabItem> {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can

    return active.module.visibleSections(can).map { section ->
        SectionTabItem(
            label = stringResource(section.label),
            isActive = section == active,
            onClick = { if (section != active) navigator.switchSection(section.route) },
        )
    }
}

@Composable
fun ModuleHeader(
    section: WarehouseSection,
    modifier: Modifier = Modifier,
    title: String = stringResource(section.label),
    kicker: String = moduleKicker(section.module.label),
    onBack: (() -> Unit)? = null,
    actions: @Composable RowScope.() -> Unit = {},
    below: (@Composable ColumnScope.() -> Unit)? = null,
) {
    AppHeader(
        title = title,
        modifier = modifier,
        kicker = kicker,
        onBack = onBack,
        tabs = sectionTabsOf(section),
        actions = actions,
        below = below,
    )
}
