package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.decodeFromJsonElement
import kotlinx.serialization.json.intOrNull
import kotlinx.serialization.json.put
import kotlinx.serialization.json.putJsonArray

private const val HTTP_OK = 200
private const val HTTP_CREATED = 201

private val sessionRowKeys = listOf("picking_sessions", "sessions")

@Serializable
private data class SessionData(val data: PickingSession)

@Serializable
private data class SessionWriteResult(
    val status: String = "",
    val message: String? = null,
    val item: ScanPickItem? = null,
    @SerialName("delivery_note") val deliveryNote: DeliveryNoteRef? = null,
)

@Serializable
private data class SessionWriteResponse(
    val data: PickingSession,
    val result: SessionWriteResult = SessionWriteResult(),
)

@Serializable
private data class SessionPackResult(
    val status: String = "",
    val message: String? = null,
    val item: ScanPackItem? = null,
    @SerialName("delivery_note") val deliveryNote: DeliveryNoteRef? = null,
)

@Serializable
private data class SessionPackResponse(
    val data: PickingSession,
    val result: SessionPackResult = SessionPackResult(),
)

@Serializable
private data class PersonnelResponse(val data: List<DispatchPerson> = emptyList())

object PickingSessionResponses {
    private val json = ClockingResponses.json

    fun listParameters(state: PickingSessionState?, page: Int): List<Pair<String, String>> =
        listOfNotNull(state?.let { "state" to it.key }, "page" to page.toString())

    fun personnelParameters(role: DispatchRole): List<Pair<String, String>> = listOf("role" to role.key)

    fun writeRequest(write: PickingSessionWrite): WriteRequest = when (write) {
        PickingSessionWrite.StartPicking -> post("start-picking")
        PickingSessionWrite.BackToPicking -> post("back-to-picking")
        is PickingSessionWrite.AttachTrolley -> WriteRequest(WriteMethod.Post, "trolleys", buildJsonObject { put("trolley_id", write.trolleyId) })
        is PickingSessionWrite.DetachTrolley -> WriteRequest(WriteMethod.Delete, "trolleys/${write.trolleyId}", body = null)
        is PickingSessionWrite.AddDeliveryNotes -> WriteRequest(WriteMethod.Post, "delivery-notes", noteIdsBody(write.deliveryNoteIds))
        is PickingSessionWrite.RemoveDeliveryNotes -> WriteRequest(WriteMethod.Post, "delivery-notes/remove", noteIdsBody(write.deliveryNoteIds))
    }

    fun createBody(deliveryNoteIds: List<Long>): JsonObject = noteIdsBody(deliveryNoteIds)

    fun scanPickBody(request: ScanPickRequest): JsonObject = GoodsOutResponses.scanPickBody(request)

    fun scanPackBody(request: ScanPackRequest): JsonObject = GoodsOutResponses.scanPackBody(request)

    fun parseList(statusCode: Int, body: String): GoodsOutResult<PickingSessionPage> {
        if (statusCode != HTTP_OK) {
            return GoodsOutResponses.failure(statusCode, body)
        }
        val root = element(body) as? JsonObject ?: return GoodsOutResult.ServerError(statusCode)
        val data = root["data"]
        val rows = when (data) {
            is JsonArray -> data
            is JsonObject -> sessionRowKeys.firstNotNullOfOrNull { data[it] as? JsonArray }
            else -> null
        } ?: return GoodsOutResult.ServerError(statusCode)
        val sessions = runCatching { rows.map { json.decodeFromJsonElement<PickingSessionRow>(it) } }.getOrNull()
            ?: return GoodsOutResult.ServerError(statusCode)
        val counts = countsOf(root["counts"]) ?: countsOf((data as? JsonObject)?.get("counts")).orEmpty()
        val pagination = root["pagination"]?.let { runCatching { json.decodeFromJsonElement<GoodsOutPagination>(it) }.getOrNull() }
            ?: GoodsOutPagination(total = sessions.size)
        return GoodsOutResult.Loaded(PickingSessionPage(sessions = sessions, counts = counts, pagination = pagination))
    }

    fun parseSession(statusCode: Int, body: String): GoodsOutResult<PickingSession> = when (statusCode) {
        HTTP_OK, HTTP_CREATED -> decode<SessionData>(body)?.let { GoodsOutResult.Loaded(it.data) } ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseWrite(statusCode: Int, body: String): GoodsOutResult<SessionActionOutcome> = when (statusCode) {
        HTTP_OK -> decode<SessionWriteResponse>(body)
            ?.let { GoodsOutResult.Loaded(SessionActionOutcome(session = it.data, status = it.result.status, message = it.result.message?.takeIf(String::isNotBlank))) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseScanPick(statusCode: Int, body: String): GoodsOutResult<SessionScanPickOutcome> = when (statusCode) {
        HTTP_OK -> decode<SessionWriteResponse>(body)
            ?.let {
                GoodsOutResult.Loaded(
                    SessionScanPickOutcome(
                        session = it.data,
                        status = it.result.status,
                        message = it.result.message?.takeIf(String::isNotBlank),
                        item = it.result.item,
                        deliveryNote = it.result.deliveryNote,
                    ),
                )
            }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseScanPack(statusCode: Int, body: String): GoodsOutResult<SessionScanPackOutcome> = when (statusCode) {
        HTTP_OK -> decode<SessionPackResponse>(body)
            ?.let {
                GoodsOutResult.Loaded(
                    SessionScanPackOutcome(
                        session = it.data,
                        status = it.result.status,
                        message = it.result.message?.takeIf(String::isNotBlank),
                        item = it.result.item,
                        deliveryNote = it.result.deliveryNote,
                    ),
                )
            }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parsePersonnel(statusCode: Int, body: String): GoodsOutResult<List<DispatchPerson>> = when (statusCode) {
        HTTP_OK -> decode<PersonnelResponse>(body)?.let { GoodsOutResult.Loaded(it.data) } ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    private fun countsOf(element: JsonElement?): Map<String, Int>? = (element as? JsonObject)
        ?.mapNotNull { (state, value) -> (value as? JsonPrimitive)?.intOrNull?.let { state to it } }
        ?.toMap()

    private fun noteIdsBody(deliveryNoteIds: List<Long>): JsonObject = buildJsonObject {
        putJsonArray("delivery_note_ids") { deliveryNoteIds.forEach { add(JsonPrimitive(it)) } }
    }

    private fun post(path: String): WriteRequest = WriteRequest(WriteMethod.Post, path, JsonObject(emptyMap()))

    private fun element(body: String): JsonElement? = runCatching { json.parseToJsonElement(body) }.getOrNull()

    private inline fun <reified T> decode(body: String): T? = runCatching { json.decodeFromString<T>(body) }.getOrNull()
}
