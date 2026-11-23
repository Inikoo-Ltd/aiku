package io.aiku.droid.data.api

import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonPrimitive
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.LocalDate

class GoodsInResponsesTest {

    private val deliveryJson = """
        {"id":1,"reference":"SD-1","agent_reference":null,"state":"checked","state_label":"Checked",
         "supplier":{"type":"OrgSupplier","code":"ABC","name":"Acme"},"source":"suppliers",
         "dispatched_at":"2026-11-10T08:00:00+00:00","received_at":"2026-11-12T09:30:00+00:00","estimated_receiving_date":"2026-11-12",
         "delivery":{"type":"container","type_label":"Container","container_size":40,"incoterm":"FOB","port_of_export":null,"port_of_import":"Felixstowe",
            "shipper_reference":null,"tracking_number":"TRK-1","container_number":null,"shipping_agent":{"name":"Maersk"}},
         "purchase_orders":[{"id":5,"reference":"PO-1"},{"id":6,"reference":"PO-2"}],
         "summary":{"number_items":"3","number_checked":2,"number_placed":1,"number_not_received":1,"number_new_org_stocks":0,"under_delivered":1,"over_delivered":"0"},
         "items":[
           {"id":10,"state":"checked","state_label":"Checked","supplier_product":{"code":"SP-1","name":"Lavender"},"purchase_order_reference":"PO-1","is_new_org_stock":false,
            "sko":{"id":77,"code":"AWO-12","name":"Lavender oil","packed_in":"6","barcode":"5060","image_url":null},
            "sko_expected":"2.5","sko_expected_label":"2½","sko_checked":2.25,"sko_checked_label":"2¼","sko_placed":1,"sko_placed_label":"1","sko_to_place":"1.25","sko_to_place_label":"1¼",
            "has_picking_location":true,
            "locations":[{"location_org_stock_id":5501,"location":{"id":220,"code":"A-01-03"},"type":"picking","quantity":"4.5","quantity_label":"4½","is_default_wholesale":true,"is_default_dropshipping":false}],
            "placements":[{"id":900,"quantity":"1","quantity_label":"1","location":{"id":220,"code":"A-01-03"}}]},
           {"id":11,"state":"received","state_label":"Received","sko":{"id":78,"code":"AWO-13","name":"Rose"},"sko_expected":10,"sko_checked":null,"sko_placed":0,"sko_to_place":0,
            "has_picking_location":false,"locations":[],"placements":[]}
         ],
         "checklist":{"sections":[{"key":"supplier:3","title":"Acme","subtitle":"Packing","checks":[
             {"key":"supplier:3:labels","label":"Labels","detail":"Every carton","severity":"high","result":"failed","checked_by":{"id":4,"name":"Ana"},"checked_at":"2026-11-12T10:00:00+00:00"},
             {"key":"supplier:3:wrap","label":"Wrapped","result":null,"checked_by":null,"checked_at":null},
             {"key":"supplier:3:seal","label":"Sealed","result":"passed","checked_by":{"id":null,"name":"Old check"},"checked_at":"2026-11-10T09:00:00+00:00"}]}],
           "total":2,"checked":"1","failed":1,"incident_types":[{"value":"damaged","label":"Damaged"}]},
         "available_actions":[{"key":"check","label":"Check"},{"key":"place","label":"Place"},{"key":"checklist","label":"Checklist"}]}
    """.trimIndent()

    @Test
    fun `the hub reads rows in order and keeps the figures the web has none of as null`() {
        val body = """{"data":[
            {"key":"stock_deliveries","label":"Stock deliveries","arriving":"3","received":2,"checked":1,"booking_in":1,"booked_in_7d":9,"placed_7d":"4"},
            {"key":"returns","label":"Returns","arriving":null,"received":3,"checked":null,"booking_in":2,"booked_in_7d":null,"placed_7d":null}]}"""

        val rows = (GoodsInResponses.parseHub(200, body) as GoodsOutResult.Loaded).value

        assertEquals(listOf(GoodsInHubKey.STOCK_DELIVERIES, GoodsInHubKey.RETURNS), rows.map { it.key })
        assertEquals(3, rows[0].arriving)
        assertEquals(4, rows[0].placedWeek)
        assertNull(rows[1].arriving)
        assertNull(rows[1].checked)
        assertEquals(2, rows[1].bookingIn)
        assertNull(rows[1].bookedInWeek)
    }

    @Test
    fun `the hub also reads rows wrapped in an object`() {
        val rows = (GoodsInResponses.parseHub(200, """{"data":{"rows":[{"key":"agents","label":"Agents","arriving":1}]}}""") as GoodsOutResult.Loaded).value

        assertEquals("Agents", rows.single().label)
        assertNull(rows.single().received)
    }

    @Test
    fun `the stock delivery list reads rows, meta and counts per state`() {
        val body = """{"data":[{"id":1,"reference":"SD-1","agent_reference":"AG-9","supplier":{"type":"OrgAgent","code":"AG","name":"Agent Co"},"source":"agents",
            "state":"dispatched","state_label":"Dispatched","dispatched_at":"2026-11-07T08:00:00+00:00","received_at":null,"estimated_receiving_date":"2026-11-14",
            "number_items":"22","number_checked":0,"number_placed":0}],
            "meta":{"current_page":1,"last_page":3,"per_page":50,"total":"128"},
            "counts":{"dispatched":3,"received":"2","checked":1,"booking_in":1,"booked_in":0,"placed":40,"not_received":null,"active":7}}"""

        val page = (GoodsInResponses.parseStockDeliveries(200, body) as GoodsOutResult.Loaded).value

        assertEquals("AG-9", page.rows.single().agentReference)
        assertEquals(22, page.rows.single().numberItems)
        assertEquals("Agent Co", page.rows.single().supplier?.label)
        assertEquals(3, page.meta.lastPage)
        assertEquals(128, page.meta.total)
        assertEquals(2, page.counts?.count(StockDeliveryStateFilter.Received.key))
        assertEquals(7, page.counts?.count(StockDeliveryStateFilter.Active.key))
        assertNull(page.counts?.count(StockDeliveryStateFilter.NotReceived.key))
    }

    @Test
    fun `the detail reads decimal SKO quantities, this warehouse's locations, placements and the checklist`() {
        val delivery = (GoodsInResponses.parseStockDelivery(200, """{"data":$deliveryJson}""") as GoodsOutResult.Loaded).value
        val item = delivery.items.first()

        assertEquals(2.5, item.skoExpected, 0.0)
        assertEquals("2½", item.skoExpectedLabel)
        assertEquals(2.25, item.skoChecked!!, 0.0)
        assertEquals(1.25, item.skoToPlace, 0.0)
        assertEquals(6.0, item.sko?.packedIn!!, 0.0)
        assertEquals(4.5, item.locations.single().quantity, 0.0)
        assertEquals(LocationStockType.Picking, item.locations.single().stockType)
        assertTrue(item.locations.single().isDefaultWholesale)
        assertEquals("A-01-03", item.placements.single().location?.code)
        assertNull(delivery.items[1].skoChecked)
        assertTrue(delivery.items[1].needsCheck)
        assertEquals(3, delivery.summary.numberItems)
        assertEquals(listOf("PO-1", "PO-2"), delivery.purchaseOrders.map { it.reference })
        assertEquals("Container", delivery.delivery?.typeText)
        assertEquals("40", delivery.delivery?.containerSizeText)
        assertEquals("Maersk", delivery.delivery?.shippingAgentText)
        assertNull(delivery.delivery?.portOfExportText)
        assertEquals(1, delivery.checklist?.checked)
        assertEquals(CheckResult.Failed, CheckResult.ofKey(delivery.checklist?.check("supplier:3:labels")?.result))
        assertEquals("Ana", delivery.checklist?.check("supplier:3:labels")?.checkedBy?.name)
        assertEquals("Old check", delivery.checklist?.check("supplier:3:seal")?.checkedBy?.name)
        assertEquals(null, delivery.checklist?.check("supplier:3:seal")?.checkedBy?.id)
        assertEquals("Damaged", delivery.checklist?.incidentTypes?.single()?.label)
        assertTrue(delivery.can(GoodsInActionKey.PLACE))
        assertFalse(delivery.can(GoodsInActionKey.RECEIVE))
    }

    @Test
    fun `a detail without a checklist or actions still loads`() {
        val delivery = (GoodsInResponses.parseStockDelivery(200, """{"data":{"id":2,"reference":"SD-2","state":"placed","checklist":null}}""") as GoodsOutResult.Loaded).value

        assertNull(delivery.checklist)
        assertTrue(delivery.availableActions.isEmpty())
        assertTrue(delivery.items.isEmpty())
    }

    @Test
    fun `a write answers the re-read detail with its result`() {
        val body = """{"data":$deliveryJson,"result":{"status":"nothing_changed","message":"Already placed."}}"""

        val outcome = (GoodsInResponses.parseStockDeliveryWrite(200, body) as GoodsOutResult.Loaded).value

        assertEquals("SD-1", outcome.delivery.reference)
        assertEquals(GoodsInWriteStatus.NOTHING_CHANGED, outcome.status)
        assertEquals("Already placed.", outcome.message)
    }

    @Test
    fun `a refused write keeps the field errors`() {
        val body = """{"message":"The given data was invalid.","errors":{"quantity":["You can not place more than the checked quantity (1.25 remaining)"]}}"""

        val refusal = GoodsInResponses.parseStockDeliveryWrite(422, body) as GoodsOutResult.Invalid

        assertEquals("You can not place more than the checked quantity (1.25 remaining)", refusal.message)
        assertEquals(listOf(GoodsInErrorKey.QUANTITY), refusal.errors.keys.toList())
        assertTrue(GoodsInResponses.parseStockDelivery(404, """{"message":"Not found"}""") is GoodsOutResult.NotFound)
        assertTrue(GoodsInResponses.parseStockDelivery(403, """{"message":"No"}""") is GoodsOutResult.Forbidden)
    }

    @Test
    fun `list parameters carry the state, source, search, dispatched bucket, sort and paging`() {
        val query = StockDeliveryQuery(
            state = StockDeliveryStateFilter.BookingIn,
            source = StockDeliverySource.Partners,
            search = "  sd-9 ",
            dispatched = DispatchedBucket.Week,
            sort = StockDeliverySort.Reference,
            page = 2,
            perPage = 20,
        )

        assertEquals(
            listOf("state" to "booking_in", "source" to "partners", "q" to "sd-9", "dispatched" to "week", "sort" to "reference", "page" to "2", "per_page" to "20"),
            GoodsInResponses.stockDeliveriesParameters(query),
        )
        assertEquals(listOf("state" to "active", "page" to "1", "per_page" to "50"), GoodsInResponses.stockDeliveriesParameters(StockDeliveryQuery()))
    }

    @Test
    fun `writes go to their paths with decimal quantities and exactly one location field`() {
        val check = GoodsInResponses.stockDeliveryWriteRequest(StockDeliveryWrite.Check(itemId = 10, skoQuantity = 2.5))
        val notReceived = GoodsInResponses.stockDeliveryWriteRequest(StockDeliveryWrite.Check(itemId = 10, skoQuantity = 0.0))
        val place = GoodsInResponses.stockDeliveryWriteRequest(
            StockDeliveryWrite.Place(itemId = 10, quantity = 1.25, target = PlaceTarget.Link(5501, "A-01-03"), setAsPickingLocation = false, clientRequestId = "req-1"),
        )
        val placeAll = GoodsInResponses.stockDeliveryWriteRequest(
            StockDeliveryWrite.PlaceAll(itemId = 10, target = PlaceTarget.Location(330, "B-02"), setAsPickingLocation = true, clientRequestId = "req-2"),
        )
        val failed = GoodsInResponses.stockDeliveryWriteRequest(
            StockDeliveryWrite.RecordCheck(key = "supplier:3:labels", result = CheckResult.Failed, body = " Torn ", incidentType = "damaged", incidentQuantity = 2.0),
        )

        assertEquals("items/10/check", check.path)
        assertEquals(JsonPrimitive(2.5), check.body?.get("sko_quantity"))
        assertEquals(JsonPrimitive(0), notReceived.body?.get("sko_quantity"))
        assertEquals("items/10/place", place.path)
        assertEquals(JsonPrimitive(5501), place.body?.get("location_org_stock_id"))
        assertFalse(place.body?.containsKey("location_id") == true)
        assertEquals(JsonPrimitive("req-1"), place.body?.get("client_request_id"))
        assertEquals("items/10/place-all", placeAll.path)
        assertEquals(JsonPrimitive(330), placeAll.body?.get("location_id"))
        assertFalse(placeAll.body?.containsKey("location_org_stock_id") == true)
        assertEquals(JsonPrimitive(true), placeAll.body?.get("set_as_picking_location"))
        assertEquals("checklist", failed.path)
        assertEquals(JsonPrimitive("Torn"), failed.body?.get("body"))
        assertEquals(JsonPrimitive(2), failed.body?.get("incident_quantity"))
        assertEquals("receive", GoodsInResponses.stockDeliveryWriteRequest(StockDeliveryWrite.Receive).path)
        assertEquals("unreceive", GoodsInResponses.stockDeliveryWriteRequest(StockDeliveryWrite.Unreceive).path)
        assertEquals("check-all", GoodsInResponses.stockDeliveryWriteRequest(StockDeliveryWrite.CheckAll).path)
    }

    @Test
    fun `the returns list reads rows, meta and counts`() {
        val body = """{"data":[{"id":5,"reference":"DN1-ret","type":"cancellation","type_label":"Cancellation","state":"returning","state_label":"Returning to Locations",
            "created_at":"2026-11-15T10:00:00+00:00","customer":{"name":"Jane Gifts"},"delivery_note":{"id":9,"reference":"DN1"},"number_items":"2","number_handled":1,
            "handler":{"id":3,"name":"Ana"}}],"meta":{"current_page":1,"last_page":1,"per_page":50,"total":1},
            "counts":{"received":3,"returning":"1","returned":0,"done":12,"cancelled":0,"all":16}}"""

        val page = (GoodsInResponses.parseReturns(200, body) as GoodsOutResult.Loaded).value
        val row = page.rows.single()

        assertEquals(ReturnType.CANCELLATION, row.type)
        assertEquals(9L, row.deliveryNote?.id)
        assertEquals(2, row.numberItems)
        assertEquals(1, row.numberHandled)
        assertEquals(16, page.counts?.count(ReturnStateFilter.All.key))
        assertEquals(1, page.counts?.count(ReturnStateFilter.Returning.key))
        assertEquals(listOf("state" to "returning", "q" to "DN1", "page" to "1", "per_page" to "50"), GoodsInResponses.returnsParameters(ReturnQuery(state = ReturnStateFilter.Returning, search = "DN1")))
    }

    @Test
    fun `the return detail reads items with decimal quantities, puts and locations`() {
        val body = """{"data":{"id":5,"reference":"DN1-ret","type":"return","type_label":"Return","state":"returning","state_label":"Returning to Locations",
            "customer":{"name":"Jane"},"order":{"reference":"O-1"},"delivery_note":{"id":9,"reference":"DN1"},"handler":{"id":3,"name":"Ana"},
            "timeline":[{"state":"received","label":"Received","at":"2026-11-15T10:00:00+00:00"}],
            "items":[{"id":51,"state":"handling","is_handled":false,"sko":{"id":77,"code":"AWO-12","name":"Lavender","packed_in":6,"image_url":null},
              "expected":"3","expected_label":"3","returned":1,"returned_label":"1","damaged":"0.5","damaged_label":"½","not_returned":0,"not_returned_label":"0",
              "remaining":1.5,"remaining_label":"1½",
              "puts":[{"id":700,"type":"sow","type_label":"Returned","quantity":1,"quantity_label":"1","location":{"id":220,"code":"A-01-03"},"by":{"id":3,"name":"Ana"}},
                      {"id":701,"type":"damaged","type_label":"Damaged","quantity":"0.5","location":null,"by":null}],
              "locations":[{"location_org_stock_id":5501,"location":{"id":220,"code":"A-01-03"},"type":"picking","quantity":4,"quantity_label":"4"}]}],
            "available_actions":[{"key":"put_back","label":"Put back"},{"key":"undo","label":"Undo"},{"key":"set_as_returned","label":"Set as returned"}]}}"""

        val customerReturn = (GoodsInResponses.parseReturn(200, body) as GoodsOutResult.Loaded).value
        val item = customerReturn.items.single()

        assertEquals("O-1", customerReturn.order?.reference)
        assertEquals("Ana", customerReturn.handler?.name)
        assertEquals(0.5, item.damaged, 0.0)
        assertEquals(1.5, item.remaining, 0.0)
        assertTrue(item.hasRemaining)
        assertEquals(PutType.DAMAGED, item.puts[1].type)
        assertNull(item.puts[1].location)
        assertEquals(5501L, item.locations.single().locationOrgStockId)
        assertEquals("Received", customerReturn.timeline.single().label)
        assertTrue(customerReturn.action(GoodsInActionKey.SET_AS_RETURNED) != null)
    }

    @Test
    fun `return writes go to their paths and null quantity means all remaining`() {
        val returned = GoodsInResponses.returnWriteRequest(ReturnWrite.Returned(itemId = 51, quantity = 1.5, locationOrgStockId = 5501))
        val damaged = GoodsInResponses.returnWriteRequest(ReturnWrite.Damaged(itemId = 51, quantity = null))
        val undo = GoodsInResponses.returnWriteRequest(ReturnWrite.UndoPut(putId = 700))

        assertEquals("items/51/returned", returned.path)
        assertEquals(JsonPrimitive(1.5), returned.body?.get("quantity"))
        assertEquals(JsonPrimitive(5501), returned.body?.get("location_org_stock_id"))
        assertEquals("items/51/damaged", damaged.path)
        assertEquals(JsonNull, damaged.body?.get("quantity"))
        assertEquals("items/51/not-returned", GoodsInResponses.returnWriteRequest(ReturnWrite.NotReturned(itemId = 51, quantity = 1.0)).path)
        assertEquals(WriteMethod.Delete, undo.method)
        assertEquals("puts/700", undo.path)
        assertEquals("start", GoodsInResponses.returnWriteRequest(ReturnWrite.Start).path)
        assertEquals("set-as-returned", GoodsInResponses.returnWriteRequest(ReturnWrite.SetAsReturned).path)
    }

    @Test
    fun `a return write answers the re-read return and its message`() {
        val body = """{"data":{"id":5,"reference":"DN1-ret","state":"returned"},"result":{"status":"done","message":"1.5 remaining set as not returned."}}"""

        val outcome = (GoodsInResponses.parseReturnWrite(200, body) as GoodsOutResult.Loaded).value

        assertEquals(ReturnState.RETURNED, outcome.customerReturn.state)
        assertEquals("1.5 remaining set as not returned.", outcome.message)
    }

    @Test
    fun `the scan can find a stock delivery`() {
        val body = """{"data":{"code":"SD-1","type":"stock_delivery","sko":null,"location":null,"delivery_note":null,"stock_delivery":{"id":1,"reference":"SD-1","state":"received","state_label":"Received"}}}"""

        val lookup = (GoodsOutResponses.parseScan(200, body) as GoodsOutResult.Loaded).value

        assertEquals(ScanMatch.STOCK_DELIVERY, lookup.type)
        assertEquals(1L, lookup.stockDelivery?.id)
        assertEquals("Received", lookup.stockDelivery?.stateLabel)
    }

    @Test
    fun `the warehouse abilities read the return rights`() {
        val body = """{"data":[{"id":7,"slug":"wh1","code":"WH1","name":"Main","organisation":{"id":3,"slug":"aw","name":"AW"},
            "can":{"view_goods_in":false,"view_returns":true,"handle_returns":true}}]}"""

        val can = (WarehouseResponses.parse(200, body) as WarehousesResult.Loaded).warehouses.single().can

        assertTrue(can.viewReturns)
        assertTrue(can.handleReturns)
        assertFalse(can.viewGoodsIn)
        assertTrue(can.allows(Ability.ViewReturns))
        assertFalse(can.allows(Ability.BookIn))
    }

    @Test
    fun `stock delivery and return lists send the date range and the new sorts`() {
        val deliveries = StockDeliveryQuery(from = LocalDate.of(2026, 11, 18), to = LocalDate.of(2026, 11, 23), sort = StockDeliverySort.Supplier)

        assertEquals(
            listOf("state" to "active", "from" to "2026-11-18", "to" to "2026-11-23", "sort" to "supplier", "page" to "1", "per_page" to "50"),
            GoodsInResponses.stockDeliveriesParameters(deliveries),
        )
        assertEquals(
            listOf("state" to "active", "to" to "2026-11-23", "sort" to "estimated_receiving_date", "page" to "1", "per_page" to "50"),
            GoodsInResponses.stockDeliveriesParameters(StockDeliveryQuery(to = LocalDate.of(2026, 11, 23), sort = StockDeliverySort.ExpectedDate)),
        )
        assertEquals(
            listOf("-date", "date", "reference", "supplier", "estimated_receiving_date", "-dispatched_at", "-number_items"),
            StockDeliverySort.entries.map { it.key },
        )
        assertEquals(
            listOf("state" to "all", "q" to "DN1", "from" to "2026-11-01", "sort" to "customer", "page" to "1", "per_page" to "50"),
            GoodsInResponses.returnsParameters(ReturnQuery(search = "DN1", from = LocalDate.of(2026, 11, 1), sort = ReturnSort.Customer)),
        )
        assertEquals(listOf("created_at", "reference", "customer"), ReturnSort.entries.map { it.key })
    }
}
