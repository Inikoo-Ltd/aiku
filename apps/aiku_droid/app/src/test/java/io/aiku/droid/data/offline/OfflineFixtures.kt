package io.aiku.droid.data.offline

import io.aiku.droid.data.cache.XorCipher
import io.aiku.droid.data.session.ByteCipher
import io.aiku.droid.data.session.StaffSession
import io.aiku.droid.data.settings.MemoryPreferences
import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import java.time.Instant

val OFFLINE_NOW: Instant = Instant.parse("2026-11-20T10:00:00Z")

val ANA = StaffSession(token = "7|abc", userId = 42, username = "stock", displayName = "Ana")

val BEN = StaffSession(token = "8|def", userId = 43, username = "picker", displayName = "Ben")

val NOTE_5 = OfflineRecordKey(OfflineRecordType.DELIVERY_NOTE, 5)

val NOTE_6 = OfflineRecordKey(OfflineRecordType.DELIVERY_NOTE, 6)

fun pickPayload(quantity: Int, requestId: String = "req-$quantity"): JsonObject = JsonObject(
    mapOf(
        "delivery_note_id" to JsonPrimitive(5),
        "delivery_note_item_id" to JsonPrimitive(11),
        "location_org_stock_id" to JsonPrimitive(9),
        "quantity" to JsonPrimitive(quantity),
        "client_request_id" to JsonPrimitive(requestId),
    ),
)

class InMemoryOfflineStore : OfflineStore {
    val downloads = linkedMapOf<DownloadKey, StoredDownload>()
    val actions = linkedMapOf<Long, StoredAction>()
    private var nextId = 1L

    override fun readDownload(key: DownloadKey): StoredDownload? = downloads[key]

    override fun writeDownload(download: StoredDownload) {
        downloads[download.key] = download
    }

    override fun markDownloadOpened(key: DownloadKey, openedAt: Long) {
        downloads[key]?.let { downloads[key] = StoredDownload(it.key, it.payload, it.seenAt, it.downloadedAt, openedAt) }
    }

    override fun deleteDownload(key: DownloadKey) {
        downloads.remove(key)
    }

    override fun deleteDownloadsOpenedBefore(openedAt: Long) {
        downloads.values.removeAll { it.lastOpenedAt < openedAt }
    }

    override fun deleteDownloadsOfOtherUsers(userId: Long) {
        downloads.keys.removeAll { it.userId != userId }
    }

    override fun deleteAllDownloads() {
        downloads.clear()
    }

    override fun actions(): List<StoredAction> = actions.values.toList()

    override fun insertAction(action: StoredAction): Long {
        val id = nextId++
        actions[id] = copy(action, id)
        return id
    }

    override fun updateAction(action: StoredAction) {
        actions[action.localId] = action
    }

    override fun deleteAction(localId: Long) {
        actions.remove(localId)
    }

    private fun copy(action: StoredAction, localId: Long) = StoredAction(
        localId = localId,
        userId = action.userId,
        warehouseId = action.warehouseId,
        operation = action.operation,
        recordType = action.recordType,
        recordId = action.recordId,
        payload = action.payload,
        clientRequestId = action.clientRequestId,
        performedAt = action.performedAt,
        timeSourceIsServer = action.timeSourceIsServer,
        seenAt = action.seenAt,
        sequence = action.sequence,
        state = action.state,
        result = action.result,
        syncedAt = action.syncedAt,
        createdAt = action.createdAt,
    )
}

data class SyncCall(val token: String, val warehouseId: Long, val actions: List<SyncActionRequest>)

class FakeSyncApi : OfflineSyncApi {
    val calls = mutableListOf<SyncCall>()
    val refetched = mutableListOf<String>()
    val notices = mutableListOf<Triple<String, Long, Int>>()
    var noticeAnswer = NoticeAnswer.Sent
    var openOperations: OpenOperationsAnswer = OpenOperationsAnswer.Loaded(emptyList())
    var openRequests = 0
    var answer: (SyncCall) -> SyncAnswer = { call -> SyncAnswer.Answered(call.actions.map { applied(it.sequence) }, expectedSequence = null) }

    override suspend fun sync(token: String, warehouseId: Long, actions: List<SyncActionRequest>): SyncAnswer {
        val call = SyncCall(token, warehouseId, actions)
        calls += call
        return answer(call)
    }

    override suspend fun openOperations(token: String): OpenOperationsAnswer {
        openRequests++
        return openOperations
    }

    override suspend fun waitingNotice(token: String, userId: Long, count: Int, oldestPerformedAt: Instant): NoticeAnswer {
        notices += Triple(token, userId, count)
        return noticeAnswer
    }

    override suspend fun refetch(token: String, path: String): Boolean {
        refetched += path
        return true
    }

    val sentSequences: List<Long> get() = calls.flatMap { call -> call.actions.map { it.sequence } }
}

fun result(sequence: Long, status: String, operationId: Long = 80 + sequence, reason: String? = null): SyncResult = SyncResult(
    sequence = sequence,
    status = status,
    result = OfflineResult(
        status = status,
        operationId = operationId,
        reason = reason,
        instruction = "none",
        message = null,
        requestedQuantity = null,
        appliedQuantity = null,
        countedSince = false,
        taskReference = null,
        conflictingUserName = null,
    ),
)

fun applied(sequence: Long): SyncResult = result(sequence, OfflineSyncResponses.STATUS_APPLIED)

class FakeScheduler : SyncScheduler {
    val scheduled = mutableListOf<SyncReason>()
    var followUps = 0
    var periodic: Boolean? = null

    override fun schedule(reason: SyncReason) {
        scheduled += reason
    }

    override fun scheduleFollowUp() {
        followUps++
    }

    override fun keepPeriodic(isNeeded: Boolean) {
        periodic = isNeeded
    }
}

internal class OfflineHarness(
    session: StaffSession? = ANA,
    val store: InMemoryOfflineStore = InMemoryOfflineStore(),
    val preferences: MemoryPreferences = MemoryPreferences(),
    val cipher: ByteCipher = XorCipher(),
) {
    val sessions = FakeSessions(session)
    val api = FakeSyncApi()
    val scheduler = FakeScheduler()
    val online = MutableStateFlow(true)
    var now: Instant = OFFLINE_NOW
    var elapsed = 1_000L
    val device = OfflineDevice(preferences)
    val clock = ServerClock(preferences, elapsedRealtime = { elapsed }, bootCount = { 1 }, deviceClock = { now })
    val queue = OfflineQueue(store, cipher) { now }
    val downloads = OfflineDownloads(store, cipher, sessions, clock = { now }, ioDispatcher = Dispatchers.Unconfined)
    val engine = OfflineSyncEngine(queue, device, api, sessions) { now }
    val work = DeviceOfflineWork(
        actions = queue,
        downloads = downloads,
        engine = engine,
        api = api,
        device = device,
        sessions = sessions,
        clock = clock,
        scheduler = scheduler,
        isOnline = online,
        scope = CoroutineScope(Dispatchers.Unconfined),
        notices = null,
        deviceClock = { now },
    )

    fun start(): OfflineHarness {
        downloads.start(CoroutineScope(Dispatchers.Unconfined))
        work.start()
        return this
    }

    fun pick(quantity: Int, record: OfflineRecordKey = NOTE_5, warehouseId: Long = 1): QueuedAction =
        work.enqueue(warehouseId, OfflineOperation.PICK, record, pickPayload(quantity), "req-$quantity", OFFLINE_NOW.minusSeconds(600))
}
