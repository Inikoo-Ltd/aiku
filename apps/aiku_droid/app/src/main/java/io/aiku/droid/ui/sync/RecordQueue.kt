package io.aiku.droid.ui.sync

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.offline.OfflineOperation
import io.aiku.droid.data.offline.OfflineRecordKey
import io.aiku.droid.data.offline.OfflineWork
import io.aiku.droid.data.offline.QueuedAction
import io.aiku.droid.data.offline.QueuedActionState
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.doubleOrNull
import kotlinx.serialization.json.longOrNull
import java.time.Instant

const val CLIENT_REQUEST_ID = "client_request_id"

fun JsonObject.longField(key: String): Long? = (this[key] as? JsonPrimitive)?.longOrNull

fun JsonObject.doubleField(key: String): Double? = (this[key] as? JsonPrimitive)?.doubleOrNull

val QueuedAction.isUnsent: Boolean get() = state == QueuedActionState.WAITING || state == QueuedActionState.SENDING

val QueuedAction.canUndo: Boolean get() = state == QueuedActionState.WAITING

data class RecordLoad<out T>(val result: GoodsOutResult<T>, val copyAt: Instant? = null)

sealed interface QueueEvent {
    data class Queued(val operation: OfflineOperation) : QueueEvent
    data object OnlineOnly : QueueEvent
    data object NeedsYou : QueueEvent
}

enum class StatusGate { Go, OnlineOnly, NeedsYou }

class RecordQueue(
    private val offlineWork: OfflineWork,
    private val warehouseId: Long,
    val record: OfflineRecordKey,
) {
    val online: StateFlow<Boolean> = offlineWork.isOnline

    val isOnline: Boolean get() = online.value

    val pending: StateFlow<List<QueuedAction>> = offlineWork.pendingFor(record)

    fun shouldQueue(): Boolean = offlineWork.shouldQueue(record)

    fun newClientRequestId(): String = offlineWork.newClientRequestId()

    suspend fun <T> load(live: suspend () -> GoodsOutResult<T>, parse: (String) -> GoodsOutResult<T>): RecordLoad<T> {
        if (isOnline) {
            val result = live()
            if (result != GoodsOutResult.Unreachable) {
                return RecordLoad(result)
            }
        }
        val copy = offlineWork.downloaded(record) ?: return RecordLoad(GoodsOutResult.Unreachable)
        val parsed = parse(copy.body)
        return if (parsed is GoodsOutResult.Loaded) RecordLoad(parsed, copyAt = copy.downloadedAt) else RecordLoad(GoodsOutResult.Unreachable)
    }

    suspend fun enqueue(operation: OfflineOperation, payload: JsonObject): QueuedAction? {
        val clientRequestId = (payload[CLIENT_REQUEST_ID] as? JsonPrimitive)?.content ?: offlineWork.newClientRequestId()
        val seenAt = offlineWork.downloaded(record)?.seenAt
        return try {
            offlineWork.enqueue(
                warehouseId = warehouseId,
                operation = operation,
                record = record,
                payload = payload,
                clientRequestId = clientRequestId,
                seenAt = seenAt,
            )
        } catch (unavailable: IllegalStateException) {
            null
        }
    }

    suspend fun statusGate(): StatusGate = when {
        !isOnline -> StatusGate.OnlineOnly
        offlineWork.flush(record) -> StatusGate.Go
        else -> StatusGate.NeedsYou
    }

    fun undo(localId: Long) = offlineWork.undo(localId)

    fun watchSettled(scope: CoroutineScope, onSettled: () -> Unit) {
        scope.launch {
            var unsent = pending.value.filter { it.isUnsent }.map { it.localId }.toSet()
            pending.collect { actions ->
                val now = actions.filter { it.isUnsent }.map { it.localId }.toSet()
                if ((unsent - now).isNotEmpty() && isOnline) {
                    onSettled()
                }
                unsent = now
            }
        }
    }
}
