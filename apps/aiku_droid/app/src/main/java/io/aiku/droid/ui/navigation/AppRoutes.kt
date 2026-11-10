package io.aiku.droid.ui.navigation

import kotlinx.serialization.Serializable

@Serializable data object HomeRoute
@Serializable data object MyWorkRoute
@Serializable data object ScanResultRoute
@Serializable data object InboxRoute
@Serializable data object MenuRoute
@Serializable data object SearchRoute
@Serializable data object WarehouseDashboardRoute
@Serializable data object ProfileRoute
@Serializable data object AppearanceRoute
@Serializable data object LanguageRoute
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
@Serializable data object DeliveryNotesRoute
@Serializable data class DeliveryNoteToDoRoute(val reference: String)
@Serializable data class DeliveryNotePickingRoute(val reference: String)
@Serializable data class ScanToPickRoute(val reference: String)
@Serializable data class FastPickingRoute(val reference: String)
@Serializable data class DeliveryNoteAllPickedRoute(val reference: String)
@Serializable data class DeliveryNotePickedRoute(val reference: String)
@Serializable data class DeliveryNotePackingRoute(val reference: String)
@Serializable data object PackingQueueRoute
@Serializable data class AddShipmentRoute(val reference: String)
@Serializable data class ManualShipmentRoute(val reference: String)
@Serializable data class ShipmentsRoute(val reference: String)
@Serializable data class DeliveryNoteCollectionRoute(val reference: String)
@Serializable data class DeliveryNoteCollectedRoute(val reference: String)
@Serializable data class DeliveryNoteFinalisedRoute(val reference: String)
@Serializable data class DeliveryNoteDispatchedRoute(val reference: String)
@Serializable data class DeliveryNoteWaitingRoute(val reference: String)
@Serializable data class DeliveryNoteCancelledRoute(val reference: String)
@Serializable data class DeliveryNoteIncompleteSetRoute(val reference: String)
@Serializable data class DeliveryNoteTakeOverRoute(val reference: String)
@Serializable data class AskCrmRoute(val reference: String)
@Serializable data class ExternalShipmentRoute(val reference: String)
@Serializable data class TariffCodesRoute(val reference: String)
@Serializable data class CustomsFileRoute(val reference: String)
@Serializable data class DispatchedEmailsRoute(val reference: String)
@Serializable data object ShippersRoute
@Serializable data object PickingSessionsRoute
@Serializable data class PickingSessionRoute(val reference: String)
@Serializable data class PickByLocationRoute(val reference: String)
@Serializable data class PackByScanRoute(val reference: String)
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
@Serializable data class ClockingErrorRoute(val kind: String)
