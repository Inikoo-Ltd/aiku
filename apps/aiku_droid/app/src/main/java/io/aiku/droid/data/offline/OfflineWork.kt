package io.aiku.droid.data.offline

import java.time.Instant
import kotlinx.coroutines.flow.StateFlow
import kotlinx.serialization.json.JsonObject

enum class OfflineOperation(val wire: String) {
    PICK("pick"),
    NOT_PICKED("not_picked"),
    SET_WAITING_WAREHOUSE("set_waiting_warehouse"),
    SET_WAITING_CRM("set_waiting_crm"),
    PACK("pack"),
    MOVE_STOCK("move_stock"),
    PLACE_STOCK_DELIVERY_ITEM("place_stock_delivery_item"),
    RETURN_PUT_BACK("return_put_back"),
    RETURN_DAMAGED("return_damaged"),
    RETURN_NOT_RETURNED("return_not_returned"),
    AUDIT_LOCATION("audit_location"),
    AUDIT_ORG_STOCK("audit_org_stock"),
}

enum class OfflineRecordType { DELIVERY_NOTE, RETURN, STOCK_DELIVERY, LOCATION, ORG_STOCK }

data class OfflineRecordKey(val type: OfflineRecordType, val id: Long)

enum class QueuedActionState { WAITING, SENDING, APPLIED, NEEDS_YOU, VOID }

enum class SyncReason { QUEUED, RECONNECTED, FOREGROUND, CLOCK_OUT, MANUAL, PERIODIC }

data class DownloadedRecord(val body: String, val seenAt: Instant, val downloadedAt: Instant)

data class OfflineResult(
    val status: String,
    val operationId: Long?,
    val reason: String?,
    val instruction: String,
    val message: String?,
    val requestedQuantity: Double?,
    val appliedQuantity: Double?,
    val countedSince: Boolean,
    val taskReference: String?,
    val conflictingUserName: String?,
)

data class QueuedAction(
    val localId: Long,
    val userId: Long,
    val warehouseId: Long,
    val operation: OfflineOperation,
    val record: OfflineRecordKey,
    val payload: JsonObject,
    val clientRequestId: String,
    val performedAt: Instant,
    val timeSourceIsServer: Boolean,
    val seenAt: Instant?,
    val sequence: Long?,
    val state: QueuedActionState,
    val result: OfflineResult?,
)

interface OfflineWork {
    val isOnline: StateFlow<Boolean>

    val needsYouCount: StateFlow<Int>

    val waitingCount: StateFlow<Int>

    val queue: StateFlow<List<QueuedAction>>

    fun shouldQueue(record: OfflineRecordKey): Boolean

    fun enqueue(
        warehouseId: Long,
        operation: OfflineOperation,
        record: OfflineRecordKey,
        payload: JsonObject,
        clientRequestId: String,
        seenAt: Instant?,
    ): QueuedAction

    fun pendingFor(record: OfflineRecordKey): StateFlow<List<QueuedAction>>

    fun undo(localId: Long)

    suspend fun downloaded(record: OfflineRecordKey): DownloadedRecord?

    fun syncNow(reason: SyncReason)

    suspend fun flush(record: OfflineRecordKey): Boolean

    fun newClientRequestId(): String
}
