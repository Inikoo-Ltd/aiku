package io.aiku.droid.ui.navigation

import kotlinx.serialization.Serializable

@Serializable data object HomeRoute
@Serializable data object MyWorkRoute
@Serializable data object ScanRoute
@Serializable data class ScanResultRoute(val code: String)
@Serializable data object InboxRoute
@Serializable data object MenuRoute
@Serializable data object SearchRoute
@Serializable data object WarehouseDashboardRoute
@Serializable data object ProfileRoute
@Serializable data object AppearanceRoute
@Serializable data object LanguageRoute
@Serializable data object ChangePasswordRoute
@Serializable data object SyncQueueRoute
@Serializable data object ResolveConflictsRoute
@Serializable data class ConflictRoute(val reference: String)
@Serializable data class OnlineOnlyRoute(val reference: String)
@Serializable data object NoTasksRoute
@Serializable data class StatePreviewRoute(val kind: String)
@Serializable data class NotDesignedRoute(val title: String)
@Serializable data object InventoryDashboardRoute
@Serializable data object SkuFamiliesRoute
@Serializable data class SkuFamilyRoute(val code: String)
@Serializable data object SkusRoute
@Serializable data object SkuAbnormalitiesRoute
@Serializable data class SkuRoute(val code: String)
@Serializable data object StockHistoryRoute
@Serializable data object WarehouseOverviewRoute
@Serializable data object AreasRoute
@Serializable data class AreaRoute(val code: String)
@Serializable data object LocationsRoute
@Serializable data class LocationRoute(val code: String)
@Serializable data class LocationSkuRoute(val locationCode: String, val skuCode: String)
@Serializable data object GoodsInBacklogRoute
@Serializable data object StockDeliveriesRoute
@Serializable data class StockDeliveryArrivingRoute(val reference: String)
@Serializable data class StockDeliveryCheckingRoute(val reference: String)
@Serializable data object ScanCameraRoute
@Serializable data class StockDeliveryChecklistRoute(val reference: String)
@Serializable data class BookingInRoute(val reference: String)
@Serializable data class PlaceItemRoute(val reference: String, val skuCode: String)
@Serializable data class PlaceItemNoPickingLocationRoute(val reference: String, val skuCode: String)
@Serializable data class BookedInRoute(val reference: String)
@Serializable data object GoodsInReturnsRoute
@Serializable data class GoodsInReturnRoute(val reference: String)
@Serializable data object GoodsOutBacklogRoute
@Serializable data class DeliveryNotesRoute(val bucket: String? = null, val channel: String? = null, val sessionId: Long? = null, val sessionReference: String? = null)
@Serializable data class DeliveryNoteToDoRoute(val id: Long, val reference: String)
@Serializable data class DeliveryNotePickingRoute(val id: Long, val reference: String)
@Serializable data class ScanToPickRoute(val reference: String, val id: Long? = null)
@Serializable data class DeliveryNotePickedRoute(val id: Long, val reference: String)
@Serializable data class DeliveryNotePackingRoute(val id: Long, val reference: String)
@Serializable data class AddShipmentRoute(val id: Long, val reference: String)
@Serializable data class DeliveryNoteFinalisedRoute(val id: Long, val reference: String)
@Serializable data class DeliveryNoteDispatchedRoute(val id: Long, val reference: String)
@Serializable data class DeliveryNoteCancelledRoute(val id: Long, val reference: String)
@Serializable data class AskCrmRoute(val context: String, val id: Long, val reference: String)
@Serializable data class ExternalShipmentRoute(val id: Long, val reference: String)
@Serializable data object PickingSessionsRoute
@Serializable data class PickingSessionRoute(val id: Long, val reference: String)
@Serializable data class PickByLocationRoute(val id: Long, val reference: String)
@Serializable data class PackByScanRoute(val id: Long, val reference: String)
@Serializable data object TrolleysRoute
@Serializable data object PickedBaysRoute
@Serializable data object FulfilmentDeliveriesRoute
@Serializable data class FulfilmentDeliveryRoute(val reference: String)
@Serializable data class FulfilmentBookInRoute(val reference: String)
@Serializable data class FulfilmentBookedInRoute(val reference: String)
@Serializable data object StoredPalletsRoute
@Serializable data class PalletRoute(val reference: String)
@Serializable data object PalletIncidentsRoute
@Serializable data object StoredItemsRoute
@Serializable data class StoredItemAuditRoute(val reference: String)
@Serializable data object FulfilmentReturnsRoute
@Serializable data class ReturnPickPalletsRoute(val reference: String)
@Serializable data class ReturnPickItemsRoute(val reference: String)
@Serializable data class ReturnPickedRoute(val reference: String)
@Serializable data class ReturnShipmentRoute(val reference: String)
@Serializable data class ReturnDispatchedRoute(val reference: String)
@Serializable data object ClockingRoute
@Serializable data object ClockScanQrRoute
@Serializable data object TimesheetsRoute
@Serializable data class TimesheetDayRoute(val date: String)
@Serializable data object LeaveRoute
@Serializable data object ClockingAdjustmentsRoute
@Serializable data object OvertimeRoute
@Serializable data object ClockingCalendarRoute
@Serializable data class ClockingErrorRoute(val kind: String, val message: String? = null, val scanned: String? = null)
