package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put

private const val HTTP_OK = 200

enum class OfflineOperationsState(val key: String) {
    Open("open"),
    Recent("recent"),
}

enum class OfflineResolution(val key: String) {
    PutBack("put_back"),
    Done("done"),
    Retry("retry"),
    Recount("recount"),
    Discard("discard"),
    KeepCount("keep_count"),
    ;

    companion object {
        fun ofKey(key: String?): OfflineResolution? = entries.firstOrNull { it.key == key }
    }
}

object OfflineOutcomeStatus {
    const val APPLIED = "applied"
    const val PARTLY_APPLIED = "partly_applied"
    const val REFUSED = "refused"
    const val CONFLICT = "conflict"
    const val BLOCKED = "blocked"
    const val VOID = "void"
    const val ALREADY_DONE = "already_done"
}

object OfflineInstruction {
    const val NONE = "none"
    const val PUT_BACK = "put_back"
    const val UNPACK = "unpack"
    const val KEEP_IN_TOTE = "keep_in_tote"
    const val PICK_AGAIN = "pick_again"
    const val RECOUNT = "recount"
    const val CHECK_SHELF = "check_shelf"
}

object OfflineRecordKind {
    const val DELIVERY_NOTE = "delivery_note"
    const val RETURN = "return"
    const val STOCK_DELIVERY = "stock_delivery"
    const val LOCATION = "location"
    const val ORG_STOCK = "org_stock"
}

@Serializable
data class OfflineOperationLabels(
    val title: String = "",
    val subtitle: String? = null,
    @SerialName("location_code") val locationCode: String? = null,
)

@Serializable
data class OfflineCountedRow(
    @SerialName("location_code") val locationCode: String? = null,
    @SerialName("sko_code") val skoCode: String? = null,
    @SerialName("sko_name") val skoName: String? = null,
    @Serializable(with = LenientDoubleOrNull::class) val counted: Double? = null,
    @Serializable(with = LenientDoubleOrNull::class) val quantity: Double? = null,
)

@Serializable
data class OfflineOperationRecord(val type: String = "", val id: Long = 0)

@Serializable
data class OfflineOperationEntry(
    @Serializable(with = LenientIntOrNull::class) val sequence: Int? = null,
    val status: String = "",
    @SerialName("operation_id") val operationId: Long? = null,
    val id: Long? = null,
    val operation: String = "",
    val reason: String? = null,
    val instruction: String = OfflineInstruction.NONE,
    val message: String? = null,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("requested_quantity") val requestedQuantity: Double? = null,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("applied_quantity") val appliedQuantity: Double? = null,
    @SerialName("counted_since") val countedSince: Boolean = false,
    @SerialName("task_reference") val taskReference: String? = null,
    @SerialName("conflicting_user") val conflictingUser: StaffRef? = null,
    @Serializable(with = LenientIntOrNull::class) @SerialName("blocked_by_sequence") val blockedBySequence: Int? = null,
    @SerialName("performed_at") val performedAt: String? = null,
    @SerialName("synced_at") val syncedAt: String? = null,
    val state: String? = null,
    val resolution: String? = null,
    val labels: OfflineOperationLabels = OfflineOperationLabels(),
    val record: OfflineOperationRecord? = null,
    val resolutions: List<String> = emptyList(),
    @SerialName("can_rollback") val canRollback: Boolean = false,
    @SerialName("counted_rows") val countedRows: List<OfflineCountedRow> = emptyList(),
    val item: OfflineOperationItem? = null,
) {
    val key: Long? get() = operationId ?: id

    val currentState: String get() = state ?: status

    val allowedResolutions: List<OfflineResolution> get() = resolutions.mapNotNull(OfflineResolution::ofKey)

    val missingQuantity: Double?
        get() {
            val requested = requestedQuantity ?: return null
            val applied = appliedQuantity ?: 0.0
            return (requested - applied).takeIf { it > 0.0 }
        }
}

@Serializable
data class OfflineOperationItem(
    @SerialName("sko_code") val skoCode: String? = null,
    @SerialName("sko_name") val skoName: String? = null,
    @SerialName("image_url") val imageUrl: String? = null,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("packed_in") val packedIn: Double? = null,
    @SerialName("record_type") val recordType: String? = null,
    @SerialName("record_reference") val recordReference: String? = null,
    @Serializable(with = LenientIntOrNull::class) val line: Int? = null,
    val quantities: OfflineItemQuantities? = null,
)

@Serializable
data class OfflineItemQuantities(
    @Serializable(with = LenientDoubleOrNull::class) val required: Double? = null,
    @Serializable(with = LenientDoubleOrNull::class) val picked: Double? = null,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("not_picked") val notPicked: Double? = null,
    @Serializable(with = LenientDoubleOrNull::class) val packed: Double? = null,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("stock_now") val stockNow: Double? = null,
)

@Serializable
data class OfflineResolveOutcome(val operation: OfflineOperationEntry, val results: List<OfflineOperationEntry>)

@Serializable
private data class OperationListResponse(val data: List<OfflineOperationEntry> = emptyList())

@Serializable
private data class ResolveData(val operation: OfflineOperationEntry, val results: List<OfflineOperationEntry> = emptyList())

@Serializable
private data class ResolveResponse(val data: ResolveData)

@Serializable
private data class RollbackData(val operation: OfflineOperationEntry)

@Serializable
private data class RollbackResponse(val data: RollbackData)

object OfflineOperationsResponses {
    private val json = ClockingResponses.json

    fun listParameters(state: OfflineOperationsState): List<Pair<String, String>> = listOf("state" to state.key)

    fun resolveBody(resolution: OfflineResolution): String = buildJsonObject { put("resolution", resolution.key) }.toString()

    fun rollbackBody(reason: String?): String = buildJsonObject {
        reason?.trim()?.takeIf { it.isNotEmpty() }?.let { put("reason", it) }
    }.toString()

    fun parseList(statusCode: Int, body: String): GoodsOutResult<List<OfflineOperationEntry>> = when (statusCode) {
        HTTP_OK -> decode<OperationListResponse>(body)?.let { GoodsOutResult.Loaded(it.data) } ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseResolve(statusCode: Int, body: String): GoodsOutResult<OfflineResolveOutcome> = when (statusCode) {
        HTTP_OK -> decode<ResolveResponse>(body)
            ?.let { GoodsOutResult.Loaded(OfflineResolveOutcome(operation = it.data.operation, results = it.data.results)) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseRollback(statusCode: Int, body: String): GoodsOutResult<OfflineOperationEntry> = when (statusCode) {
        HTTP_OK -> decode<RollbackResponse>(body)?.let { GoodsOutResult.Loaded(it.data.operation) } ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    private inline fun <reified T> decode(body: String): T? = runCatching { json.decodeFromString<T>(body) }.getOrNull()
}

interface OfflineOperationsApi {
    suspend fun operations(token: String, state: OfflineOperationsState): GoodsOutResult<List<OfflineOperationEntry>>

    suspend fun resolve(token: String, operationId: Long, resolution: OfflineResolution): GoodsOutResult<OfflineResolveOutcome>

    suspend fun rollback(token: String, operationId: Long, reason: String?): GoodsOutResult<OfflineOperationEntry>
}

class OkHttpOfflineOperationsApi(private val http: OkHttpMobileApi) : OfflineOperationsApi {

    private val operationsUrl = "${http.mobileApiUrl}/offline/operations"

    override suspend fun operations(token: String, state: OfflineOperationsState): GoodsOutResult<List<OfflineOperationEntry>> =
        http.execute(http.get(http.urlWith(operationsUrl, OfflineOperationsResponses.listParameters(state)), token))
            ?.let { (statusCode, body) -> OfflineOperationsResponses.parseList(statusCode, body) }
            ?: GoodsOutResult.Unreachable

    override suspend fun resolve(token: String, operationId: Long, resolution: OfflineResolution): GoodsOutResult<OfflineResolveOutcome> =
        http.execute(http.post("$operationsUrl/$operationId/resolve", OfflineOperationsResponses.resolveBody(resolution), bearerToken = token))
            ?.let { (statusCode, body) -> OfflineOperationsResponses.parseResolve(statusCode, body) }
            ?: GoodsOutResult.Unreachable

    override suspend fun rollback(token: String, operationId: Long, reason: String?): GoodsOutResult<OfflineOperationEntry> =
        http.execute(http.post("$operationsUrl/$operationId/rollback", OfflineOperationsResponses.rollbackBody(reason), bearerToken = token))
            ?.let { (statusCode, body) -> OfflineOperationsResponses.parseRollback(statusCode, body) }
            ?: GoodsOutResult.Unreachable
}
