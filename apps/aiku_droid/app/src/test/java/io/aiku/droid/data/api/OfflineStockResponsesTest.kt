package io.aiku.droid.data.api

import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.jsonObject
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class OfflineStockResponsesTest {

    private val json = ClockingResponses.json

    @Test
    fun `return items read their barcodes and the version of every location row`() {
        val body = """
            {"data":{"id":5,"reference":"R-5","state":"returning","items":[
              {"id":51,"sko":{"id":77,"code":"AWO-12"},"expected":3,"remaining":3,
               "locations":[{"location_org_stock_id":5501,"location":{"id":220,"code":"E-04-2"},"type":"picking","quantity":"4","location_id":220,"location_code":"E-04-2","version":7}],
               "barcodes":["5060","5061"]},
              {"id":52,"sko":{"id":78,"code":"AWO-13"},"expected":1,"remaining":1,"locations":[{"location_org_stock_id":5502,"location":{"id":221,"code":"E-04-3"},"quantity":0}]}
            ]}}
        """.trimIndent()

        val loaded = (GoodsInResponses.parseReturn(200, body) as GoodsOutResult.Loaded).value

        assertEquals(listOf("5060", "5061"), loaded.item(51)?.barcodes)
        assertEquals(7, loaded.item(51)?.locations?.single()?.version)
        assertEquals("E-04-2", loaded.item(51)?.locations?.single()?.location?.code)
        assertTrue(loaded.item(52)?.barcodes.orEmpty().isEmpty())
        assertNull(loaded.item(52)?.locations?.single()?.version)
    }

    @Test
    fun `stock delivery item locations, the location page and the SKO page read the row version`() {
        val delivery = """
            {"data":{"id":1,"reference":"SD-1","items":[{"id":10,"sko_to_place":2,
              "locations":[{"location_org_stock_id":5501,"location":{"id":220,"code":"A-01"},"quantity":4,"version":"3"}]}]}}
        """.trimIndent()
        val location = """
            {"data":{"id":220,"code":"A-01","org_stocks":[{"id":77,"code":"AWO-12","quantity":30,"location_org_stock_id":5501,"version":9}]}}
        """.trimIndent()
        val sko = """
            {"data":{"id":77,"code":"AWO-12","locations":[{"location_org_stock_id":5501,"quantity":30,"version":12,"location":{"id":220,"code":"A-01"}}]}}
        """.trimIndent()

        assertEquals(3, (GoodsInResponses.parseStockDelivery(200, delivery) as GoodsOutResult.Loaded).value.item(10)?.locations?.single()?.version)
        assertEquals(9, (InventoryResponses.parseLocation(200, location) as GoodsOutResult.Loaded).value.orgStock(5501)?.version)
        assertEquals(12, (InventoryResponses.parseOrgStock(200, sko) as GoodsOutResult.Loaded).value.location(5501)?.version)
    }

    @Test
    fun `return puts, moves and audits send the client request id online`() {
        val returned = GoodsInResponses.returnWriteRequest(ReturnWrite.Returned(itemId = 51, quantity = 2.0, locationOrgStockId = 5501, clientRequestId = "req-1"))
        val damaged = GoodsInResponses.returnWriteRequest(ReturnWrite.Damaged(itemId = 51, quantity = 1.0, clientRequestId = "req-2"))
        val notReturned = GoodsInResponses.returnWriteRequest(ReturnWrite.NotReturned(itemId = 51, quantity = 1.0, clientRequestId = "req-3"))
        val withoutId = GoodsInResponses.returnWriteRequest(ReturnWrite.Damaged(itemId = 51, quantity = 1.0))

        assertEquals(JsonPrimitive("req-1"), returned.body?.get("client_request_id"))
        assertEquals(JsonPrimitive(5501), returned.body?.get("location_org_stock_id"))
        assertEquals(JsonPrimitive("req-2"), damaged.body?.get("client_request_id"))
        assertEquals(JsonPrimitive("req-3"), notReturned.body?.get("client_request_id"))
        assertFalse(withoutId.body.orEmpty().containsKey("client_request_id"))

        val counts = listOf(AuditCount(locationOrgStockId = 5501, quantity = 28.0, reason = "damaged", note = null))
        assertEquals(JsonPrimitive("a"), InventoryResponses.locationWriteBody(LocationWrite.Audit(counts, clientRequestId = "a"))["client_request_id"])
        assertEquals(JsonPrimitive("b"), InventoryResponses.locationWriteBody(LocationWrite.MoveAll(targetLocationId = 9, removeAfterMove = false, clientRequestId = "b"))["client_request_id"])
        assertEquals(
            JsonPrimitive("c"),
            InventoryResponses.locationWriteBody(LocationWrite.MoveSome(targetLocationId = 9, items = listOf(MoveSomeItem(77, 2.0, false)), clientRequestId = "c"))["client_request_id"],
        )
        assertEquals(JsonPrimitive("d"), InventoryResponses.orgStockWriteBody(OrgStockWrite.Audit(counts, clientRequestId = "d"))["client_request_id"])
        assertEquals(JsonPrimitive("e"), InventoryResponses.orgStockWriteBody(OrgStockWrite.Move(5501, listOf(MoveTarget(5502, 2.0)), clientRequestId = "e"))["client_request_id"])
        assertFalse(InventoryResponses.locationWriteBody(LocationWrite.Audit(counts)).containsKey("client_request_id"))
    }

    @Test
    fun `the open actions list reads the result shape, labels, record, resolutions and rollback`() {
        val body = """
            {"data":[{"sequence":12,"status":"partly_applied","operation_id":81,"operation":"return_put_back","reason":"over_expected","instruction":"put_back",
              "message":"Put back 1 of 3.","requested_quantity":"3","applied_quantity":1,"counted_since":false,"task_reference":"TASK-12",
              "conflicting_user":{"id":4,"name":"Sam"},"blocked_by_sequence":null,"versions":[{"location_org_stock_id":9,"version":8,"quantity":10}],
              "performed_at":"2026-11-20T10:00:00.000Z","synced_at":"2026-11-20T10:30:00.000Z","state":"partly_applied","resolution":null,
              "labels":{"title":"R-5 · line 1","subtitle":"AWO-12 Lavender","location_code":"E-04-2"},"record":{"type":"return","id":5},
              "resolutions":["put_back","done","teleport"],"can_rollback":true},
             {"status":"conflict","operation_id":82,"operation":"audit_location","instruction":"recount","resolutions":["recount","discard"],
              "counted_rows":[{"location_code":"ZQ28A","counted":9,"quantity":"10"}]}]}
        """.trimIndent()

        val list = (OfflineOperationsResponses.parseList(200, body) as GoodsOutResult.Loaded).value

        val first = list.first()
        assertEquals(81L, first.key)
        assertEquals(12, first.sequence)
        assertEquals(OfflineOutcomeStatus.PARTLY_APPLIED, first.currentState)
        assertEquals(3.0, first.requestedQuantity ?: 0.0, 0.0)
        assertEquals(2.0, first.missingQuantity ?: 0.0, 0.0)
        assertEquals("Sam", first.conflictingUser?.name)
        assertEquals("TASK-12", first.taskReference)
        assertEquals("E-04-2", first.labels.locationCode)
        assertEquals(OfflineOperationRecord(type = "return", id = 5), first.record)
        assertEquals(listOf(OfflineResolution.PutBack, OfflineResolution.Done), first.allowedResolutions)
        assertTrue(first.canRollback)
        assertEquals(OfflineOutcomeStatus.CONFLICT, list[1].currentState)
        assertFalse(list[1].canRollback)
        assertNull(list[1].missingQuantity)
        assertEquals(emptyList<OfflineCountedRow>(), first.countedRows)
        assertEquals(listOf(OfflineCountedRow(locationCode = "ZQ28A", counted = 9.0, quantity = 10.0)), list[1].countedRows)
    }

    @Test
    fun `resolve answers the operation and the blocked actions that ran again, rollback answers the operation`() {
        val resolved = """
            {"data":{"operation":{"operation_id":81,"status":"partly_applied","state":"resolved","resolution":"done","operation":"pick"},
              "results":[{"sequence":13,"status":"applied","operation_id":83,"operation":"pick","message":"Picked 2."}]}}
        """.trimIndent()
        val rolledBack = """{"data":{"operation":{"operation_id":81,"status":"applied","state":"rolled_back","operation":"pick"}}}"""

        val outcome = (OfflineOperationsResponses.parseResolve(200, resolved) as GoodsOutResult.Loaded).value
        assertEquals("resolved", outcome.operation.currentState)
        assertEquals(listOf(83L), outcome.results.map { it.key })
        assertEquals("rolled_back", (OfflineOperationsResponses.parseRollback(200, rolledBack) as GoodsOutResult.Loaded).value.currentState)
        assertEquals(GoodsOutResult.SessionExpired, OfflineOperationsResponses.parseResolve(401, "{}"))
        assertTrue(OfflineOperationsResponses.parseRollback(422, """{"message":"Too late to roll back."}""") is GoodsOutResult.Invalid)
    }

    @Test
    fun `the operation requests carry the state, the resolution and an optional reason`() {
        assertEquals(listOf("state" to "open"), OfflineOperationsResponses.listParameters(OfflineOperationsState.Open))
        assertEquals(listOf("state" to "recent"), OfflineOperationsResponses.listParameters(OfflineOperationsState.Recent))
        assertEquals(JsonPrimitive("keep_count"), json.parseToJsonElement(OfflineOperationsResponses.resolveBody(OfflineResolution.KeepCount)).jsonObject["resolution"])
        assertEquals("{}", OfflineOperationsResponses.rollbackBody("  "))
        assertEquals(JsonPrimitive("Wrong shelf"), json.parseToJsonElement(OfflineOperationsResponses.rollbackBody("Wrong shelf")).jsonObject["reason"])
    }
}
