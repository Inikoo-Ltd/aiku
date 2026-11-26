package io.aiku.droid.data.api

import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonObjectBuilder
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put

private const val HTTP_OK = 200
private const val INCIDENT_STATES = "incident_states"

@Serializable
private data class InboundData<T>(val data: T)

@Serializable
private data class InboundResult(val status: String = "", val message: String? = null)

@Serializable
private data class PalletDeliveryListResponse(
    val data: List<PalletDeliveryRow> = emptyList(),
    val meta: ListMeta = ListMeta(),
    val counts: JsonElement? = null,
)

@Serializable
private data class PalletListResponse(
    val data: List<Pallet> = emptyList(),
    val meta: ListMeta = ListMeta(),
    val counts: JsonElement? = null,
)

@Serializable
private data class PalletDeliveryWriteResponse(val data: PalletDelivery, val result: InboundResult = InboundResult())

@Serializable
private data class PalletWriteResponse(val data: Pallet, val result: InboundResult = InboundResult())

@Serializable
private data class AuditReference(val id: Long)

@Serializable
private data class AuditStartResponse(val data: AuditReference, val result: InboundResult = InboundResult())

object FulfilmentInboundResponses {
    private val json = ClockingResponses.json

    fun palletDeliveriesParameters(query: PalletDeliveryQuery): List<Pair<String, String>> = listOfNotNull(
        "state" to query.state.key,
        query.search.trimmedOrNull()?.let { "q" to it },
        query.from?.let { "from" to it.toString() },
        query.to?.let { "to" to it.toString() },
        query.sort?.let { "sort" to it.key },
        "page" to query.page.toString(),
        "per_page" to query.perPage.toString(),
    )

    fun palletsParameters(query: PalletQuery): List<Pair<String, String>> = listOfNotNull(
        "filter" to query.filter.key,
        query.incidentState?.takeIf { query.filter == PalletFilter.Incidents }?.let { "incident_state" to it.key },
        query.search.trimmedOrNull()?.let { "q" to it },
        "page" to query.page.toString(),
        "per_page" to query.perPage.toString(),
    )

    fun locationParameters(search: String): List<Pair<String, String>> = listOf("q" to search.trim())

    fun palletDeliveryWriteRequest(deliveryId: Long, write: PalletDeliveryWrite): WriteRequest = when (write) {
        PalletDeliveryWrite.Receive -> post("pallet-deliveries/$deliveryId/receive")
        PalletDeliveryWrite.StartBooking -> post("pallet-deliveries/$deliveryId/start-booking")
        PalletDeliveryWrite.FinishBooking -> post("pallet-deliveries/$deliveryId/finish-booking")
        is PalletDeliveryWrite.BookIn -> post("pallets/${write.palletId}/book-in") { putTarget(write.target) }
        is PalletDeliveryWrite.NotReceived -> post("pallets/${write.palletId}/not-received")
        is PalletDeliveryWrite.UndoNotReceived -> post("pallets/${write.palletId}/undo-not-received")
        PalletDeliveryWrite.NotReceivedRemaining -> post("pallet-deliveries/$deliveryId/not-received")
    }

    fun palletWriteRequest(write: PalletWrite): WriteRequest = when (write) {
        is PalletWrite.Move -> post("move") { putTarget(write.target) }
        is PalletWrite.Incident -> post("incident") {
            put("state", write.state.key)
            put("message", write.message.trimmedOrNull())
        }
        PalletWrite.BackToStoring -> post("back-to-storing")
    }

    fun auditStartRequest(): WriteRequest = post("audit")

    fun parsePalletDeliveries(statusCode: Int, body: String): GoodsOutResult<PalletDeliveryPage> = when (statusCode) {
        HTTP_OK -> decode<PalletDeliveryListResponse>(body)
            ?.let { GoodsOutResult.Loaded(PalletDeliveryPage(rows = it.data, meta = it.meta, counts = countsOf(it.counts))) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parsePalletDelivery(statusCode: Int, body: String): GoodsOutResult<PalletDelivery> = parseData(statusCode, body)

    fun parsePalletDeliveryWrite(statusCode: Int, body: String): GoodsOutResult<PalletDeliveryOutcome> = when (statusCode) {
        HTTP_OK -> decode<PalletDeliveryWriteResponse>(body)
            ?.let { GoodsOutResult.Loaded(PalletDeliveryOutcome(delivery = it.data, status = it.result.status, message = it.result.message.trimmedOrNull())) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parsePallets(statusCode: Int, body: String): GoodsOutResult<PalletPage> = when (statusCode) {
        HTTP_OK -> decode<PalletListResponse>(body)
            ?.let { response ->
                val counts = response.counts as? JsonObject
                GoodsOutResult.Loaded(
                    PalletPage(rows = response.data, meta = response.meta, counts = countsOf(counts), incidentCounts = countsOf(counts?.get(INCIDENT_STATES))),
                )
            }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parsePallet(statusCode: Int, body: String): GoodsOutResult<Pallet> = parseData(statusCode, body)

    fun parsePalletWrite(statusCode: Int, body: String): GoodsOutResult<PalletOutcome> = when (statusCode) {
        HTTP_OK -> decode<PalletWriteResponse>(body)
            ?.let { GoodsOutResult.Loaded(PalletOutcome(pallet = it.data, status = it.result.status, message = it.result.message.trimmedOrNull())) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseAuditStart(statusCode: Int, body: String): GoodsOutResult<PalletAuditStart> = when (statusCode) {
        HTTP_OK -> decode<AuditStartResponse>(body)
            ?.let { GoodsOutResult.Loaded(PalletAuditStart(auditId = it.data.id, message = it.result.message.trimmedOrNull())) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseLocations(statusCode: Int, body: String): GoodsOutResult<List<FulfilmentLocation>> = parseData(statusCode, body)

    private fun countsOf(element: JsonElement?): StateCounts? {
        val counts = element as? JsonObject ?: return null
        return StateCounts(
            counts.mapNotNull { (key, value) ->
                (value as? JsonPrimitive)?.takeUnless { it is JsonNull }?.content?.trim()?.toDoubleOrNull()?.let { key to it.toInt() }
            }.toMap(),
        )
    }

    private fun JsonObjectBuilder.putTarget(target: PalletLocationTarget) {
        when (target) {
            is PalletLocationTarget.Picked -> put("location_id", target.locationId)
            is PalletLocationTarget.Scanned -> put("location_code", target.code.trim())
        }
    }

    private fun post(path: String, fields: JsonObjectBuilder.() -> Unit = {}): WriteRequest =
        WriteRequest(WriteMethod.Post, path, buildJsonObject(fields))

    private inline fun <reified T> parseData(statusCode: Int, body: String): GoodsOutResult<T> = when (statusCode) {
        HTTP_OK -> decode<InboundData<T>>(body)
            ?.let { GoodsOutResult.Loaded(it.data) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    private inline fun <reified T> decode(body: String): T? = runCatching { json.decodeFromString<T>(body) }.getOrNull()

    private fun String?.trimmedOrNull(): String? = this?.trim()?.takeIf { it.isNotEmpty() }
}
