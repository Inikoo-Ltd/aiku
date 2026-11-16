package io.aiku.droid.data.api

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class PackingResponsesTest {

    private val noteJson = """
        {"id":9,"reference":"AR24811","state":"packed","state_label":"Packed","warehouse_id":1,"type":"order",
        "shop":{"id":3,"name":"AW Dropship","type":"dropshipping"},"is_editable":true,"allow_actions":true,"can_handle":true,
        "available_actions":[{"key":"add_shipment","label":"Add shipment"},{"key":"finalise_and_dispatch","label":"Finalise and Dispatch","disabled_reason":"Print every insert before continuing"},{"key":"unpack","label":"Unpack"}],
        "settings":{"allow_scan_to_pick":true,"allow_waiting":true,"allow_picker_set_not_picked":true,"allow_picker_choose_location":false,"allow_scan_to_pack":true},
        "consumables":[{"code":"BOX-S","quantity":2}],
        "packaging":{"current":{"id":3,"name":"Gift box M"}},
        "inserts":{"leaflets":[{"id":51,"name":"Thank you card","type":"leaflet","copies":1,"state":"pending_print","state_label":"Pending print","can_open_pdf":true}],
        "total":2,"printed":1,"all_printed":false,"label":"1 of 2 printed","can_print_all":true},
        "box_packing_list":{"required":true,"number_boxes":3,"missing_message":"Box every picked line before packing.","pdf_url":"https://app.aiku.test/app/api/mobile/delivery-notes/9/packing-list.pdf"},
        "parcels":[{"weight_kg":1.2,"dimensions_cm":[30,20,10],"boxes":2},{"weight_kg":0.5,"dimensions_cm":[null,null,null],"boxes":null}],
        "parcels_editable":false,
        "shipping":{"can_add":true,"can_print_labels":true,"locked_shipper_id":null,"preferred_shipper_id":4,"locked_by":null,"can_override_lock":false,
        "tracking_channel":"Shopify","unconfirmed_shipping_order":false,"external":{"engine_label":"Faire"}},
        "shipments":[{"id":77,"shipper":"APC","shipper_code":"apc","tracking":"JD0001","trackings":["JD0001","JD0002"],"tracking_urls":["https://apc.example/JD0001",null],
        "is_printable":true,"label":{"kind":"download","url":"https://app.aiku.test/app/api/mobile/delivery-notes/9/shipments/77/label"},"can_delete":true},
        {"id":78,"shipper":"Faire","tracking":null,"is_printable":true,"label":{"kind":"external","url":"https://faire.example/label/78"}}],
        "documents":{"delivery_note_pdf_url":"https://app.aiku.test/app/api/mobile/delivery-notes/9/pdf","gift_message_pdf_url":null},
        "delivery_address":{"lines":["12 High St","Sheffield"],"country_code":"GB","fields":{"contact_name":"Jane Doe","company_name":"Jane Gifts","email":"jane@example.com",
        "phone":"+44 114","address_line_1":"12 High St","address_line_2":null,"sorting_code":null,"postal_code":"S1 2AB","locality":"Sheffield","dependent_locality":null,"administrative_area":"South Yorkshire","country_code":"GB"}},
        "lines":[{"id":41001,"quantity_required":3,"quantity_picked":3,"quantity_packed":2,"quantity_packed_label":"2","quantity_to_pack":1,"quantity_to_pack_label":"1",
        "is_done_packing":false,"is_packed":false,"boxes":[{"box":1,"quantity":2}]},{"id":41002,"quantity_picked":1,"quantity_packed":1,"is_done_packing":true}]}
    """.trimIndent()

    @Test
    fun `the note carries the packing, shipping and document fields`() {
        val note = (GoodsOutResponses.parseDeliveryNote(200, """{"data":$noteJson}""") as GoodsOutResult.Loaded).value

        assertTrue(note.settings.allowScanToPack)
        assertEquals(listOf(Consumable(code = "BOX-S", quantity = 2.0)), note.consumables)
        assertEquals(PackagingRef(id = 3, name = "Gift box M"), note.packaging?.current)
        assertEquals(
            NoteInserts(
                leaflets = listOf(InsertLeaflet(id = 51, name = "Thank you card", type = "leaflet", copies = 1, state = LeafletState.PENDING_PRINT, stateLabel = "Pending print", canOpenPdf = true)),
                total = 2,
                printed = 1,
                allPrinted = false,
                label = "1 of 2 printed",
                canPrintAll = true,
            ),
            note.inserts,
        )
        assertFalse(note.inserts?.leaflets?.single()?.isDone ?: true)
        assertEquals("Box every picked line before packing.", note.boxPackingList?.missingMessage)
        assertEquals(3, note.boxPackingList?.numberBoxes)
        assertEquals(DeliveryNoteParcel(weightKg = 1.2, dimensionsCm = listOf(30.0, 20.0, 10.0), boxes = 2), note.parcels.first())
        assertNull(note.parcels.last().boxes)
        assertFalse(note.parcelsEditable)
        assertEquals(4L, note.shipping.preferredShipperId)
        assertTrue(note.shipping.canPrintLabels)
        assertEquals("Shopify", note.shipping.trackingChannel)
        assertEquals("Faire", note.shipping.external?.engineLabel)
        assertEquals("https://app.aiku.test/app/api/mobile/delivery-notes/9/pdf", note.documents.deliveryNotePdfUrl)
        assertNull(note.documents.giftMessagePdfUrl)
        assertEquals("S1 2AB", note.deliveryAddress?.fields?.postalCode)
        assertEquals("South Yorkshire", note.deliveryAddress?.fields?.administrativeArea)
    }

    @Test
    fun `shipments carry their trackings, label and delete right`() {
        val note = (GoodsOutResponses.parseDeliveryNote(200, """{"data":$noteJson}""") as GoodsOutResult.Loaded).value
        val apc = note.shipment(77)
        val faire = note.shipment(78)

        assertEquals(listOf("JD0001", "JD0002"), apc?.trackingNumbers)
        assertEquals("https://apc.example/JD0001", apc?.trackingUrl(0))
        assertNull(apc?.trackingUrl(1))
        assertEquals(ShipmentLabel(kind = ShipmentLabelKind.DOWNLOAD, url = "https://app.aiku.test/app/api/mobile/delivery-notes/9/shipments/77/label"), apc?.label)
        assertTrue(apc?.canDelete ?: false)
        assertEquals(emptyList<String>(), faire?.trackingNumbers)
        assertEquals(ShipmentLabelKind.EXTERNAL, faire?.label?.kind)
        assertFalse(faire?.canDelete ?: true)
        assertEquals(ShipmentLabel(), DeliveryNoteShipment(id = 1).label)
        assertEquals(listOf("T-1"), DeliveryNoteShipment(id = 1, tracking = "T-1").trackingNumbers)
    }

    @Test
    fun `lines say when packing is done and which boxes hold them`() {
        val note = (GoodsOutResponses.parseDeliveryNote(200, """{"data":$noteJson}""") as GoodsOutResult.Loaded).value

        assertEquals("1", note.lines.first().quantityToPackLabel)
        assertEquals(listOf(LineBox(box = 1, quantity = 2.0)), note.lines.first().boxes)
        assertFalse(note.lines.first().isPackingDone)
        assertTrue(note.lines.last().isPackingDone)
        assertEquals(emptyList<LineBox>(), note.lines.last().boxes)
    }

    @Test
    fun `a key can carry the server's reason it can't be used`() {
        val note = (GoodsOutResponses.parseDeliveryNote(200, """{"data":$noteJson}""") as GoodsOutResult.Loaded).value

        assertEquals("Print every insert before continuing", note.action(NoteActionKey.FINALISE_AND_DISPATCH)?.disabledReason)
        assertTrue(note.action(NoteActionKey.FINALISE_AND_DISPATCH)?.isDisabled ?: false)
        assertFalse(note.action(NoteActionKey.ADD_SHIPMENT)?.isDisabled ?: true)
    }

    @Test
    fun `a note from before 2d reads with nothing to pack or ship`() {
        val note = (GoodsOutResponses.parseDeliveryNote(200, """{"data":{"id":1,"shipments":[{"id":3,"shipper":"DPD","tracking":"1"}]}}""") as GoodsOutResult.Loaded).value

        assertFalse(note.settings.allowScanToPack)
        assertEquals(emptyList<Consumable>(), note.consumables)
        assertNull(note.packaging)
        assertNull(note.inserts)
        assertNull(note.boxPackingList)
        assertFalse(note.parcelsEditable)
        assertEquals(NoteShipping(), note.shipping)
        assertEquals(NoteDocuments(), note.documents)
        assertEquals(ShipmentLabelKind.NONE, note.shipments.single().label.kind)
    }

    @Test
    fun `every packing and dispatch action posts to its own path`() {
        assertWrite(DeliveryNoteWrite.StartPacking, "start-packing", "{}")
        assertWrite(DeliveryNoteWrite.SetAsPacked, "set-as-packed", "{}")
        assertWrite(DeliveryNoteWrite.UndoPacking, "undo-packing", "{}")
        assertWrite(DeliveryNoteWrite.Unpack, "unpack", "{}")
        assertWrite(DeliveryNoteWrite.FinaliseAndDispatch, "finalise-and-dispatch", "{}")
        assertWrite(DeliveryNoteWrite.Finalise, "finalise", "{}")
        assertWrite(DeliveryNoteWrite.Dispatch, "dispatch", "{}")
        assertWrite(DeliveryNoteWrite.GetExternalShipment, "external-shipment", "{}")
        assertWrite(DeliveryNoteWrite.PrintInserts, "inserts/print", "{}")
        assertWrite(DeliveryNoteWrite.PrintLabel(shipmentId = 77), "shipments/77/print-label", "{}")
        assertEquals(WriteRequest(WriteMethod.Delete, "shipments/77", body = null), GoodsOutResponses.writeRequest(DeliveryNoteWrite.DeleteShipment(shipmentId = 77)))
        assertEquals("inserts/51/pdf", GoodsOutResponses.insertPdfPath(51))
    }

    @Test
    fun `parcels send the whole list, leaving out empty boxes`() {
        assertWrite(
            DeliveryNoteWrite.SetParcels(
                listOf(
                    ParcelWrite(weightKg = 1.2, dimensionsCm = listOf(30.0, 20.0, 10.0), boxes = 2),
                    ParcelWrite(weightKg = 3.0, dimensionsCm = listOf(40.5, 30.0, 20.0), boxes = null),
                ),
            ),
            "parcels",
            """{"parcels":[{"weight_kg":1.2,"dimensions_cm":[30,20,10],"boxes":2},{"weight_kg":3,"dimensions_cm":[40.5,30,20],"boxes":null}]}""",
        )
    }

    @Test
    fun `line packing posts to the line`() {
        assertWrite(DeliveryNoteWrite.Pack(lineId = 41001, quantity = null), "lines/41001/pack", """{"quantity":null}""")
        assertWrite(DeliveryNoteWrite.Pack(lineId = 41001, quantity = 1.5), "lines/41001/pack", """{"quantity":1.5}""")
        assertWrite(DeliveryNoteWrite.UnpackLine(lineId = 41001), "lines/41001/unpack", "{}")
        assertWrite(
            DeliveryNoteWrite.SetBoxes(lineId = 41001, boxes = listOf(LineBox(box = 1, quantity = 2.0), LineBox(box = 2, quantity = 0.5))),
            "lines/41001/boxes",
            """{"boxes":[{"box":1,"quantity":2},{"box":2,"quantity":0.5}]}""",
        )
    }

    @Test
    fun `a shipment sends the shipper, a trimmed tracking and the override flag`() {
        assertWrite(
            DeliveryNoteWrite.AddShipment(shipperId = 4, tracking = "  "),
            "shipments",
            """{"shipper_id":4,"tracking":null,"cost":null,"sender_name":null,"override_lock":false}""",
        )
        assertWrite(
            DeliveryNoteWrite.AddShipment(shipperId = 9, tracking = " TRK-1 ", overrideLock = true),
            "shipments",
            """{"shipper_id":9,"tracking":"TRK-1","cost":null,"sender_name":null,"override_lock":true}""",
        )
        assertWrite(
            DeliveryNoteWrite.RetryShipmentWithAddress(
                shipperId = 4,
                address = DeliveryAddressFields(contactName = " Jane ", companyName = "", email = "jane@example.com", phone = null, addressLine1 = "12 High St", postalCode = "S1 2AB", locality = "Sheffield", countryCode = "gb"),
            ),
            "shipments/retry-with-address",
            """{"shipper_id":4,"contact_name":"Jane","company_name":null,"email":"jane@example.com","phone":null,""" +
                """"address":{"address_line_1":"12 High St","address_line_2":null,"sorting_code":null,"postal_code":"S1 2AB","locality":"Sheffield",""" +
                """"dependent_locality":null,"administrative_area":null,"country_code":"GB"},"override_lock":false}""",
        )
    }

    @Test
    fun `a write can answer with a warning next to its message`() {
        val outcome = (GoodsOutResponses.parseWrite(200, """{"data":$noteJson,"result":{"status":"done","message":"Packed 1 x AWO-12","warning":"Print every insert before continuing"}}""") as GoodsOutResult.Loaded).value
        val quiet = (GoodsOutResponses.parseWrite(200, """{"data":$noteJson,"result":{"status":"done","message":"Shipment added.","warning":""}}""") as GoodsOutResult.Loaded).value

        assertEquals("Packed 1 x AWO-12", outcome.message)
        assertEquals("Print every insert before continuing", outcome.warning)
        assertNull(quiet.warning)
    }

    @Test
    fun `a refusal keeps every error under its key`() {
        val body = """
            {"message":"This delivery note is locked to APC.","errors":{"override_lock":["This delivery note is locked to APC. Confirm to use another shipper."],
            "address":{"postcode":["Invalid postcode"],"street":"Too long"},"others":"Carrier said no","empty":[]}}
        """.trimIndent()

        assertEquals(
            GoodsOutResult.Invalid(
                "This delivery note is locked to APC. Confirm to use another shipper.",
                mapOf(
                    "override_lock" to listOf("This delivery note is locked to APC. Confirm to use another shipper."),
                    "address" to listOf("Invalid postcode", "Too long"),
                    "others" to listOf("Carrier said no"),
                ),
            ),
            GoodsOutResponses.parseWrite(422, body),
        )
        assertEquals(
            GoodsOutResult.Invalid("The order has just been changed.", emptyMap()),
            GoodsOutResponses.parseWrite(409, """{"message":"The order has just been changed."}"""),
        )
    }

    @Test
    fun `scan to pack sends one SKO per scan and reads the item block`() {
        assertEquals(
            """{"barcode":"AWO-12","quantity":1,"delivery_note_item_id":null}""",
            GoodsOutResponses.scanPackBody(ScanPackRequest(barcode = "AWO-12", quantity = 1.0)).toString(),
        )
        assertEquals(
            """{"barcode":"AWO-12","quantity":null,"delivery_note_item_id":41001}""",
            GoodsOutResponses.scanPackBody(ScanPackRequest(barcode = "AWO-12", quantity = null, deliveryNoteItemId = 41001)).toString(),
        )

        val body = """
            {"data":$noteJson,"result":{"status":"packed","message":"Packed 1 x AWO-12",
            "item":{"id":41001,"code":"AWO-12","name":"Lavender oil","packed_in":12,"quantity_picked":3,"quantity_packed":2,"quantity_to_pack":1,"quantity_to_pack_label":"1","quantity_to_pack_fractional":null}}}
        """.trimIndent()
        val outcome = (GoodsOutResponses.parseScanPack(200, body) as GoodsOutResult.Loaded).value
        val wrong = (GoodsOutResponses.parseScanPack(200, """{"data":$noteJson,"result":{"status":"wrong_state","message":"This delivery note is not being packed.","item":null}}""") as GoodsOutResult.Loaded).value

        assertEquals(ScanPackStatus.PACKED, outcome.status)
        assertEquals(ScanPackItem(id = 41001, code = "AWO-12", name = "Lavender oil", quantityPicked = 3.0, quantityPacked = 2.0, quantityToPack = 1.0, quantityToPackLabel = "1"), outcome.item)
        assertEquals(9L, outcome.note.id)
        assertEquals(ScanPackStatus.WRONG_STATE, wrong.status)
        assertNull(wrong.item)
        assertEquals(GoodsOutResult.Forbidden("Scan to pack is off"), GoodsOutResponses.parseScanPack(403, """{"message":"Scan to pack is off"}"""))
    }

    @Test
    fun `the shippers come with the note's directive`() {
        val body = """
            {"data":[{"id":4,"code":"apc","name":"APC","trade_as":null,"api_shipper":"apc-gb","type":"api","needs_tracking":false,"is_locked_out":false},
            {"id":8,"code":"van","name":"Our van","trade_as":"Van","api_shipper":null,"type":"manual","needs_tracking":true,"is_locked_out":true}],
            "directive":{"locked_shipper_id":4,"preferred_shipper_id":4,"locked_by":"preferred_shipping","can_override_lock":true,"tracking_channel":"eBay"}}
        """.trimIndent()

        val list = (GoodsOutResponses.parseShippers(200, body) as GoodsOutResult.Loaded).value

        assertEquals(listOf(4L, 8L), list.shippers.map { it.id })
        assertTrue(list.shippers.first().isApi)
        assertFalse(list.shippers.last().isApi)
        assertTrue(list.shippers.last().needsTracking)
        assertTrue(list.shippers.last().isLockedOut)
        assertEquals(ShipperDirective(lockedShipperId = 4, preferredShipperId = 4, lockedBy = "preferred_shipping", canOverrideLock = true, trackingChannel = "eBay"), list.directive)
        assertEquals(ShipperDirective(), (GoodsOutResponses.parseShippers(200, """{"data":[]}""") as GoodsOutResult.Loaded).value.directive)
        assertEquals(GoodsOutResult.Forbidden(""), GoodsOutResponses.parseShippers(403, ""))
    }

    @Test
    fun `a refused document download keeps the server's reason`() {
        assertEquals(AttachmentDownload.Refused("This leaflet has nothing to print."), HrResponses.parseDownloadFailure(422, """{"message":"Invalid","errors":{"leaflet":["This leaflet has nothing to print."]}}"""))
        assertEquals(AttachmentDownload.Refused("No packing list for this delivery note."), HrResponses.parseDownloadFailure(404, """{"message":"No packing list for this delivery note."}"""))
    }

    private fun assertWrite(write: DeliveryNoteWrite, path: String, body: String) {
        val request = GoodsOutResponses.writeRequest(write)

        assertEquals(WriteMethod.Post, request.method)
        assertEquals(path, request.path)
        assertEquals(body, request.body.toString())
    }
}
