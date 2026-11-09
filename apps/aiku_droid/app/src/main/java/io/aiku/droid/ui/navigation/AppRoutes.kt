package io.aiku.droid.ui.navigation

import kotlinx.serialization.Serializable

@Serializable data object DashboardRoute
@Serializable data object WarehouseMenuRoute

@Serializable data object InventoryDashboardRoute
@Serializable data object SkuFamiliesRoute
@Serializable data class SkuFamilyDetailRoute(val code: String)
@Serializable data object SkusRoute
@Serializable data class SkuDetailRoute(val code: String)

@Serializable data object WarehouseOverviewRoute
@Serializable data object AreasRoute
@Serializable data class AreaDetailRoute(val code: String)
@Serializable data object LocationsRoute
@Serializable data class LocationDetailRoute(val code: String)

@Serializable data object GoodsInBacklogRoute
@Serializable data object StockDeliveriesRoute
@Serializable data class StockDeliveryDetailRoute(val reference: String)

@Serializable data object GoodsOutBacklogRoute
@Serializable data object DeliveryNotesRoute
@Serializable data class DeliveryNoteDetailRoute(val reference: String)
@Serializable data class PackingRoute(val reference: String)
@Serializable data object ShippersRoute

@Serializable data object ScanRoute
@Serializable data object ScanCameraRoute
@Serializable data object ScanHardwareRoute
@Serializable data object ScanSettingsRoute

@Serializable data object SearchRoute
@Serializable data object NotificationsRoute
@Serializable data object ProfileRoute
@Serializable data object AppearanceRoute
