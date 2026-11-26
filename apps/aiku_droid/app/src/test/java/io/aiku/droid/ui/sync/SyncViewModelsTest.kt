package io.aiku.droid.ui.sync

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.OfflineOperationEntry
import io.aiku.droid.data.api.OfflineOperationLabels
import io.aiku.droid.data.api.OfflineOperationRecord
import io.aiku.droid.data.api.OfflineOperationsApi
import io.aiku.droid.data.api.OfflineOperationsState
import io.aiku.droid.data.api.OfflineResolution
import io.aiku.droid.data.api.OfflineResolveOutcome
import io.aiku.droid.data.offline.DownloadedRecord
import io.aiku.droid.data.offline.OfflineOperation
import io.aiku.droid.data.offline.OfflineRecordKey
import io.aiku.droid.data.offline.OfflineRecordType
import io.aiku.droid.data.offline.QueuedActionState
import io.aiku.droid.data.offline.SyncReason
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import java.time.Instant

private class FakeOperationsApi : OfflineOperationsApi {
    val lists = mutableMapOf<OfflineOperationsState, GoodsOutResult<List<OfflineOperationEntry>>>(
        OfflineOperationsState.Open to GoodsOutResult.Loaded(emptyList()),
        OfflineOperationsState.Recent to GoodsOutResult.Loaded(emptyList()),
    )
    val asked = mutableListOf<OfflineOperationsState>()
    val resolutions = mutableListOf<Pair<Long, OfflineResolution>>()
    var resolveResult: (OfflineResolution) -> GoodsOutResult<OfflineResolveOutcome> = { GoodsOutResult.Unreachable }
    val resolveById = mutableMapOf<Long, GoodsOutResult<OfflineResolveOutcome>>()
    val rollbacks = mutableListOf<Long>()
    var rollbackResult: GoodsOutResult<OfflineOperationEntry> = GoodsOutResult.Unreachable

    override suspend fun operations(token: String, state: OfflineOperationsState): GoodsOutResult<List<OfflineOperationEntry>> {
        asked += state
        return lists.getValue(state)
    }

    override suspend fun resolve(token: String, operationId: Long, resolution: OfflineResolution): GoodsOutResult<OfflineResolveOutcome> {
        resolutions += operationId to resolution
        return resolveById[operationId] ?: resolveResult(resolution)
    }

    override suspend fun rollback(token: String, operationId: Long, reason: String?): GoodsOutResult<OfflineOperationEntry> {
        rollbacks += operationId
        return rollbackResult
    }
}

@OptIn(ExperimentalCoroutinesApi::class)
class SyncViewModelsTest {

    private val offline = FakeOfflineWork()
    private val api = FakeOperationsApi()

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    private fun entry(id: Long, status: String = "refused", resolutions: List<String> = listOf("put_back", "done"), canRollback: Boolean = false) = OfflineOperationEntry(
        status = status,
        state = status,
        operationId = id,
        operation = "pick",
        reason = "over_required",
        instruction = "put_back",
        message = "B picked this line at 10:42.",
        requestedQuantity = 3.0,
        appliedQuantity = 0.0,
        labels = OfflineOperationLabels(title = "GB592347 · line 3", locationCode = "P"),
        resolutions = resolutions,
        canRollback = canRollback,
    )

    @Test
    fun `the queue is grouped by record in time order with titles from the downloaded copies`() {
        val returnKey = OfflineRecordKey(OfflineRecordType.RETURN, 5)
        val locationKey = OfflineRecordKey(OfflineRecordType.LOCATION, 220)
        offline.downloads[returnKey] = DownloadedRecord("""{"data":{"id":5,"reference":"R-5"}}""", Instant.EPOCH, Instant.EPOCH)
        offline.downloads[locationKey] = DownloadedRecord("""{"data":{"id":220,"code":"A-03-02"}}""", Instant.EPOCH, Instant.EPOCH)
        val viewModel = SyncQueueViewModel(offline)

        offline.enqueue(1, OfflineOperation.RETURN_DAMAGED, returnKey, buildJsonObject { put("quantity", 1) }, "a", null)
        offline.enqueue(1, OfflineOperation.MOVE_STOCK, locationKey, buildJsonObject { put("quantity", 2) }, "b", null)
        offline.enqueue(1, OfflineOperation.RETURN_PUT_BACK, returnKey, buildJsonObject { put("quantity", 3) }, "c", null)
        offline.enqueue(1, OfflineOperation.AUDIT_LOCATION, OfflineRecordKey(OfflineRecordType.LOCATION, 999), buildJsonObject { }, "d", null)
        offline.setState(4, QueuedActionState.VOID)

        val groups = viewModel.state.value.groups
        assertEquals(listOf(returnKey, locationKey), groups.map { it.record })
        assertEquals(listOf("R-5", "A-03-02"), groups.map { it.title })
        assertEquals(listOf("a", "c"), groups.first().actions.map { it.clientRequestId })
        assertFalse(viewModel.state.value.isEmpty)
    }

    @Test
    fun `a record with no downloaded copy takes its title from the recent actions on the server`() {
        val noteKey = OfflineRecordKey(OfflineRecordType.DELIVERY_NOTE, 1142033)
        api.lists[OfflineOperationsState.Recent] = GoodsOutResult.Loaded(
            listOf(
                OfflineOperationEntry(
                    operationId = 81,
                    labels = OfflineOperationLabels(title = "AWD203229 · line 2"),
                    record = OfflineOperationRecord(type = "delivery_note", id = 1142033),
                ),
            ),
        )
        offline.enqueue(1, OfflineOperation.PICK, noteKey, buildJsonObject { put("quantity", 1) }, "a", null)

        val viewModel = SyncQueueViewModel(offline, api, FakeSessions())

        assertEquals("AWD203229", viewModel.state.value.groups.single().title)
        assertEquals(listOf(OfflineOperationsState.Recent), api.asked)
    }

    @Test
    fun `the queue screen syncs now, undoes and follows connection and counts`() {
        val viewModel = SyncQueueViewModel(offline)
        assertTrue(viewModel.state.value.isEmpty)

        offline.online.value = false
        offline.needsYouCount.value = 2
        offline.waitingCount.value = 3
        viewModel.syncNow()
        viewModel.undo(7)

        assertFalse(viewModel.state.value.isOnline)
        assertEquals(2, viewModel.state.value.needsYouCount)
        assertEquals(3, viewModel.state.value.waitingCount)
        assertEquals(listOf(SyncReason.MANUAL), offline.syncs)
        assertEquals(listOf(7L), offline.undone)
    }

    @Test
    fun `recordReference reads the reference or the code of a downloaded answer`() {
        assertEquals("R-5", recordReference("""{"data":{"reference":"R-5","code":"X"}}"""))
        assertEquals("A-01", recordReference("""{"data":{"code":"A-01"}}"""))
        assertNull(recordReference("not json"))
    }

    @Test
    fun `resolve conflicts loads the open actions, shows empty and errors`() {
        api.lists[OfflineOperationsState.Open] = GoodsOutResult.Loaded(listOf(entry(81), entry(82, status = "conflict")))
        val viewModel = ResolveConflictsViewModel(api, FakeSessions())

        viewModel.onStart()
        assertEquals(listOf(81L, 82L), viewModel.state.value.operations.map { it.key })
        assertFalse(viewModel.state.value.isEmpty)

        api.lists[OfflineOperationsState.Open] = GoodsOutResult.Loaded(emptyList())
        viewModel.refresh()
        assertTrue(viewModel.state.value.isEmpty)

        api.lists[OfflineOperationsState.Open] = GoodsOutResult.Unreachable
        viewModel.retry()
        assertEquals(GoodsOutProblem.Offline, viewModel.state.value.problem)

        api.lists[OfflineOperationsState.Open] = GoodsOutResult.SessionExpired
        viewModel.retry()
        assertTrue(viewModel.state.value.isSessionExpired)
        assertEquals(listOf(OfflineOperationsState.Open), api.asked.distinct())
    }

    private fun conflict(id: Long = 81): ConflictViewModel = ConflictViewModel(api, FakeSessions(), offline, id).also { it.onStart() }

    @Test
    fun `one open action loads by its id, falling back to the recent list, or is gone`() {
        api.lists[OfflineOperationsState.Open] = GoodsOutResult.Loaded(listOf(entry(80), entry(81)))
        assertEquals(81L, conflict().state.value.operation?.key)

        api.lists[OfflineOperationsState.Recent] = GoodsOutResult.Loaded(listOf(entry(90, status = "resolved", resolutions = emptyList())))
        assertEquals("resolved", conflict(90).state.value.operation?.currentState)

        val gone = conflict(91)
        assertNull(gone.state.value.operation)
        assertTrue(gone.state.value.isGone)

        api.lists[OfflineOperationsState.Open] = GoodsOutResult.Unreachable
        assertEquals(GoodsOutProblem.Offline, conflict(95).state.value.problem)
    }

    @Test
    fun `every allowed resolution is sent, the actions that ran again are shown and a sync follows`() {
        OfflineResolution.entries.forEach { resolution ->
            api.lists[OfflineOperationsState.Open] = GoodsOutResult.Loaded(listOf(entry(81, resolutions = listOf(resolution.key))))
            val ranAgain = listOf(entry(83, status = "applied", resolutions = emptyList()))
            api.resolveResult = { GoodsOutResult.Loaded(OfflineResolveOutcome(entry(81, status = "resolved", resolutions = emptyList()), ranAgain)) }
            val viewModel = conflict()

            viewModel.resolve(resolution)

            val state = viewModel.state.value
            assertEquals(81L to resolution, api.resolutions.last())
            assertEquals(ranAgain, state.ranAgain)
            assertTrue(state.isClosed)
            assertEquals(resolution, state.resolvedWith)
            assertEquals(ConflictEvent.Resolved(resolution), state.event)
        }
        assertEquals(OfflineResolution.entries.size, offline.syncs.count { it == SyncReason.MANUAL })
    }

    @Test
    fun `a resolution that is not offered is not sent, a refusal keeps the action open`() {
        api.lists[OfflineOperationsState.Open] = GoodsOutResult.Loaded(listOf(entry(81, resolutions = listOf("done"))))
        val viewModel = conflict()

        viewModel.resolve(OfflineResolution.KeepCount)
        assertTrue(api.resolutions.isEmpty())

        api.resolveResult = { GoodsOutResult.Forbidden("Supervisors only") }
        viewModel.resolve(OfflineResolution.Done)
        assertEquals(ConflictEvent.Refused("Supervisors only", GoodsOutProblem.Forbidden), viewModel.state.value.event)
        assertFalse(viewModel.state.value.isClosed)
        assertTrue(offline.syncs.isEmpty())
    }

    @Test
    fun `roll back only when the action allows it`() {
        api.lists[OfflineOperationsState.Open] = GoodsOutResult.Loaded(listOf(entry(81), entry(82, canRollback = true)))
        conflict(81).rollback()
        assertTrue(api.rollbacks.isEmpty())

        api.rollbackResult = GoodsOutResult.Loaded(entry(82, status = "rolled_back"))
        val viewModel = conflict(82)
        viewModel.rollback()

        assertEquals(listOf(82L), api.rollbacks)
        assertEquals(ConflictEvent.RolledBack, viewModel.state.value.event)
        assertEquals("rolled_back", viewModel.state.value.operation?.currentState)
        assertTrue(viewModel.state.value.isClosed)
    }

    @Test
    fun `conflicts are grouped per record, offering only the resolutions every line allows`() {
        val note = OfflineOperationRecord(type = "delivery_note", id = 7)
        val first = entry(81, resolutions = listOf("put_back", "done")).copy(record = note)
        val second = entry(82, resolutions = listOf("put_back", "discard")).copy(record = note)
        val alone = entry(83).copy(record = OfflineOperationRecord(type = "location", id = 4), labels = OfflineOperationLabels(title = "P-01", locationCode = "P-01"))
        val unknown = entry(84)

        val groups = groupConflicts(listOf(first, alone, second, unknown))

        assertEquals(listOf(listOf(81L, 82L), listOf(83L), listOf(84L)), groups.map { group -> group.lines.map { it.key } })
        assertEquals(OfflineRecordKey(OfflineRecordType.DELIVERY_NOTE, 7), groups[0].recordKey)
        assertEquals(listOf(OfflineResolution.PutBack), groups[0].bulkResolutions)
        assertEquals("P-01", groups[1].reference)
        assertTrue(groups[1].bulkResolutions.isEmpty())
        assertNull(groups[2].record)
    }

    @Test
    fun `resolving a whole record runs every line and shows what happened to each`() {
        val note = OfflineOperationRecord(type = "delivery_note", id = 7)
        val lines = listOf(entry(81).copy(record = note), entry(82).copy(record = note))
        api.lists[OfflineOperationsState.Open] = GoodsOutResult.Loaded(lines + entry(90).copy(record = OfflineOperationRecord(type = "delivery_note", id = 8)))
        api.resolveById[81] = GoodsOutResult.Loaded(OfflineResolveOutcome(entry(81, status = "resolved", resolutions = emptyList()), emptyList()))
        api.resolveById[82] = GoodsOutResult.Invalid("Already resolved by B")
        val viewModel = ConflictRecordViewModel(api, FakeSessions(), offline, note)
        viewModel.onStart()

        assertEquals(listOf(81L, 82L), viewModel.state.value.lines.map { it.key })

        viewModel.resolveAll(OfflineResolution.Discard)
        assertTrue(api.resolutions.isEmpty())

        viewModel.resolveAll(OfflineResolution.PutBack)

        val state = viewModel.state.value
        assertEquals(listOf(81L to OfflineResolution.PutBack, 82L to OfflineResolution.PutBack), api.resolutions)
        assertEquals(listOf(true, false), state.results.map { it.isResolved })
        assertEquals("Already resolved by B", state.results[1].message)
        assertEquals(ConflictRecordEvent(resolved = 1, total = 2), state.event)
        assertEquals(listOf(SyncReason.MANUAL), offline.syncs)
    }
}
