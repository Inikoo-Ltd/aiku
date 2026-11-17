package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

object PalletDeliveryState {
    const val CONFIRMED = "confirmed"
    const val RECEIVED = "received"
    const val BOOKING_IN = "booking_in"
    const val BOOKED_IN = "booked_in"
    const val NOT_RECEIVED = "not_received"
}

object PalletState {
    const val RECEIVED = "received"
    const val BOOKING_IN = "booking_in"
    const val BOOKED_IN = "booked_in"
    const val NOT_RECEIVED = "not_received"
    const val STORING = "storing"
}

object PalletStatus {
    const val RECEIVING = "receiving"
    const val STORING = "storing"
    const val RETURNING = "returning"
    const val RETURNED = "returned"
    const val INCIDENT = "incident"
    const val NOT_RECEIVED = "not_received"
}

object FulfilmentInboundActionKey {
    const val RECEIVE = "receive"
    const val START_BOOKING = "start_booking"
    const val FINISH_BOOKING = "finish_booking"
    const val BOOK_IN = "book_in"
    const val NOT_RECEIVED = "not_received"
    const val UNDO_NOT_RECEIVED = "undo_not_received"
    const val MOVE = "move"
    const val DAMAGED = "damaged"
    const val LOST = "lost"
    const val AUDIT = "audit"
}

object FulfilmentInboundErrorKey {
    const val LOCATION_ID = "location_id"
    const val LOCATION_CODE = "location_code"
    const val MESSAGE = "message"
}

enum class PalletDeliveryStateFilter(val key: String) {
    Active("active"),
    Confirmed(PalletDeliveryState.CONFIRMED),
    Received(PalletDeliveryState.RECEIVED),
    BookingIn(PalletDeliveryState.BOOKING_IN),
    BookedIn(PalletDeliveryState.BOOKED_IN),
    NotReceived(PalletDeliveryState.NOT_RECEIVED),
}

data class PalletDeliveryQuery(
    val state: PalletDeliveryStateFilter = PalletDeliveryStateFilter.Active,
    val search: String? = null,
    val page: Int = 1,
    val perPage: Int = DEFAULT_PER_PAGE,
) {
    companion object {
        const val DEFAULT_PER_PAGE = 50
    }
}

enum class PalletFilter(val key: String) {
    Storing("storing"),
    Receiving("receiving"),
    Returning("returning"),
    Incidents("incidents"),
    Returned("returned"),
}

enum class PalletIncidentState(val key: String) {
    Damaged("damaged"),
    Lost("lost"),
    OtherIncident("other_incident"),
}

data class PalletQuery(
    val filter: PalletFilter = PalletFilter.Storing,
    val incidentState: PalletIncidentState? = null,
    val search: String? = null,
    val page: Int = 1,
    val perPage: Int = DEFAULT_PER_PAGE,
) {
    companion object {
        const val DEFAULT_PER_PAGE = 50
    }
}

@Serializable
data class FulfilmentPerson(val id: Long? = null, val name: String = "")

@Serializable
data class PalletRecordRef(val id: Long, val reference: String = "")

@Serializable
data class PalletIncident(
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    val message: String? = null,
    val reporter: FulfilmentPerson? = null,
    val date: String? = null,
    @SerialName("return") val palletReturn: PalletRecordRef? = null,
)

@Serializable
data class PalletStoredItem(
    val id: Long? = null,
    val reference: String = "",
    val name: String = "",
    val barcode: String? = null,
    @Serializable(with = LenientInt::class) val quantity: Int = 0,
    val state: String? = null,
    @SerialName("state_label") val stateLabel: String? = null,
)

@Serializable
data class Pallet(
    val id: Long,
    val reference: String = "",
    @SerialName("customer_reference") val customerReference: String? = null,
    val type: String = "",
    @SerialName("type_label") val typeLabel: String = "",
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    val status: String = "",
    @SerialName("status_label") val statusLabel: String = "",
    val customer: FulfilmentPerson? = null,
    val location: PlaceRef? = null,
    val notes: String? = null,
    @Serializable(with = LenientInt::class) @SerialName("number_stored_items") val numberStoredItems: Int = 0,
    val incident: PalletIncident? = null,
    @SerialName("has_rental") val hasRental: Boolean? = null,
    @SerialName("stored_items") val storedItems: List<PalletStoredItem> = emptyList(),
    val delivery: PalletRecordRef? = null,
    @SerialName("return") val palletReturn: PalletRecordRef? = null,
    @SerialName("received_at") val receivedAt: String? = null,
    @SerialName("booked_in_at") val bookedInAt: String? = null,
    @SerialName("storing_at") val storingAt: String? = null,
    @SerialName("items_storage") val itemsStorage: Boolean = false,
    @SerialName("open_audit_id") val openAuditId: Long? = null,
    @SerialName("available_actions") val availableActions: List<DeliveryNoteAction> = emptyList(),
) {
    val locationCode: String? get() = location?.code?.takeIf { it.isNotBlank() }

    fun action(key: String): DeliveryNoteAction? = availableActions.firstOrNull { it.key == key }

    fun can(key: String): Boolean = action(key)?.isDisabled == false

    fun isLabelled(code: String): Boolean {
        val scanned = code.trim()
        return scanned.isNotEmpty() && (reference.equals(scanned, ignoreCase = true) || customerReference?.equals(scanned, ignoreCase = true) == true)
    }
}

@Serializable
data class PalletDeliveryCounts(
    @Serializable(with = LenientInt::class) val pallets: Int = 0,
    @Serializable(with = LenientInt::class) val boxes: Int = 0,
    @Serializable(with = LenientInt::class) val oversizes: Int = 0,
    @Serializable(with = LenientInt::class) val total: Int = 0,
    @Serializable(with = LenientInt::class) val received: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("booking_in") val bookingIn: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("booked_in") val bookedIn: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("not_received") val notReceived: Int = 0,
)

@Serializable
data class PalletDeliveryRow(
    val id: Long,
    val reference: String = "",
    @SerialName("customer_reference") val customerReference: String? = null,
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    val customer: FulfilmentPerson? = null,
    @SerialName("estimated_delivery_date") val estimatedDeliveryDate: String? = null,
    @SerialName("received_at") val receivedAt: String? = null,
    @SerialName("booked_in_at") val bookedInAt: String? = null,
    val counts: PalletDeliveryCounts = PalletDeliveryCounts(),
)

data class PalletDeliveryPage(val rows: List<PalletDeliveryRow>, val meta: ListMeta, val counts: StateCounts? = null)

@Serializable
data class DeliveryCharge(
    val code: String = "",
    val name: String = "",
    @Serializable(with = LenientDouble::class) val quantity: Double = 0.0,
    @SerialName("unit_label") val unitLabel: String? = null,
)

@Serializable
data class PalletDelivery(
    val id: Long,
    val reference: String = "",
    @SerialName("customer_reference") val customerReference: String? = null,
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    val customer: FulfilmentPerson? = null,
    @SerialName("estimated_delivery_date") val estimatedDeliveryDate: String? = null,
    @SerialName("handing_type") val handingType: String? = null,
    @SerialName("customer_notes") val customerNotes: String? = null,
    @SerialName("public_notes") val publicNotes: String? = null,
    @SerialName("internal_notes") val internalNotes: String? = null,
    val timeline: List<DeliveryNoteTimelineEntry> = emptyList(),
    val counts: PalletDeliveryCounts = PalletDeliveryCounts(),
    val pallets: List<Pallet> = emptyList(),
    val services: List<DeliveryCharge> = emptyList(),
    @SerialName("physical_goods") val physicalGoods: List<DeliveryCharge> = emptyList(),
    @SerialName("finish_booking_blocked") val finishBookingBlocked: String? = null,
    @SerialName("available_actions") val availableActions: List<DeliveryNoteAction> = emptyList(),
) {
    fun action(key: String): DeliveryNoteAction? = availableActions.firstOrNull { it.key == key }

    fun can(key: String): Boolean = action(key)?.isDisabled == false

    fun pallet(id: Long): Pallet? = pallets.firstOrNull { it.id == id }

    fun palletLabelled(code: String): Pallet? =
        pallets.firstOrNull { it.reference.equals(code.trim(), ignoreCase = true) } ?: pallets.singleOrNull { it.isLabelled(code) }

    fun at(state: String): String? = timeline.firstOrNull { it.state == state }?.at
}

data class PalletPage(val rows: List<Pallet>, val meta: ListMeta, val counts: StateCounts? = null, val incidentCounts: StateCounts? = null)

@Serializable
data class FulfilmentLocation(
    val id: Long,
    val code: String = "",
    @Serializable(with = LenientInt::class) @SerialName("number_pallets") val numberPallets: Int = 0,
)

@Serializable
data class ScannedPallet(
    val id: Long,
    val reference: String = "",
    @SerialName("customer_reference") val customerReference: String? = null,
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    val status: String = "",
    @SerialName("location_code") val locationCode: String? = null,
)

@Serializable
data class ScannedPalletDelivery(
    val id: Long,
    val reference: String = "",
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
)

sealed interface PalletLocationTarget {
    val code: String

    data class Picked(val locationId: Long, override val code: String) : PalletLocationTarget
    data class Scanned(override val code: String) : PalletLocationTarget
}

sealed interface PalletDeliveryWrite {
    data object Receive : PalletDeliveryWrite
    data object StartBooking : PalletDeliveryWrite
    data object FinishBooking : PalletDeliveryWrite
    data class BookIn(val palletId: Long, val target: PalletLocationTarget) : PalletDeliveryWrite
    data class NotReceived(val palletId: Long) : PalletDeliveryWrite
    data class UndoNotReceived(val palletId: Long) : PalletDeliveryWrite
}

sealed interface PalletWrite {
    data class Move(val target: PalletLocationTarget) : PalletWrite
    data class Incident(val state: PalletIncidentState, val message: String?) : PalletWrite
}

data class PalletDeliveryOutcome(val delivery: PalletDelivery, val status: String, val message: String?)

data class PalletOutcome(val pallet: Pallet, val status: String, val message: String?)

data class PalletAuditStart(val auditId: Long, val message: String?)

interface FulfilmentInboundApi {
    suspend fun palletDeliveries(token: String, warehouseId: Long, query: PalletDeliveryQuery): GoodsOutResult<PalletDeliveryPage>

    suspend fun palletDelivery(token: String, warehouseId: Long, deliveryId: Long): GoodsOutResult<PalletDelivery>

    suspend fun writePalletDelivery(token: String, warehouseId: Long, deliveryId: Long, write: PalletDeliveryWrite): GoodsOutResult<PalletDeliveryOutcome>

    suspend fun pallets(token: String, warehouseId: Long, query: PalletQuery): GoodsOutResult<PalletPage>

    suspend fun pallet(token: String, warehouseId: Long, palletId: Long): GoodsOutResult<Pallet>

    suspend fun writePallet(token: String, warehouseId: Long, palletId: Long, write: PalletWrite): GoodsOutResult<PalletOutcome>

    suspend fun startPalletAudit(token: String, warehouseId: Long, palletId: Long): GoodsOutResult<PalletAuditStart>

    suspend fun fulfilmentLocations(token: String, warehouseId: Long, search: String): GoodsOutResult<List<FulfilmentLocation>>

    suspend fun scan(token: String, warehouseId: Long, code: String): GoodsOutResult<ScanLookup>
}

class OkHttpFulfilmentInboundApi(private val http: OkHttpMobileApi) : FulfilmentInboundApi {

    private fun warehouseUrl(warehouseId: Long): String = "${http.mobileApiUrl}/warehouses/$warehouseId"

    override suspend fun palletDeliveries(token: String, warehouseId: Long, query: PalletDeliveryQuery): GoodsOutResult<PalletDeliveryPage> =
        http.execute(http.get(http.urlWith("${warehouseUrl(warehouseId)}/pallet-deliveries", FulfilmentInboundResponses.palletDeliveriesParameters(query)), token))
            ?.let { (statusCode, responseBody) -> FulfilmentInboundResponses.parsePalletDeliveries(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun palletDelivery(token: String, warehouseId: Long, deliveryId: Long): GoodsOutResult<PalletDelivery> =
        http.execute(http.get("${warehouseUrl(warehouseId)}/pallet-deliveries/$deliveryId", token))
            ?.let { (statusCode, responseBody) -> FulfilmentInboundResponses.parsePalletDelivery(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun writePalletDelivery(
        token: String,
        warehouseId: Long,
        deliveryId: Long,
        write: PalletDeliveryWrite,
    ): GoodsOutResult<PalletDeliveryOutcome> =
        http.execute(http.writeRequest(warehouseUrl(warehouseId), FulfilmentInboundResponses.palletDeliveryWriteRequest(deliveryId, write), token))
            ?.let { (statusCode, responseBody) -> FulfilmentInboundResponses.parsePalletDeliveryWrite(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun pallets(token: String, warehouseId: Long, query: PalletQuery): GoodsOutResult<PalletPage> =
        http.execute(http.get(http.urlWith("${warehouseUrl(warehouseId)}/pallets", FulfilmentInboundResponses.palletsParameters(query)), token))
            ?.let { (statusCode, responseBody) -> FulfilmentInboundResponses.parsePallets(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun pallet(token: String, warehouseId: Long, palletId: Long): GoodsOutResult<Pallet> =
        http.execute(http.get("${warehouseUrl(warehouseId)}/pallets/$palletId", token))
            ?.let { (statusCode, responseBody) -> FulfilmentInboundResponses.parsePallet(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun writePallet(token: String, warehouseId: Long, palletId: Long, write: PalletWrite): GoodsOutResult<PalletOutcome> =
        http.execute(http.writeRequest("${warehouseUrl(warehouseId)}/pallets/$palletId", FulfilmentInboundResponses.palletWriteRequest(write), token))
            ?.let { (statusCode, responseBody) -> FulfilmentInboundResponses.parsePalletWrite(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun startPalletAudit(token: String, warehouseId: Long, palletId: Long): GoodsOutResult<PalletAuditStart> =
        http.execute(http.writeRequest("${warehouseUrl(warehouseId)}/pallets/$palletId", FulfilmentInboundResponses.auditStartRequest(), token))
            ?.let { (statusCode, responseBody) -> FulfilmentInboundResponses.parseAuditStart(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun fulfilmentLocations(token: String, warehouseId: Long, search: String): GoodsOutResult<List<FulfilmentLocation>> =
        http.execute(http.get(http.urlWith("${warehouseUrl(warehouseId)}/fulfilment-locations", FulfilmentInboundResponses.locationParameters(search)), token))
            ?.let { (statusCode, responseBody) -> FulfilmentInboundResponses.parseLocations(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun scan(token: String, warehouseId: Long, code: String): GoodsOutResult<ScanLookup> =
        http.execute(http.get(http.urlWith("${warehouseUrl(warehouseId)}/scan", listOf("code" to code)), token))
            ?.let { (statusCode, responseBody) -> GoodsOutResponses.parseScan(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable
}
