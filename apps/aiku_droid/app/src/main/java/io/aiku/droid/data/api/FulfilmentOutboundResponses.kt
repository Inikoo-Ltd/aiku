package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
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

private const val HTTP_OK = 200
private const val TYPE_COUNTS = "types"

@Serializable
private data class OutboundData<T>(val data: T)

@Serializable
private data class OutboundResult(
    val status: String = "",
    val message: String? = null,
    @SerialName("line_id") val lineId: Long? = null,
)

@Serializable
private data class PalletReturnListResponse(
    val data: List<PalletReturnRow> = emptyList(),
    val meta: ListMeta = ListMeta(),
    val counts: JsonElement? = null,
)

@Serializable
private data class PalletReturnWriteResponse(val data: PalletReturn, val result: OutboundResult = OutboundResult())

@Serializable
private data class StoredItemListResponse(
    val data: List<StoredItemRow> = emptyList(),
    val meta: ListMeta = ListMeta(),
    val counts: JsonElement? = null,
)

@Serializable
private data class AuditWriteResponse(val data: StoredItemAudit, val result: OutboundResult = OutboundResult())

object FulfilmentOutboundResponses {
    private val json = ClockingResponses.json

    fun palletReturnsParameters(query: PalletReturnQuery): List<Pair<String, String>> = listOfNotNull(
        "state" to query.state.key,
        query.type?.let { "type" to it.key },
        query.search.trimmedOrNull()?.let { "q" to it },
        "page" to query.page.toString(),
        "per_page" to query.perPage.toString(),
    )

    fun storedItemsParameters(query: StoredItemQuery): List<Pair<String, String>> = listOfNotNull(
        "state" to query.state.key,
        query.search.trimmedOrNull()?.let { "q" to it },
        "page" to query.page.toString(),
        "per_page" to query.perPage.toString(),
    )

    fun searchParameters(search: String?): List<Pair<String, String>> = listOfNotNull(search.trimmedOrNull()?.let { "q" to it })

    fun palletReturnWriteRequest(write: PalletReturnWrite): WriteRequest = when (write) {
        PalletReturnWrite.StartPicking -> post("start-picking")
        PalletReturnWrite.FinishPicking -> post("finish-picking")
        PalletReturnWrite.Dispatch -> post("dispatch")
        PalletReturnWrite.PrintInserts -> post("inserts/print")
        is PalletReturnWrite.PickPallet -> post(linePath(write.lineId, "pick"))
        is PalletReturnWrite.PickItem -> post(linePath(write.lineId, "pick")) {
            if (write.quantity == null) put("all", true) else put("quantity", write.quantity)
            put("client_request_id", write.clientRequestId)
        }
        is PalletReturnWrite.NotPicked -> post(linePath(write.lineId, "not-picked")) {
            write.quantity?.let { put("quantity", it) }
        }
        is PalletReturnWrite.Undo -> post(linePath(write.lineId, "undo"))
        is PalletReturnWrite.PickFromOtherPallet -> post("pallet-stored-items/${write.palletStoredItemId}/pick") {
            put("quantity", write.quantity)
            put("from_line_id", write.fromLineId)
            put("client_request_id", write.clientRequestId)
        }
        is PalletReturnWrite.SetParcels -> post("parcels") {
            putJsonArray("parcels") {
                write.parcels.forEach { parcel ->
                    addJsonObject {
                        put("weight_kg", GoodsOutResponses.quantityValue(parcel.weightKg))
                        putJsonArray("dimensions_cm") { parcel.dimensionsCm.forEach { add(GoodsOutResponses.quantityValue(it)) } }
                    }
                }
            }
        }
        is PalletReturnWrite.AddShipment -> post("shipments") {
            put("shipper_id", write.shipperId)
            put("tracking", write.tracking.trimmedOrNull())
        }
        is PalletReturnWrite.DeleteShipment -> WriteRequest(WriteMethod.Delete, "shipments/${write.shipmentId}", body = null)
        is PalletReturnWrite.PrintLabel -> post("shipments/${write.shipmentId}/print-label")
    }

    fun scanBody(request: PalletReturnScanRequest): JsonObject = buildJsonObject {
        put("barcode", request.barcode)
        put("quantity", request.quantity)
        put("client_request_id", request.clientRequestId)
    }

    fun auditWriteRequest(write: StoredItemAuditWrite): WriteRequest = when (write) {
        is StoredItemAuditWrite.Count -> post("count") {
            putTarget(write.target)
            put("audited_quantity", write.auditedQuantity)
        }
        is StoredItemAuditWrite.Add -> post("count") {
            putTarget(write.target)
            put("add", write.add)
            put("client_request_id", write.clientRequestId)
        }
        is StoredItemAuditWrite.RemoveCount -> WriteRequest(WriteMethod.Delete, "deltas/${write.deltaId}", body = null)
        StoredItemAuditWrite.Complete -> post("complete")
    }

    fun parsePalletReturns(statusCode: Int, body: String): GoodsOutResult<PalletReturnPage> = when (statusCode) {
        HTTP_OK -> decode<PalletReturnListResponse>(body)
            ?.let { GoodsOutResult.Loaded(PalletReturnPage(rows = it.data, meta = it.meta, counts = returnCounts(it.counts))) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parsePalletReturn(statusCode: Int, body: String): GoodsOutResult<PalletReturn> = parseData(statusCode, body)

    fun parsePalletReturnWrite(statusCode: Int, body: String): GoodsOutResult<PalletReturnOutcome> = when (statusCode) {
        HTTP_OK -> decode<PalletReturnWriteResponse>(body)
            ?.let { GoodsOutResult.Loaded(PalletReturnOutcome(palletReturn = it.data, status = it.result.status, message = it.result.message.trimmedOrNull())) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseScan(statusCode: Int, body: String): GoodsOutResult<PalletReturnScanOutcome> = when (statusCode) {
        HTTP_OK -> decode<PalletReturnWriteResponse>(body)
            ?.let {
                GoodsOutResult.Loaded(
                    PalletReturnScanOutcome(palletReturn = it.data, status = it.result.status, message = it.result.message.trimmedOrNull(), lineId = it.result.lineId),
                )
            }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseShippers(statusCode: Int, body: String): GoodsOutResult<List<Shipper>> = parseData(statusCode, body)

    fun parseStoredItems(statusCode: Int, body: String): GoodsOutResult<StoredItemPage> = when (statusCode) {
        HTTP_OK -> decode<StoredItemListResponse>(body)
            ?.let { GoodsOutResult.Loaded(StoredItemPage(rows = it.data, meta = it.meta, counts = flatCounts(it.counts))) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseStoredItem(statusCode: Int, body: String): GoodsOutResult<StoredItemDetail> = parseData(statusCode, body)

    fun parseAudit(statusCode: Int, body: String): GoodsOutResult<StoredItemAudit> = parseData(statusCode, body)

    fun parseAuditWrite(statusCode: Int, body: String): GoodsOutResult<StoredItemAuditOutcome> = when (statusCode) {
        HTTP_OK -> decode<AuditWriteResponse>(body)
            ?.let { GoodsOutResult.Loaded(StoredItemAuditOutcome(audit = it.data, status = it.result.status, message = it.result.message.trimmedOrNull())) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseAuditStoredItemChoices(statusCode: Int, body: String): GoodsOutResult<List<OutboundStoredItemRef>> = parseData(statusCode, body)

    private fun returnCounts(element: JsonElement?): PalletReturnCounts? {
        val counts = element as? JsonObject ?: return null
        return PalletReturnCounts(states = flatCounts(counts) ?: StateCounts(), types = flatCounts(counts[TYPE_COUNTS]) ?: StateCounts())
    }

    private fun flatCounts(element: JsonElement?): StateCounts? {
        val counts = element as? JsonObject ?: return null
        return StateCounts(
            counts.mapNotNull { (key, value) ->
                (value as? JsonPrimitive)?.takeUnless { it is JsonNull }?.content?.trim()?.toDoubleOrNull()?.let { key to it.toInt() }
            }.toMap(),
        )
    }

    private fun JsonObjectBuilder.putTarget(target: AuditItemTarget) {
        when (target) {
            is AuditItemTarget.Item -> put("stored_item_id", target.storedItemId)
            is AuditItemTarget.Barcode -> put("barcode", target.code.trim())
        }
    }

    private fun linePath(lineId: Long, action: String): String = "lines/$lineId/$action"

    private fun post(path: String, fields: JsonObjectBuilder.() -> Unit = {}): WriteRequest =
        WriteRequest(WriteMethod.Post, path, buildJsonObject(fields))

    private inline fun <reified T> parseData(statusCode: Int, body: String): GoodsOutResult<T> = when (statusCode) {
        HTTP_OK -> decode<OutboundData<T>>(body)
            ?.let { GoodsOutResult.Loaded(it.data) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    private inline fun <reified T> decode(body: String): T? = runCatching { json.decodeFromString<T>(body) }.getOrNull()

    private fun String?.trimmedOrNull(): String? = this?.trim()?.takeIf { it.isNotEmpty() }
}
