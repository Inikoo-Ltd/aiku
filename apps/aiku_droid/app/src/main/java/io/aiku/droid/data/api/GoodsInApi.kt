package io.aiku.droid.data.api

import kotlinx.serialization.KSerializer
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.descriptors.PrimitiveKind
import kotlinx.serialization.descriptors.PrimitiveSerialDescriptor
import kotlinx.serialization.descriptors.SerialDescriptor
import kotlinx.serialization.encoding.Decoder
import kotlinx.serialization.encoding.Encoder
import kotlinx.serialization.json.JsonElement
import java.time.LocalDate

object LenientIntOrNull : KSerializer<Int?> {
    override val descriptor: SerialDescriptor = PrimitiveSerialDescriptor("io.aiku.droid.LenientIntOrNull", PrimitiveKind.INT)

    override fun deserialize(decoder: Decoder): Int? = LenientDoubleOrNull.deserialize(decoder)?.toInt()

    override fun serialize(encoder: Encoder, value: Int?) = LenientDoubleOrNull.serialize(encoder, value?.toDouble())
}

object StockDeliveryState {
    const val IN_PROCESS = "in_process"
    const val CONFIRMED = "confirmed"
    const val READY_TO_SHIP = "ready_to_ship"
    const val DISPATCHED = "dispatched"
    const val RECEIVED = "received"
    const val CHECKED = "checked"
    const val BOOKING_IN = "booking_in"
    const val BOOKED_IN = "booked_in"
    const val PLACED = "placed"
    const val CANCELLED = "cancelled"
    const val NOT_RECEIVED = "not_received"

    val beforeReceiving = setOf(IN_PROCESS, CONFIRMED, READY_TO_SHIP, DISPATCHED)
    val inGoodsIn = setOf(RECEIVED, CHECKED, BOOKING_IN, BOOKED_IN)
    val placing = setOf(RECEIVED, CHECKED, BOOKING_IN)
}

object GoodsInActionKey {
    const val RECEIVE = "receive"
    const val UNRECEIVE = "unreceive"
    const val CANCEL = "cancel"
    const val ADD_ITEM = "add_item"
    const val CHECK = "check"
    const val PLACE = "place"
    const val CHECKLIST = "checklist"
    const val START = "start"
    const val PUT_BACK = "put_back"
    const val UNDO = "undo"
    const val SET_AS_RETURNED = "set_as_returned"
}

object GoodsInHubKey {
    const val STOCK_DELIVERIES = "stock_deliveries"
    const val PARTNERS = "partners"
    const val AGENTS = "agents"
    const val FULFILMENT = "fulfilment"
    const val RETURNS = "returns"
}

@Serializable
data class ChecklistChecker(val id: Long? = null, val name: String = "")

@Serializable
data class GoodsInHubRow(
    val key: String = "",
    val label: String = "",
    @Serializable(with = LenientIntOrNull::class) val arriving: Int? = null,
    @Serializable(with = LenientIntOrNull::class) val received: Int? = null,
    @Serializable(with = LenientIntOrNull::class) val checked: Int? = null,
    @Serializable(with = LenientIntOrNull::class) @SerialName("booking_in") val bookingIn: Int? = null,
    @Serializable(with = LenientIntOrNull::class) @SerialName("booked_in_7d") val bookedInWeek: Int? = null,
    @Serializable(with = LenientIntOrNull::class) @SerialName("placed_7d") val placedWeek: Int? = null,
)

enum class StockDeliveryStateFilter(val key: String) {
    Active("active"),
    Dispatched(StockDeliveryState.DISPATCHED),
    Received(StockDeliveryState.RECEIVED),
    Checked(StockDeliveryState.CHECKED),
    BookingIn(StockDeliveryState.BOOKING_IN),
    BookedIn(StockDeliveryState.BOOKED_IN),
    Placed(StockDeliveryState.PLACED),
    NotReceived(StockDeliveryState.NOT_RECEIVED),
    Cancelled(StockDeliveryState.CANCELLED),
    ;

    companion object {
        fun ofKey(key: String?): StockDeliveryStateFilter? = entries.firstOrNull { it.key == key }
    }
}

enum class StockDeliverySource(val key: String) {
    Suppliers("suppliers"),
    Agents("agents"),
    Partners("partners"),
    ;

    companion object {
        fun ofKey(key: String?): StockDeliverySource? = entries.firstOrNull { it.key == key }
    }
}

enum class DispatchedBucket(val key: String) {
    Today("today"),
    Week("week"),
    Older("older"),
}

enum class StockDeliverySort(val key: String) {
    Newest("-date"),
    Oldest("date"),
    Reference("reference"),
    Supplier("supplier"),
    ExpectedDate("estimated_receiving_date"),
    DispatchedAt("-dispatched_at"),
    Items("-number_items"),
}

data class StockDeliveryQuery(
    val state: StockDeliveryStateFilter = StockDeliveryStateFilter.Active,
    val source: StockDeliverySource? = null,
    val search: String? = null,
    val dispatched: DispatchedBucket? = null,
    val from: LocalDate? = null,
    val to: LocalDate? = null,
    val sort: StockDeliverySort? = null,
    val page: Int = 1,
    val perPage: Int = DEFAULT_PER_PAGE,
) {
    companion object {
        const val DEFAULT_PER_PAGE = 50
    }
}

@Serializable
data class SupplierRef(val type: String = "", val code: String = "", val name: String = "") {
    val label: String get() = name.ifBlank { code }
}

@Serializable
data class StockDeliveryRow(
    val id: Long,
    val reference: String = "",
    @SerialName("agent_reference") val agentReference: String? = null,
    val supplier: SupplierRef? = null,
    val source: String? = null,
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    @SerialName("dispatched_at") val dispatchedAt: String? = null,
    @SerialName("received_at") val receivedAt: String? = null,
    @SerialName("estimated_receiving_date") val estimatedReceivingDate: String? = null,
    @Serializable(with = LenientInt::class) @SerialName("number_items") val numberItems: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("number_checked") val numberChecked: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("number_placed") val numberPlaced: Int = 0,
)

data class StateCounts(val values: Map<String, Int> = emptyMap()) {
    fun count(key: String): Int? = values[key]
}

data class StockDeliveryPage(val rows: List<StockDeliveryRow>, val meta: ListMeta, val counts: StateCounts? = null)

@Serializable
data class DeliveryInfo(
    val type: JsonElement? = null,
    @SerialName("type_label") val typeLabel: JsonElement? = null,
    @SerialName("container_size") val containerSize: JsonElement? = null,
    val incoterm: JsonElement? = null,
    @SerialName("port_of_export") val portOfExport: JsonElement? = null,
    @SerialName("port_of_import") val portOfImport: JsonElement? = null,
    @SerialName("shipper_reference") val shipperReference: JsonElement? = null,
    @SerialName("tracking_number") val trackingNumber: JsonElement? = null,
    @SerialName("container_number") val containerNumber: JsonElement? = null,
    @SerialName("shipping_agent") val shippingAgent: JsonElement? = null,
) {
    val typeText: String? get() = typeLabel.displayText() ?: type.displayText()

    val containerSizeText: String? get() = containerSize.displayText()

    val incotermText: String? get() = incoterm.displayText()

    val portOfExportText: String? get() = portOfExport.displayText()

    val portOfImportText: String? get() = portOfImport.displayText()

    val shipperReferenceText: String? get() = shipperReference.displayText()

    val trackingNumberText: String? get() = trackingNumber.displayText()

    val containerNumberText: String? get() = containerNumber.displayText()

    val shippingAgentText: String? get() = shippingAgent.displayText()
}

@Serializable
data class PurchaseOrderRef(val id: Long, val reference: String = "")

@Serializable
data class StockDeliverySummary(
    @Serializable(with = LenientInt::class) @SerialName("number_items") val numberItems: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("number_checked") val numberChecked: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("number_placed") val numberPlaced: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("number_not_received") val numberNotReceived: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("number_new_org_stocks") val numberNewOrgStocks: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("under_delivered") val underDelivered: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("over_delivered") val overDelivered: Int = 0,
)

@Serializable
data class GoodsInSko(
    val id: Long,
    val code: String = "",
    val name: String = "",
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("packed_in") val packedIn: Double? = null,
    val barcode: String? = null,
    @SerialName("image_url") val imageUrl: String? = null,
)

@Serializable
data class SupplierProductRef(val code: String = "", val name: String = "")

@Serializable
data class ItemLocation(
    @SerialName("location_org_stock_id") val locationOrgStockId: Long,
    val location: PlaceRef,
    val type: String = "",
    @Serializable(with = LenientDouble::class) val quantity: Double = 0.0,
    @SerialName("quantity_label") val quantityLabel: String? = null,
    @SerialName("is_default_wholesale") val isDefaultWholesale: Boolean = false,
    @SerialName("is_default_dropshipping") val isDefaultDropshipping: Boolean = false,
    @Serializable(with = LenientIntOrNull::class) val version: Int? = null,
) {
    val stockType: LocationStockType? get() = LocationStockType.ofKey(type)
}

@Serializable
data class ItemPlacement(
    val id: Long,
    @Serializable(with = LenientDouble::class) val quantity: Double = 0.0,
    @SerialName("quantity_label") val quantityLabel: String? = null,
    val location: PlaceRef? = null,
)

@Serializable
data class StockDeliveryItem(
    val id: Long,
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    @SerialName("supplier_product") val supplierProduct: SupplierProductRef? = null,
    @SerialName("purchase_order_reference") val purchaseOrderReference: String? = null,
    @SerialName("is_new_org_stock") val isNewOrgStock: Boolean = false,
    val sko: GoodsInSko? = null,
    @Serializable(with = LenientDouble::class) @SerialName("sko_expected") val skoExpected: Double = 0.0,
    @SerialName("sko_expected_label") val skoExpectedLabel: String? = null,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("sko_checked") val skoChecked: Double? = null,
    @SerialName("sko_checked_label") val skoCheckedLabel: String? = null,
    @Serializable(with = LenientDouble::class) @SerialName("sko_placed") val skoPlaced: Double = 0.0,
    @SerialName("sko_placed_label") val skoPlacedLabel: String? = null,
    @Serializable(with = LenientDouble::class) @SerialName("sko_to_place") val skoToPlace: Double = 0.0,
    @SerialName("sko_to_place_label") val skoToPlaceLabel: String? = null,
    @SerialName("has_picking_location") val hasPickingLocation: Boolean = false,
    val locations: List<ItemLocation> = emptyList(),
    val placements: List<ItemPlacement> = emptyList(),
) {
    val code: String get() = sko?.code?.takeIf { it.isNotBlank() } ?: supplierProduct?.code.orEmpty()

    val name: String get() = sko?.name?.takeIf { it.isNotBlank() } ?: supplierProduct?.name.orEmpty()

    val isDone: Boolean get() = state == StockDeliveryState.PLACED || state == StockDeliveryState.CANCELLED

    val isNotReceived: Boolean get() = state == StockDeliveryState.NOT_RECEIVED || skoChecked == 0.0

    val needsCheck: Boolean get() = !isDone && skoChecked == null

    val canBePlaced: Boolean get() = state != StockDeliveryState.CANCELLED && skoToPlace > 0.0

    val hasBeenChecked: Boolean get() = (skoChecked ?: 0.0) > 0.0
}

@Serializable
data class ChecklistCheck(
    val key: String,
    val label: String = "",
    val detail: String? = null,
    val severity: String? = null,
    val result: String? = null,
    @SerialName("checked_by") val checkedBy: ChecklistChecker? = null,
    @SerialName("checked_at") val checkedAt: String? = null,
)

@Serializable
data class ChecklistSection(
    val key: String = "",
    val title: String = "",
    val subtitle: String? = null,
    val checks: List<ChecklistCheck> = emptyList(),
)

@Serializable
data class IncidentType(val value: String, val label: String = "")

@Serializable
data class GoodsInChecklist(
    val sections: List<ChecklistSection> = emptyList(),
    @Serializable(with = LenientInt::class) val total: Int = 0,
    @Serializable(with = LenientInt::class) val checked: Int = 0,
    @Serializable(with = LenientInt::class) val failed: Int = 0,
    @SerialName("incident_types") val incidentTypes: List<IncidentType> = emptyList(),
) {
    fun check(key: String): ChecklistCheck? = sections.firstNotNullOfOrNull { section -> section.checks.firstOrNull { it.key == key } }
}

enum class CheckResult(val key: String) {
    Passed("passed"),
    Failed("failed"),
    ;

    companion object {
        fun ofKey(key: String?): CheckResult? = entries.firstOrNull { it.key == key }
    }
}

@Serializable
data class StockDelivery(
    val id: Long,
    val reference: String = "",
    @SerialName("agent_reference") val agentReference: String? = null,
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    val supplier: SupplierRef? = null,
    val source: String? = null,
    @SerialName("dispatched_at") val dispatchedAt: String? = null,
    @SerialName("received_at") val receivedAt: String? = null,
    @SerialName("estimated_receiving_date") val estimatedReceivingDate: String? = null,
    val delivery: DeliveryInfo? = null,
    @SerialName("purchase_orders") val purchaseOrders: List<PurchaseOrderRef> = emptyList(),
    val summary: StockDeliverySummary = StockDeliverySummary(),
    val items: List<StockDeliveryItem> = emptyList(),
    val checklist: GoodsInChecklist? = null,
    @SerialName("available_actions") val availableActions: List<DeliveryNoteAction> = emptyList(),
) {
    fun action(key: String): DeliveryNoteAction? = availableActions.firstOrNull { it.key == key }

    fun can(key: String): Boolean = action(key)?.isDisabled == false

    fun item(id: Long): StockDeliveryItem? = items.firstOrNull { it.id == id }
}

@Serializable
data class ScannedStockDelivery(
    val id: Long,
    val reference: String = "",
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
)

sealed interface PlaceTarget {
    val code: String

    data class Link(val locationOrgStockId: Long, override val code: String) : PlaceTarget
    data class Location(val locationId: Long, override val code: String) : PlaceTarget
}

@Serializable
data class AddableSko(
    val id: Long,
    val code: String = "",
    val name: String? = null,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("packed_in") val packedIn: Double? = null,
)

@Serializable
data class AddableItem(
    val id: Long,
    val field: String = "",
    val code: String? = null,
    val name: String? = null,
    val sko: AddableSko? = null,
)

sealed interface StockDeliveryWrite {
    data object Receive : StockDeliveryWrite
    data object Unreceive : StockDeliveryWrite
    data object Cancel : StockDeliveryWrite
    data class AddItem(val itemId: Long, val skoQuantity: Double) : StockDeliveryWrite
    data object CheckAll : StockDeliveryWrite
    data class Check(val itemId: Long, val skoQuantity: Double) : StockDeliveryWrite
    data class Place(
        val itemId: Long,
        val quantity: Double,
        val target: PlaceTarget,
        val setAsPickingLocation: Boolean,
        val clientRequestId: String,
    ) : StockDeliveryWrite
    data class PlaceAll(val itemId: Long, val target: PlaceTarget, val setAsPickingLocation: Boolean, val clientRequestId: String) : StockDeliveryWrite
    data class RecordCheck(
        val key: String,
        val result: CheckResult,
        val body: String?,
        val incidentType: String?,
        val incidentQuantity: Double?,
    ) : StockDeliveryWrite
}

object GoodsInWriteStatus {
    const val DONE = "done"
    const val NOTHING_CHANGED = "nothing_changed"
}

object GoodsInErrorKey {
    const val QUANTITY = "quantity"
    const val SKO_QUANTITY = "sko_quantity"
    const val LOCATION_ORG_STOCK_ID = "location_org_stock_id"
    const val LOCATION_ID = "location_id"
    const val BODY = "body"
    const val STATE = "state"
}

data class StockDeliveryOutcome(val delivery: StockDelivery, val status: String, val message: String?)

enum class ReturnStateFilter(val key: String) {
    All("all"),
    ToProcess("to_process"),
    Received("received"),
    Returning("returning"),
    Returned("returned"),
    Done("done"),
    Cancelled("cancelled"),
    ;

    companion object {
        fun ofKey(key: String?): ReturnStateFilter? = entries.firstOrNull { it.key == key }
    }
}

object ReturnState {
    const val RECEIVED = "received"
    const val RETURNING = "returning"
    const val RETURNED = "returned"
    const val DONE = "done"
    const val CANCELLED = "cancelled"
}

object ReturnType {
    const val RETURN = "return"
    const val CANCELLATION = "cancellation"
}

object PutType {
    const val SOW = "sow"
    const val NOT_SOW = "not-sow"
    const val DAMAGED = "damaged"
}

enum class ReturnSort(val key: String) {
    OldestReceived("created_at"),
    Reference("reference"),
    Customer("customer"),
}

data class ReturnQuery(
    val state: ReturnStateFilter = ReturnStateFilter.All,
    val search: String? = null,
    val from: LocalDate? = null,
    val to: LocalDate? = null,
    val sort: ReturnSort? = null,
    val page: Int = 1,
    val perPage: Int = DEFAULT_PER_PAGE,
) {
    companion object {
        const val DEFAULT_PER_PAGE = 50
    }
}

@Serializable
data class CustomerRef(val name: String = "")

@Serializable
data class CustomerReturnRow(
    val id: Long,
    val reference: String = "",
    val type: String = "",
    @SerialName("type_label") val typeLabel: String = "",
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    @SerialName("created_at") val createdAt: String? = null,
    val customer: CustomerRef? = null,
    @SerialName("delivery_note") val deliveryNote: NoteReference? = null,
    @Serializable(with = LenientInt::class) @SerialName("number_items") val numberItems: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("number_handled") val numberHandled: Int = 0,
    val handler: StaffRef? = null,
)

data class ReturnPage(val rows: List<CustomerReturnRow>, val meta: ListMeta, val counts: StateCounts? = null)

@Serializable
data class ReturnPut(
    val id: Long,
    val type: String = "",
    @SerialName("type_label") val typeLabel: String = "",
    @Serializable(with = LenientDouble::class) val quantity: Double = 0.0,
    @SerialName("quantity_label") val quantityLabel: String? = null,
    val location: PlaceRef? = null,
    val by: StaffRef? = null,
)

@Serializable
data class CustomerReturnItem(
    val id: Long,
    val state: String = "",
    @SerialName("is_handled") val isHandled: Boolean = false,
    val sko: GoodsInSko? = null,
    @Serializable(with = LenientDouble::class) val expected: Double = 0.0,
    @SerialName("expected_label") val expectedLabel: String? = null,
    @Serializable(with = LenientDouble::class) val returned: Double = 0.0,
    @SerialName("returned_label") val returnedLabel: String? = null,
    @Serializable(with = LenientDouble::class) val damaged: Double = 0.0,
    @SerialName("damaged_label") val damagedLabel: String? = null,
    @Serializable(with = LenientDouble::class) @SerialName("not_returned") val notReturned: Double = 0.0,
    @SerialName("not_returned_label") val notReturnedLabel: String? = null,
    @Serializable(with = LenientDouble::class) val remaining: Double = 0.0,
    @SerialName("remaining_label") val remainingLabel: String? = null,
    val puts: List<ReturnPut> = emptyList(),
    val locations: List<ItemLocation> = emptyList(),
    val barcodes: List<String> = emptyList(),
) {
    val code: String get() = sko?.code.orEmpty()

    val hasRemaining: Boolean get() = remaining > 0.0
}

@Serializable
data class CustomerReturn(
    val id: Long,
    val reference: String = "",
    val type: String = "",
    @SerialName("type_label") val typeLabel: String = "",
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    val customer: CustomerRef? = null,
    val order: DeliveryNoteOrder? = null,
    @SerialName("delivery_note") val deliveryNote: NoteReference? = null,
    val handler: StaffRef? = null,
    val timeline: List<DeliveryNoteTimelineEntry> = emptyList(),
    val items: List<CustomerReturnItem> = emptyList(),
    @SerialName("available_actions") val availableActions: List<DeliveryNoteAction> = emptyList(),
) {
    fun action(key: String): DeliveryNoteAction? = availableActions.firstOrNull { it.key == key }

    fun item(id: Long): CustomerReturnItem? = items.firstOrNull { it.id == id }
}

sealed interface ReturnWrite {
    data object Start : ReturnWrite
    data class Returned(val itemId: Long, val quantity: Double?, val locationOrgStockId: Long, val clientRequestId: String? = null) : ReturnWrite
    data class Damaged(val itemId: Long, val quantity: Double?, val clientRequestId: String? = null) : ReturnWrite
    data class NotReturned(val itemId: Long, val quantity: Double?, val clientRequestId: String? = null) : ReturnWrite
    data class UndoPut(val putId: Long) : ReturnWrite
    data object SetAsReturned : ReturnWrite
}

data class ReturnOutcome(val customerReturn: CustomerReturn, val status: String, val message: String?)

interface GoodsInApi {
    suspend fun goodsInHub(token: String, warehouseId: Long): GoodsOutResult<List<GoodsInHubRow>>

    suspend fun stockDeliveries(token: String, warehouseId: Long, query: StockDeliveryQuery): GoodsOutResult<StockDeliveryPage>

    suspend fun stockDelivery(token: String, warehouseId: Long, stockDeliveryId: Long): GoodsOutResult<StockDelivery>

    suspend fun writeStockDelivery(token: String, warehouseId: Long, stockDeliveryId: Long, write: StockDeliveryWrite): GoodsOutResult<StockDeliveryOutcome>

    suspend fun addableItems(token: String, warehouseId: Long, stockDeliveryId: Long, search: String?, orgStockId: Long?): GoodsOutResult<List<AddableItem>>

    suspend fun returns(token: String, warehouseId: Long, query: ReturnQuery): GoodsOutResult<ReturnPage>

    suspend fun customerReturn(token: String, warehouseId: Long, returnId: Long): GoodsOutResult<CustomerReturn>

    suspend fun writeReturn(token: String, warehouseId: Long, returnId: Long, write: ReturnWrite): GoodsOutResult<ReturnOutcome>

    suspend fun scan(token: String, warehouseId: Long, code: String): GoodsOutResult<ScanLookup>
}
