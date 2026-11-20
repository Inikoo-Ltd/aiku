package io.aiku.droid.data.offline

import io.aiku.droid.ui.components.OfflineIndicatorKind
import io.aiku.droid.ui.components.OfflineIndicatorState
import io.aiku.droid.ui.components.offlineIndicatorState
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonObject
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test
import java.time.Instant

class OfflineSyncResponsesTest {

    @Test
    fun `the sync request follows the contract`() {
        val body = OfflineSyncResponses.syncBody(
            listOf(
                SyncActionRequest(12, "pick", Instant.parse("2026-11-20T10:00:00Z"), true, Instant.parse("2026-11-20T09:30:00Z"), pickPayload(3)),
                SyncActionRequest(13, "void", Instant.parse("2026-11-20T10:01:00.250Z"), false, null, kotlinx.serialization.json.JsonObject(emptyMap())),
            ),
        )

        val actions = Json.parseToJsonElement(body).jsonObject["actions"]!!.jsonArray
        assertEquals(
            Json.parseToJsonElement(
                """{"sequence":12,"operation":"pick","performed_at":"2026-11-20T10:00:00.000Z","time_source":"server","seen_at":"2026-11-20T09:30:00.000Z",""" +
                    """"payload":{"delivery_note_id":5,"delivery_note_item_id":11,"location_org_stock_id":9,"quantity":3,"client_request_id":"req-3"}}""",
            ),
            actions[0],
        )
        assertEquals("device", actions[1].jsonObject["time_source"].toString().trim('"'))
        assertEquals(JsonNull, actions[1].jsonObject["seen_at"])
        assertEquals("2026-11-20T10:01:00.250Z", actions[1].jsonObject["performed_at"].toString().trim('"'))
    }

    @Test
    fun `the sync answer gives every result with its message, quantities, task and conflicting user`() {
        val answer = OfflineSyncResponses.parseSync(
            200,
            """{"data":{"results":[{"sequence":12,"status":"partly_applied","operation_id":81,"operation":"pick","reason":"over_required","instruction":"put_back",
                "message":"Picked 1 of 3 at E-04-2.","requested_quantity":3,"applied_quantity":"1.000000","counted_since":false,"task_reference":"TASK-12",
                "conflicting_user":{"id":7,"name":"Ben"},"blocked_by_sequence":null,"versions":[{"location_org_stock_id":9,"version":8,"quantity":10}]}],
                "last_sequence":12,"open_count":1}}""",
        ) as SyncAnswer.Answered

        val result = answer.results.single()
        assertEquals(12L, result.sequence)
        assertEquals(
            OfflineResult(
                status = "partly_applied",
                operationId = 81,
                reason = "over_required",
                instruction = "put_back",
                message = "Picked 1 of 3 at E-04-2.",
                requestedQuantity = 3.0,
                appliedQuantity = 1.0,
                countedSince = false,
                taskReference = "TASK-12",
                conflictingUserName = "Ben",
            ),
            result.result,
        )
        assertNull(answer.expectedSequence)
    }

    @Test
    fun `missing previous carries the expected sequence and other answers map to failures`() {
        val answer = OfflineSyncResponses.parseSync(200, """{"data":{"results":[{"sequence":14,"status":"missing_previous"}],"expected_sequence":13}}""") as SyncAnswer.Answered

        assertEquals(13L, answer.expectedSequence)
        assertEquals("none", answer.results.single().result.instruction)
        assertEquals(SyncAnswer.SessionExpired, OfflineSyncResponses.parseSync(401, "{}"))
        assertEquals(SyncAnswer.Failed(422), OfflineSyncResponses.parseSync(422, "{}"))
        assertEquals(SyncAnswer.Failed(200), OfflineSyncResponses.parseSync(200, "<html>"))
    }

    @Test
    fun `open operations are read with their ids`() {
        val answer = OfflineSyncResponses.parseOpenOperations(
            200,
            """{"data":[{"sequence":12,"status":"refused","operation_id":81,"reason":"location_empty","instruction":"keep_in_tote","state":"refused"}]}""",
        ) as OpenOperationsAnswer.Loaded

        assertEquals(81L, answer.operations.single().operationId)
        assertEquals("location_empty", answer.operations.single().result.reason)
    }

    @Test
    fun `a stored result reads back the same`() {
        val stored = result(3, OfflineSyncResponses.STATUS_CONFLICT, reason = "other_change").result.copy(conflictingUserName = "Ben", appliedQuantity = 0.5)

        assertEquals(stored, OfflineSyncResponses.parseResultJson(OfflineSyncResponses.resultJson(stored)))
    }

    @Test
    fun `the shell indicator says offline first, then what needs the person, then what is syncing`() {
        assertEquals(OfflineIndicatorState(OfflineIndicatorKind.Offline, 3), offlineIndicatorState(isOnline = false, waiting = 3, needYou = 2))
        assertEquals(OfflineIndicatorState(OfflineIndicatorKind.Offline, 0), offlineIndicatorState(isOnline = false, waiting = 0, needYou = 0))
        assertEquals(OfflineIndicatorState(OfflineIndicatorKind.NeedYou, 2), offlineIndicatorState(isOnline = true, waiting = 3, needYou = 2))
        assertEquals(OfflineIndicatorState(OfflineIndicatorKind.Syncing, 3), offlineIndicatorState(isOnline = true, waiting = 3, needYou = 0))
        assertNull(offlineIndicatorState(isOnline = true, waiting = 0, needYou = 0))
    }
}
