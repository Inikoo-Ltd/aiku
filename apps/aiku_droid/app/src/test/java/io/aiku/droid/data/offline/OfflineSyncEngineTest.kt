package io.aiku.droid.data.offline

import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class OfflineSyncEngineTest {

    private val harness = OfflineHarness().start()
    private val api = harness.api

    private fun states(): List<QueuedActionState> = harness.queue.entries().filter { it.operation != null }.map { it.state }

    @Test
    fun `every answer status maps to a queued state and keeps the server result`() = runTest {
        val statuses = listOf(
            OfflineSyncResponses.STATUS_APPLIED,
            OfflineSyncResponses.STATUS_PARTLY_APPLIED,
            OfflineSyncResponses.STATUS_REFUSED,
            OfflineSyncResponses.STATUS_CONFLICT,
            OfflineSyncResponses.STATUS_BLOCKED,
            OfflineSyncResponses.STATUS_ALREADY_DONE,
            OfflineSyncResponses.STATUS_VOID,
        )
        statuses.indices.forEach { harness.pick(it + 1) }
        api.answer = { call -> SyncAnswer.Answered(call.actions.mapIndexed { index, action -> result(action.sequence, statuses[index], reason = "over_required") }, null) }
        api.openOperations = OpenOperationsAnswer.Loaded((82L..85L).map { OpenOperation(it, result(it - 80, OfflineSyncResponses.STATUS_REFUSED, operationId = it).result) })

        val outcome = harness.engine.run(maxBatches = 4)

        assertEquals(SyncRunStatus.COMPLETE, outcome.status)
        assertEquals(4, outcome.newlyNeedingYou)
        assertEquals(
            listOf(
                QueuedActionState.APPLIED,
                QueuedActionState.NEEDS_YOU,
                QueuedActionState.NEEDS_YOU,
                QueuedActionState.NEEDS_YOU,
                QueuedActionState.NEEDS_YOU,
                QueuedActionState.APPLIED,
                QueuedActionState.VOID,
            ),
            states(),
        )
        val alreadyDone = harness.queue.entries()[5]
        assertEquals(OfflineSyncResponses.STATUS_ALREADY_DONE, alreadyDone.result?.status)
        assertEquals(86L, alreadyDone.result?.operationId)
        assertEquals(4, harness.work.needsYouCount.value)
        assertEquals(0, harness.work.waitingCount.value)
    }

    @Test
    fun `the request carries the sequence, operation, both times and the explicit payload`() = runTest {
        harness.clock.record(OFFLINE_NOW)
        harness.pick(3)

        harness.engine.run(maxBatches = 4)

        val sent = api.calls.single().actions.single()
        assertEquals(1L, sent.sequence)
        assertEquals("pick", sent.operation)
        assertTrue(sent.timeSourceIsServer)
        assertEquals(OFFLINE_NOW.minusSeconds(600), sent.seenAt)
        assertEquals(pickPayload(3), sent.payload)
        assertEquals(1L, api.calls.single().warehouseId)
    }

    @Test
    fun `missing previous resends from the expected sequence and fills numbers the phone no longer holds with voids`() = runTest {
        harness.device.reserveSequences(ANA.userId, 3)
        harness.pick(1)
        api.answer = { call ->
            if (api.calls.size == 1) {
                SyncAnswer.Answered(listOf(result(call.actions.first().sequence, OfflineSyncResponses.STATUS_MISSING_PREVIOUS)), expectedSequence = 2)
            } else {
                SyncAnswer.Answered(call.actions.map { applied(it.sequence) }, null)
            }
        }

        val outcome = harness.engine.run(maxBatches = 4)

        assertEquals(SyncRunStatus.COMPLETE, outcome.status)
        assertEquals(listOf(4L), api.calls[0].actions.map { it.sequence })
        assertEquals(listOf(2L, 3L, 4L), api.calls[1].actions.map { it.sequence })
        assertEquals(listOf("void", "void", "pick"), api.calls[1].actions.map { it.operation })
        assertEquals(listOf(QueuedActionState.APPLIED), states())
    }

    @Test
    fun `missing previous resends an action the phone already thought was sent`() = runTest {
        harness.pick(1)
        harness.engine.run(maxBatches = 4)
        harness.pick(2)
        api.answer = { call ->
            if (api.calls.size == 2) {
                SyncAnswer.Answered(listOf(result(2, OfflineSyncResponses.STATUS_MISSING_PREVIOUS)), expectedSequence = 1)
            } else {
                SyncAnswer.Answered(call.actions.map { applied(it.sequence) }, null)
            }
        }

        harness.engine.run(maxBatches = 4)

        assertEquals(listOf(1L, 2L), api.calls[2].actions.map { it.sequence })
        assertEquals(listOf("pick", "pick"), api.calls[2].actions.map { it.operation })
        assertEquals(listOf(QueuedActionState.APPLIED, QueuedActionState.APPLIED), states())
    }

    @Test
    fun `numbers are kept per user and only the signed-in user's actions are sent`() = runTest {
        harness.pick(1)
        harness.pick(2)
        harness.engine.run(maxBatches = 4)
        harness.online.value = false
        harness.pick(3)
        harness.sessions.end()
        harness.sessions.start(BEN, rememberOnDevice = false)
        harness.online.value = true
        harness.pick(4)

        harness.engine.run(maxBatches = 4)

        assertEquals(listOf(BEN.token), api.calls.drop(1).map { it.token })
        assertEquals(listOf(1L), api.calls.drop(1).flatMap { call -> call.actions.map { it.sequence } })
        val anasWaiting = harness.queue.entries().single { it.userId == ANA.userId && it.state == QueuedActionState.WAITING }
        assertNull(anasWaiting.sequence)

        harness.sessions.end()
        harness.sessions.start(ANA, rememberOnDevice = false)
        harness.engine.run(maxBatches = 4)

        assertEquals(listOf(3L), api.calls.last().actions.map { it.sequence })
        assertEquals(ANA.token, api.calls.last().token)
    }

    @Test
    fun `a run sends at most four batches of fifty in sequence order`() = runTest {
        repeat(230) { harness.pick(it + 1) }

        val outcome = harness.engine.run(maxBatches = 4)

        assertEquals(listOf(50, 50, 50, 50), api.calls.map { it.actions.size })
        assertEquals((1L..200L).toList(), api.sentSequences)
        assertTrue(outcome.hasMore)
        assertEquals(30, harness.work.waitingCount.value)
    }

    @Test
    fun `batches follow the sequence across warehouses`() = runTest {
        harness.pick(1, warehouseId = 1)
        harness.pick(2, warehouseId = 2)
        harness.pick(3, warehouseId = 1)

        harness.engine.run(maxBatches = 4)

        assertEquals(listOf(1L, 2L, 1L), api.calls.map { it.warehouseId })
        assertEquals(listOf(1L, 2L, 3L), api.sentSequences)
    }

    @Test
    fun `a network failure keeps the numbers and the actions waiting for a retry`() = runTest {
        harness.pick(1)
        harness.pick(2)
        api.answer = { SyncAnswer.Unreachable }

        val failed = harness.engine.run(maxBatches = 4)

        assertEquals(SyncRunStatus.RETRY, failed.status)
        assertEquals(listOf(QueuedActionState.WAITING, QueuedActionState.WAITING), states())
        assertEquals(listOf(1L, 2L), harness.queue.entries().map { it.sequence })

        api.answer = { call -> SyncAnswer.Answered(call.actions.map { applied(it.sequence) }, null) }
        harness.engine.run(maxBatches = 4)

        assertEquals(listOf(1L, 2L), api.calls.last().actions.map { it.sequence })
        assertEquals(listOf(QueuedActionState.APPLIED, QueuedActionState.APPLIED), states())
    }

    @Test
    fun `a server error asks for a retry and a refused request stops`() = runTest {
        harness.pick(1)
        api.answer = { SyncAnswer.Failed(503) }
        assertEquals(SyncRunStatus.RETRY, harness.engine.run(maxBatches = 4).status)

        api.answer = { SyncAnswer.Failed(422) }
        assertEquals(SyncRunStatus.STOPPED, harness.engine.run(maxBatches = 4).status)

        api.answer = { SyncAnswer.SessionExpired }
        assertEquals(SyncRunStatus.STOPPED, harness.engine.run(maxBatches = 4).status)
        assertEquals(listOf(QueuedActionState.WAITING), states())
    }

    @Test
    fun `a record changed by a sync is fetched again so its download is fresh`() = runTest {
        harness.pick(1)
        harness.work.enqueue(
            2,
            OfflineOperation.RETURN_DAMAGED,
            OfflineRecordKey(OfflineRecordType.RETURN, 31),
            pickPayload(1),
            "req-r",
            null,
        )

        harness.engine.run(maxBatches = 4)

        assertEquals(listOf("delivery-notes/5", "warehouses/2/returns/31"), api.refetched)
    }

    @Test
    fun `an action that needed the person leaves the queue once the server no longer lists it open`() = runTest {
        harness.pick(1)
        api.answer = { call -> SyncAnswer.Answered(call.actions.map { result(it.sequence, OfflineSyncResponses.STATUS_REFUSED) }, null) }
        api.openOperations = OpenOperationsAnswer.Loaded(listOf(OpenOperation(81, result(1, OfflineSyncResponses.STATUS_REFUSED).result)))
        harness.engine.run(maxBatches = 4)
        assertEquals(1, harness.work.needsYouCount.value)

        api.openOperations = OpenOperationsAnswer.Loaded(emptyList())
        harness.engine.run(maxBatches = 4)

        assertEquals(0, harness.work.needsYouCount.value)
        assertTrue(harness.queue.entries().isEmpty())
    }

    @Test
    fun `nothing is sent without a signed-in user`() = runTest {
        harness.pick(1)
        harness.sessions.end()

        val outcome = harness.engine.run(maxBatches = 4)

        assertEquals(SyncRunStatus.STOPPED, outcome.status)
        assertTrue(api.calls.isEmpty())
        assertFalse(harness.queue.entries().isEmpty())
    }
}
