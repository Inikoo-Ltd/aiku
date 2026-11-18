package io.aiku.droid.data.api

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class InboxResponsesTest {

    @Test
    fun `the list reads rows, paging and the unread count`() {
        val body = """{"data":[
            {"id":"9b1c","kind":"delivery_note","event":"modified","title":"AR24811 was changed",
             "body":"Heads up: AR24811, assigned to you, was changed by Sandra.","created_at":"2026-11-18T09:00:00+00:00","read_at":null,
             "target":{"screen":"delivery_note","id":991,"reference":"AR24811","state":"handling","warehouse_id":1,"type_group":null}},
            {"id":"77aa","kind":"pallet_return","event":"ready_again","title":"acme-r004 can go ahead","body":"acme-r004 is no longer waiting and can be done again.",
             "created_at":"2026-11-17T15:00:00+00:00","read_at":"2026-11-17T16:00:00+00:00",
             "target":{"screen":"pallet_return","id":7,"reference":"acme-r004","state":"picking","warehouse_id":"2","type_group":"items"}}
            ],
            "meta":{"current_page":1,"last_page":3,"per_page":30,"total":61},
            "unread":"12"}"""

        val page = (InboxResponses.parseList(200, body) as GoodsOutResult.Loaded).value

        assertEquals(
            InboxNotification(
                id = "9b1c",
                kind = "delivery_note",
                event = WorkEvent.MODIFIED,
                title = "AR24811 was changed",
                body = "Heads up: AR24811, assigned to you, was changed by Sandra.",
                createdAt = "2026-11-18T09:00:00+00:00",
                readAt = null,
                target = WorkRecord(kind = MyWorkKind.DeliveryNote, id = 991, reference = "AR24811", state = "handling", warehouseId = 1, typeGroup = null),
            ),
            page.notifications.first(),
        )
        assertTrue(page.notifications.first().isUnread)
        assertFalse(page.notifications.last().isUnread)
        assertEquals(WorkRecord(kind = MyWorkKind.PalletReturn, id = 7, reference = "acme-r004", state = "picking", warehouseId = 2, typeGroup = "items"), page.notifications.last().target)
        assertEquals(ListMeta(currentPage = 1, lastPage = 3, perPage = 30, total = 61), page.meta)
        assertEquals(12, page.unread)
    }

    @Test
    fun `a row the user may no longer open keeps its text without a target`() {
        val body = """{"data":[{"id":"1","kind":"return","event":"cancelled","title":"RET-31 cancelled","body":"Sandra cancelled RET-31.","created_at":"2026-11-18T09:00:00Z","read_at":null,"target":null}],"meta":{},"unread":1}"""

        val row = (InboxResponses.parseList(200, body) as GoodsOutResult.Loaded).value.notifications.single()

        assertEquals("RET-31 cancelled", row.title)
        assertNull(row.target)
    }

    @Test
    fun `a target of an unknown screen or without an id is dropped, the row stays`() {
        val body = """{"data":[
            {"id":"1","kind":"stock_delivery","event":"assigned","title":"SD-41 assigned to you","body":"","created_at":null,"read_at":null,
             "target":{"screen":"stock_delivery","id":41,"reference":"SD-41","state":"received","warehouse_id":1}},
            {"id":"2","kind":"delivery_note","event":"assigned","title":"AR1 assigned to you","body":"","created_at":null,"read_at":null,
             "target":{"screen":"delivery_note","id":null,"reference":"AR1","state":"unassigned","warehouse_id":1}}
            ],"meta":{"current_page":1,"last_page":1,"per_page":30,"total":2}}"""

        val rows = (InboxResponses.parseList(200, body) as GoodsOutResult.Loaded).value.notifications

        assertEquals(listOf("1", "2"), rows.map { it.id })
        assertEquals("stock_delivery", rows.first().kind)
        assertTrue(rows.all { it.target == null })
    }

    @Test
    fun `rows without an id or that are not objects are skipped, nulls become empty`() {
        val body = """{"data":[
            {"id":null,"kind":"delivery_note","title":"No id"},
            "not a row",
            {"id":"  ","title":"Blank id"},
            {"id":"5","kind":null,"event":null,"title":null,"body":null,"created_at":"","read_at":"","target":{"screen":null}}
            ],"meta":{"current_page":1,"last_page":1,"per_page":30,"total":4},"unread":null}"""

        val page = (InboxResponses.parseList(200, body) as GoodsOutResult.Loaded).value

        assertEquals(listOf(InboxNotification(id = "5")), page.notifications)
        assertNull(page.unread)
    }

    @Test
    fun `the list maps failures`() {
        assertEquals(GoodsOutResult.SessionExpired, InboxResponses.parseList(401, """{"message":"Unauthenticated."}"""))
        assertTrue(InboxResponses.parseList(403, """{"message":"Forbidden"}""") is GoodsOutResult.Forbidden)
        assertEquals(GoodsOutResult.ServerError(200), InboxResponses.parseList(200, "not json"))
        assertEquals(GoodsOutResult.ServerError(500), InboxResponses.parseList(500, ""))
    }

    @Test
    fun `the list asks for every notification thirty at a time`() {
        assertEquals(listOf("filter" to "all", "page" to "2", "per_page" to "30"), InboxResponses.listParameters(2))
    }

    @Test
    fun `the unread count and read all read the count`() {
        assertEquals(GoodsOutResult.Loaded(100), InboxResponses.parseUnread(200, """{"data":{"unread":100}}"""))
        assertEquals(GoodsOutResult.Loaded(0), InboxResponses.parseUnread(200, """{"data":{"unread":0}}"""))
        assertEquals(GoodsOutResult.Loaded(0), InboxResponses.parseUnread(200, """{"data":{"unread":null}}"""))
        assertEquals(GoodsOutResult.ServerError(200), InboxResponses.parseUnread(200, """{"unread":3}"""))
        assertEquals(GoodsOutResult.SessionExpired, InboxResponses.parseUnread(401, ""))
    }

    @Test
    fun `marking read returns the row`() {
        val body = """{"data":{"id":"9b1c","kind":"picking_session","event":"taken_over","title":"PS-9 taken over","body":"Ana has taken over PS-9, which was assigned to you.",
            "created_at":"2026-11-18T09:00:00+00:00","read_at":"2026-11-18T09:05:00+00:00",
            "target":{"screen":"picking_session","id":9,"reference":"PS-9","state":"in_process","warehouse_id":1,"type_group":null}}}"""

        val row = (InboxResponses.parseNotification(200, body) as GoodsOutResult.Loaded).value

        assertEquals("2026-11-18T09:05:00+00:00", row.readAt)
        assertEquals(WorkRecord(kind = MyWorkKind.PickingSession, id = 9, reference = "PS-9", state = "in_process", warehouseId = 1), row.target)
        assertTrue(InboxResponses.parseNotification(404, """{"message":"Not found"}""") is GoodsOutResult.NotFound)
        assertEquals(GoodsOutResult.ServerError(200), InboxResponses.parseNotification(200, """{"data":{"id":null}}"""))
    }
}
