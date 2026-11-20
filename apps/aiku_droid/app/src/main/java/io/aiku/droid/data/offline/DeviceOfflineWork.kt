package io.aiku.droid.data.offline

import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.data.session.StaffSession
import io.aiku.droid.push.OfflineNotices
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.drop
import kotlinx.coroutines.flow.filter
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.serialization.json.JsonObject
import java.time.Instant
import java.util.UUID
import java.util.concurrent.ConcurrentHashMap

class DeviceOfflineWork(
    private val actions: OfflineQueue,
    private val downloads: OfflineDownloads,
    private val engine: OfflineSyncEngine,
    private val api: OfflineSyncApi,
    private val device: OfflineDevice,
    private val sessions: SessionRepository,
    private val clock: ServerClock,
    private val scheduler: SyncScheduler,
    override val isOnline: StateFlow<Boolean>,
    private val scope: CoroutineScope,
    internal val notices: OfflineNotices?,
    private val deviceClock: () -> Instant = Instant::now,
) : OfflineWork {

    private val mutableNeedsYou = MutableStateFlow(0)
    override val needsYouCount: StateFlow<Int> = mutableNeedsYou.asStateFlow()

    private val mutableWaiting = MutableStateFlow(0)
    override val waitingCount: StateFlow<Int> = mutableWaiting.asStateFlow()

    private val mutableQueue = MutableStateFlow<List<QueuedAction>>(emptyList())
    override val queue: StateFlow<List<QueuedAction>> = mutableQueue.asStateFlow()

    private val pendingByRecord = ConcurrentHashMap<OfflineRecordKey, MutableStateFlow<List<QueuedAction>>>()

    private val noticeMutex = Mutex()

    fun start() {
        actions.onChange { publish(it) }
        publish(actions.entries())
        scope.launch {
            sessions.session.collect { publish(actions.entries()) }
        }
        scope.launch {
            sessions.session.distinctUntilChanged { old, new -> old?.token == new?.token }.collect { session ->
                if (session != null) {
                    sendWaitingNotices(session)
                    syncNow(SyncReason.FOREGROUND)
                }
            }
        }
        scope.launch {
            isOnline.drop(1).distinctUntilChanged().filter { it }.collect {
                sessions.session.value?.let { sendWaitingNotices(it) }
                syncNow(SyncReason.RECONNECTED)
            }
        }
    }

    override fun shouldQueue(record: OfflineRecordKey): Boolean {
        if (!isOnline.value) {
            return true
        }
        val userId = sessions.session.value?.userId ?: return false
        return actions.entries().any { it.userId == userId && it.record == record && it.isUnsent }
    }

    override fun enqueue(
        warehouseId: Long,
        operation: OfflineOperation,
        record: OfflineRecordKey,
        payload: JsonObject,
        clientRequestId: String,
        seenAt: Instant?,
    ): QueuedAction {
        val userId = checkNotNull(sessions.session.value?.userId) { "Offline work needs a signed-in user" }
        val performed = clock.now()
        val added = actions.add(
            QueueEntry(
                localId = 0,
                userId = userId,
                warehouseId = warehouseId,
                operation = operation,
                record = record,
                payload = payload,
                clientRequestId = clientRequestId,
                performedAt = performed.instant,
                timeSourceIsServer = performed.isServerTime,
                seenAt = seenAt,
                sequence = null,
                state = QueuedActionState.WAITING,
                result = null,
                syncedAt = null,
                createdAt = deviceClock(),
            ),
        )
        scheduler.schedule(SyncReason.QUEUED)
        return checkNotNull(added.toAction())
    }

    override fun pendingFor(record: OfflineRecordKey): StateFlow<List<QueuedAction>> =
        pendingByRecord.getOrPut(record) { MutableStateFlow(pendingOf(record, actions.entries())) }

    override fun undo(localId: Long) {
        val userId = sessions.session.value?.userId ?: return
        var mustSendVoid = false
        actions.change(localId) { entry ->
            when {
                entry.userId != userId || entry.state != QueuedActionState.WAITING -> entry
                entry.sequence == null -> null
                else -> entry.copy(state = QueuedActionState.VOID).also { mustSendVoid = true }
            }
        }
        if (mustSendVoid) {
            scheduler.schedule(SyncReason.QUEUED)
        }
    }

    override suspend fun downloaded(record: OfflineRecordKey): DownloadedRecord? = downloads.read(record)

    override fun syncNow(reason: SyncReason) {
        when (reason) {
            SyncReason.MANUAL -> scope.launch { runInForeground() }
            SyncReason.CLOCK_OUT, SyncReason.QUEUED -> scheduler.schedule(reason)
            SyncReason.FOREGROUND -> if (hasWorkToSettle()) scope.launch { runInForeground() }
            SyncReason.RECONNECTED -> if (hasWorkToSettle()) scope.launch { runOnReconnect() }
            SyncReason.PERIODIC -> if (hasWorkToSettle()) scheduler.schedule(reason)
        }
    }

    override suspend fun flush(record: OfflineRecordKey): Boolean {
        val userId = sessions.session.value?.userId ?: return false
        if (isOnline.value && hasPendingSend(userId, record)) {
            engine.run(maxBatches = FLUSH_BATCHES) { !hasPendingSend(userId, record) }
        }
        return actions.entries().none { entry ->
            entry.userId == userId && entry.record == record &&
                (entry.isUnsent || entry.state == QueuedActionState.NEEDS_YOU)
        }
    }

    override fun newClientRequestId(): String = UUID.randomUUID().toString()

    suspend fun runInBackground(): SyncRunOutcome {
        val outcome = engine.run(maxBatches = OfflineSyncRequests.BATCHES_PER_RUN)
        if (outcome.newlyNeedingYou > 0) {
            notices?.needYou(needsYouCount.value)
        }
        if (outcome.status == SyncRunStatus.COMPLETE && outcome.hasMore) {
            scheduler.scheduleFollowUp()
        }
        return outcome
    }

    private suspend fun runOnReconnect() {
        if (runInBackground().status == SyncRunStatus.RETRY) {
            scheduler.schedule(SyncReason.QUEUED)
        }
    }

    private suspend fun runInForeground() {
        val outcome = engine.run(maxBatches = MANUAL_BATCHES)
        if (outcome.status == SyncRunStatus.RETRY || outcome.hasMore) {
            scheduler.schedule(SyncReason.QUEUED)
        }
    }

    private fun hasWorkToSettle(): Boolean {
        val userId = sessions.session.value?.userId ?: return false
        return actions.entries().any { it.userId == userId && (it.isPendingSend || it.state == QueuedActionState.NEEDS_YOU) }
    }

    private fun hasPendingSend(userId: Long, record: OfflineRecordKey): Boolean =
        actions.entries().any { it.userId == userId && it.record == record && it.isPendingSend }

    private suspend fun sendWaitingNotices(session: StaffSession) = noticeMutex.withLock {
        val sessionKey = session.token.substringBefore(TOKEN_ID_SEPARATOR)
        if (device.noticesSentFor(session.userId) == sessionKey) {
            return@withLock
        }
        val waitingByUser = actions.entries()
            .filter { it.userId != session.userId && it.operation != null && it.isUnsent }
            .groupBy { it.userId }
        var complete = true
        waitingByUser.forEach { (userId, actions) ->
            val oldest = actions.minOf { it.performedAt }
            when (api.waitingNotice(session.token, userId, actions.size, oldest)) {
                NoticeAnswer.Sent, NoticeAnswer.Refused -> Unit
                NoticeAnswer.Unreachable -> complete = false
            }
        }
        if (complete) {
            device.markNoticesSent(session.userId, sessionKey)
        }
    }

    private fun publish(entries: List<QueueEntry>) {
        val userId = sessions.session.value?.userId
        val own = entries.filter { it.userId == userId }
        mutableQueue.value = own.filter { it.state != QueuedActionState.VOID }.mapNotNull { it.toAction() }
        mutableNeedsYou.value = own.count { it.state == QueuedActionState.NEEDS_YOU && it.operation != null }
        val waiting = own.count { it.isUnsent && it.operation != null }
        mutableWaiting.value = waiting
        pendingByRecord.forEach { (record, flow) -> flow.value = pendingOf(record, entries) }
        scheduler.keepPeriodic(waiting > 0)
    }

    private fun pendingOf(record: OfflineRecordKey, entries: List<QueueEntry>): List<QueuedAction> {
        val userId = sessions.session.value?.userId ?: return emptyList()
        return entries
            .filter { it.userId == userId && it.record == record && (it.isUnsent || it.state == QueuedActionState.NEEDS_YOU) }
            .mapNotNull { it.toAction() }
    }

    private companion object {
        const val FLUSH_BATCHES = 20
        const val MANUAL_BATCHES = 20
        const val TOKEN_ID_SEPARATOR = '|'
    }
}
