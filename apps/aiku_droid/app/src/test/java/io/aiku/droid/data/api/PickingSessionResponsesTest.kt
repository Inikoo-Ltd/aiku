package io.aiku.droid.data.api

import kotlinx.serialization.json.JsonObject
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class PickingSessionResponsesTest {

    private val rowJson = """
        {"id":12,"reference":"PS-0012","state":"handling","state_label":"Picking","picker":{"id":5,"name":"Ana"},
        "number_delivery_notes":8,"number_items":31,"number_trolleys":2,"picking_percentage":40.0,"packing_percentage":0.0,
        "is_waiting_ready":false,"start_at":"2026-11-13T09:00:00Z","end_at":null}
    """.trimIndent()

    private val sessionJson = """
        {"id":12,"reference":"PS-0012","state":"handling","state_label":"Picking","picker":{"id":5,"name":"Ana"},
        "number_delivery_notes":2,"number_items":6,"number_trolleys":1,"picking_percentage":33.3,"packing_percentage":0,
        "can_work":true,"trolleys":[{"id":3,"name":"T03"}],"can_change_trolleys":true,
        "settings":{"allow_scan_to_pick":true,"allow_scan_to_pack":false,"allow_waiting":true,"allow_picker_set_not_picked":true},
        "available_actions":[{"key":"scan_pick","label":"Scan to pick"}],
        "delivery_notes":[{"id":9,"reference":"DN-9","state":"handling","state_label":"Picking","number_items":4,"picking_percentage":50.0,
        "packing_percentage":0.0,"has_waiting_items":true,"is_for_collection":false}],
        "pick_list":[{"key":"5501-77","location":{"id":5501,"code":"A-01-03"},
        "org_stock":{"id":77,"code":"AWO-12","name":"Lavender oil","barcode":"501","image_url":"https://app.aiku.test/i/77.jpg"},
        "quantity_required":6,"quantity_picked":2,"quantity_to_pick":4,"quantity_to_pick_label":"4",
        "lines":[{"delivery_note_item_id":801,"delivery_note_id":9,"delivery_note_reference":"DN-9","quantity_required":2,"quantity_picked":2,"quantity_to_pick":0},
        {"delivery_note_item_id":802,"delivery_note_id":10,"delivery_note_reference":"DN-10","quantity_required":4,"quantity_picked":0,"quantity_to_pick":4}]}]}
    """.trimIndent()

    @Test
    fun `the list reads rows, counts and pagination from either data shape`() {
        val flat = PickingSessionResponses.parseList(
            200,
            """{"data":[$rowJson],"counts":{"in_process":3,"handling":2,"broken":"x"},"pagination":{"current_page":1,"last_page":2,"per_page":25,"total":30}}""",
        ) as GoodsOutResult.Loaded
        val nested = PickingSessionResponses.parseList(200, """{"data":{"picking_sessions":[$rowJson],"counts":{"handling":2}}}""") as GoodsOutResult.Loaded
        val row = flat.value.sessions.single()

        assertEquals("PS-0012", row.reference)
        assertEquals(PickingSessionState.Handling, row.sessionState)
        assertEquals(StaffRef(id = 5, name = "Ana"), row.picker)
        assertEquals(40.0, row.pickingPercentage, 0.0)
        assertEquals(31, row.numberItems)
        assertEquals(mapOf("in_process" to 3, "handling" to 2), flat.value.counts)
        assertEquals(GoodsOutPagination(currentPage = 1, lastPage = 2, perPage = 25, total = 30), flat.value.pagination)
        assertEquals(listOf(row), nested.value.sessions)
        assertEquals(mapOf("handling" to 2), nested.value.counts)
        assertEquals(1, nested.value.pagination.total)
    }

    @Test
    fun `the list query names the state only when one is chosen`() {
        assertEquals(listOf("page" to "2"), PickingSessionResponses.listParameters(state = null, page = 2))
        assertEquals(listOf("state" to "handling_blocked", "page" to "1"), PickingSessionResponses.listParameters(PickingSessionState.HandlingBlocked, page = 1))
    }

    @Test
    fun `the session carries trolleys, settings, actions, notes and the pick list`() {
        val session = (PickingSessionResponses.parseSession(200, """{"data":$sessionJson}""") as GoodsOutResult.Loaded).value
        val entry = session.pickList.single()

        assertTrue(session.canWork)
        assertTrue(session.canChangeTrolleys)
        assertEquals(listOf(TrolleyRef(id = 3, name = "T03")), session.trolleys)
        assertTrue(session.settings.allowScanToPick)
        assertFalse(session.settings.allowScanToPack)
        assertEquals("Scan to pick", session.action(SessionActionKey.SCAN_PICK)?.label)
        assertNull(session.action(SessionActionKey.START_PICKING))
        assertTrue(session.deliveryNotes.single().hasWaitingItems)
        assertEquals(PickListLocation(id = 5501, code = "A-01-03"), entry.location)
        assertEquals("https://app.aiku.test/i/77.jpg", entry.orgStock?.imageUrl)
        assertEquals("4", entry.quantityToPickLabel)
        assertFalse(entry.isDone)
        assertEquals(listOf(801L, 802L), entry.lines.map { it.deliveryNoteItemId })
        assertEquals("DN-10", session.pickLine(802)?.deliveryNoteReference)
    }

    @Test
    fun `a created session answers 201 with the session`() {
        val created = PickingSessionResponses.parseSession(201, """{"data":$sessionJson}""")
        val refused = PickingSessionResponses.parseSession(422, """{"message":"x","errors":{"delivery_note_ids":["DN-9 is already in a picking session."]}}""")

        assertEquals(12L, (created as GoodsOutResult.Loaded).value.id)
        assertEquals(
            GoodsOutResult.Invalid("DN-9 is already in a picking session.", mapOf("delivery_note_ids" to listOf("DN-9 is already in a picking session."))),
            refused,
        )
        assertEquals("""{"delivery_note_ids":[9,10]}""", PickingSessionResponses.createBody(listOf(9, 10)).toString())
    }

    @Test
    fun `session writes go to their paths with their bodies`() {
        assertEquals(WriteRequest(WriteMethod.Post, "start-picking", JsonObject(emptyMap())), PickingSessionResponses.writeRequest(PickingSessionWrite.StartPicking))
        assertEquals("back-to-picking", PickingSessionResponses.writeRequest(PickingSessionWrite.BackToPicking).path)
        PickingSessionResponses.writeRequest(PickingSessionWrite.AttachTrolley(3)).let {
            assertEquals(WriteMethod.Post, it.method)
            assertEquals("trolleys", it.path)
            assertEquals("""{"trolley_id":3}""", it.body.toString())
        }
        assertEquals(WriteRequest(WriteMethod.Delete, "trolleys/3", null), PickingSessionResponses.writeRequest(PickingSessionWrite.DetachTrolley(3)))
        PickingSessionResponses.writeRequest(PickingSessionWrite.AddDeliveryNotes(listOf(9, 10))).let {
            assertEquals("delivery-notes", it.path)
            assertEquals("""{"delivery_note_ids":[9,10]}""", it.body.toString())
        }
        PickingSessionResponses.writeRequest(PickingSessionWrite.RemoveDeliveryNotes(listOf(9))).let {
            assertEquals("delivery-notes/remove", it.path)
            assertEquals("""{"delivery_note_ids":[9]}""", it.body.toString())
        }
    }

    @Test
    fun `a write answers with the session and its result, and refusals keep their message`() {
        val outcome = (PickingSessionResponses.parseWrite(200, """{"data":$sessionJson,"result":{"status":"done","message":"Picking started"}}""") as GoodsOutResult.Loaded).value
        val refused = PickingSessionResponses.parseWrite(422, """{"message":"Wrong state","errors":{"state":["This session is not in process."]}}""")

        assertEquals("PS-0012", outcome.session.reference)
        assertEquals("Picking started", outcome.message)
        assertEquals(GoodsOutResult.Invalid("This session is not in process.", mapOf("state" to listOf("This session is not in process."))), refused)
    }

    @Test
    fun `scan to pick and pack in a session carry the item and the note it went to`() {
        val pick = (
            PickingSessionResponses.parseScanPick(
                200,
                """{"data":$sessionJson,"result":{"status":"picked","message":"Picked 1 for DN-10","item":{"id":802,"code":"AWO-12","name":"Lavender oil",
                |"quantity_required":4,"quantity_picked":1,"quantity_to_pick":3,"quantity_to_pick_label":"3","location_code":"A-01-03","location_org_stock_id":4401},
                |"delivery_note":{"id":10,"reference":"DN-10","state":"handling"}}}""".trimMargin(),
            ) as GoodsOutResult.Loaded
            ).value
        val pack = (
            PickingSessionResponses.parseScanPack(
                200,
                """{"data":$sessionJson,"result":{"status":"not_scannable","message":"Dropshipping items can't be scanned","item":null,"delivery_note":null}}""",
            ) as GoodsOutResult.Loaded
            ).value

        assertEquals(ScanPickStatus.PICKED, pick.status)
        assertEquals(4401L, pick.item?.locationOrgStockId)
        assertEquals(3.0, pick.item?.quantityToPick ?: 0.0, 0.0)
        assertEquals(DeliveryNoteRef(id = 10, reference = "DN-10", state = "handling"), pick.deliveryNote)
        assertEquals(ScanStatus.NOT_SCANNABLE, pack.status)
        assertNull(pack.item)
        assertEquals(
            """{"barcode":"501","quantity":null,"delivery_note_item_id":802,"location_org_stock_id":4401}""",
            PickingSessionResponses.scanPickBody(ScanPickRequest(barcode = "501", quantity = null, deliveryNoteItemId = 802, locationOrgStockId = 4401)).toString(),
        )
    }

    @Test
    fun `pickers and packers now carry their notes, trolleys and sessions`() {
        val people = (
            PickingSessionResponses.parsePersonnel(
                200,
                """{"data":[{"user":{"id":5,"name":"Ana"},"delivery_notes":[{"id":9,"reference":"DN-9"}],"trolleys":[{"id":3,"name":"T03"}],
                |"picking_sessions":[{"id":12,"reference":"PS-0012"}]}]}""".trimMargin(),
            ) as GoodsOutResult.Loaded
            ).value

        assertEquals(
            listOf(
                DispatchPerson(
                    user = StaffRef(id = 5, name = "Ana"),
                    deliveryNotes = listOf(NoteReference(id = 9, reference = "DN-9")),
                    trolleys = listOf(TrolleyRef(id = 3, name = "T03")),
                    pickingSessions = listOf(NoteReference(id = 12, reference = "PS-0012")),
                ),
            ),
            people,
        )
        assertEquals(listOf("role" to "packers"), PickingSessionResponses.personnelParameters(DispatchRole.Packers))
    }
}
