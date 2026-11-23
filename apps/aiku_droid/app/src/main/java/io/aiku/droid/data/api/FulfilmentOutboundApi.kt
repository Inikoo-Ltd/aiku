package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import java.io.File

object PalletReturnState {
    const val CONFIRMED = "confirmed"
    const val PICKING = "picking"
    const val PICKED = "picked"
    const val DISPATCHED = "dispatched"
    const val CANCEL = "cancel"
}

object PalletReturnTypeGroup {
    const val PALLETS = "pallets"
    const val ITEMS = "items"
}

object PalletReturnActionKey {
    const val START_PICKING = "start_picking"
    const val FINISH_PICKING = "finish_picking"
    const val DISPATCH = "dispatch"
    const val ADD_SHIPMENT = "add_shipment"
    const val PRINT_INSERTS = "print_inserts"
    const val PICK = "pick"
    const val NOT_PICKED = "not_picked"
    const val UNDO = "undo"
}

object PalletReturnScanStatus {
    const val PICKED = "picked"
    const val NOT_FOUND = "not_found"
    const val ALREADY_PICKED = "already_picked"
    const val NO_STOCK = "no_stock"
    const val WRONG_STATE = "wrong_state"
}

object FulfilmentOutboundErrorKey {
    const val STATE = "state"
    const val QUANTITY = "quantity"
    const val SHIPPER_ID = "shipper_id"
    const val TRACKING = "tracking"
    const val PARCELS = "parcels"
    const val STORED_ITEM_ID = "stored_item_id"
    const val BARCODE = "barcode"
    const val AUDITED_QUANTITY = "audited_quantity"
    const val ADD = "add"
}

object OutboundWriteStatus {
    const val DONE = "done"
    const val NOTHING_CHANGED = "nothing_changed"
}

enum class PalletReturnStateFilter(val key: String) {
    Active("active"),
    Confirmed(PalletReturnState.CONFIRMED),
    Picking(PalletReturnState.PICKING),
    Picked(PalletReturnState.PICKED),
    Dispatched(PalletReturnState.DISPATCHED),
    Cancelled(PalletReturnState.CANCEL),
}

enum class PalletReturnTypeFilter(val key: String) {
    Pallets(PalletReturnTypeGroup.PALLETS),
    Items(PalletReturnTypeGroup.ITEMS),
}

enum class PalletReturnSort(val key: String) {
    Oldest("date"),
    Reference("reference"),
    Customer("customer"),
    Dispatched("-dispatched_at"),
}

data class PalletReturnQuery(
    val state: PalletReturnStateFilter = PalletReturnStateFilter.Active,
    val type: PalletReturnTypeFilter? = null,
    val search: String? = null,
    val sort: PalletReturnSort? = null,
    val page: Int = 1,
    val perPage: Int = DEFAULT_PER_PAGE,
) {
    companion object {
        const val DEFAULT_PER_PAGE = 50
    }
}

@Serializable
data class OutboundNamedRef(val id: Long? = null, val name: String = "")

@Serializable
data class ReturnPlatform(val code: String = "", val name: String = "") {
    val label: String get() = name.ifBlank { code }
}

@Serializable
data class PalletReturnRow(
    val id: Long,
    val reference: String = "",
    @SerialName("customer_reference") val customerReference: String? = null,
    val type: String = "",
    @SerialName("type_group") val typeGroup: String = PalletReturnTypeGroup.PALLETS,
    @SerialName("type_label") val typeLabel: String = "",
    val platform: ReturnPlatform? = null,
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    val customer: OutboundNamedRef? = null,
    @Serializable(with = LenientInt::class) @SerialName("number_pallets") val numberPallets: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("number_items") val numberItems: Int = 0,
    val date: String? = null,
    val picker: OutboundNamedRef? = null,
    @SerialName("is_collection") val isCollection: Boolean = false,
)

data class PalletReturnCounts(val states: StateCounts = StateCounts(), val types: StateCounts = StateCounts())

data class PalletReturnPage(val rows: List<PalletReturnRow>, val meta: ListMeta, val counts: PalletReturnCounts? = null)

@Serializable
data class OutboundPalletRef(
    val id: Long,
    val reference: String = "",
    @SerialName("customer_reference") val customerReference: String? = null,
    val type: String? = null,
    @SerialName("type_label") val typeLabel: String? = null,
    val state: String? = null,
    @SerialName("state_label") val stateLabel: String? = null,
)

@Serializable
data class OutboundStoredItemRef(
    val id: Long,
    val reference: String = "",
    val name: String = "",
    val barcode: String? = null,
)

@Serializable
data class ReturnPalletContent(
    val reference: String = "",
    val name: String = "",
    @Serializable(with = LenientInt::class) val quantity: Int = 0,
)

@Serializable
data class ReturnPalletLine(
    @SerialName("line_id") val lineId: Long,
    @SerialName("line_state") val lineState: String = "",
    @SerialName("line_state_label") val lineStateLabel: String = "",
    @SerialName("waiting_customer_service") val waitingCustomerService: Boolean = false,
    val pallet: OutboundPalletRef,
    val location: PlaceRef? = null,
    @SerialName("stored_items") val storedItems: List<ReturnPalletContent> = emptyList(),
    @SerialName("available_actions") val availableActions: List<DeliveryNoteAction> = emptyList(),
) {
    fun can(key: String): Boolean = availableActions.any { it.key == key && !it.isDisabled }

    val hasActions: Boolean get() = availableActions.any { !it.isDisabled }
}

@Serializable
data class ReturnItemLine(
    @SerialName("line_id") val lineId: Long,
    val pallet: OutboundPalletRef? = null,
    val location: PlaceRef? = null,
    @Serializable(with = LenientInt::class) @SerialName("quantity_ordered") val quantityOrdered: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("quantity_picked") val quantityPicked: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("quantity_not_picked") val quantityNotPicked: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("quantity_to_pick") val quantityToPick: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("quantity_on_pallet") val quantityOnPallet: Int = 0,
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    @SerialName("waiting_customer_service") val waitingCustomerService: Boolean = false,
    @SerialName("available_actions") val availableActions: List<DeliveryNoteAction> = emptyList(),
) {
    fun can(key: String): Boolean = availableActions.any { it.key == key && !it.isDisabled }

    val hasActions: Boolean get() = availableActions.any { !it.isDisabled }

    val maxPick: Int get() = minOf(quantityToPick, quantityOnPallet).coerceAtLeast(0)

    val shortfall: Int get() = (quantityToPick - quantityOnPallet).coerceAtLeast(0)

    val isFullyPicked: Boolean get() = quantityOrdered > 0 && quantityPicked + quantityNotPicked >= quantityOrdered
}

@Serializable
data class ReturnOtherPallet(
    @SerialName("pallet_stored_item_id") val palletStoredItemId: Long,
    val pallet: OutboundPalletRef? = null,
    val location: PlaceRef? = null,
    @Serializable(with = LenientInt::class) @SerialName("quantity_available") val quantityAvailable: Int = 0,
)

@Serializable
data class ReturnStoredItem(
    @SerialName("stored_item") val storedItem: OutboundStoredItemRef,
    @Serializable(with = LenientInt::class) @SerialName("quantity_ordered") val quantityOrdered: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("quantity_picked") val quantityPicked: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("quantity_not_picked") val quantityNotPicked: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("quantity_to_pick") val quantityToPick: Int = 0,
    val lines: List<ReturnItemLine> = emptyList(),
    @SerialName("other_pallets") val otherPallets: List<ReturnOtherPallet> = emptyList(),
) {
    val shortLine: ReturnItemLine? get() = lines.firstOrNull { it.quantityToPick > 0 && it.shortfall > 0 }

    val canPickFromOtherPallet: Boolean
        get() = quantityToPick > 0 && shortLine != null && otherPallets.any { it.quantityAvailable > 0 } && lines.any { it.hasActions }
}

@Serializable
data class ReturnInserts(
    @Serializable(with = LenientInt::class) val total: Int = 0,
    @Serializable(with = LenientInt::class) val unprinted: Int = 0,
)

@Serializable
data class ReturnParcel(
    @Serializable(with = LenientDoubleOrNull::class) val weight: Double? = null,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("weight_kg") val weightKg: Double? = null,
    val dimensions: List<Double?> = emptyList(),
    @SerialName("dimensions_cm") val dimensionsCm: List<Double?> = emptyList(),
) {
    fun asParcel(): DeliveryNoteParcel = DeliveryNoteParcel(weightKg = weight ?: weightKg, dimensionsCm = dimensions.ifEmpty { dimensionsCm })
}

@Serializable
data class ReturnPickCounts(
    @Serializable(with = LenientInt::class) val lines: Int = 0,
    @Serializable(with = LenientInt::class) val picked: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("not_picked") val notPicked: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("to_pick") val toPick: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("waiting_customer_service") val waitingCustomerService: Int = 0,
)

@Serializable
data class PalletReturn(
    val id: Long,
    val reference: String = "",
    @SerialName("customer_reference") val customerReference: String? = null,
    val type: String = "",
    @SerialName("type_group") val typeGroup: String = PalletReturnTypeGroup.PALLETS,
    @SerialName("type_label") val typeLabel: String = "",
    val platform: ReturnPlatform? = null,
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    val customer: OutboundNamedRef? = null,
    @SerialName("is_collection") val isCollection: Boolean = false,
    @SerialName("collection_notes") val collectionNotes: String? = null,
    @SerialName("customer_notes") val customerNotes: String? = null,
    @SerialName("public_notes") val publicNotes: String? = null,
    @SerialName("internal_notes") val internalNotes: String? = null,
    val picker: OutboundNamedRef? = null,
    val packer: OutboundNamedRef? = null,
    @SerialName("estimated_delivery_date") val estimatedDeliveryDate: String? = null,
    @SerialName("in_picking_session") val inPickingSession: Boolean = false,
    val timeline: List<DeliveryNoteTimelineEntry> = emptyList(),
    val counts: ReturnPickCounts = ReturnPickCounts(),
    val pallets: List<ReturnPalletLine> = emptyList(),
    @SerialName("stored_items") val storedItems: List<ReturnStoredItem> = emptyList(),
    val inserts: ReturnInserts = ReturnInserts(),
    val shipments: List<DeliveryNoteShipment> = emptyList(),
    val parcels: List<ReturnParcel> = emptyList(),
    @SerialName("shipment_required") val shipmentRequired: Boolean = false,
    @SerialName("dispatch_blocked") val dispatchBlocked: String? = null,
    @SerialName("can_scan") val canScan: Boolean = false,
    @SerialName("available_actions") val availableActions: List<DeliveryNoteAction> = emptyList(),
) {
    val isItems: Boolean get() = typeGroup == PalletReturnTypeGroup.ITEMS

    fun action(key: String): DeliveryNoteAction? = availableActions.firstOrNull { it.key == key && !it.isDisabled }

    fun can(key: String): Boolean = action(key) != null

    fun palletLine(lineId: Long): ReturnPalletLine? = pallets.firstOrNull { it.lineId == lineId }

    fun itemLine(lineId: Long): Pair<ReturnStoredItem, ReturnItemLine>? =
        storedItems.firstNotNullOfOrNull { item -> item.lines.firstOrNull { it.lineId == lineId }?.let { item to it } }

    fun storedItem(storedItemId: Long): ReturnStoredItem? = storedItems.firstOrNull { it.storedItem.id == storedItemId }

    val hasLineActions: Boolean get() = pallets.any { it.hasActions } || storedItems.any { item -> item.lines.any { it.hasActions } }

    fun timelineAt(state: String): String? = timeline.firstOrNull { it.state == state }?.at?.takeIf { it.isNotBlank() }
}

sealed interface PalletReturnWrite {
    data object StartPicking : PalletReturnWrite
    data object FinishPicking : PalletReturnWrite
    data object Dispatch : PalletReturnWrite
    data object PrintInserts : PalletReturnWrite
    data class PickPallet(val lineId: Long) : PalletReturnWrite
    data class PickItem(val lineId: Long, val quantity: Int?, val clientRequestId: String) : PalletReturnWrite
    data class NotPicked(val lineId: Long, val quantity: Int?) : PalletReturnWrite
    data class Undo(val lineId: Long) : PalletReturnWrite
    data class PickFromOtherPallet(val palletStoredItemId: Long, val quantity: Int, val fromLineId: Long?, val clientRequestId: String) : PalletReturnWrite
    data class SetParcels(val parcels: List<ParcelWrite>) : PalletReturnWrite
    data class AddShipment(val shipperId: Long, val tracking: String?) : PalletReturnWrite
    data class DeleteShipment(val shipmentId: Long) : PalletReturnWrite
    data class PrintLabel(val shipmentId: Long) : PalletReturnWrite
}

data class PalletReturnOutcome(val palletReturn: PalletReturn, val status: String, val message: String?)

data class PalletReturnScanRequest(val barcode: String, val quantity: Int, val clientRequestId: String)

data class PalletReturnScanOutcome(val palletReturn: PalletReturn, val status: String, val message: String?, val lineId: Long?)

enum class StoredItemStateFilter(val key: String) {
    Active("active"),
    Discontinuing("discontinuing"),
    Discontinued("discontinued"),
    All("all"),
}

data class StoredItemQuery(
    val state: StoredItemStateFilter = StoredItemStateFilter.Active,
    val search: String? = null,
    val page: Int = 1,
    val perPage: Int = DEFAULT_PER_PAGE,
) {
    companion object {
        const val DEFAULT_PER_PAGE = 50
    }
}

@Serializable
data class StoredItemRow(
    val id: Long,
    val reference: String = "",
    val name: String = "",
    val barcode: String? = null,
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    val customer: OutboundNamedRef? = null,
    @Serializable(with = LenientInt::class) val quantity: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("number_pallets") val numberPallets: Int = 0,
)

data class StoredItemPage(val rows: List<StoredItemRow>, val meta: ListMeta, val counts: StateCounts? = null)

@Serializable
data class StoredItemPalletRow(
    val pallet: OutboundPalletRef,
    val location: PlaceRef? = null,
    @Serializable(with = LenientInt::class) val quantity: Int = 0,
)

@Serializable
data class StoredItemDetail(
    val id: Long,
    val reference: String = "",
    val name: String = "",
    val barcode: String? = null,
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    val customer: OutboundNamedRef? = null,
    @Serializable(with = LenientInt::class) val quantity: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("number_pallets") val numberPallets: Int = 0,
    val pallets: List<StoredItemPalletRow> = emptyList(),
)

object StoredItemAuditActionKey {
    const val COUNT = "count"
    const val ADD_ITEM = "add_item"
    const val COMPLETE = "complete"
}

object StoredItemAuditType {
    const val NO_CHANGE = "no_change"
    const val ADDITION = "addition"
    const val SUBTRACTION = "subtraction"
    const val SET_UP = "set_up"
}

@Serializable
data class AuditPalletRef(
    val id: Long,
    val reference: String = "",
    @SerialName("customer_reference") val customerReference: String? = null,
    val location: PlaceRef? = null,
)

@Serializable
data class StoredItemAuditLine(
    @SerialName("stored_item") val storedItem: OutboundStoredItemRef,
    @Serializable(with = LenientInt::class) val quantity: Int = 0,
    @Serializable(with = LenientIntOrNull::class) @SerialName("audited_quantity") val auditedQuantity: Int? = null,
    @SerialName("delta_id") val deltaId: Long? = null,
    @SerialName("audit_type") val auditType: String? = null,
    @SerialName("audit_type_label") val auditTypeLabel: String? = null,
    @SerialName("is_new_item") val isNewItem: Boolean = false,
) {
    val isCounted: Boolean get() = auditedQuantity != null

    val difference: Int? get() = auditedQuantity?.let { it - quantity }
}

@Serializable
data class StoredItemAuditCounts(
    @Serializable(with = LenientInt::class) val lines: Int = 0,
    @Serializable(with = LenientInt::class) val counted: Int = 0,
    @Serializable(with = LenientInt::class) val differences: Int = 0,
)

@Serializable
data class StoredItemAudit(
    val id: Long,
    val reference: String = "",
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    val pallet: AuditPalletRef? = null,
    val customer: OutboundNamedRef? = null,
    val lines: List<StoredItemAuditLine> = emptyList(),
    val counts: StoredItemAuditCounts = StoredItemAuditCounts(),
    @SerialName("available_actions") val availableActions: List<DeliveryNoteAction> = emptyList(),
) {
    fun action(key: String): DeliveryNoteAction? = availableActions.firstOrNull { it.key == key && !it.isDisabled }

    fun can(key: String): Boolean = action(key) != null

    fun line(storedItemId: Long): StoredItemAuditLine? = lines.firstOrNull { it.storedItem.id == storedItemId }

    fun lineForCode(code: String): StoredItemAuditLine? {
        val needle = code.trim()
        return lines.firstOrNull { it.storedItem.reference.equals(needle, ignoreCase = true) }
            ?: lines.firstOrNull { it.storedItem.barcode?.equals(needle, ignoreCase = true) == true }
    }
}

sealed interface AuditItemTarget {
    data class Item(val storedItemId: Long) : AuditItemTarget
    data class Barcode(val code: String) : AuditItemTarget
}

sealed interface StoredItemAuditWrite {
    data class Count(val target: AuditItemTarget, val auditedQuantity: Int) : StoredItemAuditWrite
    data class Add(val target: AuditItemTarget, val add: Int, val clientRequestId: String) : StoredItemAuditWrite
    data class RemoveCount(val deltaId: Long) : StoredItemAuditWrite
    data object Complete : StoredItemAuditWrite
}

data class StoredItemAuditOutcome(val audit: StoredItemAudit, val status: String, val message: String?)

interface FulfilmentOutboundApi {
    suspend fun palletReturns(token: String, warehouseId: Long, query: PalletReturnQuery): GoodsOutResult<PalletReturnPage>

    suspend fun palletReturn(token: String, warehouseId: Long, returnId: Long): GoodsOutResult<PalletReturn>

    suspend fun writePalletReturn(token: String, warehouseId: Long, returnId: Long, write: PalletReturnWrite): GoodsOutResult<PalletReturnOutcome>

    suspend fun scanPalletReturn(token: String, warehouseId: Long, returnId: Long, request: PalletReturnScanRequest): GoodsOutResult<PalletReturnScanOutcome>

    suspend fun palletReturnShippers(token: String, warehouseId: Long, returnId: Long): GoodsOutResult<List<Shipper>>

    suspend fun downloadShipmentLabel(token: String, warehouseId: Long, returnId: Long, shipmentId: Long, target: File): AttachmentDownload

    suspend fun storedItems(token: String, warehouseId: Long, query: StoredItemQuery): GoodsOutResult<StoredItemPage>

    suspend fun storedItem(token: String, warehouseId: Long, storedItemId: Long): GoodsOutResult<StoredItemDetail>

    suspend fun storedItemAudit(token: String, warehouseId: Long, auditId: Long): GoodsOutResult<StoredItemAudit>

    suspend fun writeStoredItemAudit(token: String, warehouseId: Long, auditId: Long, write: StoredItemAuditWrite): GoodsOutResult<StoredItemAuditOutcome>

    suspend fun auditStoredItemChoices(token: String, warehouseId: Long, auditId: Long, search: String?): GoodsOutResult<List<OutboundStoredItemRef>>
}

class OkHttpFulfilmentOutboundApi(private val http: OkHttpMobileApi) : FulfilmentOutboundApi {

    private fun warehouseUrl(warehouseId: Long): String = "${http.mobileApiUrl}/warehouses/$warehouseId"

    private fun returnUrl(warehouseId: Long, returnId: Long): String = "${warehouseUrl(warehouseId)}/pallet-returns/$returnId"

    private fun auditUrl(warehouseId: Long, auditId: Long): String = "${warehouseUrl(warehouseId)}/stored-item-audits/$auditId"

    override suspend fun palletReturns(token: String, warehouseId: Long, query: PalletReturnQuery): GoodsOutResult<PalletReturnPage> =
        http.execute(http.get(http.urlWith("${warehouseUrl(warehouseId)}/pallet-returns", FulfilmentOutboundResponses.palletReturnsParameters(query)), token))
            ?.let { (statusCode, body) -> FulfilmentOutboundResponses.parsePalletReturns(statusCode, body) }
            ?: GoodsOutResult.Unreachable

    override suspend fun palletReturn(token: String, warehouseId: Long, returnId: Long): GoodsOutResult<PalletReturn> =
        http.execute(http.get(returnUrl(warehouseId, returnId), token))
            ?.let { (statusCode, body) -> FulfilmentOutboundResponses.parsePalletReturn(statusCode, body) }
            ?: GoodsOutResult.Unreachable

    override suspend fun writePalletReturn(token: String, warehouseId: Long, returnId: Long, write: PalletReturnWrite): GoodsOutResult<PalletReturnOutcome> =
        http.execute(http.writeRequest(returnUrl(warehouseId, returnId), FulfilmentOutboundResponses.palletReturnWriteRequest(write), token))
            ?.let { (statusCode, body) -> FulfilmentOutboundResponses.parsePalletReturnWrite(statusCode, body) }
            ?: GoodsOutResult.Unreachable

    override suspend fun scanPalletReturn(token: String, warehouseId: Long, returnId: Long, request: PalletReturnScanRequest): GoodsOutResult<PalletReturnScanOutcome> =
        http.execute(http.post("${returnUrl(warehouseId, returnId)}/scan", FulfilmentOutboundResponses.scanBody(request).toString(), bearerToken = token))
            ?.let { (statusCode, body) -> FulfilmentOutboundResponses.parseScan(statusCode, body) }
            ?: GoodsOutResult.Unreachable

    override suspend fun palletReturnShippers(token: String, warehouseId: Long, returnId: Long): GoodsOutResult<List<Shipper>> =
        http.execute(http.get("${returnUrl(warehouseId, returnId)}/shippers", token))
            ?.let { (statusCode, body) -> FulfilmentOutboundResponses.parseShippers(statusCode, body) }
            ?: GoodsOutResult.Unreachable

    override suspend fun downloadShipmentLabel(token: String, warehouseId: Long, returnId: Long, shipmentId: Long, target: File): AttachmentDownload =
        http.downloadTo(http.get("${returnUrl(warehouseId, returnId)}/shipments/$shipmentId/label", token), target)

    override suspend fun storedItems(token: String, warehouseId: Long, query: StoredItemQuery): GoodsOutResult<StoredItemPage> =
        http.execute(http.get(http.urlWith("${warehouseUrl(warehouseId)}/stored-items", FulfilmentOutboundResponses.storedItemsParameters(query)), token))
            ?.let { (statusCode, body) -> FulfilmentOutboundResponses.parseStoredItems(statusCode, body) }
            ?: GoodsOutResult.Unreachable

    override suspend fun storedItem(token: String, warehouseId: Long, storedItemId: Long): GoodsOutResult<StoredItemDetail> =
        http.execute(http.get("${warehouseUrl(warehouseId)}/stored-items/$storedItemId", token))
            ?.let { (statusCode, body) -> FulfilmentOutboundResponses.parseStoredItem(statusCode, body) }
            ?: GoodsOutResult.Unreachable

    override suspend fun storedItemAudit(token: String, warehouseId: Long, auditId: Long): GoodsOutResult<StoredItemAudit> =
        http.execute(http.get(auditUrl(warehouseId, auditId), token))
            ?.let { (statusCode, body) -> FulfilmentOutboundResponses.parseAudit(statusCode, body) }
            ?: GoodsOutResult.Unreachable

    override suspend fun writeStoredItemAudit(token: String, warehouseId: Long, auditId: Long, write: StoredItemAuditWrite): GoodsOutResult<StoredItemAuditOutcome> =
        http.execute(http.writeRequest(auditUrl(warehouseId, auditId), FulfilmentOutboundResponses.auditWriteRequest(write), token))
            ?.let { (statusCode, body) -> FulfilmentOutboundResponses.parseAuditWrite(statusCode, body) }
            ?: GoodsOutResult.Unreachable

    override suspend fun auditStoredItemChoices(token: String, warehouseId: Long, auditId: Long, search: String?): GoodsOutResult<List<OutboundStoredItemRef>> =
        http.execute(http.get(http.urlWith("${auditUrl(warehouseId, auditId)}/stored-items", FulfilmentOutboundResponses.searchParameters(search)), token))
            ?.let { (statusCode, body) -> FulfilmentOutboundResponses.parseAuditStoredItemChoices(statusCode, body) }
            ?: GoodsOutResult.Unreachable
}
