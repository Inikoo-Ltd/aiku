package io.aiku.droid.data.offline

import io.aiku.droid.data.session.ByteCipher
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import java.time.Duration
import java.time.Instant

data class QueueEntry(
    val localId: Long,
    val userId: Long,
    val warehouseId: Long,
    val operation: OfflineOperation?,
    val record: OfflineRecordKey?,
    val payload: JsonObject?,
    val clientRequestId: String,
    val performedAt: Instant,
    val timeSourceIsServer: Boolean,
    val seenAt: Instant?,
    val sequence: Long?,
    val state: QueuedActionState,
    val result: OfflineResult?,
    val syncedAt: Instant?,
    val createdAt: Instant,
) {
    val isPendingSend: Boolean
        get() = state == QueuedActionState.WAITING || (state == QueuedActionState.VOID && sequence != null && syncedAt == null)

    val isUnsent: Boolean get() = state == QueuedActionState.WAITING || state == QueuedActionState.SENDING

    val sendsAsVoid: Boolean get() = state == QueuedActionState.VOID || operation == null || payload == null

    fun toAction(): QueuedAction? {
        val queuedOperation = operation ?: return null
        val queuedRecord = record ?: return null
        return QueuedAction(
            localId = localId,
            userId = userId,
            warehouseId = warehouseId,
            operation = queuedOperation,
            record = queuedRecord,
            payload = payload ?: JsonObject(emptyMap()),
            clientRequestId = clientRequestId,
            performedAt = performedAt,
            timeSourceIsServer = timeSourceIsServer,
            seenAt = seenAt,
            sequence = sequence,
            state = state,
            result = result,
        )
    }
}

class OfflineQueue(
    private val store: OfflineQueueStore,
    private val cipher: ByteCipher,
    private val clock: () -> Instant = Instant::now,
) {

    private var loaded: List<QueueEntry>? = null

    private val listeners = mutableListOf<(List<QueueEntry>) -> Unit>()

    @Synchronized
    fun entries(): List<QueueEntry> = loaded ?: load()

    @Synchronized
    fun onChange(listener: (List<QueueEntry>) -> Unit) {
        listeners += listener
    }

    @Synchronized
    fun add(entry: QueueEntry): QueueEntry {
        val current = entries()
        val localId = store.insertAction(stored(entry))
        val added = entry.copy(localId = localId)
        publish(current + added)
        return added
    }

    @Synchronized
    fun update(entry: QueueEntry) {
        val current = entries()
        if (current.none { it.localId == entry.localId }) {
            return
        }
        store.updateAction(stored(entry))
        publish(current.map { if (it.localId == entry.localId) entry else it })
    }

    @Synchronized
    fun delete(localId: Long) {
        val current = entries()
        store.deleteAction(localId)
        publish(current.filterNot { it.localId == localId })
    }

    @Synchronized
    fun change(localId: Long, transform: (QueueEntry) -> QueueEntry?): QueueEntry? {
        val entry = entries().firstOrNull { it.localId == localId } ?: return null
        val changed = transform(entry)
        when {
            changed == null -> delete(localId)
            changed != entry -> update(changed)
        }
        return changed
    }

    @Synchronized
    fun claimBatch(userId: Long, maxSize: Int, reserve: (Int) -> List<Long>): List<QueueEntry> {
        val pending = entries()
            .filter { it.userId == userId && it.isPendingSend }
            .sortedWith(compareBy<QueueEntry>({ it.sequence ?: Long.MAX_VALUE }, { it.localId }))
        val first = pending.firstOrNull() ?: return emptyList()
        val batch = pending.takeWhile { it.warehouseId == first.warehouseId }.take(maxSize)
        val unnumbered = batch.filter { it.sequence == null }
        val numbers = reserve(unnumbered.size).iterator()
        val claimed = batch.map { entry ->
            val numbered = if (entry.sequence == null) entry.copy(sequence = numbers.next()) else entry
            if (numbered.state == QueuedActionState.WAITING) numbered.copy(state = QueuedActionState.SENDING) else numbered
        }
        claimed.forEach(::update)
        return claimed
    }

    @Synchronized
    fun releaseSending(userId: Long) {
        entries().filter { it.userId == userId && it.state == QueuedActionState.SENDING }
            .forEach { update(it.copy(state = QueuedActionState.WAITING)) }
    }

    @Synchronized
    fun purgeSettled() {
        val cutOff = clock() - KEEP_SETTLED_FOR
        entries().filter { entry ->
            val syncedAt = entry.syncedAt
            syncedAt != null && syncedAt < cutOff && (entry.state == QueuedActionState.APPLIED || entry.state == QueuedActionState.VOID)
        }.forEach { delete(it.localId) }
    }

    private fun publish(entries: List<QueueEntry>) {
        loaded = entries
        listeners.forEach { it(entries) }
    }

    private fun load(): List<QueueEntry> {
        val restored = store.actions().mapNotNull { stored ->
            val entry = entryOf(stored)
            when {
                entry.state == QueuedActionState.SENDING -> entry.copy(state = QueuedActionState.WAITING).also { store.updateAction(stored(it)) }
                entry.payload == null && entry.operation != null && entry.isUnsent && entry.sequence == null -> {
                    store.deleteAction(entry.localId)
                    null
                }
                entry.payload == null && entry.operation != null && entry.isUnsent ->
                    entry.copy(state = QueuedActionState.VOID).also { store.updateAction(stored(it)) }
                else -> entry
            }
        }
        loaded = restored
        return restored
    }

    private fun entryOf(stored: StoredAction): QueueEntry = QueueEntry(
        localId = stored.localId,
        userId = stored.userId,
        warehouseId = stored.warehouseId,
        operation = stored.operation?.let { wire -> OfflineOperation.entries.firstOrNull { it.wire == wire } },
        record = stored.recordType?.let { type ->
            val recordType = OfflineRecordType.entries.firstOrNull { it.name == type }
            val recordId = stored.recordId
            if (recordType != null && recordId != null) OfflineRecordKey(recordType, recordId) else null
        },
        payload = stored.payload?.let { decrypted(it) }?.let { runCatching { Json.parseToJsonElement(it) as? JsonObject }.getOrNull() },
        clientRequestId = stored.clientRequestId,
        performedAt = Instant.ofEpochMilli(stored.performedAt),
        timeSourceIsServer = stored.timeSourceIsServer,
        seenAt = stored.seenAt?.let(Instant::ofEpochMilli),
        sequence = stored.sequence,
        state = QueuedActionState.entries.firstOrNull { it.name == stored.state } ?: QueuedActionState.WAITING,
        result = stored.result?.let { decrypted(it) }?.let(OfflineSyncResponses::parseResultJson),
        syncedAt = stored.syncedAt?.let(Instant::ofEpochMilli),
        createdAt = Instant.ofEpochMilli(stored.createdAt),
    )

    private fun stored(entry: QueueEntry): StoredAction = StoredAction(
        localId = entry.localId,
        userId = entry.userId,
        warehouseId = entry.warehouseId,
        operation = entry.operation?.wire,
        recordType = entry.record?.type?.name,
        recordId = entry.record?.id,
        payload = entry.payload?.let { encrypted(it.toString()) },
        clientRequestId = entry.clientRequestId,
        performedAt = entry.performedAt.toEpochMilli(),
        timeSourceIsServer = entry.timeSourceIsServer,
        seenAt = entry.seenAt?.toEpochMilli(),
        sequence = entry.sequence,
        state = entry.state.name,
        result = entry.result?.let { encrypted(OfflineSyncResponses.resultJson(it)) },
        syncedAt = entry.syncedAt?.toEpochMilli(),
        createdAt = entry.createdAt.toEpochMilli(),
    )

    private fun decrypted(bytes: ByteArray): String? = runCatching { cipher.decrypt(bytes).toString(Charsets.UTF_8) }.getOrNull()

    private fun encrypted(text: String): ByteArray = cipher.encrypt(text.toByteArray(Charsets.UTF_8))

    private companion object {
        val KEEP_SETTLED_FOR: Duration = Duration.ofHours(24)
    }
}
