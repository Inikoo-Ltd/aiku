package io.aiku.droid.data.api

import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonObjectBuilder
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.decodeFromJsonElement
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.put

private const val HTTP_OK = 200
private const val HUB_ROWS = "rows"

@Serializable
private data class GoodsInData<T>(val data: T)

@Serializable
private data class GoodsInResult(val status: String = "", val message: String? = null)

@Serializable
private data class StockDeliveryListResponse(
    val data: List<StockDeliveryRow> = emptyList(),
    val meta: ListMeta = ListMeta(),
    val counts: JsonElement? = null,
)

@Serializable
private data class StockDeliveryWriteResponse(val data: StockDelivery, val result: GoodsInResult = GoodsInResult())

@Serializable
private data class ReturnListResponse(
    val data: List<CustomerReturnRow> = emptyList(),
    val meta: ListMeta = ListMeta(),
    val counts: JsonElement? = null,
)

@Serializable
private data class ReturnWriteResponse(val data: CustomerReturn, val result: GoodsInResult = GoodsInResult())

object GoodsInResponses {
    private val json = ClockingResponses.json

    fun stockDeliveriesParameters(query: StockDeliveryQuery): List<Pair<String, String>> = listOfNotNull(
        "state" to query.state.key,
        query.source?.let { "source" to it.key },
        query.search.trimmedOrNull()?.let { "q" to it },
        query.dispatched?.let { "dispatched" to it.key },
        query.sort?.let { "sort" to it.key },
        "page" to query.page.toString(),
        "per_page" to query.perPage.toString(),
    )

    fun returnsParameters(query: ReturnQuery): List<Pair<String, String>> = listOfNotNull(
        "state" to query.state.key,
        query.search.trimmedOrNull()?.let { "q" to it },
        "page" to query.page.toString(),
        "per_page" to query.perPage.toString(),
    )

    fun stockDeliveryWriteRequest(write: StockDeliveryWrite): WriteRequest = when (write) {
        StockDeliveryWrite.Receive -> post("receive")
        StockDeliveryWrite.Unreceive -> post("unreceive")
        StockDeliveryWrite.CheckAll -> post("check-all")
        is StockDeliveryWrite.Check -> post("items/${write.itemId}/check") { put("sko_quantity", GoodsOutResponses.quantityValue(write.skoQuantity)) }
        is StockDeliveryWrite.Place -> post("items/${write.itemId}/place") {
            put("quantity", GoodsOutResponses.quantityValue(write.quantity))
            putTarget(write.target)
            put("set_as_picking_location", write.setAsPickingLocation)
            put("client_request_id", write.clientRequestId)
        }
        is StockDeliveryWrite.PlaceAll -> post("items/${write.itemId}/place-all") {
            putTarget(write.target)
            put("set_as_picking_location", write.setAsPickingLocation)
            put("client_request_id", write.clientRequestId)
        }
        is StockDeliveryWrite.RecordCheck -> post("checklist") {
            put("key", write.key)
            put("result", write.result.key)
            put("body", write.body.trimmedOrNull())
            put("incident_type", write.incidentType.trimmedOrNull())
            put("incident_quantity", write.incidentQuantity?.let(GoodsOutResponses::quantityValue))
        }
    }

    fun returnWriteRequest(write: ReturnWrite): WriteRequest = when (write) {
        ReturnWrite.Start -> post("start")
        is ReturnWrite.Returned -> post("items/${write.itemId}/returned") {
            put("quantity", write.quantity?.let(GoodsOutResponses::quantityValue))
            put("location_org_stock_id", write.locationOrgStockId)
        }
        is ReturnWrite.Damaged -> post("items/${write.itemId}/damaged") { put("quantity", write.quantity?.let(GoodsOutResponses::quantityValue)) }
        is ReturnWrite.NotReturned -> post("items/${write.itemId}/not-returned") { put("quantity", write.quantity?.let(GoodsOutResponses::quantityValue)) }
        is ReturnWrite.UndoPut -> WriteRequest(WriteMethod.Delete, "puts/${write.putId}", body = null)
        ReturnWrite.SetAsReturned -> post("set-as-returned")
    }

    fun parseHub(statusCode: Int, body: String): GoodsOutResult<List<GoodsInHubRow>> = when (statusCode) {
        HTTP_OK -> dataElement(body)
            ?.let(::hubRows)
            ?.let { GoodsOutResult.Loaded(it) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseStockDeliveries(statusCode: Int, body: String): GoodsOutResult<StockDeliveryPage> = when (statusCode) {
        HTTP_OK -> decode<StockDeliveryListResponse>(body)
            ?.let { GoodsOutResult.Loaded(StockDeliveryPage(rows = it.data, meta = it.meta, counts = stateCounts(it.counts))) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseStockDelivery(statusCode: Int, body: String): GoodsOutResult<StockDelivery> = parseData(statusCode, body)

    fun parseStockDeliveryWrite(statusCode: Int, body: String): GoodsOutResult<StockDeliveryOutcome> = when (statusCode) {
        HTTP_OK -> decode<StockDeliveryWriteResponse>(body)
            ?.let { GoodsOutResult.Loaded(StockDeliveryOutcome(delivery = it.data, status = it.result.status, message = it.result.message.trimmedOrNull())) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseReturns(statusCode: Int, body: String): GoodsOutResult<ReturnPage> = when (statusCode) {
        HTTP_OK -> decode<ReturnListResponse>(body)
            ?.let { GoodsOutResult.Loaded(ReturnPage(rows = it.data, meta = it.meta, counts = stateCounts(it.counts))) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseReturn(statusCode: Int, body: String): GoodsOutResult<CustomerReturn> = parseData(statusCode, body)

    fun parseReturnWrite(statusCode: Int, body: String): GoodsOutResult<ReturnOutcome> = when (statusCode) {
        HTTP_OK -> decode<ReturnWriteResponse>(body)
            ?.let { GoodsOutResult.Loaded(ReturnOutcome(customerReturn = it.data, status = it.result.status, message = it.result.message.trimmedOrNull())) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    private fun hubRows(data: JsonElement): List<GoodsInHubRow>? {
        val rows = when (data) {
            is JsonArray -> data
            is JsonObject -> data[HUB_ROWS] as? JsonArray
            else -> null
        } ?: return null
        return runCatching { json.decodeFromJsonElement<List<GoodsInHubRow>>(rows) }.getOrNull()
    }

    private fun stateCounts(element: JsonElement?): StateCounts? {
        val counts = element as? JsonObject ?: return null
        return StateCounts(
            counts.mapNotNull { (key, value) ->
                (value as? JsonPrimitive)?.takeUnless { it is JsonNull }?.content?.trim()?.toDoubleOrNull()?.let { key to it.toInt() }
            }.toMap(),
        )
    }

    private fun JsonObjectBuilder.putTarget(target: PlaceTarget) {
        when (target) {
            is PlaceTarget.Link -> put("location_org_stock_id", target.locationOrgStockId)
            is PlaceTarget.Location -> put("location_id", target.locationId)
        }
    }

    private fun post(path: String, fields: JsonObjectBuilder.() -> Unit = {}): WriteRequest =
        WriteRequest(WriteMethod.Post, path, buildJsonObject(fields))

    private fun dataElement(body: String): JsonElement? = runCatching { json.parseToJsonElement(body).jsonObject["data"] }.getOrNull()

    private inline fun <reified T> parseData(statusCode: Int, body: String): GoodsOutResult<T> = when (statusCode) {
        HTTP_OK -> decode<GoodsInData<T>>(body)
            ?.let { GoodsOutResult.Loaded(it.data) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    private inline fun <reified T> decode(body: String): T? = runCatching { json.decodeFromString<T>(body) }.getOrNull()

    private fun String?.trimmedOrNull(): String? = this?.trim()?.takeIf { it.isNotEmpty() }
}
