package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import java.io.File
import java.time.LocalDate

enum class DeliveryNoteBucket(val key: String) {
    Unassigned("unassigned"),
    Queued("queued"),
    Handling("handling"),
    HandlingBlocked("handling_blocked"),
    Picked("picked"),
    Packing("packing"),
    Packed("packed"),
    Finalised("finalised"),
    Dispatched("dispatched"),
    InWarehouse("in_warehouse"),
    All("all"),
    ;

    companion object {
        fun ofKey(key: String?): DeliveryNoteBucket? = entries.firstOrNull { it.key == key }
    }
}

enum class DispatchChannel(val key: String) {
    All("all"),
    B2b("b2b"),
    Dropshipping("dropshipping"),
    B2c("b2c"),
    External("external"),
    Partners("partners"),
    Fulfilment("fulfilment"),
    ;

    companion object {
        fun ofKey(key: String?): DispatchChannel? = entries.firstOrNull { it.key == key }
    }
}

enum class PickerFilter(val key: String) {
    Me("me"),
    Nobody("none"),
    Others("others"),
}

enum class DeliveryNoteSort(val key: String) {
    DateOldest("date"),
    DateNewest("-date"),
    MostItems("-number_items"),
    Heaviest("-effective_weight"),
    Customer("customer_name"),
}

data class DeliveryNoteQuery(
    val bucket: DeliveryNoteBucket = DeliveryNoteBucket.Unassigned,
    val channel: DispatchChannel = DispatchChannel.All,
    val search: String? = null,
    val from: LocalDate? = null,
    val to: LocalDate? = null,
    val picker: PickerFilter? = null,
    val sort: DeliveryNoteSort? = null,
    val page: Int = 1,
    val perPage: Int = DEFAULT_PER_PAGE,
) {
    companion object {
        const val DEFAULT_PER_PAGE = 50
    }
}

@Serializable
data class GoodsOutPagination(
    @SerialName("current_page") val currentPage: Int = 1,
    @SerialName("last_page") val lastPage: Int = 1,
    @SerialName("per_page") val perPage: Int = DeliveryNoteQuery.DEFAULT_PER_PAGE,
    val total: Int = 0,
)

@Serializable
data class StaffRef(val id: Long, val name: String = "")

@Serializable
data class TrolleyRef(val id: Long, val name: String = "")

@Serializable
data class DeliveryNoteShop(val id: Long, val name: String = "", val type: String = "")

@Serializable
data class DeliveryNoteRow(
    val id: Long,
    val slug: String = "",
    val reference: String = "",
    val type: String = "",
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    val date: String? = null,
    @SerialName("last_modified_at") val lastModifiedAt: String? = null,
    @SerialName("customer_name") val customerName: String = "",
    @SerialName("is_customer_vip") val isCustomerVip: Boolean = false,
    val shop: DeliveryNoteShop? = null,
    val channel: String = "",
    @SerialName("number_items") val numberItems: Int = 0,
    @SerialName("estimated_weight_kg") val estimatedWeightKg: Double? = null,
    val parcels: Int = 0,
    @SerialName("is_premium_dispatch") val isPremiumDispatch: Boolean = false,
    @SerialName("is_priority") val isPriority: Boolean = false,
    @SerialName("is_collection") val isCollection: Boolean = false,
    @SerialName("is_export") val isExport: Boolean = false,
    @SerialName("has_extra_packing") val hasExtraPacking: Boolean = false,
    val picker: StaffRef? = null,
    val packer: StaffRef? = null,
    val trolleys: List<TrolleyRef> = emptyList(),
    @SerialName("picked_bays") val pickedBays: List<JsonElement> = emptyList(),
    @SerialName("waiting_warehouse_count") val waitingWarehouseCount: Int = 0,
    @SerialName("waiting_crm_count") val waitingCrmCount: Int = 0,
    @SerialName("number_of_days_in_warehouse") val numberOfDaysInWarehouse: Double? = null,
    @SerialName("can_add_to_picking_session") val canAddToPickingSession: Boolean = false,
) {
    val waitingCount: Int get() = waitingWarehouseCount + waitingCrmCount
}

data class DeliveryNotePage(
    val notes: List<DeliveryNoteRow>,
    val canWorkInWarehouse: Boolean,
    val pagination: GoodsOutPagination,
)

@Serializable
data class ChannelCounts(
    val channel: String,
    val label: String = "",
    val buckets: Map<String, Int> = emptyMap(),
) {
    fun count(bucket: DeliveryNoteBucket): Int = buckets[bucket.key] ?: 0
}

@Serializable
data class DeliveryNoteCounts(
    val buckets: Map<String, Int> = emptyMap(),
    val channels: List<ChannelCounts> = emptyList(),
) {
    fun count(bucket: DeliveryNoteBucket): Int = buckets[bucket.key] ?: 0
}

@Serializable
data class DeliveryNotePlatform(val name: String = "")

@Serializable
data class DeliveryNoteOrder(val id: Long? = null, val reference: String = "")

@Serializable
data class DeliveryNoteCustomer(
    val name: String = "",
    val reference: String? = null,
    @SerialName("contact_name") val contactName: String? = null,
    @SerialName("company_name") val companyName: String? = null,
    val email: String? = null,
    val phone: String? = null,
    @SerialName("is_vip") val isVip: Boolean = false,
)

@Serializable
data class DeliveryAddressFields(
    @SerialName("contact_name") val contactName: String? = null,
    @SerialName("company_name") val companyName: String? = null,
    val email: String? = null,
    val phone: String? = null,
    @SerialName("address_line_1") val addressLine1: String? = null,
    @SerialName("address_line_2") val addressLine2: String? = null,
    @SerialName("sorting_code") val sortingCode: String? = null,
    @SerialName("postal_code") val postalCode: String? = null,
    val locality: String? = null,
    @SerialName("dependent_locality") val dependentLocality: String? = null,
    @SerialName("administrative_area") val administrativeArea: String? = null,
    @SerialName("country_code") val countryCode: String? = null,
)

@Serializable
data class DeliveryAddress(
    val lines: List<String> = emptyList(),
    @SerialName("country_code") val countryCode: String? = null,
    val fields: DeliveryAddressFields? = null,
)

@Serializable
data class DeliveryNoteSummary(
    @SerialName("number_items") val numberItems: Double = 0.0,
    @SerialName("number_skos") val numberSkos: Double = 0.0,
    @SerialName("number_units") val numberUnits: Double = 0.0,
    @SerialName("estimated_weight_kg") val estimatedWeightKg: Double? = null,
    @SerialName("estimated_picking_minutes") val estimatedPickingMinutes: Double? = null,
    @SerialName("estimated_packing_minutes") val estimatedPackingMinutes: Double? = null,
)

@Serializable
data class DeliveryNoteParcel(
    @SerialName("weight_kg") val weightKg: Double? = null,
    @SerialName("dimensions_cm") val dimensionsCm: List<Double?> = emptyList(),
    val boxes: Int? = null,
)

data class ParcelWrite(val weightKg: Double, val dimensionsCm: List<Double>, val boxes: Int?)

@Serializable
data class DeliveryNoteNote(val text: String? = null, val editable: Boolean = false)

@Serializable
data class DeliveryNoteNotes(
    val shipping: DeliveryNoteNote = DeliveryNoteNote(),
    val customer: DeliveryNoteNote = DeliveryNoteNote(),
    val warehouse: DeliveryNoteNote = DeliveryNoteNote(),
)

enum class NoteField(val key: String) {
    Warehouse("warehouse"),
    Shipping("shipping"),
}

@Serializable
data class ReturnDeliveryNote(val id: Long, val reference: String = "", @SerialName("state_label") val stateLabel: String = "")

@Serializable
data class DeliveryNoteTimelineEntry(val state: String = "", val label: String = "", val at: String? = null)

object ShipmentLabelKind {
    const val DOWNLOAD = "download"
    const val EXTERNAL = "external"
    const val NONE = "none"
}

@Serializable
data class ShipmentLabel(val kind: String = ShipmentLabelKind.NONE, val url: String? = null)

@Serializable
data class DeliveryNoteShipment(
    val id: Long,
    val shipper: String? = null,
    @SerialName("shipper_code") val shipperCode: String? = null,
    val tracking: String? = null,
    val trackings: List<String?> = emptyList(),
    @SerialName("tracking_urls") val trackingUrls: List<String?> = emptyList(),
    @SerialName("is_printable") val isPrintable: Boolean = false,
    val label: ShipmentLabel = ShipmentLabel(),
    @SerialName("can_delete") val canDelete: Boolean = false,
) {
    val trackingNumbers: List<String>
        get() = trackings.filterNotNull().filter { it.isNotBlank() }.ifEmpty { listOfNotNull(tracking?.takeIf { it.isNotBlank() }) }

    fun trackingUrl(index: Int): String? = trackingUrls.getOrNull(index)?.takeIf { it.isNotBlank() }
}

@Serializable
data class ExternalShipping(@SerialName("engine_label") val engineLabel: String = "")

@Serializable
data class NoteShipping(
    @SerialName("can_add") val canAdd: Boolean = false,
    @SerialName("can_print_labels") val canPrintLabels: Boolean = false,
    @SerialName("locked_shipper_id") val lockedShipperId: Long? = null,
    @SerialName("preferred_shipper_id") val preferredShipperId: Long? = null,
    @SerialName("locked_by") val lockedBy: String? = null,
    @SerialName("can_override_lock") val canOverrideLock: Boolean = false,
    @SerialName("tracking_channel") val trackingChannel: String? = null,
    @SerialName("unconfirmed_shipping_order") val unconfirmedShippingOrder: Boolean = false,
    val external: ExternalShipping? = null,
)

@Serializable
data class NoteDocuments(
    @SerialName("delivery_note_pdf_url") val deliveryNotePdfUrl: String? = null,
    @SerialName("gift_message_pdf_url") val giftMessagePdfUrl: String? = null,
)

@Serializable
data class Consumable(val code: String = "", val quantity: Double = 0.0)

@Serializable
data class PackagingRef(val id: Long, val name: String = "")

@Serializable
data class NotePackaging(val current: PackagingRef? = null)

object LeafletState {
    const val PENDING_PRINT = "pending_print"
    const val PRINTED = "printed"
    const val INCLUDED = "included"
}

@Serializable
data class InsertLeaflet(
    val id: Long,
    val name: String = "",
    val type: String? = null,
    val copies: Int = 1,
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    @SerialName("can_open_pdf") val canOpenPdf: Boolean = false,
) {
    val isDone: Boolean get() = state == LeafletState.PRINTED || state == LeafletState.INCLUDED
}

@Serializable
data class NoteInserts(
    val leaflets: List<InsertLeaflet> = emptyList(),
    val total: Int = 0,
    val printed: Int = 0,
    @SerialName("all_printed") val allPrinted: Boolean = false,
    val label: String? = null,
    @SerialName("can_print_all") val canPrintAll: Boolean = false,
)

@Serializable
data class BoxPackingList(
    val required: Boolean = false,
    @SerialName("number_boxes") val numberBoxes: Int? = null,
    @SerialName("missing_message") val missingMessage: String? = null,
    @SerialName("pdf_url") val pdfUrl: String? = null,
)

@Serializable
data class DeliveryNoteLineCounts(val all: Int = 0, val todo: Int = 0, val done: Int = 0)

@Serializable
data class LineOrgStock(
    val id: Long,
    val code: String = "",
    val name: String = "",
    val barcode: String? = null,
    @SerialName("packed_in") val packedIn: Double? = null,
    @SerialName("image_url") val imageUrl: String? = null,
)

@Serializable
data class LineLocation(
    @SerialName("location_org_stock_id") val locationOrgStockId: Long? = null,
    @SerialName("location_id") val locationId: Long? = null,
    val code: String = "",
    val stock: Double? = null,
    @SerialName("stock_label") val stockLabel: String? = null,
    val type: String? = null,
    @SerialName("quantity_picked") val quantityPicked: Double? = null,
    @SerialName("picking_id") val pickingId: Long? = null,
)

@Serializable
data class LineBox(val box: Int, val quantity: Double = 0.0)

object PickingType {
    const val PICK = "pick"
    const val NOT_PICK = "not-pick"
}

@Serializable
data class LinePicking(
    val id: Long,
    val type: String = PickingType.PICK,
    @SerialName("location_org_stock_id") val locationOrgStockId: Long? = null,
    @SerialName("location_code") val locationCode: String? = null,
    val quantity: Double = 0.0,
    @SerialName("quantity_label") val quantityLabel: String? = null,
    @SerialName("not_picked_reason") val notPickedReason: String? = null,
    @SerialName("not_picked_reason_label") val notPickedReasonLabel: String? = null,
) {
    val isNotPicked: Boolean get() = type == PickingType.NOT_PICK
}

@Serializable
data class DeliveryNoteLine(
    val id: Long,
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    @SerialName("org_stock") val orgStock: LineOrgStock? = null,
    @SerialName("quantity_required") val quantityRequired: Double = 0.0,
    @SerialName("quantity_required_label") val quantityRequiredLabel: String? = null,
    @SerialName("quantity_picked") val quantityPicked: Double = 0.0,
    @SerialName("quantity_picked_label") val quantityPickedLabel: String? = null,
    @SerialName("quantity_to_pick") val quantityToPick: Double = 0.0,
    @SerialName("quantity_to_pick_label") val quantityToPickLabel: String? = null,
    @SerialName("quantity_not_picked") val quantityNotPicked: Double = 0.0,
    @SerialName("quantity_not_picked_label") val quantityNotPickedLabel: String? = null,
    @SerialName("quantity_packed") val quantityPacked: Double = 0.0,
    @SerialName("quantity_packed_label") val quantityPackedLabel: String? = null,
    @SerialName("quantity_to_pack") val quantityToPack: Double = 0.0,
    @SerialName("quantity_to_pack_label") val quantityToPackLabel: String? = null,
    @SerialName("quantity_waiting_warehouse") val quantityWaitingWarehouse: Double = 0.0,
    @SerialName("quantity_waiting_warehouse_label") val quantityWaitingWarehouseLabel: String? = null,
    @SerialName("quantity_waiting_crm") val quantityWaitingCrm: Double = 0.0,
    @SerialName("quantity_waiting_crm_label") val quantityWaitingCrmLabel: String? = null,
    @SerialName("is_handled") val isHandled: Boolean = false,
    @SerialName("is_picked") val isPicked: Boolean = false,
    @SerialName("is_packed") val isPacked: Boolean = false,
    @SerialName("is_done_packing") val isDonePacking: Boolean = false,
    val boxes: List<LineBox> = emptyList(),
    val locations: List<LineLocation> = emptyList(),
    @SerialName("warehouse_area") val warehouseArea: String? = null,
    @SerialName("note_to_pickers") val noteToPickers: String? = null,
    @SerialName("note_to_packers") val noteToPackers: String? = null,
    @SerialName("un_numbers") val unNumbers: JsonElement? = null,
    @SerialName("replacement_reason") val replacementReason: String? = null,
    @SerialName("indivisible_set") val isIndivisibleSet: Boolean = false,
    val pickings: List<LinePicking> = emptyList(),
) {
    val unNumbersText: String? get() = unNumbers.displayText()

    val isPackingDone: Boolean get() = isDonePacking || isPacked
}

@Serializable
data class DeliveryNoteAction(
    val key: String,
    val label: String = "",
    @SerialName("disabled_reason") val disabledReason: String? = null,
) {
    val isDisabled: Boolean get() = !disabledReason.isNullOrBlank()
}

object NoteActionKey {
    const val START_PICKING = "start_picking"
    const val RESUME_PICKING = "resume_picking"
    const val TAKE_OVER = "take_over"
    const val SET_AS_PICKED = "set_as_picked"
    const val SET_AS_PACKED = "set_as_packed"
    const val PUT_BACK_INCOMPLETE_SETS = "put_back_incomplete_sets"
    const val AUTO_FINISH_WAITING = "auto_finish_waiting"
    const val BACK_TO_PICKING = "back_to_picking"
    const val UNDO_SET_AS_PICKED = "undo_set_as_picked"
    const val CHANGE_TROLLEY = "change_trolley"
    const val CHANGE_PICKED_BAY = "change_picked_bay"
    const val ASSIGN_PICKER = "assign_picker"
    const val REMOVE_PICKER = "remove_picker"
    const val START_PACKING = "start_packing"
    const val UNDO_PACKING = "undo_packing"
    const val UNPACK = "unpack"
    const val ADD_SHIPMENT = "add_shipment"
    const val GET_EXTERNAL_SHIPMENT = "get_external_shipment"
    const val FINALISE_AND_DISPATCH = "finalise_and_dispatch"
    const val FINALISE = "finalise"
    const val DISPATCH = "dispatch"
    const val CANCEL = "cancel"
    const val CHANGE_PACKER = "change_packer"
    const val EDIT_DELIVERY_ADDRESS = "edit_delivery_address"
}

@Serializable
data class PickingSettings(
    @SerialName("allow_scan_to_pick") val allowScanToPick: Boolean = false,
    @SerialName("allow_waiting") val allowWaiting: Boolean = false,
    @SerialName("allow_picker_set_not_picked") val allowPickerSetNotPicked: Boolean = false,
    @SerialName("allow_picker_choose_location") val allowPickerChooseLocation: Boolean = false,
    @SerialName("allow_scan_to_pack") val allowScanToPack: Boolean = false,
)

@Serializable
data class IncompleteSetPart(
    val code: String = "",
    val name: String = "",
    val quantity: Double = 0.0,
    @SerialName("quantity_label") val quantityLabel: String? = null,
    val locations: List<String> = emptyList(),
)

@Serializable
data class DeliveryNote(
    val id: Long,
    val slug: String = "",
    val reference: String = "",
    val type: String = "",
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    @SerialName("warehouse_id") val warehouseId: Long? = null,
    val shop: DeliveryNoteShop? = null,
    val channel: String = "",
    val platform: DeliveryNotePlatform? = null,
    val order: DeliveryNoteOrder? = null,
    val customer: DeliveryNoteCustomer? = null,
    @SerialName("delivery_address") val deliveryAddress: DeliveryAddress? = null,
    @SerialName("is_collection") val isCollection: Boolean = false,
    @SerialName("is_shipping_by_external") val isShippingByExternal: Boolean = false,
    @SerialName("is_premium_dispatch") val isPremiumDispatch: Boolean = false,
    @SerialName("is_cash_on_delivery") val isCashOnDelivery: Boolean = false,
    @SerialName("has_extra_packing") val hasExtraPacking: Boolean = false,
    @SerialName("has_gift_message") val hasGiftMessage: Boolean = false,
    @SerialName("is_export") val isExport: Boolean = false,
    val summary: DeliveryNoteSummary = DeliveryNoteSummary(),
    val picker: StaffRef? = null,
    val packer: StaffRef? = null,
    val trolleys: List<TrolleyRef> = emptyList(),
    @SerialName("picked_bays") val pickedBays: List<JsonElement> = emptyList(),
    val parcels: List<DeliveryNoteParcel> = emptyList(),
    val notes: DeliveryNoteNotes = DeliveryNoteNotes(),
    val timeline: List<DeliveryNoteTimelineEntry> = emptyList(),
    val shipments: List<DeliveryNoteShipment> = emptyList(),
    @SerialName("waiting_warehouse_count") val waitingWarehouseCount: Int = 0,
    @SerialName("waiting_crm_count") val waitingCrmCount: Int = 0,
    @SerialName("is_editable") val isEditable: Boolean = false,
    @SerialName("allow_actions") val allowActions: Boolean = false,
    @SerialName("read_only_notice") val readOnlyNotice: String? = null,
    val warning: String? = null,
    @SerialName("line_counts") val lineCounts: DeliveryNoteLineCounts = DeliveryNoteLineCounts(),
    val lines: List<DeliveryNoteLine> = emptyList(),
    @SerialName("available_actions") val availableActions: List<DeliveryNoteAction> = emptyList(),
    @SerialName("can_handle") val canHandle: Boolean = false,
    val settings: PickingSettings = PickingSettings(),
    @SerialName("incomplete_sets") val incompleteSets: List<IncompleteSetPart> = emptyList(),
    val consumables: List<Consumable> = emptyList(),
    val packaging: NotePackaging? = null,
    val inserts: NoteInserts? = null,
    @SerialName("box_packing_list") val boxPackingList: BoxPackingList? = null,
    @SerialName("parcels_editable") val parcelsEditable: Boolean = false,
    val shipping: NoteShipping = NoteShipping(),
    val documents: NoteDocuments = NoteDocuments(),
    @SerialName("can_edit_contact") val canEditContact: Boolean = false,
    @SerialName("return_delivery_note") val returnDeliveryNote: ReturnDeliveryNote? = null,
) {
    val canAct: Boolean get() = isEditable && allowActions

    val pickedBayLabels: List<String> get() = pickedBays.mapNotNull { it.displayText() }

    val isDropshipping: Boolean get() = shop?.type == DispatchChannel.Dropshipping.key

    fun action(key: String): DeliveryNoteAction? = availableActions.firstOrNull { it.key == key }

    fun line(id: Long): DeliveryNoteLine? = lines.firstOrNull { it.id == id }

    fun shipment(id: Long): DeliveryNoteShipment? = shipments.firstOrNull { it.id == id }
}

@Serializable
data class ScannedSkoLocation(val code: String = "", val quantity: Double? = null)

@Serializable
data class ScannedSko(
    val id: Long,
    val code: String = "",
    val name: String = "",
    val state: String? = null,
    val barcode: String? = null,
    @SerialName("unit_barcode") val unitBarcode: String? = null,
    @SerialName("packed_in") val packedIn: Double? = null,
    @SerialName("quantity_in_locations") val quantityInLocations: Double? = null,
    @SerialName("image_url") val imageUrl: String? = null,
    val locations: List<ScannedSkoLocation> = emptyList(),
    @SerialName("matched_on") val matchedOn: String? = null,
)

@Serializable
data class ScannedLocation(
    val id: Long,
    val code: String = "",
    val slug: String = "",
    @SerialName("warehouse_area") val warehouseArea: JsonElement? = null,
    @SerialName("number_org_stocks") val numberOrgStocks: Double? = null,
)

object ScanMatch {
    const val SKO = "sko"
    const val LOCATION = "location"
    const val DELIVERY_NOTE = "delivery_note"
    const val UNKNOWN = "unknown"
}

@Serializable
data class ScanLookup(
    val code: String = "",
    val type: String = ScanMatch.UNKNOWN,
    val sko: ScannedSko? = null,
    val location: ScannedLocation? = null,
    @SerialName("delivery_note") val deliveryNote: DeliveryNoteRow? = null,
)

object NoteActionStatus {
    const val NOTHING_CHANGED = "nothing_changed"
}

object WriteErrorKey {
    const val OVERRIDE_LOCK = "override_lock"
    const val ADDRESS = "address"
    const val MESSAGE = "message"
    const val OTHERS = "others"
    const val SENDER = "sender"
    const val SHIPPER = "shipper"
    const val TRACKING = "tracking"
    const val PARCELS = "parcels"
    const val BOXES = "boxes"
}

data class NoteActionOutcome(val note: DeliveryNote, val status: String, val message: String?, val warning: String? = null)

enum class NotPickedReason(val key: String) {
    OutOfStock("out-of-stock"),
    CancelledByCustomer("cancelled-by-customer"),
    CancelledByWarehouse("cancelled-by-warehouse"),
    NotApplicable("not-applicable"),
}

enum class WaitingKind(val key: String) {
    Warehouse("warehouse"),
    Crm("crm"),
}

sealed interface DeliveryNoteWrite {
    data class StartPicking(val trolleyId: Long?) : DeliveryNoteWrite
    data object TakeOver : DeliveryNoteWrite
    data class SetAsPicked(val pickedBayId: Long?) : DeliveryNoteWrite
    data object SetAsPacked : DeliveryNoteWrite
    data object PutBackIncompleteSets : DeliveryNoteWrite
    data object AutoFinishWaiting : DeliveryNoteWrite
    data object BackToPicking : DeliveryNoteWrite
    data object UndoSetAsPicked : DeliveryNoteWrite
    data class ChangeTrolley(val trolleyId: Long?) : DeliveryNoteWrite
    data class ChangePickedBay(val pickedBayId: Long?) : DeliveryNoteWrite
    data class SetPicker(val userId: Long?) : DeliveryNoteWrite
    data class Pick(val lineId: Long, val locationOrgStockId: Long, val quantity: Double) : DeliveryNoteWrite
    data class PickAll(val lineId: Long, val locationOrgStockId: Long?) : DeliveryNoteWrite
    data class NotPicked(val lineId: Long, val quantity: Double?, val reason: NotPickedReason, val note: String?) : DeliveryNoteWrite
    data class Waiting(val lineId: Long, val kind: WaitingKind, val quantity: Double, val note: String?) : DeliveryNoteWrite
    data class UndoPicking(val lineId: Long, val pickingId: Long) : DeliveryNoteWrite
    data object StartPacking : DeliveryNoteWrite
    data object UndoPacking : DeliveryNoteWrite
    data object Unpack : DeliveryNoteWrite
    data class SetParcels(val parcels: List<ParcelWrite>) : DeliveryNoteWrite
    data class Pack(val lineId: Long, val quantity: Double?) : DeliveryNoteWrite
    data class UnpackLine(val lineId: Long) : DeliveryNoteWrite
    data class SetBoxes(val lineId: Long, val boxes: List<LineBox>) : DeliveryNoteWrite
    data object FinaliseAndDispatch : DeliveryNoteWrite
    data object Finalise : DeliveryNoteWrite
    data object Dispatch : DeliveryNoteWrite
    data class AddShipment(val shipperId: Long, val tracking: String?, val overrideLock: Boolean = false) : DeliveryNoteWrite
    data class RetryShipmentWithAddress(val shipperId: Long, val address: DeliveryAddressFields, val overrideLock: Boolean = false) : DeliveryNoteWrite
    data object GetExternalShipment : DeliveryNoteWrite
    data class DeleteShipment(val shipmentId: Long) : DeliveryNoteWrite
    data class PrintLabel(val shipmentId: Long) : DeliveryNoteWrite
    data object PrintInserts : DeliveryNoteWrite
    data class Cancel(val createReturn: Boolean) : DeliveryNoteWrite
    data class SetNote(val field: NoteField, val text: String) : DeliveryNoteWrite
    data class SetPacker(val userId: Long) : DeliveryNoteWrite
    data class EditDeliveryAddress(val address: DeliveryAddressFields, val includesContact: Boolean) : DeliveryNoteWrite
}

object ScanPickStatus {
    const val PICKED = "picked"
    const val ALREADY_PICKED = "already_picked"
    const val NO_STOCK = "no_stock"
    const val NOT_FOUND = "not_found"
    const val WRONG_STATE = "wrong_state"
}

data class ScanPickRequest(
    val barcode: String,
    val quantity: Double?,
    val deliveryNoteItemId: Long? = null,
    val locationOrgStockId: Long? = null,
)

@Serializable
data class ScanPickItem(
    val id: Long,
    val code: String? = null,
    val name: String? = null,
    @SerialName("quantity_required") val quantityRequired: Double = 0.0,
    @SerialName("quantity_picked") val quantityPicked: Double = 0.0,
    @SerialName("quantity_to_pick") val quantityToPick: Double = 0.0,
    @SerialName("quantity_to_pick_label") val quantityToPickLabel: String? = null,
    @SerialName("location_code") val locationCode: String? = null,
    @SerialName("location_org_stock_id") val locationOrgStockId: Long? = null,
)

data class ScanPickOutcome(val note: DeliveryNote, val status: String, val message: String?, val item: ScanPickItem?)

object ScanPackStatus {
    const val PACKED = "packed"
    const val ALREADY_PACKED = "already_packed"
    const val NOTHING_TO_PACK = "nothing_to_pack"
    const val NOT_FOUND = "not_found"
    const val WRONG_STATE = "wrong_state"
}

data class ScanPackRequest(val barcode: String, val quantity: Double?, val deliveryNoteItemId: Long? = null)

@Serializable
data class ScanPackItem(
    val id: Long,
    val code: String? = null,
    val name: String? = null,
    @SerialName("quantity_picked") val quantityPicked: Double = 0.0,
    @SerialName("quantity_packed") val quantityPacked: Double = 0.0,
    @SerialName("quantity_to_pack") val quantityToPack: Double = 0.0,
    @SerialName("quantity_to_pack_label") val quantityToPackLabel: String? = null,
)

data class ScanPackOutcome(val note: DeliveryNote, val status: String, val message: String?, val item: ScanPackItem?)

object ShipperType {
    const val API = "api"
    const val MANUAL = "manual"
}

@Serializable
data class Shipper(
    val id: Long,
    val code: String = "",
    val name: String = "",
    @SerialName("trade_as") val tradeAs: String? = null,
    @SerialName("api_shipper") val apiShipper: String? = null,
    val type: String = ShipperType.MANUAL,
    @SerialName("needs_tracking") val needsTracking: Boolean = false,
    @SerialName("is_locked_out") val isLockedOut: Boolean = false,
) {
    val isApi: Boolean get() = type == ShipperType.API
}

@Serializable
data class ShipperDirective(
    @SerialName("locked_shipper_id") val lockedShipperId: Long? = null,
    @SerialName("preferred_shipper_id") val preferredShipperId: Long? = null,
    @SerialName("locked_by") val lockedBy: String? = null,
    @SerialName("can_override_lock") val canOverrideLock: Boolean = false,
    @SerialName("tracking_channel") val trackingChannel: String? = null,
)

data class ShipperList(val shippers: List<Shipper>, val directive: ShipperDirective)

@Serializable
data class NoteReference(val id: Long, val reference: String = "")

@Serializable
data class Trolley(
    val id: Long,
    val name: String = "",
    @SerialName("current_delivery_note") val currentDeliveryNote: NoteReference? = null,
    @SerialName("current_picking_session") val currentPickingSession: NoteReference? = null,
)

@Serializable
data class DeliveryNoteRef(
    val id: Long,
    val reference: String = "",
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
)

@Serializable
data class PickedBay(
    val id: Long,
    val code: String = "",
    @SerialName("number_delivery_notes") val numberDeliveryNotes: Int = 0,
    @SerialName("number_waiting") val numberWaiting: Int = 0,
    @SerialName("delivery_notes") val deliveryNotes: List<DeliveryNoteRef> = emptyList(),
) {
    val isFree: Boolean get() = numberDeliveryNotes <= 0 && deliveryNotes.isEmpty()
}

@Serializable
data class Picker(
    @SerialName("user_id") val userId: Long,
    val name: String = "",
    val alias: String? = null,
)

sealed interface GoodsOutResult<out T> {
    data class Loaded<T>(val value: T) : GoodsOutResult<T>
    data object SessionExpired : GoodsOutResult<Nothing>
    data class Forbidden(val message: String) : GoodsOutResult<Nothing>
    data class NotFound(val message: String) : GoodsOutResult<Nothing>
    data class Invalid(val message: String, val errors: Map<String, List<String>> = emptyMap()) : GoodsOutResult<Nothing>
    data object Unreachable : GoodsOutResult<Nothing>
    data class ServerError(val statusCode: Int) : GoodsOutResult<Nothing>
}

interface GoodsOutApi {
    suspend fun deliveryNotes(token: String, warehouseId: Long, query: DeliveryNoteQuery): GoodsOutResult<DeliveryNotePage>

    suspend fun deliveryNoteCounts(token: String, warehouseId: Long, picker: PickerFilter?): GoodsOutResult<DeliveryNoteCounts>

    suspend fun deliveryNote(token: String, deliveryNoteId: Long): GoodsOutResult<DeliveryNote>

    suspend fun scan(token: String, warehouseId: Long, code: String): GoodsOutResult<ScanLookup>

    suspend fun write(token: String, deliveryNoteId: Long, write: DeliveryNoteWrite): GoodsOutResult<NoteActionOutcome>

    suspend fun scanPick(token: String, deliveryNoteId: Long, request: ScanPickRequest): GoodsOutResult<ScanPickOutcome>

    suspend fun trolleys(token: String, warehouseId: Long, availableOnly: Boolean): GoodsOutResult<List<Trolley>>

    suspend fun pickedBays(token: String, warehouseId: Long): GoodsOutResult<List<PickedBay>>

    suspend fun pickers(token: String, warehouseId: Long): GoodsOutResult<List<Picker>>

    suspend fun scanPack(token: String, deliveryNoteId: Long, request: ScanPackRequest): GoodsOutResult<ScanPackOutcome>

    suspend fun shippers(token: String, deliveryNoteId: Long): GoodsOutResult<ShipperList>

    suspend fun downloadDocument(token: String, url: String, target: File): AttachmentDownload

    suspend fun downloadInsertPdf(token: String, deliveryNoteId: Long, leafletId: Long, target: File): AttachmentDownload
}

private val displayKeys = listOf("name", "code", "label", "reference", "slug")

internal fun JsonElement?.displayText(): String? = when (this) {
    null, JsonNull -> null
    is JsonPrimitive -> content.takeIf { it.isNotBlank() }
    is JsonArray -> mapNotNull { it.displayText() }.joinToString(", ").takeIf { it.isNotBlank() }
    is JsonObject -> displayKeys.firstNotNullOfOrNull { key -> this[key].displayText() }
}
