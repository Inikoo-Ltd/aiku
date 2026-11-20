package io.aiku.droid.data.offline

import io.aiku.droid.data.session.SessionRepository
import kotlinx.coroutines.NonCancellable
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.JsonObject
import java.time.Instant
import java.util.UUID

enum class SyncRunStatus { COMPLETE, RETRY, STOPPED }

data class SyncRunOutcome(val status: SyncRunStatus, val newlyNeedingYou: Int = 0, val hasMore: Boolean = false)

class OfflineSyncEngine(
    private val queue: OfflineQueue,
    private val device: OfflineDevice,
    private val api: OfflineSyncApi,
    private val sessions: SessionRepository,
    private val clock: () -> Instant = Instant::now,
) {

    private val mutex = Mutex()

    suspend fun run(maxBatches: Int, isDone: () -> Boolean = { false }): SyncRunOutcome = mutex.withLock {
        val session = sessions.session.value ?: return@withLock SyncRunOutcome(SyncRunStatus.STOPPED)
        val userId = session.userId
        queue.purgeSettled()
        var batches = 0
        var newlyNeedingYou = 0
        var status = SyncRunStatus.COMPLETE
        val changed = linkedSetOf<Pair<OfflineRecordKey, Long>>()
        val missingAnswers = mutableSetOf<Long>()

        while (batches < maxBatches && !isDone()) {
            if (sessions.session.value?.token != session.token) {
                status = SyncRunStatus.STOPPED
                break
            }
            val batch = queue.claimBatch(userId, MAX_BATCH_SIZE) { count -> device.reserveSequences(userId, count) }
            if (batch.isEmpty()) {
                break
            }
            batches++
            var missingFrom: Long? = null
            val answer = try {
                api.sync(session.token, batch.first().warehouseId, batch.map(::requestOf))
            } catch (failure: Throwable) {
                withContext(NonCancellable) { queue.releaseSending(userId) }
                throw failure
            }
            if (answer is SyncAnswer.Answered) {
                val applied = apply(batch, answer)
                newlyNeedingYou += applied.newlyNeedingYou
                changed += applied.changed
                missingFrom = applied.missingFrom
            }
            queue.releaseSending(userId)
            when (answer) {
                is SyncAnswer.Answered -> {
                    val missing = missingFrom
                    if (missing != null) {
                        val expected = answer.expectedSequence
                        if (expected == null || expected >= missing || !missingAnswers.add(expected)) {
                            status = SyncRunStatus.STOPPED
                            break
                        }
                        fillFrom(userId, batch.first().warehouseId, expected, missing)
                    }
                }
                SyncAnswer.SessionExpired -> {
                    status = SyncRunStatus.STOPPED
                    break
                }
                SyncAnswer.Unreachable -> {
                    status = SyncRunStatus.RETRY
                    break
                }
                is SyncAnswer.Failed -> {
                    status = if (answer.statusCode >= HTTP_SERVER_ERROR) SyncRunStatus.RETRY else SyncRunStatus.STOPPED
                    break
                }
            }
        }

        if (status != SyncRunStatus.RETRY && sessions.session.value?.token == session.token) {
            changed.forEach { (record, warehouseId) -> api.refetch(session.token, OfflineRoutes.detailPath(record, warehouseId)) }
            reconcile(session.token, userId)
        }
        val hasMore = queue.entries().any { it.userId == userId && it.isPendingSend }
        SyncRunOutcome(status = status, newlyNeedingYou = newlyNeedingYou, hasMore = hasMore)
    }

    private data class Applied(val newlyNeedingYou: Int, val changed: Set<Pair<OfflineRecordKey, Long>>, val missingFrom: Long?)

    private fun apply(batch: List<QueueEntry>, answer: SyncAnswer.Answered): Applied {
        val results = answer.results.associateBy { it.sequence }
        val syncedAt = clock()
        var newlyNeedingYou = 0
        var missingFrom: Long? = null
        val changed = linkedSetOf<Pair<OfflineRecordKey, Long>>()
        batch.forEach { sent ->
            val result = results[sent.sequence]
            if (result == null || result.status == OfflineSyncResponses.STATUS_MISSING_PREVIOUS || missingFrom != null) {
                if (result?.status == OfflineSyncResponses.STATUS_MISSING_PREVIOUS && missingFrom == null) {
                    missingFrom = sent.sequence
                }
                return@forEach
            }
            val state = stateOf(sent, result.status)
            queue.change(sent.localId) { current -> current.copy(state = state, result = result.result, syncedAt = syncedAt) }
            if (state == QueuedActionState.NEEDS_YOU) {
                newlyNeedingYou++
            }
            val record = sent.record
            if (record != null && state != QueuedActionState.VOID) {
                changed += record to sent.warehouseId
            }
        }
        return Applied(newlyNeedingYou = newlyNeedingYou, changed = changed, missingFrom = missingFrom)
    }

    private fun stateOf(sent: QueueEntry, status: String): QueuedActionState = when {
        sent.operation == null -> QueuedActionState.VOID
        status == OfflineSyncResponses.STATUS_APPLIED || status == OfflineSyncResponses.STATUS_ALREADY_DONE -> QueuedActionState.APPLIED
        status == OfflineSyncResponses.STATUS_VOID -> QueuedActionState.VOID
        else -> QueuedActionState.NEEDS_YOU
    }

    private fun fillFrom(userId: Long, warehouseId: Long, expected: Long, missing: Long) {
        val held = queue.entries().filter { it.userId == userId && it.sequence != null }.associateBy { it.sequence }
        (expected until missing).forEach { sequence ->
            val entry = held[sequence]
            if (entry == null) {
                val now = clock()
                queue.add(
                    QueueEntry(
                        localId = 0,
                        userId = userId,
                        warehouseId = warehouseId,
                        operation = null,
                        record = null,
                        payload = null,
                        clientRequestId = UUID.randomUUID().toString(),
                        performedAt = now,
                        timeSourceIsServer = false,
                        seenAt = null,
                        sequence = sequence,
                        state = QueuedActionState.VOID,
                        result = null,
                        syncedAt = null,
                        createdAt = now,
                    ),
                )
            } else {
                queue.change(entry.localId) { current ->
                    val resent = if (current.state == QueuedActionState.VOID || current.operation == null) QueuedActionState.VOID else QueuedActionState.WAITING
                    current.copy(state = resent, syncedAt = null)
                }
            }
        }
    }

    private suspend fun reconcile(token: String, userId: Long) {
        val open = queue.entries().filter { entry ->
            entry.userId == userId && entry.result?.operationId != null &&
                (entry.state == QueuedActionState.NEEDS_YOU || entry.result.status == OfflineSyncResponses.STATUS_ALREADY_DONE)
        }
        if (open.isEmpty()) {
            return
        }
        val answer = api.openOperations(token) as? OpenOperationsAnswer.Loaded ?: return
        val stillOpen = answer.operations.associateBy { it.operationId }
        open.forEach { entry ->
            val operation = stillOpen[entry.result?.operationId]
            when {
                operation != null -> queue.change(entry.localId) { it.copy(state = QueuedActionState.NEEDS_YOU, result = operation.result) }
                entry.state == QueuedActionState.NEEDS_YOU -> queue.delete(entry.localId)
            }
        }
    }

    private fun requestOf(entry: QueueEntry): SyncActionRequest = SyncActionRequest(
        sequence = checkNotNull(entry.sequence),
        operation = if (entry.sendsAsVoid) OfflineSyncResponses.OPERATION_VOID else checkNotNull(entry.operation).wire,
        performedAt = entry.performedAt,
        timeSourceIsServer = entry.timeSourceIsServer,
        seenAt = entry.seenAt,
        payload = if (entry.sendsAsVoid) JsonObject(emptyMap()) else checkNotNull(entry.payload),
    )

    companion object {
        const val MAX_BATCH_SIZE = 50
        private const val HTTP_SERVER_ERROR = 500
    }
}
