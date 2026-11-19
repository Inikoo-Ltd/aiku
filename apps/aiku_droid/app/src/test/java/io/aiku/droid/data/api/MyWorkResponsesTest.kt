package io.aiku.droid.data.api

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class MyWorkResponsesTest {

    @Test
    fun `badges read both counts`() {
        val badges = (MyWorkResponses.parseBadges(200, """{"data":{"my_work":4,"goods_out_unassigned":"12"}}""") as GoodsOutResult.Loaded).value

        assertEquals(WorkBadges(myWork = 4, goodsOutUnassigned = 12), badges)
    }

    @Test
    fun `badges read the inbox unread count`() {
        val badges = (MyWorkResponses.parseBadges(200, """{"data":{"my_work":4,"goods_out_unassigned":null,"inbox_unread":"100"}}""") as GoodsOutResult.Loaded).value
        val withoutInbox = (MyWorkResponses.parseBadges(200, """{"data":{"my_work":4,"inbox_unread":null}}""") as GoodsOutResult.Loaded).value

        assertEquals(WorkBadges(myWork = 4, goodsOutUnassigned = null, inboxUnread = 100), badges)
        assertNull(withoutInbox.inboxUnread)
    }

    @Test
    fun `badges read the goods in received count`() {
        val badges = (MyWorkResponses.parseBadges(200, """{"data":{"my_work":1,"goods_in_received":3}}""") as GoodsOutResult.Loaded).value
        val asText = (MyWorkResponses.parseBadges(200, """{"data":{"goods_in_received":"5"}}""") as GoodsOutResult.Loaded).value
        val withoutRight = (MyWorkResponses.parseBadges(200, """{"data":{"my_work":1,"goods_in_received":null}}""") as GoodsOutResult.Loaded).value
        val oldServer = (MyWorkResponses.parseBadges(200, """{"data":{"my_work":1,"goods_out_unassigned":2}}""") as GoodsOutResult.Loaded).value

        assertEquals(WorkBadges(myWork = 1, goodsInReceived = 3), badges)
        assertEquals(5, asText.goodsInReceived)
        assertNull(withoutRight.goodsInReceived)
        assertNull(oldServer.goodsInReceived)
    }

    @Test
    fun `badges keep a null or missing count as null`() {
        val withoutRight = (MyWorkResponses.parseBadges(200, """{"data":{"my_work":0,"goods_out_unassigned":null}}""") as GoodsOutResult.Loaded).value
        val missing = (MyWorkResponses.parseBadges(200, """{"data":{}}""") as GoodsOutResult.Loaded).value

        assertEquals(0, withoutRight.myWork)
        assertNull(withoutRight.goodsOutUnassigned)
        assertEquals(WorkBadges(), missing)
    }

    @Test
    fun `badges map failures`() {
        assertEquals(GoodsOutResult.SessionExpired, MyWorkResponses.parseBadges(401, """{"message":"Unauthenticated."}"""))
        assertTrue(MyWorkResponses.parseBadges(404, """{"message":"Not found"}""") is GoodsOutResult.NotFound)
        assertEquals(GoodsOutResult.ServerError(200), MyWorkResponses.parseBadges(200, "not json"))
        assertEquals(GoodsOutResult.ServerError(500), MyWorkResponses.parseBadges(500, ""))
    }

    @Test
    fun `my work reads every kind of row in the order received`() {
        val body = """{"data":[
            {"kind":"delivery_note","id":123,"reference":"AR24811","state":"handling_blocked","state_label":"Waiting","step":"waiting","step_label":"Waiting",
             "type_group":null,"items":14,"customer":"Acme Ltd","trolleys":["T-03"],"since":"2026-11-17T08:02:00+00:00"},
            {"kind":"picking_session","id":9,"reference":"PS-0009","state":"in_process","state_label":"In process","step":"to_start","step_label":"To start",
             "type_group":null,"items":"3","customer":null,"trolleys":["T-01","T-02"],"since":"2026-11-17T08:10:00+00:00"},
            {"kind":"return","id":31,"reference":"RET-31","state":"received","state_label":"Received","step":"to_start","step_label":"To start",
             "type_group":null,"items":2,"customer":"Bravo","trolleys":[],"since":"2026-11-17T08:20:00+00:00"},
            {"kind":"pallet_return","id":7,"reference":"acme-r004","state":"picked","state_label":"Picked","step":"to_dispatch","step_label":"To dispatch",
             "type_group":"items","items":5,"customer":"Acme Ltd","trolleys":[],"since":"2026-11-17T08:30:00+00:00"}
            ],
            "counts":{"total":4,"delivery_notes":1,"picking_sessions":1,"returns":1,"pallet_returns":1}}"""

        val page = (MyWorkResponses.parseMyWork(200, body) as GoodsOutResult.Loaded).value

        assertEquals(listOf(MyWorkKind.DeliveryNote, MyWorkKind.PickingSession, MyWorkKind.CustomerReturn, MyWorkKind.PalletReturn), page.rows.map { it.kind })
        assertEquals(
            MyWorkRow(
                kind = MyWorkKind.DeliveryNote,
                id = 123,
                reference = "AR24811",
                state = "handling_blocked",
                stateLabel = "Waiting",
                step = MyWorkStep.WAITING,
                stepLabel = "Waiting",
                items = 14,
                customer = "Acme Ltd",
                trolleys = listOf("T-03"),
                since = "2026-11-17T08:02:00+00:00",
            ),
            page.rows[0],
        )
        assertEquals(3, page.rows[1].items)
        assertEquals(listOf("T-01", "T-02"), page.rows[1].trolleys)
        assertEquals(PalletReturnTypeGroup.ITEMS, page.rows[3].typeGroup)
        assertEquals(MyWorkCounts(total = 4, deliveryNotes = 1, pickingSessions = 1, returns = 1, palletReturns = 1), page.counts)
    }

    @Test
    fun `my work tolerates nulls in a row`() {
        val body = """{"data":[{"kind":"return","id":31,"reference":null,"state":"returning","state_label":null,"step":"returning","step_label":null,
            "type_group":null,"items":null,"customer":"  ","trolleys":null,"since":null}],"counts":{"total":1,"delivery_notes":null,"returns":1}}"""

        val page = (MyWorkResponses.parseMyWork(200, body) as GoodsOutResult.Loaded).value
        val row = page.rows.single()

        assertEquals(MyWorkRow(kind = MyWorkKind.CustomerReturn, id = 31, state = "returning", step = MyWorkStep.RETURNING), row)
        assertEquals(MyWorkCounts(total = 1, returns = 1), page.counts)
    }

    @Test
    fun `my work skips rows of an unknown kind or without an id`() {
        val body = """{"data":[
            {"kind":"task","id":5,"reference":"TASK-5","step":"to_start"},
            {"kind":"delivery_note","reference":"AR1","step":"to_pick"},
            {"kind":"delivery_note","id":"not a number","reference":"AR2"},
            {"kind":"pallet_return","id":7,"reference":"acme-r004","state":"picking","step":"picking"}
            ],"counts":{"total":4}}"""

        val page = (MyWorkResponses.parseMyWork(200, body) as GoodsOutResult.Loaded).value

        assertEquals(listOf(7L), page.rows.map { it.id })
        assertEquals(4, page.counts?.total)
    }

    @Test
    fun `my work without counts or rows still loads`() {
        val page = (MyWorkResponses.parseMyWork(200, """{"data":[]}""") as GoodsOutResult.Loaded).value

        assertTrue(page.rows.isEmpty())
        assertNull(page.counts)
    }

    @Test
    fun `my work maps failures`() {
        assertEquals(GoodsOutResult.SessionExpired, MyWorkResponses.parseMyWork(401, ""))
        assertTrue(MyWorkResponses.parseMyWork(404, """{"message":"Not found"}""") is GoodsOutResult.NotFound)
        assertEquals(GoodsOutResult.ServerError(200), MyWorkResponses.parseMyWork(200, """{"data":"oops"}"""))
    }
}
