package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonObjectBuilder
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.add
import kotlinx.serialization.json.addJsonObject
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put
import kotlinx.serialization.json.putJsonArray
import kotlinx.serialization.json.putJsonObject

private const val HTTP_OK = 200
private const val HTTP_UNAUTHORISED = 401
private const val HTTP_FORBIDDEN = 403
private const val HTTP_NOT_FOUND = 404
private const val HTTP_CONFLICT = 409
private const val HTTP_UNPROCESSABLE = 422

@Serializable
private data class GoodsOutData<T>(val data: T)

@Serializable
private data class DeliveryNoteListData(
    @SerialName("delivery_notes") val deliveryNotes: List<DeliveryNoteRow> = emptyList(),
    @SerialName("can_work_in_warehouse") val canWorkInWarehouse: Boolean = false,
)

@Serializable
private data class DeliveryNoteListResponse(
    val data: DeliveryNoteListData,
    val pagination: GoodsOutPagination? = null,
)

@Serializable
private data class WriteResultData(
    val status: String = "",
    val message: String? = null,
    val warning: String? = null,
    val item: ScanPickItem? = null,
)

@Serializable
private data class ScanPackResultData(
    val status: String = "",
    val message: String? = null,
    val item: ScanPackItem? = null,
)

@Serializable
private data class ScanPackResponse(
    val data: DeliveryNote,
    val result: ScanPackResultData = ScanPackResultData(),
)

@Serializable
private data class ShippersResponse(
    val data: List<Shipper> = emptyList(),
    val directive: ShipperDirective = ShipperDirective(),
)

@Serializable
private data class WriteResponse(
    val data: DeliveryNote,
    val result: WriteResultData = WriteResultData(),
)

enum class WriteMethod { Post, Delete }

data class WriteRequest(val method: WriteMethod, val path: String, val body: JsonObject?)

@Serializable
private data class GoodsOutRefusal(
    val message: String = "",
    val errors: Map<String, JsonElement> = emptyMap(),
) {
    val errorMessages: Map<String, List<String>>
        get() = errors.mapValues { (_, value) -> value.messages() }.filterValues { it.isNotEmpty() }

    val firstMessage: String
        get() = errorMessages.values.flatten().firstOrNull() ?: message
}

private fun JsonElement.messages(): List<String> = when (this) {
    JsonNull -> emptyList()
    is JsonPrimitive -> listOfNotNull(content.trim().takeIf { it.isNotEmpty() })
    is JsonArray -> flatMap { it.messages() }
    is JsonObject -> values.flatMap { it.messages() }
}

object GoodsOutResponses {
    private val json = ClockingResponses.json

    fun deliveryNotesParameters(query: DeliveryNoteQuery): List<Pair<String, String>> = listOfNotNull(
        "bucket" to query.bucket.key,
        "channel" to query.channel.key,
        query.search?.trim()?.takeIf { it.isNotEmpty() }?.let { "search" to it },
        query.from?.let { "from" to it.toString() },
        query.to?.let { "to" to it.toString() },
        query.picker?.let { "picker" to it.key },
        query.sort?.let { "sort" to it.key },
        "page" to query.page.toString(),
        "per_page" to query.perPage.toString(),
    )

    fun countsParameters(picker: PickerFilter?): List<Pair<String, String>> = listOfNotNull(picker?.let { "picker" to it.key })

    fun trolleysParameters(availableOnly: Boolean): List<Pair<String, String>> = if (availableOnly) listOf("available" to "1") else emptyList()

    fun writeRequest(write: DeliveryNoteWrite): WriteRequest = when (write) {
        is DeliveryNoteWrite.StartPicking -> post("start-picking") { put("trolley_id", write.trolleyId) }
        DeliveryNoteWrite.TakeOver -> post("take-over")
        is DeliveryNoteWrite.SetAsPicked -> post("set-as-picked") { put("picked_bay_id", write.pickedBayId) }
        DeliveryNoteWrite.SetAsPacked -> post("set-as-packed")
        DeliveryNoteWrite.PutBackIncompleteSets -> post("put-back-incomplete-sets")
        DeliveryNoteWrite.AutoFinishWaiting -> post("auto-finish-waiting")
        DeliveryNoteWrite.BackToPicking -> post("back-to-picking")
        DeliveryNoteWrite.UndoSetAsPicked -> post("undo-set-as-picked")
        is DeliveryNoteWrite.ChangeTrolley -> post("trolley") { put("trolley_id", write.trolleyId) }
        is DeliveryNoteWrite.ChangePickedBay -> post("picked-bay") { put("picked_bay_id", write.pickedBayId) }
        is DeliveryNoteWrite.SetPicker -> post("picker") { put("user_id", write.userId) }
        is DeliveryNoteWrite.Pick -> post(linePath(write.lineId, "pick")) {
            put("location_org_stock_id", write.locationOrgStockId)
            put("quantity", quantityValue(write.quantity))
        }
        is DeliveryNoteWrite.PickAll -> post(linePath(write.lineId, "pick-all")) { put("location_org_stock_id", write.locationOrgStockId) }
        is DeliveryNoteWrite.NotPicked -> post(linePath(write.lineId, "not-picked")) {
            put("quantity", write.quantity?.let(::quantityValue))
            put("reason", write.reason.key)
            put("note", write.note?.trim()?.takeIf { it.isNotEmpty() })
        }
        is DeliveryNoteWrite.Waiting -> post(linePath(write.lineId, "waiting")) {
            put("kind", write.kind.key)
            put("quantity", quantityValue(write.quantity))
            put("note", write.note?.trim()?.takeIf { it.isNotEmpty() })
        }
        is DeliveryNoteWrite.UndoPicking -> WriteRequest(WriteMethod.Delete, linePath(write.lineId, "pickings/${write.pickingId}"), body = null)
        DeliveryNoteWrite.StartPacking -> post("start-packing")
        DeliveryNoteWrite.UndoPacking -> post("undo-packing")
        DeliveryNoteWrite.Unpack -> post("unpack")
        is DeliveryNoteWrite.SetParcels -> post("parcels") {
            putJsonArray("parcels") {
                write.parcels.forEach { parcel ->
                    addJsonObject {
                        put("weight_kg", quantityValue(parcel.weightKg))
                        putJsonArray("dimensions_cm") { parcel.dimensionsCm.forEach { add(quantityValue(it)) } }
                        put("boxes", parcel.boxes)
                    }
                }
            }
        }
        is DeliveryNoteWrite.Pack -> post(linePath(write.lineId, "pack")) { put("quantity", write.quantity?.let(::quantityValue)) }
        is DeliveryNoteWrite.UnpackLine -> post(linePath(write.lineId, "unpack"))
        is DeliveryNoteWrite.SetBoxes -> post(linePath(write.lineId, "boxes")) {
            putJsonArray("boxes") {
                write.boxes.forEach { box ->
                    addJsonObject {
                        put("box", box.box)
                        put("quantity", quantityValue(box.quantity))
                    }
                }
            }
        }
        DeliveryNoteWrite.FinaliseAndDispatch -> post("finalise-and-dispatch")
        DeliveryNoteWrite.Finalise -> post("finalise")
        DeliveryNoteWrite.Dispatch -> post("dispatch")
        is DeliveryNoteWrite.AddShipment -> post("shipments") {
            put("shipper_id", write.shipperId)
            put("tracking", write.tracking?.trim()?.takeIf { it.isNotEmpty() })
            put("cost", null as Number?)
            put("sender_name", null as String?)
            put("override_lock", write.overrideLock)
        }
        is DeliveryNoteWrite.RetryShipmentWithAddress -> post("shipments/retry-with-address") {
            put("shipper_id", write.shipperId)
            putContact(write.address)
            putAddress(write.address)
            put("override_lock", write.overrideLock)
        }
        DeliveryNoteWrite.GetExternalShipment -> post("external-shipment")
        is DeliveryNoteWrite.DeleteShipment -> WriteRequest(WriteMethod.Delete, "shipments/${write.shipmentId}", body = null)
        is DeliveryNoteWrite.PrintLabel -> post("shipments/${write.shipmentId}/print-label")
        DeliveryNoteWrite.PrintInserts -> post("inserts/print")
        is DeliveryNoteWrite.Cancel -> post("cancel") { put("create_return", write.createReturn) }
        is DeliveryNoteWrite.SetNote -> post("notes") {
            put("field", write.field.key)
            put("text", write.text.trim())
        }
        is DeliveryNoteWrite.SetPacker -> post("packer") { put("user_id", write.userId) }
        is DeliveryNoteWrite.EditDeliveryAddress -> post("delivery-address") {
            putAddress(write.address)
            if (write.includesContact) {
                putContact(write.address)
            }
        }
    }

    private fun JsonObjectBuilder.putContact(address: DeliveryAddressFields) {
        put("contact_name", address.contactName.trimmedOrNull())
        put("company_name", address.companyName.trimmedOrNull())
        put("email", address.email.trimmedOrNull())
        put("phone", address.phone.trimmedOrNull())
    }

    private fun JsonObjectBuilder.putAddress(address: DeliveryAddressFields) {
        putJsonObject("address") {
            put("address_line_1", address.addressLine1.trimmedOrNull())
            put("address_line_2", address.addressLine2.trimmedOrNull())
            put("sorting_code", address.sortingCode.trimmedOrNull())
            put("postal_code", address.postalCode.trimmedOrNull())
            put("locality", address.locality.trimmedOrNull())
            put("dependent_locality", address.dependentLocality.trimmedOrNull())
            put("administrative_area", address.administrativeArea.trimmedOrNull())
            put("country_code", address.countryCode.trimmedOrNull()?.uppercase())
        }
    }

    fun scanPackBody(request: ScanPackRequest): JsonObject = buildJsonObject {
        put("barcode", request.barcode)
        put("quantity", request.quantity?.let(::quantityValue))
        put("delivery_note_item_id", request.deliveryNoteItemId)
    }

    fun insertPdfPath(leafletId: Long): String = "inserts/$leafletId/pdf"

    fun parseScanPack(statusCode: Int, body: String): GoodsOutResult<ScanPackOutcome> = when (statusCode) {
        HTTP_OK -> decode<ScanPackResponse>(body)
            ?.let {
                GoodsOutResult.Loaded(
                    ScanPackOutcome(note = it.data, status = it.result.status, message = it.result.message?.takeIf(String::isNotBlank), item = it.result.item),
                )
            }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> failure(statusCode, body)
    }

    fun parseShippers(statusCode: Int, body: String): GoodsOutResult<ShipperList> = when (statusCode) {
        HTTP_OK -> decode<ShippersResponse>(body)
            ?.let { GoodsOutResult.Loaded(ShipperList(shippers = it.data, directive = it.directive)) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> failure(statusCode, body)
    }

    fun scanPickBody(request: ScanPickRequest): JsonObject = buildJsonObject {
        put("barcode", request.barcode)
        put("quantity", request.quantity?.let(::quantityValue))
        put("delivery_note_item_id", request.deliveryNoteItemId)
        put("location_org_stock_id", request.locationOrgStockId)
    }

    fun parseWrite(statusCode: Int, body: String): GoodsOutResult<NoteActionOutcome> = when (statusCode) {
        HTTP_OK -> decode<WriteResponse>(body)
            ?.let {
                GoodsOutResult.Loaded(
                    NoteActionOutcome(
                        note = it.data,
                        status = it.result.status,
                        message = it.result.message?.takeIf(String::isNotBlank),
                        warning = it.result.warning?.takeIf(String::isNotBlank),
                    ),
                )
            }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> failure(statusCode, body)
    }

    fun parseScanPick(statusCode: Int, body: String): GoodsOutResult<ScanPickOutcome> = when (statusCode) {
        HTTP_OK -> decode<WriteResponse>(body)
            ?.let {
                GoodsOutResult.Loaded(
                    ScanPickOutcome(note = it.data, status = it.result.status, message = it.result.message?.takeIf(String::isNotBlank), item = it.result.item),
                )
            }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> failure(statusCode, body)
    }

    fun parseTrolleys(statusCode: Int, body: String): GoodsOutResult<List<Trolley>> = parseData(statusCode, body)

    fun parsePickedBays(statusCode: Int, body: String): GoodsOutResult<List<PickedBay>> = parseData(statusCode, body)

    fun parsePickers(statusCode: Int, body: String): GoodsOutResult<List<Picker>> = parseData(statusCode, body)

    private fun post(path: String, fields: JsonObjectBuilder.() -> Unit = {}): WriteRequest =
        WriteRequest(WriteMethod.Post, path, buildJsonObject(fields))

    private fun linePath(lineId: Long, action: String): String = "lines/$lineId/$action"

    internal fun quantityValue(quantity: Double): Number = if (quantity % 1.0 == 0.0) quantity.toLong() else quantity

    private fun String?.trimmedOrNull(): String? = this?.trim()?.takeIf { it.isNotEmpty() }

    fun parseDeliveryNotes(statusCode: Int, body: String): GoodsOutResult<DeliveryNotePage> = when (statusCode) {
        HTTP_OK -> decode<DeliveryNoteListResponse>(body)
            ?.let { response ->
                val notes = response.data.deliveryNotes
                GoodsOutResult.Loaded(
                    DeliveryNotePage(
                        notes = notes,
                        canWorkInWarehouse = response.data.canWorkInWarehouse,
                        pagination = response.pagination ?: GoodsOutPagination(total = notes.size),
                    ),
                )
            }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> failure(statusCode, body)
    }

    fun parseCounts(statusCode: Int, body: String): GoodsOutResult<DeliveryNoteCounts> = parseData(statusCode, body)

    fun parseDeliveryNote(statusCode: Int, body: String): GoodsOutResult<DeliveryNote> = parseData(statusCode, body)

    fun parseScan(statusCode: Int, body: String): GoodsOutResult<ScanLookup> = parseData(statusCode, body)

    private inline fun <reified T> parseData(statusCode: Int, body: String): GoodsOutResult<T> = when (statusCode) {
        HTTP_OK -> decode<GoodsOutData<T>>(body)
            ?.let { GoodsOutResult.Loaded(it.data) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> failure(statusCode, body)
    }

    internal fun failure(statusCode: Int, body: String): GoodsOutResult<Nothing> {
        val refusal = decode<GoodsOutRefusal>(body)
        val message = refusal?.firstMessage.orEmpty()
        return when (statusCode) {
            HTTP_UNAUTHORISED -> GoodsOutResult.SessionExpired
            HTTP_FORBIDDEN -> GoodsOutResult.Forbidden(message)
            HTTP_NOT_FOUND -> GoodsOutResult.NotFound(message)
            HTTP_CONFLICT, HTTP_UNPROCESSABLE -> GoodsOutResult.Invalid(message, refusal?.errorMessages.orEmpty())
            else -> GoodsOutResult.ServerError(statusCode)
        }
    }

    private inline fun <reified T> decode(body: String): T? = runCatching { json.decodeFromString<T>(body) }.getOrNull()
}
