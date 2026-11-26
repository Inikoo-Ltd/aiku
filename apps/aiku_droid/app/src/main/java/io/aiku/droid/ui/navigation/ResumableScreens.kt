package io.aiku.droid.ui.navigation

import androidx.annotation.StringRes
import androidx.navigation.NavBackStackEntry
import androidx.navigation.NavDestination.Companion.hasRoute
import androidx.navigation.toRoute
import io.aiku.droid.R
import io.aiku.droid.data.settings.SavedScreen
import kotlin.reflect.KClass
import kotlinx.serialization.KSerializer
import kotlinx.serialization.json.Json

class ResumableScreen<T : Any>(
    val type: KClass<T>,
    val serializer: KSerializer<T>,
    @StringRes val label: Int,
    val accepts: (T) -> Boolean = { true },
)

data class ResumeTarget(val route: Any, @StringRes val label: Int)

object ResumableScreens {
    private val json = Json { ignoreUnknownKeys = true }

    val all: List<ResumableScreen<*>> = listOf(
        ResumableScreen(WarehouseDashboardRoute::class, WarehouseDashboardRoute.serializer(), R.string.section_dashboard),
        ResumableScreen(GoodsOutBacklogRoute::class, GoodsOutBacklogRoute.serializer(), R.string.module_goods_out),
        ResumableScreen(DeliveryNotesRoute::class, DeliveryNotesRoute.serializer(), R.string.section_delivery_notes, accepts = { it.sessionId == null }),
        ResumableScreen(PickingSessionsRoute::class, PickingSessionsRoute.serializer(), R.string.section_picking_sessions),
        ResumableScreen(TrolleysRoute::class, TrolleysRoute.serializer(), R.string.section_trolleys),
        ResumableScreen(PickedBaysRoute::class, PickedBaysRoute.serializer(), R.string.section_picked_bays),
        ResumableScreen(GoodsInBacklogRoute::class, GoodsInBacklogRoute.serializer(), R.string.module_goods_in),
        ResumableScreen(StockDeliveriesRoute::class, StockDeliveriesRoute.serializer(), R.string.section_stock_deliveries),
        ResumableScreen(GoodsInReturnsRoute::class, GoodsInReturnsRoute.serializer(), R.string.section_returns),
        ResumableScreen(FulfilmentDeliveriesRoute::class, FulfilmentDeliveriesRoute.serializer(), R.string.section_fulfilment_deliveries),
        ResumableScreen(FulfilmentReturnsRoute::class, FulfilmentReturnsRoute.serializer(), R.string.section_fulfilment_returns),
        ResumableScreen(StoredPalletsRoute::class, StoredPalletsRoute.serializer(), R.string.section_stored_pallets),
        ResumableScreen(StoredItemsRoute::class, StoredItemsRoute.serializer(), R.string.section_stored_items),
        ResumableScreen(InventoryDashboardRoute::class, InventoryDashboardRoute.serializer(), R.string.module_inventory),
        ResumableScreen(SkuFamiliesRoute::class, SkuFamiliesRoute.serializer(), R.string.section_sko_families),
        ResumableScreen(SkusRoute::class, SkusRoute.serializer(), R.string.section_skos),
        ResumableScreen(WarehouseOverviewRoute::class, WarehouseOverviewRoute.serializer(), R.string.module_locations),
        ResumableScreen(AreasRoute::class, AreasRoute.serializer(), R.string.section_areas),
        ResumableScreen(LocationsRoute::class, LocationsRoute.serializer(), R.string.section_locations),
        ResumableScreen(ClockingRoute::class, ClockingRoute.serializer(), R.string.menu_clocking),
        ResumableScreen(TimesheetsRoute::class, TimesheetsRoute.serializer(), R.string.menu_timesheets),
        ResumableScreen(LeaveRoute::class, LeaveRoute.serializer(), R.string.clocking_tab_leave),
        ResumableScreen(ClockingAdjustmentsRoute::class, ClockingAdjustmentsRoute.serializer(), R.string.clocking_tab_adjustment),
        ResumableScreen(OvertimeRoute::class, OvertimeRoute.serializer(), R.string.clocking_tab_overtime),
        ResumableScreen(ClockingCalendarRoute::class, ClockingCalendarRoute.serializer(), R.string.clocking_tab_calendar),
    )

    fun save(entry: NavBackStackEntry): SavedScreen? = all.firstNotNullOfOrNull { screen -> saveIfMatching(screen, entry) }

    fun restore(saved: SavedScreen): ResumeTarget? {
        val screen = all.firstOrNull { it.type.qualifiedName == saved.type } ?: return null
        return restoreWith(screen, saved.payload)
    }

    private fun <T : Any> saveIfMatching(screen: ResumableScreen<T>, entry: NavBackStackEntry): SavedScreen? {
        if (!entry.destination.hasRoute(screen.type)) {
            return null
        }
        val route = runCatching { entry.toRoute<T>(screen.type) }.getOrNull() ?: return null
        if (!screen.accepts(route)) {
            return null
        }
        return SavedScreen(type = screen.type.qualifiedName.orEmpty(), payload = json.encodeToString(screen.serializer, route))
    }

    private fun <T : Any> restoreWith(screen: ResumableScreen<T>, payload: String): ResumeTarget? =
        runCatching { json.decodeFromString(screen.serializer, payload) }.getOrNull()
            ?.takeIf { screen.accepts(it) }
            ?.let { ResumeTarget(route = it, label = screen.label) }
}
