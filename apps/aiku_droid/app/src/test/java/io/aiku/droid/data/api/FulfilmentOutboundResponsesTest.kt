package io.aiku.droid.data.api

import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class FulfilmentOutboundResponsesTest {

    private val palletReturnJson = """
        {"id":7,"reference":"acme-r004","customer_reference":null,"type":"pallet","type_group":"pallets","type_label":"Pallets",
         "platform":null,"state":"picking","state_label":"Picking","customer":{"id":3,"name":"Acme Ltd"},
         "is_collection":false,"collection_notes":null,"customer_notes":"Fragile","public_notes":null,"internal_notes":null,
         "picker":{"id":2,"name":"Jo"},"packer":null,"estimated_delivery_date":"2026-11-20","in_picking_session":false,
         "timeline":[{"state":"confirmed","label":"Confirmed","at":"2026-11-16T09:00:00+00:00"},{"state":"picking","label":"Picking","at":null}],
         "counts":{"lines":"2","picked":1,"not_picked":0,"to_pick":"1","waiting_customer_service":0},
         "pallets":[
           {"line_id":55,"line_state":"picked","line_state_label":"Picked","waiting_customer_service":false,
            "pallet":{"id":10,"reference":"acme-p0118","customer_reference":"Spring 1","type":"pallet","type_label":"Pallet","state":"picked","state_label":"Picked"},
            "location":{"id":220,"code":"D-01-03"},"stored_items":[{"reference":"MUG-BLUE","name":"Mug","quantity":"40"}],
            "available_actions":[{"key":"undo","label":"Undo"}]},
           {"line_id":56,"line_state":"picking","line_state_label":"Picking","waiting_customer_service":false,
            "pallet":{"id":11,"reference":"acme-p0121","customer_reference":null,"type":"pallet","type_label":"Pallet","state":"picking","state_label":"Picking"},
            "location":null,"stored_items":[],
            "available_actions":[{"key":"pick","label":"Pick"},{"key":"not_picked","label":"Not picked"}]}
         ],
         "stored_items":[],
         "inserts":{"total":1,"unprinted":"1"},
         "shipments":[{"id":91,"shipper":"DPD","shipper_code":"dpd","tracking":"TRK1","trackings":["TRK1"],"tracking_urls":[null],"is_printable":true,
            "label":{"kind":"download","url":"https://app.aiku.test/app/api/mobile/warehouses/1/pallet-returns/7/shipments/91/label"},"can_delete":true}],
         "parcels":[{"weight":"12.5","dimensions":[120,80,100]}],
         "shipment_required":true,"dispatch_blocked":null,"can_scan":true,
         "available_actions":[{"key":"finish_picking","label":"Set as picked"},{"key":"add_shipment","label":"Add shipment"}]}
    """.trimIndent()

    private val itemsReturnJson = """
        {"id":8,"reference":"bravo-r011","type":"stored_item","type_group":"items","type_label":"Stored items","platform":{"code":"shopify","name":"Shopify"},
         "state":"picking","state_label":"Picking","customer":{"id":4,"name":"Bravo"},
         "pallets":[],
         "stored_items":[{"stored_item":{"id":1,"reference":"GIFT-L","name":"Gift box large","barcode":null},
            "quantity_ordered":"6","quantity_picked":4,"quantity_not_picked":0,"quantity_to_pick":2,
            "lines":[{"line_id":61,"pallet":{"id":11,"reference":"bravo-p0140","customer_reference":null},"location":{"id":9,"code":"D-03-01"},
               "quantity_ordered":4,"quantity_picked":4,"quantity_not_picked":0,"quantity_to_pick":0,"quantity_on_pallet":"0",
               "state":"picked","state_label":"Picked","waiting_customer_service":false,"available_actions":[{"key":"undo","label":"Undo"}]},
              {"line_id":62,"pallet":{"id":12,"reference":"bravo-p0152","customer_reference":null},"location":null,
               "quantity_ordered":2,"quantity_picked":0,"quantity_not_picked":0,"quantity_to_pick":2,"quantity_on_pallet":1,
               "state":"picking","state_label":"Picking","waiting_customer_service":false,
               "available_actions":[{"key":"pick","label":"Pick"},{"key":"not_picked","label":"Not picked"}]}],
            "other_pallets":[{"pallet_stored_item_id":88,"pallet":{"id":13,"reference":"bravo-p0160","customer_reference":null},"location":{"id":30,"code":"D-04-06"},"quantity_available":"3"}]}],
         "inserts":{"total":0,"unprinted":0},"shipments":[],"parcels":[],"shipment_required":false,"dispatch_blocked":null,"can_scan":false,"available_actions":[]}
    """.trimIndent()

    private val auditJson = """
        {"id":12,"reference":"acme-sia014","state":"in_process","state_label":"In Process",
         "pallet":{"id":11,"reference":"acme-p0140","customer_reference":null,"location":{"id":9,"code":"D-03-01"}},
         "customer":{"id":3,"name":"Acme Ltd"},
         "lines":[
           {"stored_item":{"id":1,"reference":"MUG-BLUE","name":"Mug blue","barcode":"5060000000017"},"quantity":"40","audited_quantity":40,"delta_id":77,"audit_type":"no_change","audit_type_label":"No change","is_new_item":false},
           {"stored_item":{"id":2,"reference":"MUG-GREEN","name":"Mug green","barcode":null},"quantity":4,"audited_quantity":"2","delta_id":78,"audit_type":"subtraction","audit_type_label":"Subtraction","is_new_item":false},
           {"stored_item":{"id":3,"reference":"TOWEL-SET","name":"Towels","barcode":null},"quantity":18,"audited_quantity":null,"delta_id":null,"audit_type":null,"audit_type_label":null,"is_new_item":false}
         ],
         "counts":{"lines":3,"counted":"2","differences":1},
         "available_actions":[{"key":"count","label":"Count"},{"key":"add_item","label":"Add item"},{"key":"complete","label":"Complete audit"}]}
    """.trimIndent()

    @Test
    fun `the return list reads rows, meta and the state and type counts`() {
        val body = """{"data":[{"id":7,"reference":"acme-r004","customer_reference":null,"type":"stored_item","type_group":"items","type_label":"Stored items",
            "platform":{"code":"shopify","name":"Shopify"},"state":"confirmed","state_label":"Confirmed","customer":{"id":3,"name":"Acme"},
            "number_pallets":"2","number_items":3,"date":"2026-11-16T09:00:00+00:00","picker":null,"is_collection":true}],
            "meta":{"current_page":1,"last_page":2,"per_page":25,"total":"30"},
            "counts":{"confirmed":"3","picking":2,"picked":1,"dispatched":40,"cancel":null,"active":6,"types":{"pallets":4,"items":"2"}}}"""

        val page = (FulfilmentOutboundResponses.parsePalletReturns(200, body) as GoodsOutResult.Loaded).value
        val row = page.rows.single()

        assertEquals(PalletReturnTypeGroup.ITEMS, row.typeGroup)
        assertEquals("Shopify", row.platform?.label)
        assertEquals(2, row.numberPallets)
        assertEquals(3, row.numberItems)
        assertTrue(row.isCollection)
        assertNull(row.picker)
        assertEquals(30, page.meta.total)
        assertEquals(2, page.meta.lastPage)
        assertEquals(3, page.counts?.states?.count(PalletReturnStateFilter.Confirmed.key))
        assertEquals(6, page.counts?.states?.count(PalletReturnStateFilter.Active.key))
        assertNull(page.counts?.states?.count(PalletReturnStateFilter.Cancelled.key))
        assertNull(page.counts?.states?.count("types"))
        assertEquals(4, page.counts?.types?.count(PalletReturnTypeFilter.Pallets.key))
        assertEquals(2, page.counts?.types?.count(PalletReturnTypeFilter.Items.key))
    }

    @Test
    fun `a list without counts still loads`() {
        val page = (FulfilmentOutboundResponses.parsePalletReturns(200, """{"data":[]}""") as GoodsOutResult.Loaded).value

        assertTrue(page.rows.isEmpty())
        assertNull(page.counts)
        assertEquals(1, page.meta.currentPage)
    }

    @Test
    fun `the pallet return detail reads pallet lines, inserts, shipments, parcels and actions`() {
        val palletReturn = (FulfilmentOutboundResponses.parsePalletReturn(200, """{"data":$palletReturnJson}""") as GoodsOutResult.Loaded).value

        assertFalse(palletReturn.isItems)
        assertEquals("Acme Ltd", palletReturn.customer?.name)
        assertEquals(2, palletReturn.counts.lines)
        assertEquals(1, palletReturn.counts.toPick)
        assertEquals(40, palletReturn.pallets.first().storedItems.single().quantity)
        assertEquals("D-01-03", palletReturn.pallets.first().location?.code)
        assertNull(palletReturn.pallets[1].location)
        assertTrue(palletReturn.pallets.first().can(PalletReturnActionKey.UNDO))
        assertFalse(palletReturn.pallets.first().can(PalletReturnActionKey.PICK))
        assertTrue(palletReturn.pallets[1].can(PalletReturnActionKey.PICK))
        assertEquals(1, palletReturn.inserts.unprinted)
        assertEquals(listOf("TRK1"), palletReturn.shipments.single().trackingNumbers)
        assertEquals(ShipmentLabelKind.DOWNLOAD, palletReturn.shipments.single().label.kind)
        assertTrue(palletReturn.shipments.single().canDelete)
        assertEquals(12.5, palletReturn.parcels.single().asParcel().weightKg!!, 0.0)
        assertEquals(listOf(120.0, 80.0, 100.0), palletReturn.parcels.single().asParcel().dimensionsCm)
        assertTrue(palletReturn.canScan)
        assertTrue(palletReturn.can(PalletReturnActionKey.FINISH_PICKING))
        assertFalse(palletReturn.can(PalletReturnActionKey.DISPATCH))
        assertEquals("2026-11-16T09:00:00+00:00", palletReturn.timelineAt(PalletReturnState.CONFIRMED))
        assertNull(palletReturn.timelineAt(PalletReturnState.PICKING))
        assertEquals(56L, palletReturn.palletLine(56)?.lineId)
    }

    @Test
    fun `the stored item detail reads lines per pallet and the other pallets`() {
        val palletReturn = (FulfilmentOutboundResponses.parsePalletReturn(200, """{"data":$itemsReturnJson}""") as GoodsOutResult.Loaded).value
        val item = palletReturn.storedItems.single()

        assertTrue(palletReturn.isItems)
        assertEquals(6, item.quantityOrdered)
        assertEquals(0, item.lines.first().quantityOnPallet)
        assertEquals(1, item.lines[1].maxPick)
        assertEquals(1, item.lines[1].shortfall)
        assertEquals(62L, item.shortLine?.lineId)
        assertEquals(3, item.otherPallets.single().quantityAvailable)
        assertTrue(item.canPickFromOtherPallet)
        assertEquals(62L, palletReturn.itemLine(62)?.second?.lineId)
        assertEquals("GIFT-L", palletReturn.itemLine(62)?.first?.storedItem?.reference)
        assertTrue(palletReturn.parcels.isEmpty())
    }

    @Test
    fun `a write and a scan answer the detail with their result`() {
        val write = (FulfilmentOutboundResponses.parsePalletReturnWrite(200, """{"data":$palletReturnJson,"result":{"status":"nothing_changed","message":" Already picked. "}}""") as GoodsOutResult.Loaded).value
        val scan = (FulfilmentOutboundResponses.parseScan(200, """{"data":$palletReturnJson,"result":{"status":"already_picked","message":"","line_id":55}}""") as GoodsOutResult.Loaded).value

        assertEquals(OutboundWriteStatus.NOTHING_CHANGED, write.status)
        assertEquals("Already picked.", write.message)
        assertEquals(PalletReturnScanStatus.ALREADY_PICKED, scan.status)
        assertNull(scan.message)
        assertEquals(55L, scan.lineId)
        assertEquals("acme-r004", scan.palletReturn.reference)
    }

    @Test
    fun `refusals keep the server message and field errors`() {
        val invalid = FulfilmentOutboundResponses.parsePalletReturnWrite(422, """{"message":"The given data was invalid.","errors":{"quantity":["Only 1 can be picked from this pallet."]}}""") as GoodsOutResult.Invalid
        val forbidden = FulfilmentOutboundResponses.parseScan(403, """{"message":"Scan to pick is not enabled for this organisation."}""") as GoodsOutResult.Forbidden
        val notFound = FulfilmentOutboundResponses.parsePalletReturn(404, """{"message":""}""")

        assertEquals("Only 1 can be picked from this pallet.", invalid.message)
        assertEquals(listOf("Only 1 can be picked from this pallet."), invalid.errors[FulfilmentOutboundErrorKey.QUANTITY])
        assertEquals("Scan to pick is not enabled for this organisation.", forbidden.message)
        assertTrue(notFound is GoodsOutResult.NotFound)
        assertEquals(GoodsOutResult.SessionExpired, FulfilmentOutboundResponses.parseAudit(401, ""))
        assertEquals(GoodsOutResult.ServerError(200), FulfilmentOutboundResponses.parsePalletReturn(200, "not json"))
    }

    @Test
    fun `the shippers read the goods out row shape`() {
        val body = """{"data":[{"id":4,"code":"dpd","name":"DPD (GB)","type":"api","needs_tracking":false},{"id":5,"code":"van","name":"Local van","type":"manual","needs_tracking":true}]}"""

        val shippers = (FulfilmentOutboundResponses.parseShippers(200, body) as GoodsOutResult.Loaded).value

        assertTrue(shippers.first().isApi)
        assertFalse(shippers[1].isApi)
        assertEquals("Local van", shippers[1].name)
    }

    @Test
    fun `return writes go to their paths with the contract bodies`() {
        val start = FulfilmentOutboundResponses.palletReturnWriteRequest(PalletReturnWrite.StartPicking)
        val pickPallet = FulfilmentOutboundResponses.palletReturnWriteRequest(PalletReturnWrite.PickPallet(55))
        val pickItem = FulfilmentOutboundResponses.palletReturnWriteRequest(PalletReturnWrite.PickItem(lineId = 62, quantity = 2, clientRequestId = "req-1"))
        val pickAll = FulfilmentOutboundResponses.palletReturnWriteRequest(PalletReturnWrite.PickItem(lineId = 62, quantity = null, clientRequestId = "req-2"))
        val notPickedPallet = FulfilmentOutboundResponses.palletReturnWriteRequest(PalletReturnWrite.NotPicked(lineId = 56, quantity = null))
        val notPickedItem = FulfilmentOutboundResponses.palletReturnWriteRequest(PalletReturnWrite.NotPicked(lineId = 62, quantity = 1))
        val undo = FulfilmentOutboundResponses.palletReturnWriteRequest(PalletReturnWrite.Undo(61))
        val other = FulfilmentOutboundResponses.palletReturnWriteRequest(PalletReturnWrite.PickFromOtherPallet(palletStoredItemId = 88, quantity = 1, fromLineId = 62, clientRequestId = "req-3"))
        val parcels = FulfilmentOutboundResponses.palletReturnWriteRequest(PalletReturnWrite.SetParcels(listOf(ParcelWrite(weightKg = 12.5, dimensionsCm = listOf(120.0, 80.0, 100.0), boxes = null))))
        val shipment = FulfilmentOutboundResponses.palletReturnWriteRequest(PalletReturnWrite.AddShipment(shipperId = 4, tracking = " TRK "))
        val delete = FulfilmentOutboundResponses.palletReturnWriteRequest(PalletReturnWrite.DeleteShipment(91))
        val print = FulfilmentOutboundResponses.palletReturnWriteRequest(PalletReturnWrite.PrintLabel(91))

        assertEquals("start-picking", start.path)
        assertEquals("finish-picking", FulfilmentOutboundResponses.palletReturnWriteRequest(PalletReturnWrite.FinishPicking).path)
        assertEquals("dispatch", FulfilmentOutboundResponses.palletReturnWriteRequest(PalletReturnWrite.Dispatch).path)
        assertEquals("inserts/print", FulfilmentOutboundResponses.palletReturnWriteRequest(PalletReturnWrite.PrintInserts).path)
        assertEquals("lines/55/pick", pickPallet.path)
        assertTrue(pickPallet.body.orEmpty().isEmpty())
        assertEquals(JsonPrimitive(2), pickItem.body?.get("quantity"))
        assertFalse(pickItem.body?.containsKey("all") == true)
        assertEquals(JsonPrimitive("req-1"), pickItem.body?.get("client_request_id"))
        assertEquals(JsonPrimitive(true), pickAll.body?.get("all"))
        assertFalse(pickAll.body?.containsKey("quantity") == true)
        assertEquals("lines/56/not-picked", notPickedPallet.path)
        assertTrue(notPickedPallet.body.orEmpty().isEmpty())
        assertEquals(JsonPrimitive(1), notPickedItem.body?.get("quantity"))
        assertEquals("lines/61/undo", undo.path)
        assertEquals("pallet-stored-items/88/pick", other.path)
        assertEquals(JsonPrimitive(62), other.body?.get("from_line_id"))
        assertEquals(JsonPrimitive("req-3"), other.body?.get("client_request_id"))
        assertEquals("parcels", parcels.path)
        val parcel = (parcels.body?.get("parcels") as JsonArray).single() as JsonObject
        assertEquals(JsonPrimitive(12.5), parcel["weight_kg"])
        assertEquals(JsonArray(listOf(JsonPrimitive(120), JsonPrimitive(80), JsonPrimitive(100))), parcel["dimensions_cm"])
        assertEquals("shipments", shipment.path)
        assertEquals(JsonPrimitive("TRK"), shipment.body?.get("tracking"))
        assertEquals(JsonPrimitive(4), shipment.body?.get("shipper_id"))
        assertEquals(JsonNull, FulfilmentOutboundResponses.palletReturnWriteRequest(PalletReturnWrite.AddShipment(shipperId = 4, tracking = " ")).body?.get("tracking"))
        assertEquals(WriteMethod.Delete, delete.method)
        assertEquals("shipments/91", delete.path)
        assertEquals("shipments/91/print-label", print.path)
    }

    @Test
    fun `the scan body sends the barcode, quantity and request id`() {
        val body = FulfilmentOutboundResponses.scanBody(PalletReturnScanRequest(barcode = "acme-p0121", quantity = 1, clientRequestId = "req-9"))

        assertEquals(JsonPrimitive("acme-p0121"), body["barcode"])
        assertEquals(JsonPrimitive(1), body["quantity"])
        assertEquals(JsonPrimitive("req-9"), body["client_request_id"])
    }

    @Test
    fun `list parameters send the state, type, search and paging`() {
        assertEquals(
            listOf("state" to "picked", "type" to "items", "q" to "acme", "page" to "2", "per_page" to "25"),
            FulfilmentOutboundResponses.palletReturnsParameters(PalletReturnQuery(state = PalletReturnStateFilter.Picked, type = PalletReturnTypeFilter.Items, search = " acme ", page = 2, perPage = 25)),
        )
        assertEquals(
            listOf("state" to "active", "page" to "1", "per_page" to "50"),
            FulfilmentOutboundResponses.palletReturnsParameters(PalletReturnQuery(search = " ")),
        )
        assertEquals(
            listOf("state" to "all", "q" to "MUG", "page" to "1", "per_page" to "50"),
            FulfilmentOutboundResponses.storedItemsParameters(StoredItemQuery(state = StoredItemStateFilter.All, search = "MUG")),
        )
        assertTrue(FulfilmentOutboundResponses.searchParameters(null).isEmpty())
    }

    @Test
    fun `stored items read rows, counts and the pallets of one item`() {
        val list = """{"data":[{"id":1,"reference":"MUG-BLUE","name":"Mug","barcode":null,"state":"active","state_label":"Active","customer":{"id":3,"name":"Acme"},"quantity":"64","number_pallets":2}],
            "meta":{"current_page":1,"last_page":1,"per_page":50,"total":1},"counts":{"active":"10","discontinuing":2,"discontinued":0,"all":12}}"""
        val detail = """{"data":{"id":1,"reference":"MUG-BLUE","name":"Mug","state":"active","state_label":"Active","quantity":64,"number_pallets":2,
            "pallets":[{"pallet":{"id":10,"reference":"acme-p0140","customer_reference":null,"state":"storing","state_label":"Storing"},"location":{"id":9,"code":"D-03-01"},"quantity":"40"},
                       {"pallet":{"id":11,"reference":"acme-p0141","state":"storing","state_label":"Storing"},"location":null,"quantity":24}]}}"""

        val page = (FulfilmentOutboundResponses.parseStoredItems(200, list) as GoodsOutResult.Loaded).value
        val item = (FulfilmentOutboundResponses.parseStoredItem(200, detail) as GoodsOutResult.Loaded).value

        assertEquals(64, page.rows.single().quantity)
        assertEquals(10, page.counts?.count(StoredItemStateFilter.Active.key))
        assertEquals(12, page.counts?.count(StoredItemStateFilter.All.key))
        assertEquals(listOf(40, 24), item.pallets.map { it.quantity })
        assertNull(item.pallets[1].location)
        assertEquals("Storing", item.pallets.first().pallet.stateLabel)
    }

    @Test
    fun `the audit reads counted and uncounted lines and its actions`() {
        val audit = (FulfilmentOutboundResponses.parseAudit(200, """{"data":$auditJson}""") as GoodsOutResult.Loaded).value
        val written = (FulfilmentOutboundResponses.parseAuditWrite(200, """{"data":$auditJson,"result":{"status":"done","message":"Audit resumed."}}""") as GoodsOutResult.Loaded).value
        val choices = (FulfilmentOutboundResponses.parseAuditStoredItemChoices(200, """{"data":[{"id":5,"reference":"CANDLE","name":"Candle","barcode":"123"}]}""") as GoodsOutResult.Loaded).value

        assertEquals(40, audit.lines.first().quantity)
        assertEquals(0, audit.lines.first().difference)
        assertEquals(2, audit.lines[1].auditedQuantity)
        assertEquals(-2, audit.lines[1].difference)
        assertFalse(audit.lines[2].isCounted)
        assertNull(audit.lines[2].deltaId)
        assertEquals(2, audit.counts.counted)
        assertEquals("D-03-01", audit.pallet?.location?.code)
        assertTrue(audit.can(StoredItemAuditActionKey.COMPLETE))
        assertEquals("MUG-BLUE", audit.lineForCode("5060000000017")?.storedItem?.reference)
        assertEquals("MUG-GREEN", audit.lineForCode("mug-green")?.storedItem?.reference)
        assertEquals("Audit resumed.", written.message)
        assertEquals("CANDLE", choices.single().reference)
    }

    @Test
    fun `audit writes count by item or barcode and add with a request id`() {
        val count = FulfilmentOutboundResponses.auditWriteRequest(StoredItemAuditWrite.Count(target = AuditItemTarget.Item(3), auditedQuantity = 17))
        val add = FulfilmentOutboundResponses.auditWriteRequest(StoredItemAuditWrite.Add(target = AuditItemTarget.Barcode(" 5060 "), add = 1, clientRequestId = "req-4"))
        val remove = FulfilmentOutboundResponses.auditWriteRequest(StoredItemAuditWrite.RemoveCount(78))
        val complete = FulfilmentOutboundResponses.auditWriteRequest(StoredItemAuditWrite.Complete)

        assertEquals("count", count.path)
        assertEquals(JsonPrimitive(3), count.body?.get("stored_item_id"))
        assertEquals(JsonPrimitive(17), count.body?.get("audited_quantity"))
        assertFalse(count.body?.containsKey("add") == true)
        assertEquals(JsonPrimitive("5060"), add.body?.get("barcode"))
        assertEquals(JsonPrimitive(1), add.body?.get("add"))
        assertEquals(JsonPrimitive("req-4"), add.body?.get("client_request_id"))
        assertFalse(add.body?.containsKey("audited_quantity") == true)
        assertEquals(WriteMethod.Delete, remove.method)
        assertEquals("deltas/78", remove.path)
        assertEquals("complete", complete.path)
    }

    @Test
    fun `pallet return list sends the chosen sort and nothing for newest first`() {
        assertEquals(
            listOf("state" to "active", "sort" to "customer", "page" to "1", "per_page" to "50"),
            FulfilmentOutboundResponses.palletReturnsParameters(PalletReturnQuery(sort = PalletReturnSort.Customer)),
        )
        assertEquals(listOf("date", "reference", "customer", "-dispatched_at"), PalletReturnSort.entries.map { it.key })
    }

    @Test
    fun `how a return leaves reads the address, the rights and the customer's addresses`() {
        val withLeaving = palletReturnJson.trimEnd().removeSuffix("}") + ""","leaving":{"delivery_address":["1 Dock Road","Hull","GB"],"can_set_collection":true,"can_set_shipment":false}}"""
        val palletReturn = (FulfilmentOutboundResponses.parsePalletReturn(200, """{"data":$withLeaving}""") as GoodsOutResult.Loaded).value
        val older = (FulfilmentOutboundResponses.parsePalletReturn(200, """{"data":$palletReturnJson}""") as GoodsOutResult.Loaded).value
        val addresses = (
            FulfilmentOutboundResponses.parseAddresses(200, """{"data":[{"id":4,"lines":["1 Dock Road","Hull"],"is_default_delivery":true,"is_selected":false},{"id":5,"lines":["Unit 2"],"is_default_delivery":false,"is_selected":true}]}""")
                as GoodsOutResult.Loaded
            ).value

        assertEquals(listOf("1 Dock Road", "Hull", "GB"), palletReturn.leaving.deliveryAddress)
        assertTrue(palletReturn.leaving.canChange)
        assertFalse(palletReturn.leaving.canSetShipment)
        assertFalse(older.leaving.canChange)
        assertEquals(listOf(4L, 5L), addresses.map { it.id })
        assertTrue(addresses[0].isDefaultDelivery)
        assertTrue(addresses[1].isSelected)
    }

    @Test
    fun `switching how a return leaves sends collection or the chosen address`() {
        val collect = FulfilmentOutboundResponses.palletReturnWriteRequest(PalletReturnWrite.SetLeaving(collection = true, addressId = null))
        val ship = FulfilmentOutboundResponses.palletReturnWriteRequest(PalletReturnWrite.SetLeaving(collection = false, addressId = 5))

        assertEquals("leaving", collect.path)
        assertEquals(JsonPrimitive(true), collect.body?.get("collection"))
        assertFalse(collect.body?.containsKey("address_id") == true)
        assertEquals(JsonPrimitive(false), ship.body?.get("collection"))
        assertEquals(JsonPrimitive(5), ship.body?.get("address_id"))
    }
}
