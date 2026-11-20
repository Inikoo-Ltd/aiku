package io.aiku.droid.data.offline

import java.time.Instant
import java.util.UUID
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.serialization.json.JsonObject

object NoOfflineWork : OfflineWork {
    override val isOnline: StateFlow<Boolean> = MutableStateFlow(true)
    override val needsYouCount: StateFlow<Int> = MutableStateFlow(0)
    override val waitingCount: StateFlow<Int> = MutableStateFlow(0)
    override val queue: StateFlow<List<QueuedAction>> = MutableStateFlow(emptyList())

    override fun shouldQueue(record: OfflineRecordKey): Boolean = false

    override fun enqueue(
        warehouseId: Long,
        operation: OfflineOperation,
        record: OfflineRecordKey,
        payload: JsonObject,
        clientRequestId: String,
        seenAt: Instant?,
    ): QueuedAction = error("Offline work is not available")

    override fun pendingFor(record: OfflineRecordKey): StateFlow<List<QueuedAction>> = MutableStateFlow(emptyList())

    override fun undo(localId: Long) = Unit

    override suspend fun downloaded(record: OfflineRecordKey): DownloadedRecord? = null

    override fun syncNow(reason: SyncReason) = Unit

    override suspend fun flush(record: OfflineRecordKey): Boolean = true

    override fun newClientRequestId(): String = UUID.randomUUID().toString()
}
