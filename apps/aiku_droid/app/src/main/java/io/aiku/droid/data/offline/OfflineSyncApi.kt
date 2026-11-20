package io.aiku.droid.data.offline

import kotlinx.coroutines.CoroutineDispatcher
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.booleanOrNull
import kotlinx.serialization.json.buildJsonArray
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.contentOrNull
import kotlinx.serialization.json.doubleOrNull
import kotlinx.serialization.json.longOrNull
import kotlinx.serialization.json.put
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.IOException
import java.time.Instant
import java.time.ZoneOffset
import java.time.format.DateTimeFormatter

data class SyncActionRequest(
    val sequence: Long,
    val operation: String,
    val performedAt: Instant,
    val timeSourceIsServer: Boolean,
    val seenAt: Instant?,
    val payload: JsonObject,
)

data class SyncResult(val sequence: Long, val status: String, val result: OfflineResult)

sealed interface SyncAnswer {
    data class Answered(val results: List<SyncResult>, val expectedSequence: Long?) : SyncAnswer
    data object SessionExpired : SyncAnswer
    data object Unreachable : SyncAnswer
    data class Failed(val statusCode: Int) : SyncAnswer
}

data class OpenOperation(val operationId: Long, val result: OfflineResult)

sealed interface OpenOperationsAnswer {
    data class Loaded(val operations: List<OpenOperation>) : OpenOperationsAnswer
    data object Unavailable : OpenOperationsAnswer
}

enum class NoticeAnswer { Sent, Unreachable, Refused }

interface OfflineSyncApi {
    suspend fun sync(token: String, warehouseId: Long, actions: List<SyncActionRequest>): SyncAnswer

    suspend fun openOperations(token: String): OpenOperationsAnswer

    suspend fun waitingNotice(token: String, userId: Long, count: Int, oldestPerformedAt: Instant): NoticeAnswer

    suspend fun refetch(token: String, path: String): Boolean
}

object OfflineSyncResponses {
    const val STATUS_APPLIED = "applied"
    const val STATUS_PARTLY_APPLIED = "partly_applied"
    const val STATUS_REFUSED = "refused"
    const val STATUS_CONFLICT = "conflict"
    const val STATUS_BLOCKED = "blocked"
    const val STATUS_VOID = "void"
    const val STATUS_ALREADY_DONE = "already_done"
    const val STATUS_MISSING_PREVIOUS = "missing_previous"
    const val OPERATION_VOID = "void"
    private const val INSTRUCTION_NONE = "none"

    private val json = kotlinx.serialization.json.Json { ignoreUnknownKeys = true }
    private val wireTime: DateTimeFormatter = DateTimeFormatter.ofPattern("yyyy-MM-dd'T'HH:mm:ss.SSS'Z'").withZone(ZoneOffset.UTC)

    fun time(instant: Instant): String = wireTime.format(instant)

    fun syncBody(actions: List<SyncActionRequest>): String = buildJsonObject {
        put(
            "actions",
            buildJsonArray {
                actions.forEach { action ->
                    add(
                        buildJsonObject {
                            put("sequence", action.sequence)
                            put("operation", action.operation)
                            put("performed_at", time(action.performedAt))
                            put("time_source", if (action.timeSourceIsServer) "server" else "device")
                            put("seen_at", action.seenAt?.let(::time)?.let(::JsonPrimitive) ?: JsonNull)
                            put("payload", action.payload)
                        },
                    )
                }
            },
        )
    }.toString()

    fun noticeBody(userId: Long, count: Int, oldestPerformedAt: Instant): String = buildJsonObject {
        put("user_id", userId)
        put("count", count)
        put("oldest_performed_at", time(oldestPerformedAt))
    }.toString()

    fun parseSync(statusCode: Int, body: String): SyncAnswer = when (statusCode) {
        HTTP_OK -> {
            val data = objectOf(body)?.get("data") as? JsonObject
            if (data == null) {
                SyncAnswer.Failed(statusCode)
            } else {
                SyncAnswer.Answered(
                    results = (data["results"] as? JsonArray).orEmpty().mapNotNull { element ->
                        val item = element as? JsonObject ?: return@mapNotNull null
                        val sequence = item.long("sequence") ?: return@mapNotNull null
                        val result = resultOf(item) ?: return@mapNotNull null
                        SyncResult(sequence = sequence, status = result.status, result = result)
                    },
                    expectedSequence = data.long("expected_sequence"),
                )
            }
        }
        HTTP_UNAUTHORISED -> SyncAnswer.SessionExpired
        else -> SyncAnswer.Failed(statusCode)
    }

    fun parseOpenOperations(statusCode: Int, body: String): OpenOperationsAnswer {
        if (statusCode != HTTP_OK) {
            return OpenOperationsAnswer.Unavailable
        }
        val items = objectOf(body)?.get("data") as? JsonArray ?: return OpenOperationsAnswer.Unavailable
        return OpenOperationsAnswer.Loaded(
            items.mapNotNull { element ->
                val item = element as? JsonObject ?: return@mapNotNull null
                val result = resultOf(item) ?: return@mapNotNull null
                val operationId = result.operationId ?: item.long("id") ?: return@mapNotNull null
                OpenOperation(operationId = operationId, result = result.copy(operationId = operationId))
            },
        )
    }

    fun resultOf(item: JsonObject): OfflineResult? {
        val status = item.string("status") ?: item.string("state") ?: return null
        return OfflineResult(
            status = status,
            operationId = item.long("operation_id"),
            reason = item.string("reason"),
            instruction = item.string("instruction") ?: INSTRUCTION_NONE,
            message = item.string("message"),
            requestedQuantity = item.double("requested_quantity"),
            appliedQuantity = item.double("applied_quantity"),
            countedSince = (item["counted_since"] as? JsonPrimitive)?.booleanOrNull ?: false,
            taskReference = item.string("task_reference"),
            conflictingUserName = (item["conflicting_user"] as? JsonObject)?.string("name"),
        )
    }

    fun resultJson(result: OfflineResult): String = buildJsonObject {
        put("status", result.status)
        result.operationId?.let { put("operation_id", it) }
        result.reason?.let { put("reason", it) }
        put("instruction", result.instruction)
        result.message?.let { put("message", it) }
        result.requestedQuantity?.let { put("requested_quantity", it) }
        result.appliedQuantity?.let { put("applied_quantity", it) }
        put("counted_since", result.countedSince)
        result.taskReference?.let { put("task_reference", it) }
        result.conflictingUserName?.let { name -> put("conflicting_user", buildJsonObject { put("name", name) }) }
    }.toString()

    fun parseResultJson(body: String): OfflineResult? = objectOf(body)?.let(::resultOf)

    private fun objectOf(body: String): JsonObject? = runCatching { json.parseToJsonElement(body) as? JsonObject }.getOrNull()

    private fun JsonObject.primitive(key: String): JsonPrimitive? = (this[key] as? JsonPrimitive)?.takeIf { it !is JsonNull }

    private fun JsonObject.string(key: String): String? = primitive(key)?.contentOrNull?.takeIf { it.isNotBlank() }

    private fun JsonObject.long(key: String): Long? = primitive(key)?.let { it.longOrNull ?: it.doubleOrNull?.toLong() }

    private fun JsonObject.double(key: String): Double? = primitive(key)?.doubleOrNull

    private const val HTTP_OK = 200
    private const val HTTP_UNAUTHORISED = 401
}

class OkHttpOfflineSyncApi(
    private val client: () -> OkHttpClient,
    baseUrl: String,
    private val ioDispatcher: CoroutineDispatcher = Dispatchers.IO,
) : OfflineSyncApi {

    private val mobileApiUrl = baseUrl.trimEnd('/') + "/app/api/mobile"
    private val jsonMediaType = "application/json; charset=utf-8".toMediaType()

    override suspend fun sync(token: String, warehouseId: Long, actions: List<SyncActionRequest>): SyncAnswer =
        execute(post("$mobileApiUrl/warehouses/$warehouseId/offline/sync", OfflineSyncResponses.syncBody(actions), token))
            ?.let { (statusCode, body) -> OfflineSyncResponses.parseSync(statusCode, body) }
            ?: SyncAnswer.Unreachable

    override suspend fun openOperations(token: String): OpenOperationsAnswer =
        execute(get("$mobileApiUrl/offline/operations?state=open", token))
            ?.let { (statusCode, body) -> OfflineSyncResponses.parseOpenOperations(statusCode, body) }
            ?: OpenOperationsAnswer.Unavailable

    override suspend fun waitingNotice(token: String, userId: Long, count: Int, oldestPerformedAt: Instant): NoticeAnswer =
        execute(post("$mobileApiUrl/offline/waiting-notice", OfflineSyncResponses.noticeBody(userId, count, oldestPerformedAt), token))
            ?.let { (statusCode, _) -> if (statusCode in HTTP_SUCCESS) NoticeAnswer.Sent else NoticeAnswer.Refused }
            ?: NoticeAnswer.Unreachable

    override suspend fun refetch(token: String, path: String): Boolean =
        execute(get("$mobileApiUrl/$path", token)) != null

    private fun get(url: String, token: String): Request = Request.Builder().url(url).get().header("Authorization", "Bearer $token").build()

    private fun post(url: String, body: String, token: String): Request =
        Request.Builder().url(url).post(body.toRequestBody(jsonMediaType)).header("Authorization", "Bearer $token").build()

    private suspend fun execute(request: Request): Pair<Int, String>? = withContext(ioDispatcher) {
        try {
            client().newCall(request).execute().use { response -> response.code to response.body.string() }
        } catch (exception: IOException) {
            null
        }
    }

    private companion object {
        val HTTP_SUCCESS = 200..299
    }
}
