package io.aiku.droid.data.api

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.LocalDate

class GoodsOutResponsesTest {

    private val rowJson = """
        {"id":9912,"slug":"dn-12345","reference":"AR24811","type":"order","state":"handling","state_label":"Picking",
        "date":"2026-11-11T09:12:00+00:00","last_modified_at":"2026-11-12T08:01:00+00:00",
        "customer_name":"Jane Gifts Ltd","is_customer_vip":true,
        "shop":{"id":3,"name":"AW Wholesale","type":"b2b"},"channel":"b2b",
        "number_items":4,"estimated_weight_kg":6.25,"parcels":0,
        "is_premium_dispatch":false,"is_priority":true,"is_collection":false,"is_export":false,"has_extra_packing":false,
        "picker":{"id":77,"name":"Jane"},"packer":null,
        "trolleys":[{"id":4,"name":"T-04"}],"picked_bays":[{"id":2,"name":"Bay 2"}],
        "waiting_warehouse_count":1,"waiting_crm_count":2,"number_of_days_in_warehouse":1}
    """.trimIndent()

    private val noteJson = """
        {"id":9912,"slug":"dn-12345","reference":"AR24811","type":"order","state":"handling","state_label":"Picking",
        "warehouse_id":1,"shop":{"id":3,"name":"AW Wholesale","type":"b2b"},"channel":"b2b",
        "platform":{"name":"Shopify"},"order":{"id":5531,"reference":"AR24811"},
        "customer":{"name":"Jane Gifts Ltd","reference":"C1234","contact_name":"Jane Doe","company_name":"Jane Gifts Ltd","email":"jane@example.com","phone":"+44 114 000","is_vip":false},
        "delivery_address":{"lines":["12 High St","Sheffield","S1 2AB","United Kingdom"],"country_code":"GB"},
        "is_collection":false,"is_shipping_by_external":false,"is_premium_dispatch":true,"is_cash_on_delivery":false,
        "has_extra_packing":true,"has_gift_message":false,"is_export":false,
        "summary":{"number_items":4,"number_skos":6.5,"number_units":14,"estimated_weight_kg":6.2,"estimated_picking_minutes":12,"estimated_packing_minutes":6.4},
        "picker":{"id":77,"name":"Jane"},"packer":null,"trolleys":[{"id":4,"name":"T-04"}],"picked_bays":["Bay 1"],
        "parcels":[{"weight_kg":2.5,"dimensions_cm":[30,20,15]}],
        "notes":{"shipping":{"text":"Leave with neighbour","editable":true},"customer":{"text":"Please wrap","editable":false},"warehouse":{"text":null,"editable":true}},
        "timeline":[{"state":"unassigned","label":"To do","at":"2026-11-11T09:12:00+00:00"},{"state":"handling","label":"Picking","at":"2026-11-12T08:01:00+00:00"},{"state":"picked","label":"Picked","at":null}],
        "shipments":[{"id":31,"shipper":"DPD (GB)","tracking":"1234567890","is_printable":true}],
        "waiting_warehouse_count":0,"waiting_crm_count":0,"is_editable":true,"allow_actions":false,
        "read_only_notice":null,"warning":"This delivery note is in picking session PSaw-0142",
        "line_counts":{"all":4,"todo":2,"done":2},
        "lines":[{"id":41001,"state":"handling","state_label":"Picking",
        "org_stock":{"id":812,"code":"AWO-12","name":"Lavender oil 10ml","barcode":"5060","packed_in":12,"image_url":"https://media.aiku.io/64x64.jpg"},
        "quantity_required":1.4166,"quantity_required_label":"1 5/12","quantity_picked":0.5,"quantity_picked_label":"1/2",
        "quantity_to_pick":0.9166,"quantity_to_pick_label":"11/12","quantity_not_picked":0,"quantity_packed":0,"quantity_packed_label":"0",
        "quantity_to_pack":0,"quantity_waiting_warehouse":0,"quantity_waiting_crm":0,"is_handled":false,"is_picked":false,"is_packed":false,
        "locations":[{"location_org_stock_id":5501,"location_id":220,"code":"A-01-03","stock":30.5,"stock_label":"30 1/2","type":"picking","quantity_picked":0.5}],
        "warehouse_area":"Sort: 12 · Area: A","note_to_pickers":"Fragile","note_to_packers":null,"un_numbers":"UN1170",
        "replacement_reason":null,"indivisible_set":false}]}
    """.trimIndent()

    @Test
    fun `the list carries its rows, the work right and the pagination`() {
        val body = """{"data":{"delivery_notes":[$rowJson],"can_work_in_warehouse":true},"pagination":{"current_page":1,"last_page":3,"per_page":50,"total":128}}"""

        val page = (GoodsOutResponses.parseDeliveryNotes(200, body) as GoodsOutResult.Loaded).value
        val row = page.notes.single()

        assertTrue(page.canWorkInWarehouse)
        assertEquals(GoodsOutPagination(currentPage = 1, lastPage = 3, perPage = 50, total = 128), page.pagination)
        assertEquals(9912L, row.id)
        assertEquals("AR24811", row.reference)
        assertEquals("handling", row.state)
        assertEquals("Picking", row.stateLabel)
        assertEquals("Jane Gifts Ltd", row.customerName)
        assertTrue(row.isCustomerVip)
        assertEquals("b2b", row.channel)
        assertEquals(4, row.numberItems)
        assertEquals(6.25, row.estimatedWeightKg ?: 0.0, 0.0)
        assertTrue(row.isPriority)
        assertEquals(StaffRef(id = 77, name = "Jane"), row.picker)
        assertNull(row.packer)
        assertEquals(listOf(TrolleyRef(id = 4, name = "T-04")), row.trolleys)
        assertEquals(3, row.waitingCount)
    }

    @Test
    fun `a list without pagination counts its own rows`() {
        val body = """{"data":{"delivery_notes":[$rowJson]}}"""

        val page = (GoodsOutResponses.parseDeliveryNotes(200, body) as GoodsOutResult.Loaded).value

        assertFalse(page.canWorkInWarehouse)
        assertEquals(1, page.pagination.total)
        assertEquals(1, page.pagination.lastPage)
    }

    @Test
    fun `the list query sends only the filters that are set`() {
        val query = DeliveryNoteQuery(
            bucket = DeliveryNoteBucket.HandlingBlocked,
            channel = DispatchChannel.External,
            search = "  AR248  ",
            from = LocalDate.of(2026, 11, 6),
            to = LocalDate.of(2026, 11, 12),
            picker = PickerFilter.Nobody,
            sort = DeliveryNoteSort.Heaviest,
            page = 2,
            perPage = 25,
        )

        assertEquals(
            listOf(
                "bucket" to "handling_blocked",
                "channel" to "external",
                "search" to "AR248",
                "from" to "2026-11-06",
                "to" to "2026-11-12",
                "picker" to "none",
                "sort" to "-effective_weight",
                "page" to "2",
                "per_page" to "25",
            ),
            GoodsOutResponses.deliveryNotesParameters(query),
        )
        assertEquals(
            listOf("bucket" to "unassigned", "channel" to "all", "page" to "1", "per_page" to "50"),
            GoodsOutResponses.deliveryNotesParameters(DeliveryNoteQuery(search = " ")),
        )
        assertEquals(listOf("picker" to "me"), GoodsOutResponses.countsParameters(PickerFilter.Me))
        assertEquals(emptyList<Pair<String, String>>(), GoodsOutResponses.countsParameters(null))
    }

    @Test
    fun `the counts carry every bucket and one entry per channel`() {
        val body = """
            {"data":{"buckets":{"unassigned":12,"queued":0,"handling":4,"handling_blocked":1,"picked":2,"packing":1,"packed":3,"finalised":0,"dispatched":5821,"in_warehouse":23},
            "channels":[{"channel":"b2b","label":"Wholesale","buckets":{"unassigned":7,"handling":2,"in_warehouse":9}},
            {"channel":"external","label":"Marketplaces","buckets":{"unassigned":5}}]}}
        """.trimIndent()

        val counts = (GoodsOutResponses.parseCounts(200, body) as GoodsOutResult.Loaded).value

        assertEquals(12, counts.count(DeliveryNoteBucket.Unassigned))
        assertEquals(5821, counts.count(DeliveryNoteBucket.Dispatched))
        assertEquals(0, counts.count(DeliveryNoteBucket.All))
        assertEquals(listOf("b2b", "external"), counts.channels.map { it.channel })
        assertEquals("Wholesale", counts.channels.first().label)
        assertEquals(2, counts.channels.first().count(DeliveryNoteBucket.Handling))
        assertEquals(0, counts.channels.last().count(DeliveryNoteBucket.Packed))
    }

    @Test
    fun `the delivery note page keeps decimal quantities with their labels`() {
        val note = (GoodsOutResponses.parseDeliveryNote(200, """{"data":$noteJson}""") as GoodsOutResult.Loaded).value
        val line = note.lines.single()

        assertEquals("AR24811", note.reference)
        assertEquals("Shopify", note.platform?.name)
        assertEquals("C1234", note.customer?.reference)
        assertEquals(listOf("12 High St", "Sheffield", "S1 2AB", "United Kingdom"), note.deliveryAddress?.lines)
        assertTrue(note.isPremiumDispatch)
        assertTrue(note.hasExtraPacking)
        assertEquals(6.5, note.summary.numberSkos, 0.0)
        assertEquals(6.4, note.summary.estimatedPackingMinutes ?: 0.0, 0.0)
        assertEquals(listOf("Bay 1"), note.pickedBayLabels)
        assertEquals(listOf(30.0, 20.0, 15.0), note.parcels.single().dimensionsCm)
        assertTrue(note.notes.shipping.editable)
        assertFalse(note.notes.customer.editable)
        assertNull(note.notes.warehouse.text)
        assertEquals(listOf("To do", "Picking", "Picked"), note.timeline.map { it.label })
        assertNull(note.timeline.last().at)
        assertEquals("DPD (GB)", note.shipments.single().shipper)
        assertTrue(note.isEditable)
        assertFalse(note.allowActions)
        assertFalse(note.canAct)
        assertNull(note.readOnlyNotice)
        assertEquals("This delivery note is in picking session PSaw-0142", note.warning)
        assertEquals(DeliveryNoteLineCounts(all = 4, todo = 2, done = 2), note.lineCounts)

        assertEquals("AWO-12", line.orgStock?.code)
        assertEquals("https://media.aiku.io/64x64.jpg", line.orgStock?.imageUrl)
        assertEquals(1.4166, line.quantityRequired, 0.0)
        assertEquals("1 5/12", line.quantityRequiredLabel)
        assertEquals(0.5, line.quantityPicked, 0.0)
        assertEquals("1/2", line.quantityPickedLabel)
        assertEquals("11/12", line.quantityToPickLabel)
        assertEquals("30 1/2", line.locations.single().stockLabel)
        assertEquals("Fragile", line.noteToPickers)
        assertEquals("UN1170", line.unNumbersText)
    }

    @Test
    fun `picked bays and UN numbers are read whatever shape they come in`() {
        val body = """{"data":{"id":1,"picked_bays":[{"id":2,"code":"B-2"},3,null],"lines":[{"id":5,"un_numbers":["UN1170","UN1993"]}]}}"""

        val note = (GoodsOutResponses.parseDeliveryNote(200, body) as GoodsOutResult.Loaded).value

        assertEquals(listOf("B-2", "3"), note.pickedBayLabels)
        assertEquals("UN1170, UN1993", note.lines.single().unNumbersText)
    }

    @Test
    fun `a scan answers with what the code is`() {
        val sko = (GoodsOutResponses.parseScan(200, """
            {"data":{"code":"5060123","type":"sko","sko":{"id":812,"code":"AWO-12","name":"Lavender oil","state":"active","barcode":"5060123",
            "unit_barcode":null,"packed_in":12,"quantity_in_locations":40.5,"image_url":null,"locations":[{"code":"A-01-03","quantity":30.5}],"matched_on":"barcode"},
            "location":null,"delivery_note":null}}
        """.trimIndent()) as GoodsOutResult.Loaded).value
        val location = (GoodsOutResponses.parseScan(200, """
            {"data":{"code":"a-01-03","type":"location","sko":null,"location":{"id":220,"code":"A-01-03","slug":"a-01-03","warehouse_area":{"id":3,"name":"Area A"},"number_org_stocks":6},"delivery_note":null}}
        """.trimIndent()) as GoodsOutResult.Loaded).value
        val note = (GoodsOutResponses.parseScan(200, """{"data":{"code":"AR24811","type":"delivery_note","sko":null,"location":null,"delivery_note":$rowJson}}""") as GoodsOutResult.Loaded).value
        val unknown = (GoodsOutResponses.parseScan(200, """{"data":{"code":"999","type":"unknown","sko":null,"location":null,"delivery_note":null}}""") as GoodsOutResult.Loaded).value

        assertEquals(ScanMatch.SKO, sko.type)
        assertEquals("AWO-12", sko.sko?.code)
        assertEquals("barcode", sko.sko?.matchedOn)
        assertEquals(30.5, sko.sko?.locations?.single()?.quantity ?: 0.0, 0.0)
        assertEquals(ScanMatch.LOCATION, location.type)
        assertEquals("A-01-03", location.location?.code)
        assertEquals(ScanMatch.DELIVERY_NOTE, note.type)
        assertEquals(9912L, note.deliveryNote?.id)
        assertEquals(ScanMatch.UNKNOWN, unknown.type)
        assertNull(unknown.sko)
        assertNull(unknown.location)
        assertNull(unknown.deliveryNote)
    }

    @Test
    fun `refusals map to session expired, no right, not found and invalid`() {
        val refusal = """{"message":"This action is unauthorized."}"""

        assertEquals(GoodsOutResult.SessionExpired, GoodsOutResponses.parseDeliveryNotes(401, """{"message":"Unauthenticated."}"""))
        assertEquals(GoodsOutResult.Forbidden("This action is unauthorized."), GoodsOutResponses.parseDeliveryNotes(403, refusal))
        assertEquals(GoodsOutResult.Forbidden("This action is unauthorized."), GoodsOutResponses.parseCounts(403, refusal))
        assertEquals(GoodsOutResult.NotFound(""), GoodsOutResponses.parseDeliveryNote(404, "Not Found"))
        assertEquals(GoodsOutResult.SessionExpired, GoodsOutResponses.parseScan(401, ""))
        assertEquals(GoodsOutResult.Forbidden(""), GoodsOutResponses.parseScan(403, ""))
        assertEquals(GoodsOutResult.NotFound("No query results."), GoodsOutResponses.parseScan(404, """{"message":"No query results."}"""))
        assertEquals(
            GoodsOutResult.Invalid("The code field is required.", mapOf("code" to listOf("The code field is required."))),
            GoodsOutResponses.parseScan(422, """{"message":"The code field is required.","errors":{"code":["The code field is required."]}}"""),
        )
        assertEquals(GoodsOutResult.ServerError(500), GoodsOutResponses.parseDeliveryNote(500, "<html>"))
        assertEquals(GoodsOutResult.ServerError(200), GoodsOutResponses.parseDeliveryNote(200, "<html>"))
    }
}
