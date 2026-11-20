package io.aiku.droid.data.offline

import io.aiku.droid.data.cache.XorCipher
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.JsonObject
import org.junit.Assert.assertArrayEquals
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class DeviceOfflineWorkTest {

    private val harness = OfflineHarness().start()
    private val work = harness.work

    @Test
    fun `offline every record is queued`() {
        harness.online.value = false

        assertTrue(work.shouldQueue(NOTE_5))
    }

    @Test
    fun `online a record is queued only while it has actions waiting or sending`() {
        assertFalse(work.shouldQueue(NOTE_5))

        harness.api.answer = { SyncAnswer.Unreachable }
        harness.online.value = false
        harness.pick(1)
        harness.online.value = true

        assertTrue(work.shouldQueue(NOTE_5))
        assertFalse(work.shouldQueue(NOTE_6))
    }

    @Test
    fun `an action needing the person does not make its record queue`() = runTest {
        harness.pick(1)
        harness.api.answer = { call -> SyncAnswer.Answered(call.actions.map { result(it.sequence, OfflineSyncResponses.STATUS_REFUSED) }, null) }
        harness.api.openOperations = OpenOperationsAnswer.Loaded(listOf(OpenOperation(81, result(1, OfflineSyncResponses.STATUS_REFUSED).result)))
        harness.engine.run(maxBatches = 4)

        assertFalse(work.shouldQueue(NOTE_5))
        assertEquals(1, work.needsYouCount.value)
    }

    @Test
    fun `a queued action keeps its user, server time and payload, and asks for a sync`() {
        harness.clock.record(OFFLINE_NOW)
        harness.elapsed += 5_000

        val queued = harness.pick(3)

        assertEquals(ANA.userId, queued.userId)
        assertEquals(OFFLINE_NOW.plusSeconds(5), queued.performedAt)
        assertTrue(queued.timeSourceIsServer)
        assertEquals(QueuedActionState.WAITING, queued.state)
        assertNull(queued.sequence)
        assertEquals(listOf(SyncReason.QUEUED), harness.scheduler.scheduled.filter { it == SyncReason.QUEUED })
        assertEquals(listOf(queued), work.queue.value)
        assertEquals(listOf(queued), work.pendingFor(NOTE_5).value)
        assertTrue(work.pendingFor(NOTE_6).value.isEmpty())
        assertEquals(1, work.waitingCount.value)
    }

    @Test
    fun `the queue survives a restart with its payload encrypted on disk`() {
        val queued = harness.pick(3)

        val stored = harness.store.actions.values.single()
        assertFalse(stored.payload!!.toString(Charsets.UTF_8).contains("delivery_note_item_id"))
        val restored = OfflineQueue(harness.store, XorCipher()).entries().single().toAction()

        assertEquals(queued, restored)
    }

    @Test
    fun `an action that was sending when the app stopped is waiting again after a restart`() = runTest {
        harness.pick(1)
        harness.queue.claimBatch(ANA.userId, 50) { harness.device.reserveSequences(ANA.userId, it) }

        val restored = OfflineQueue(harness.store, XorCipher()).entries().single()

        assertEquals(QueuedActionState.WAITING, restored.state)
        assertEquals(1L, restored.sequence)
    }

    @Test
    fun `undo before numbering deletes the action`() {
        val queued = harness.pick(1)

        work.undo(queued.localId)

        assertTrue(harness.store.actions.isEmpty())
        assertTrue(work.queue.value.isEmpty())
        assertEquals(0, work.waitingCount.value)
    }

    @Test
    fun `undo after numbering sends the number as a void`() = runTest {
        val queued = harness.pick(1)
        harness.api.answer = { SyncAnswer.Unreachable }
        harness.engine.run(maxBatches = 4)

        work.undo(queued.localId)

        assertEquals(QueuedActionState.VOID, harness.queue.entries().single().state)
        assertTrue(work.queue.value.isEmpty())
        assertTrue(work.pendingFor(NOTE_5).value.isEmpty())

        harness.api.answer = { call -> SyncAnswer.Answered(call.actions.map { result(it.sequence, OfflineSyncResponses.STATUS_VOID) }, null) }
        harness.engine.run(maxBatches = 4)

        val sent = harness.api.calls.last().actions.single()
        assertEquals(1L, sent.sequence)
        assertEquals("void", sent.operation)
        assertEquals(JsonObject(emptyMap()), sent.payload)
        assertNotNull(harness.queue.entries().single().syncedAt)
        assertFalse(harness.queue.entries().single().isPendingSend)
    }

    @Test
    fun `undo leaves an action alone once it is sent`() = runTest {
        val queued = harness.pick(1)
        harness.engine.run(maxBatches = 4)

        work.undo(queued.localId)

        assertEquals(QueuedActionState.APPLIED, harness.queue.entries().single().state)
    }

    @Test
    fun `flush sends the record's queue and says whether anything is left for it`() = runTest {
        harness.online.value = false
        harness.pick(1)
        harness.pick(2, record = NOTE_6)
        harness.pick(3)

        assertFalse(work.flush(NOTE_5))
        assertTrue(harness.api.calls.isEmpty())

        harness.online.value = true

        assertTrue(work.flush(NOTE_5))
        assertEquals(listOf(1L, 2L, 3L), harness.api.sentSequences)
        assertFalse(work.shouldQueue(NOTE_5))
    }

    @Test
    fun `flush is false while an action on the record needs the person`() = runTest {
        harness.pick(1)
        harness.api.answer = { call -> SyncAnswer.Answered(call.actions.map { result(it.sequence, OfflineSyncResponses.STATUS_PARTLY_APPLIED) }, null) }
        harness.api.openOperations = OpenOperationsAnswer.Loaded(listOf(OpenOperation(81, result(1, OfflineSyncResponses.STATUS_PARTLY_APPLIED).result)))

        assertFalse(work.flush(NOTE_5))
        assertTrue(work.flush(NOTE_6))
    }

    @Test
    fun `sign-out keeps the queue while the downloads are purged`() = runTest {
        harness.downloads.replace(ANA.userId, NOTE_5, """{"data":{"id":5}}""", OFFLINE_NOW)
        harness.online.value = false
        harness.pick(1)

        harness.sessions.end()

        assertTrue(harness.store.downloads.isEmpty())
        assertEquals(1, harness.store.actions.size)

        harness.api.answer = { SyncAnswer.Unreachable }
        harness.sessions.start(ANA, rememberOnDevice = false)

        assertEquals(1, work.waitingCount.value)
        assertNull(work.downloaded(NOTE_5))
    }

    @Test
    fun `there is periodic work only while the queue holds waiting actions`() = runTest {
        assertEquals(false, harness.scheduler.periodic)

        harness.online.value = false
        harness.pick(1)
        assertEquals(true, harness.scheduler.periodic)

        harness.online.value = true
        harness.engine.run(maxBatches = 4)
        assertEquals(false, harness.scheduler.periodic)
    }

    @Test
    fun `another user's sign-in sends a waiting notice for each user with waiting actions, once`() = runTest {
        harness.online.value = false
        harness.pick(1)
        harness.pick(2)
        harness.sessions.end()
        harness.online.value = true

        harness.sessions.start(BEN, rememberOnDevice = false)

        assertEquals(listOf(Triple(BEN.token, ANA.userId, 2)), harness.api.notices)
        harness.engine.run(maxBatches = 4)
        assertTrue(harness.api.calls.isEmpty())

        harness.sessions.end()
        harness.sessions.start(BEN, rememberOnDevice = false)

        assertEquals(1, harness.api.notices.size)
    }

    @Test
    fun `a waiting notice that could not be sent goes out when the phone is back online`() = runTest {
        harness.online.value = false
        harness.pick(1)
        harness.sessions.end()
        harness.api.noticeAnswer = NoticeAnswer.Unreachable

        harness.sessions.start(BEN, rememberOnDevice = false)
        harness.api.noticeAnswer = NoticeAnswer.Sent
        harness.online.value = true

        assertEquals(2, harness.api.notices.size)
        harness.online.value = false
        harness.online.value = true
        assertEquals(2, harness.api.notices.size)
    }

    @Test
    fun `the downloaded copy is read back with its server time`() = runTest {
        harness.downloads.replace(ANA.userId, NOTE_5, """{"data":{"id":5}}""", OFFLINE_NOW.minusSeconds(60))

        val copy = work.downloaded(NOTE_5)

        assertEquals("""{"data":{"id":5}}""", copy?.body)
        assertEquals(OFFLINE_NOW.minusSeconds(60), copy?.seenAt)
        assertEquals(OFFLINE_NOW, copy?.downloadedAt)
        assertArrayEquals(
            XorCipher().encrypt("""{"data":{"id":5}}""".toByteArray()),
            harness.store.downloads.values.single().payload,
        )
    }

    @Test
    fun `sync now from the person runs at once and clock-out asks for expedited work`() = runTest {
        harness.pick(1)

        work.syncNow(SyncReason.MANUAL)
        work.syncNow(SyncReason.CLOCK_OUT)

        assertEquals(listOf(1L), harness.api.sentSequences)
        assertTrue(SyncReason.CLOCK_OUT in harness.scheduler.scheduled)
    }

    @Test
    fun `foreground and reconnect sync at once in the app, only when something is left to settle`() = runTest {
        harness.scheduler.scheduled.clear()

        work.syncNow(SyncReason.FOREGROUND)
        assertTrue(harness.api.sentSequences.isEmpty())

        harness.online.value = false
        harness.pick(1)
        harness.scheduler.scheduled.clear()
        harness.online.value = true

        assertEquals(listOf(1L), harness.api.sentSequences)
        assertTrue(SyncReason.RECONNECTED !in harness.scheduler.scheduled)

        harness.pick(2)
        harness.scheduler.scheduled.clear()
        work.syncNow(SyncReason.FOREGROUND)

        assertEquals(listOf(1L, 2L), harness.api.sentSequences)
    }

    @Test
    fun `a background run asks for a follow-up when more is waiting`() = runTest {
        repeat(201) { harness.pick(it + 1) }

        val outcome = work.runInBackground()

        assertEquals(SyncRunStatus.COMPLETE, outcome.status)
        assertEquals(1, harness.scheduler.followUps)
        assertEquals(200, harness.api.sentSequences.size)
    }
}
