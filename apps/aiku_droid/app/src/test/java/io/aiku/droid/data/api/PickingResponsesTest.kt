package io.aiku.droid.data.api

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class PickingResponsesTest {

    private val noteJson = """
        {"id":9912,"reference":"AR24811","state":"handling","state_label":"Picking","warehouse_id":1,
        "shop":{"id":3,"name":"AW Dropship","type":"dropshipping"},"is_editable":true,"allow_actions":true,"can_handle":true,
        "available_actions":[{"key":"take_over","label":"Take over"},{"key":"change_trolley","label":"Change trolley"}],
        "settings":{"allow_scan_to_pick":true,"allow_waiting":false,"allow_picker_set_not_picked":true,"allow_picker_choose_location":false},
        "incomplete_sets":[{"code":"SET-1","name":"Gift set","quantity":1,"quantity_label":"1","locations":["A-01-03"]}],
        "lines":[{"id":41001,"quantity_required":3,"quantity_picked":1,"quantity_to_pick":1,
        "locations":[{"location_org_stock_id":5501,"location_id":220,"code":"A-01-03","stock":30,"stock_label":"30","type":"picking","quantity_picked":1,"picking_id":7001}],
        "pickings":[{"id":7001,"type":"pick","location_org_stock_id":5501,"location_code":"A-01-03","quantity":1,"quantity_label":"1","not_picked_reason":null,"not_picked_reason_label":null},
        {"id":7002,"type":"not-pick","location_org_stock_id":null,"location_code":null,"quantity":1,"quantity_label":"1","not_picked_reason":"out-of-stock","not_picked_reason_label":"Out of stock"}]}]}
    """.trimIndent()

    @Test
    fun `the note carries its actions, the handler rule, the settings, the incomplete sets and the pickings`() {
        val note = (GoodsOutResponses.parseDeliveryNote(200, """{"data":$noteJson}""") as GoodsOutResult.Loaded).value
        val line = note.lines.single()

        assertEquals(listOf("take_over", "change_trolley"), note.availableActions.map { it.key })
        assertEquals("Take over", note.action(NoteActionKey.TAKE_OVER)?.label)
        assertNull(note.action(NoteActionKey.START_PICKING))
        assertTrue(note.canHandle)
        assertTrue(note.isDropshipping)
        assertEquals(PickingSettings(allowScanToPick = true, allowWaiting = false, allowPickerSetNotPicked = true, allowPickerChooseLocation = false), note.settings)
        assertEquals(IncompleteSetPart(code = "SET-1", name = "Gift set", quantity = 1.0, quantityLabel = "1", locations = listOf("A-01-03")), note.incompleteSets.single())
        assertEquals(7001L, line.locations.single().pickingId)
        assertEquals(listOf(7001L, 7002L), line.pickings.map { it.id })
        assertFalse(line.pickings.first().isNotPicked)
        assertEquals("A-01-03", line.pickings.first().locationCode)
        assertTrue(line.pickings.last().isNotPicked)
        assertEquals("Out of stock", line.pickings.last().notPickedReasonLabel)
        assertEquals(line, note.line(41001))
    }

    @Test
    fun `a dropshipping note offers set as packed instead of set as picked`() {
        val body = """{"data":{"id":1,"state":"handling","shop":{"id":3,"type":"dropshipping"},"available_actions":[{"key":"set_as_packed","label":"Set as packed"}]}}"""

        val note = (GoodsOutResponses.parseDeliveryNote(200, body) as GoodsOutResult.Loaded).value

        assertEquals("Set as packed", note.action(NoteActionKey.SET_AS_PACKED)?.label)
        assertNull(note.action(NoteActionKey.SET_AS_PICKED))
        assertEquals(
            GoodsOutResult.Invalid("Print every insert before continuing", mapOf("state" to listOf("Print every insert before continuing"))),
            GoodsOutResponses.parseWrite(422, """{"message":"The given data was invalid.","errors":{"state":["Print every insert before continuing"]}}"""),
        )
    }

    @Test
    fun `a note from before 2c reads with no actions and every setting off`() {
        val note = (GoodsOutResponses.parseDeliveryNote(200, """{"data":{"id":1,"lines":[{"id":5}]}}""") as GoodsOutResult.Loaded).value

        assertEquals(emptyList<DeliveryNoteAction>(), note.availableActions)
        assertFalse(note.canHandle)
        assertEquals(PickingSettings(), note.settings)
        assertEquals(emptyList<IncompleteSetPart>(), note.incompleteSets)
        assertEquals(emptyList<LinePicking>(), note.lines.single().pickings)
    }

    @Test
    fun `every note action posts to its own path with explicit values`() {
        assertWrite(DeliveryNoteWrite.StartPicking(trolleyId = 4), "start-picking", """{"trolley_id":4}""")
        assertWrite(DeliveryNoteWrite.StartPicking(trolleyId = null), "start-picking", """{"trolley_id":null}""")
        assertWrite(DeliveryNoteWrite.TakeOver, "take-over", "{}")
        assertWrite(DeliveryNoteWrite.SetAsPicked(pickedBayId = 2), "set-as-picked", """{"picked_bay_id":2}""")
        assertWrite(DeliveryNoteWrite.SetAsPicked(pickedBayId = null), "set-as-picked", """{"picked_bay_id":null}""")
        assertWrite(DeliveryNoteWrite.SetAsPacked, "set-as-packed", "{}")
        assertWrite(DeliveryNoteWrite.PutBackIncompleteSets, "put-back-incomplete-sets", "{}")
        assertWrite(DeliveryNoteWrite.AutoFinishWaiting, "auto-finish-waiting", "{}")
        assertWrite(DeliveryNoteWrite.BackToPicking, "back-to-picking", "{}")
        assertWrite(DeliveryNoteWrite.UndoSetAsPicked, "undo-set-as-picked", "{}")
        assertWrite(DeliveryNoteWrite.ChangeTrolley(trolleyId = null), "trolley", """{"trolley_id":null}""")
        assertWrite(DeliveryNoteWrite.ChangePickedBay(pickedBayId = 3), "picked-bay", """{"picked_bay_id":3}""")
        assertWrite(DeliveryNoteWrite.SetPicker(userId = 77), "picker", """{"user_id":77}""")
        assertWrite(DeliveryNoteWrite.SetPicker(userId = null), "picker", """{"user_id":null}""")
    }

    @Test
    fun `every line action posts to the line, and undo deletes the picking`() {
        assertWrite(DeliveryNoteWrite.Pick(lineId = 41001, locationOrgStockId = 5501, quantity = 3.0), "lines/41001/pick", """{"location_org_stock_id":5501,"quantity":3}""")
        assertWrite(DeliveryNoteWrite.Pick(lineId = 41001, locationOrgStockId = 5501, quantity = 1.5), "lines/41001/pick", """{"location_org_stock_id":5501,"quantity":1.5}""")
        assertWrite(DeliveryNoteWrite.PickAll(lineId = 41001, locationOrgStockId = null), "lines/41001/pick-all", """{"location_org_stock_id":null}""")
        assertWrite(
            DeliveryNoteWrite.NotPicked(lineId = 41001, quantity = null, reason = NotPickedReason.CancelledByCustomer, note = "  "),
            "lines/41001/not-picked",
            """{"quantity":null,"reason":"cancelled-by-customer","note":null}""",
        )
        assertWrite(
            DeliveryNoteWrite.Waiting(lineId = 41001, kind = WaitingKind.Crm, quantity = 2.0, note = " Substitute? "),
            "lines/41001/waiting",
            """{"kind":"crm","quantity":2,"note":"Substitute?"}""",
        )

        val undo = GoodsOutResponses.writeRequest(DeliveryNoteWrite.UndoPicking(lineId = 41001, pickingId = 7001))

        assertEquals(WriteRequest(WriteMethod.Delete, "lines/41001/pickings/7001", body = null), undo)
    }

    @Test
    fun `a write answers with the re-read note and the result to show`() {
        val done = (GoodsOutResponses.parseWrite(200, """{"data":$noteJson,"result":{"status":"done","message":"Picking started"}}""") as GoodsOutResult.Loaded).value
        val unchanged = (GoodsOutResponses.parseWrite(200, """{"data":$noteJson,"result":{"status":"nothing_changed","message":"Nothing left to pick on this line."}}""") as GoodsOutResult.Loaded).value
        val silent = (GoodsOutResponses.parseWrite(200, """{"data":$noteJson,"result":{"status":"done","message":" "}}""") as GoodsOutResult.Loaded).value

        assertEquals(9912L, done.note.id)
        assertEquals("done", done.status)
        assertEquals("Picking started", done.message)
        assertEquals(NoteActionStatus.NOTHING_CHANGED, unchanged.status)
        assertEquals("Nothing left to pick on this line.", unchanged.message)
        assertNull(silent.message)
    }

    @Test
    fun `a refused write keeps the server's message`() {
        assertEquals(
            GoodsOutResult.Invalid("This delivery note is not being picked.", mapOf("state" to listOf("This delivery note is not being picked."))),
            GoodsOutResponses.parseWrite(422, """{"message":"The given data was invalid.","errors":{"state":["This delivery note is not being picked."]}}"""),
        )
        assertEquals(
            GoodsOutResult.Invalid("That trolley is in use.", mapOf("trolley_id" to listOf("That trolley is in use."))),
            GoodsOutResponses.parseWrite(422, """{"message":"That trolley is in use.","errors":{"trolley_id":["That trolley is in use."]}}"""),
        )
        assertEquals(GoodsOutResult.Forbidden("Waiting is not enabled for this organisation"), GoodsOutResponses.parseWrite(403, """{"message":"Waiting is not enabled for this organisation"}"""))
        assertEquals(GoodsOutResult.NotFound(""), GoodsOutResponses.parseWrite(404, ""))
        assertEquals(GoodsOutResult.SessionExpired, GoodsOutResponses.parseWrite(401, ""))
        assertEquals(GoodsOutResult.ServerError(200), GoodsOutResponses.parseWrite(200, """{"data":null}"""))
    }

    @Test
    fun `scan to pick sends one SKO per scan and reads the item block`() {
        assertEquals(
            """{"barcode":"AWO-12","quantity":1,"delivery_note_item_id":null,"location_org_stock_id":null}""",
            GoodsOutResponses.scanPickBody(ScanPickRequest(barcode = "AWO-12", quantity = 1.0)).toString(),
        )
        assertEquals(
            """{"barcode":"AWO-12","quantity":null,"delivery_note_item_id":41001,"location_org_stock_id":5501}""",
            GoodsOutResponses.scanPickBody(ScanPickRequest(barcode = "AWO-12", quantity = null, deliveryNoteItemId = 41001, locationOrgStockId = 5501)).toString(),
        )

        val body = """
            {"data":$noteJson,"result":{"status":"picked","message":"Picked 1 x AWO-12 from A-01-03",
            "item":{"id":41001,"code":"AWO-12","name":"Lavender oil","packed_in":12,"quantity_required":3,"quantity_picked":2,"quantity_to_pick":1,
            "quantity_to_pick_label":"1","quantity_to_pick_fractional":null,"location_code":"A-01-03","location_org_stock_id":5501}}}
        """.trimIndent()
        val outcome = (GoodsOutResponses.parseScanPick(200, body) as GoodsOutResult.Loaded).value
        val missed = (GoodsOutResponses.parseScanPick(200, """{"data":$noteJson,"result":{"status":"not_found","message":"999 does not belong to this delivery note","item":null}}""") as GoodsOutResult.Loaded).value

        assertEquals(ScanPickStatus.PICKED, outcome.status)
        assertEquals("Picked 1 x AWO-12 from A-01-03", outcome.message)
        assertEquals(41001L, outcome.item?.id)
        assertEquals("A-01-03", outcome.item?.locationCode)
        assertEquals(5501L, outcome.item?.locationOrgStockId)
        assertEquals(9912L, outcome.note.id)
        assertEquals(ScanPickStatus.NOT_FOUND, missed.status)
        assertNull(missed.item)
        assertEquals(GoodsOutResult.Forbidden(""), GoodsOutResponses.parseScanPick(403, ""))
    }

    @Test
    fun `the equipment lists read trolleys, picked bays and pickers`() {
        val trolleys = (GoodsOutResponses.parseTrolleys(200, """
            {"data":[{"id":3,"name":"T-03","current_delivery_note":null,"current_picking_session":null},
            {"id":1,"name":"T-01","current_delivery_note":{"id":9,"reference":"AR24790"},"current_picking_session":{"id":2,"reference":"PSaw-0142"}}]}
        """.trimIndent()) as GoodsOutResult.Loaded).value
        val bays = (GoodsOutResponses.parsePickedBays(200, """{"data":[{"id":2,"code":"Bay 2","number_delivery_notes":2},{"id":1,"code":"Bay 1"}]}""") as GoodsOutResult.Loaded).value
        val pickers = (GoodsOutResponses.parsePickers(200, """{"data":[{"user_id":77,"name":"Sam Picker","alias":"Sam"},{"user_id":78,"name":"Jo"}]}""") as GoodsOutResult.Loaded).value

        assertEquals(listOf("T-03", "T-01"), trolleys.map { it.name })
        assertNull(trolleys.first().currentDeliveryNote)
        assertEquals(NoteReference(id = 9, reference = "AR24790"), trolleys.last().currentDeliveryNote)
        assertEquals("PSaw-0142", trolleys.last().currentPickingSession?.reference)
        assertEquals(listOf(PickedBay(id = 2, code = "Bay 2", numberDeliveryNotes = 2), PickedBay(id = 1, code = "Bay 1", numberDeliveryNotes = 0)), bays)
        assertEquals(listOf(Picker(userId = 77, name = "Sam Picker", alias = "Sam"), Picker(userId = 78, name = "Jo", alias = null)), pickers)
        assertEquals(listOf("available" to "1"), GoodsOutResponses.trolleysParameters(availableOnly = true))
        assertEquals(emptyList<Pair<String, String>>(), GoodsOutResponses.trolleysParameters(availableOnly = false))
        assertEquals(GoodsOutResult.Forbidden("This action is unauthorized."), GoodsOutResponses.parsePickers(403, """{"message":"This action is unauthorized."}"""))
    }

    private fun assertWrite(write: DeliveryNoteWrite, path: String, body: String) {
        val request = GoodsOutResponses.writeRequest(write)

        assertEquals(WriteMethod.Post, request.method)
        assertEquals(path, request.path)
        assertEquals(body, request.body.toString())
    }
}
