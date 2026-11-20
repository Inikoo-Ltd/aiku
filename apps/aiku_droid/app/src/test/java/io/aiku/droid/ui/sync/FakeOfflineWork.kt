package io.aiku.droid.ui.sync

import io.aiku.droid.data.offline.DownloadedRecord
import io.aiku.droid.data.offline.OfflineOperation
import io.aiku.droid.data.offline.OfflineRecordKey
import io.aiku.droid.data.offline.OfflineWork
import io.aiku.droid.data.offline.QueuedAction
import io.aiku.droid.data.offline.QueuedActionState
import io.aiku.droid.data.offline.SyncReason
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.serialization.json.JsonObject
import java.time.Instant

class FakeOfflineWork(online: Boolean = true) : OfflineWork {
    val online = MutableStateFlow(online)
    override val isOnline: StateFlow<Boolean> = this.online
    override val needsYouCount = MutableStateFlow(0)
    override val waitingCount = MutableStateFlow(0)
    private val actions = MutableStateFlow<List<QueuedAction>>(emptyList())
    override val queue: StateFlow<List<QueuedAction>> = actions
    private val perRecord = mutableMapOf<OfflineRecordKey, MutableStateFlow<List<QueuedAction>>>()
    val downloads = mutableMapOf<OfflineRecordKey, DownloadedRecord>()
    var flushResult = true
    val flushed = mutableListOf<OfflineRecordKey>()
    val undone = mutableListOf<Long>()
    val syncs = mutableListOf<SyncReason>()
    private var nextRequestId = 0
    private var nextLocalId = 0L

    val queued: List<QueuedAction> get() = actions.value

    fun replace(list: List<QueuedAction>) {
        actions.value = list
        perRecord.forEach { (record, flow) -> flow.value = list.filter { it.record == record } }
    }

    fun setState(localId: Long, state: QueuedActionState) {
        replace(actions.value.map { if (it.localId == localId) it.copy(state = state) else it })
    }

    override fun shouldQueue(record: OfflineRecordKey): Boolean =
        !online.value || actions.value.any { it.record == record && (it.state == QueuedActionState.WAITING || it.state == QueuedActionState.SENDING) }

    override fun enqueue(
        warehouseId: Long,
        operation: OfflineOperation,
        record: OfflineRecordKey,
        payload: JsonObject,
        clientRequestId: String,
        seenAt: Instant?,
    ): QueuedAction {
        nextLocalId += 1
        val action = QueuedAction(
            localId = nextLocalId,
            userId = 42,
            warehouseId = warehouseId,
            operation = operation,
            record = record,
            payload = payload,
            clientRequestId = clientRequestId,
            performedAt = Instant.parse("2026-11-20T10:00:00Z").plusSeconds(nextLocalId),
            timeSourceIsServer = true,
            seenAt = seenAt,
            sequence = null,
            state = QueuedActionState.WAITING,
            result = null,
        )
        replace(actions.value + action)
        return action
    }

    override fun pendingFor(record: OfflineRecordKey): StateFlow<List<QueuedAction>> =
        perRecord.getOrPut(record) { MutableStateFlow(actions.value.filter { it.record == record }) }

    override fun undo(localId: Long) {
        undone += localId
        replace(actions.value.filterNot { it.localId == localId })
    }

    override suspend fun downloaded(record: OfflineRecordKey): DownloadedRecord? = downloads[record]

    override fun syncNow(reason: SyncReason) {
        syncs += reason
    }

    override suspend fun flush(record: OfflineRecordKey): Boolean {
        flushed += record
        return flushResult
    }

    override fun newClientRequestId(): String {
        nextRequestId += 1
        return "req-$nextRequestId"
    }
}
