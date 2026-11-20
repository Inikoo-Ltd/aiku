package io.aiku.droid.data.api

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class GoodsOutOfflineResponsesTest {

    @Test
    fun `a note reads the barcodes, the picking session flag, the location versions and the picking settings`() {
        val body = """
            {"data":{"id":9912,"state":"handling","warehouse_id":1,
            "picking_settings":{"allow_waiting":true,"allow_picker_set_not_picked":true,"allow_scan_to_pick":true,"allow_picker_choose_location":false,"allow_scan_to_pack":true},
            "lines":[
            {"id":41001,"quantity_required":3,"quantity_to_pick":3,"barcodes":["5060001","", null,"5060002"],"in_picking_session":true,
            "org_stock":{"id":812,"code":"AWO-12","barcode":"5060001"},
            "locations":[{"location_org_stock_id":5501,"location_id":220,"code":"A-01-03","stock":30,"version":7}]},
            {"id":41002,"locations":[{"location_org_stock_id":5502,"code":"B-01"}]}]}}
        """.trimIndent()

        val note = (GoodsOutResponses.parseDeliveryNote(200, body) as GoodsOutResult.Loaded).value
        val first = note.lines.first()
        val second = note.lines.last()

        assertEquals(listOf("5060001", "5060002"), first.barcodes)
        assertTrue(first.inPickingSession)
        assertEquals(7L, first.locations.single().version)
        assertEquals(PickingSettings(allowScanToPick = true, allowWaiting = true, allowPickerSetNotPicked = true, allowPickerChooseLocation = false, allowScanToPack = true), note.settings)
        assertEquals(emptyList<String>(), second.barcodes)
        assertFalse(second.inPickingSession)
        assertNull(second.locations.single().version)
    }

    @Test
    fun `the settings still read from the older settings key`() {
        val note = (GoodsOutResponses.parseDeliveryNote(200, """{"data":{"id":1,"settings":{"allow_waiting":true}}}""") as GoodsOutResult.Loaded).value

        assertTrue(note.settings.allowWaiting)
        assertFalse(note.settings.allowPickerSetNotPicked)
    }

    @Test
    fun `line writes that can also be done offline carry their client request id`() {
        val id = "0b8f2c1e-4d1a-4f2e-9a51-7c3e2b1d0a99"

        assertBody(DeliveryNoteWrite.Pick(lineId = 41001, locationOrgStockId = 5501, quantity = 3.0, clientRequestId = id), """{"location_org_stock_id":5501,"quantity":3,"client_request_id":"$id"}""")
        assertBody(DeliveryNoteWrite.PickAll(lineId = 41001, locationOrgStockId = 5501, clientRequestId = id), """{"location_org_stock_id":5501,"client_request_id":"$id"}""")
        assertBody(
            DeliveryNoteWrite.NotPicked(lineId = 41001, quantity = null, reason = NotPickedReason.OutOfStock, note = null, clientRequestId = id),
            """{"quantity":null,"reason":"out-of-stock","note":null,"client_request_id":"$id"}""",
        )
        assertBody(
            DeliveryNoteWrite.Waiting(lineId = 41001, kind = WaitingKind.Warehouse, quantity = 2.0, note = null, clientRequestId = id),
            """{"kind":"warehouse","quantity":2,"note":null,"client_request_id":"$id"}""",
        )
        assertBody(DeliveryNoteWrite.Pack(lineId = 41001, quantity = 1.5, clientRequestId = id), """{"quantity":1.5,"client_request_id":"$id"}""")
        assertEquals(id, DeliveryNoteWrite.Pack(lineId = 41001, quantity = null).withClientRequestId(id).clientRequestId)
        assertEquals(DeliveryNoteWrite.TakeOver, DeliveryNoteWrite.TakeOver.withClientRequestId(id))
        assertTrue(DeliveryNoteWrite.Waiting(lineId = 1, kind = WaitingKind.Crm, quantity = 1.0, note = null).isOfflineLineWork)
        assertFalse(DeliveryNoteWrite.UndoPicking(lineId = 1, pickingId = 2).isOfflineLineWork)
    }

    private fun assertBody(write: DeliveryNoteWrite, body: String) {
        assertEquals(body, GoodsOutResponses.writeRequest(write).body.toString())
    }
}
