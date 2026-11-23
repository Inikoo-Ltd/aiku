import Foundation
import Testing
@testable import Aiku

private func decodePacking<T: Decodable>(_ type: T.Type, _ json: String) throws -> T {
    let decoder = JSONDecoder()
    decoder.keyDecodingStrategy = .convertFromSnakeCase
    return try decoder.decode(T.self, from: Data(json.utf8))
}

private func bodyText(_ action: GoodsOutNoteAction) throws -> String {
    String(decoding: try #require(Endpoint.goodsOutNoteAction(noteID: 700, action).body), as: UTF8.self)
}

private func packedNoteJSON(state: String = "packed", actions: String = #"[{"key": "finalise_and_dispatch", "label": "Finalise and Dispatch", "disabled_reason": "Print every insert before continuing"}, {"key": "add_shipment", "label": "Add shipment"}, {"key": "unpack", "label": "Unpack"}, {"key": "change_packer", "label": "Change packer"}, {"key": "edit_delivery_address", "label": "Edit delivery address"}]"#, shipments: String? = nil) -> String {
    let shipmentList = shipments ?? #"""
    [{"id": 41, "shipper": "GLS", "shipper_code": "gls", "tracking": "T1", "trackings": ["T1", "T2"], "tracking_urls": ["https://track/T1", null], "is_printable": true, "label": {"kind": "download", "url": "https://x/label"}, "can_delete": true},
     {"id": 42, "shipper": "Faire", "shipper_code": null, "tracking": null, "trackings": [], "tracking_urls": [], "is_printable": false, "label": {"kind": "external", "url": "https://faire/label.pdf"}, "can_delete": false}]
    """#
    return """
    {
      "id": 700, "reference": "AR700", "type": "order", "state": "\(state)", "state_label": "Packed", "warehouse_id": 4,
      "shop": {"id": 1, "name": "AW", "type": "b2b"}, "channel": "b2b",
      "delivery_address": {"lines": ["1 High St", "Bath"], "country_code": "GB",
        "fields": {"contact_name": "Jo", "company_name": null, "email": "jo@example.com", "phone": null, "address_line_1": "1 High St", "address_line_2": null, "sorting_code": null, "postal_code": "BA1 1AA", "locality": "Bath", "dependent_locality": null, "administrative_area": null, "country_code": "GB"}},
      "is_collection": false, "is_shipping_by_external": false, "has_gift_message": true,
      "summary": {"number_items": 2, "number_skos": 2, "number_units": 5, "estimated_weight_kg": 3.2},
      "picker": {"id": 7, "name": "Artha"}, "packer": {"id": 8, "name": "Kim"},
      "parcels": [{"weight_kg": 1.5, "dimensions_cm": [30, 20.4, 0], "boxes": 2}, {"weight_kg": 2, "dimensions_cm": [10, 10, 10], "boxes": null}],
      "parcels_editable": false,
      "consumables": [{"code": "BOX-M", "quantity": 2}, {"code": "", "quantity": 1}],
      "packaging": {"current": {"id": 3, "name": "Gift box"}},
      "inserts": {"leaflets": [{"id": 5, "name": "Spring leaflet", "type": "leaflet", "copies": 2, "state": "printed", "state_label": "Printed", "can_open_pdf": true},
                               {"id": 6, "name": "Catalogue", "type": "catalogue", "copies": 1, "state": "pending", "state_label": "Not printed", "can_open_pdf": false}],
                  "total": 2, "printed": 1, "all_printed": false, "label": "1 of 2 printed", "can_print_all": true},
      "box_packing_list": {"required": true, "number_boxes": 2, "missing_message": "Line SKO-2 has no box", "pdf_url": "https://x/packing-list.pdf"},
      "shipping": {"can_add": true, "can_print_labels": true, "locked_shipper_id": 38, "preferred_shipper_id": null, "locked_by": "preferred_shipping", "can_override_lock": true, "tracking_channel": null, "external": null},
      "notes": {"shipping": {"text": "Ring the bell", "editable": true}, "customer": {"text": null, "editable": false}, "warehouse": {"text": null, "editable": false}},
      "timeline": [], "shipments": \(shipmentList),
      "documents": {"delivery_note_pdf_url": "https://x/pdf", "gift_message_pdf_url": "https://x/gift.pdf"},
      "is_editable": true, "can_handle": true, "allow_actions": true, "can_edit_contact": false,
      "available_actions": \(actions),
      "settings": {"allow_scan_to_pick": true, "allow_scan_to_pack": true},
      "return_delivery_note": {"id": 9, "reference": "RET-9", "state_label": "In process"},
      "lines": [
        {"id": 21, "org_stock": {"code": "SKO-1", "name": "Bath bomb", "barcode": "5001"}, "quantity_required": 3, "quantity_picked": 3, "quantity_packed": 3, "quantity_packed_label": "3", "quantity_to_pack": 0, "is_done_packing": true, "boxes": [{"box": 1, "quantity": 2}, {"box": 2, "quantity": 1}]},
        {"id": 22, "org_stock": {"code": "SKO-2", "name": "Candle"}, "quantity_required": 2, "quantity_picked": 2, "quantity_packed": 0.5, "quantity_to_pack": 1.5, "is_done_packing": false, "boxes": [], "note_to_packers": "Wrap it"}
      ]
    }
    """
}

private actor PackingCalls {
    var writes: [GoodsOutNoteAction] = []
    var documents: [GoodsOutDocument] = []

    func record(_ action: GoodsOutNoteAction) { writes.append(action) }
    func record(_ document: GoodsOutDocument) { documents.append(document) }
}

private func packingService(calls: PackingCalls, write: @escaping @Sendable (GoodsOutNoteAction) throws -> String) -> GoodsOutService {
    GoodsOutService(
        deliveryNotes: { _, _ in throw APIError.offline },
        counts: { _, _ in throw APIError.offline },
        deliveryNote: { _ in try decodePacking(GoodsOutDeliveryNoteDTO.self, packedNoteJSON()) },
        perform: { _, _ in throw APIError.offline },
        trolleys: { _ in [] },
        pickedBays: { _ in [] },
        pickers: { _ in [] },
        dispatchPersonnel: { _, _ in [] },
        write: { _, action in
            await calls.record(action)
            return try decodePacking(GoodsOutActionResponseDTO.self, try write(action))
        },
        shippers: { _ in
            try decodePacking(GoodsOutShippersDTO.self, #"""
            {"data": [{"id": 38, "code": "es-gls", "name": "General Logistics Systems", "api_shipper": "gls-es", "type": "api", "needs_tracking": false, "is_locked_out": false},
                      {"id": 39, "code": "ce", "name": "Correos Express", "api_shipper": null, "type": "manual", "needs_tracking": true, "is_locked_out": false},
                      {"id": 40, "code": "rl", "name": "Rhenus", "api_shipper": null, "type": "manual", "needs_tracking": false, "is_locked_out": true}],
             "directive": {"locked_shipper_id": 38, "preferred_shipper_id": null, "locked_by": "preferred_shipping", "can_override_lock": true, "tracking_channel": "Shopify"}}
            """#)
        },
        document: { _, document in
            await calls.record(document)
            return document == .giftMessage ? Data("<html>".utf8) : Data("%PDF-1.4 test".utf8)
        }
    )
}

private func response(_ note: String = packedNoteJSON(), status: String = "done", message: String = "Done.", item: String = "null", warning: String = "null") -> String {
    #"{"data": \#(note), "result": {"status": "\#(status)", "message": "\#(message)", "warning": \#(warning), "item": \#(item)}}"#
}

@MainActor
private func packingStore(calls: PackingCalls, write: @escaping @Sendable (GoodsOutNoteAction) throws -> String = { _ in response() }) throws -> GoodsOutStore {
    let store = GoodsOutStore(service: packingService(calls: calls, write: write), now: { GoodsOutDateFormat.date(day: 11, hour: 10, minute: 0) })
    store.configure(
        userID: 7,
        warehouse: try decodePacking(MobileWarehouse.self, #"{"id": 4, "code": "PAR", "name": "Paris", "organisation": {"id": 1, "name": "AW"}, "can": {"view_locations": true, "edit_locations": false, "view_goods_out": true, "pick_and_pack": true, "supervise_goods_out": false}}"#),
        currentUser: "Artha"
    )
    store.store(try decodePacking(GoodsOutDeliveryNoteDTO.self, packedNoteJSON()))
    return store
}

struct GoodsOutPackingTests {
    @Test func packedNoteMapsParcelsShipmentsInsertsAndLines() throws {
        let dto = try decodePacking(GoodsOutDeliveryNoteDTO.self, packedNoteJSON())
        let note = GoodsOutMapper.note(from: dto, currentUserID: 7, currentUser: "Artha", now: Date())

        #expect(note.parcels.map(\.number) == [1, 2])
        #expect(note.parcels[0].length == 30)
        #expect(note.parcels[0].width == 20)
        #expect(note.parcels[0].height == nil)
        #expect(note.parcels[0].boxes == 2)
        #expect(note.parcels[1].hasDimensions)
        #expect(note.packingMaterials == ["2 × BOX-M"])

        #expect(note.shipments.map(\.id) == [41, 42])
        #expect(note.shipments[0].trackingNumbers == ["T1", "T2"])
        #expect(note.shipments[0].trackingURL(at: 0) == URL(string: "https://track/T1"))
        #expect(note.shipments[0].trackingURL(at: 1) == nil)
        #expect(note.shipments[0].label == .download)
        #expect(note.shipments[0].canDelete)
        #expect(note.shipments[1].label == .external(try #require(URL(string: "https://faire/label.pdf"))))
        #expect(note.shipments[1].trackingNumbers.isEmpty)

        #expect(note.leaflets.map(\.title) == ["Spring leaflet", "Catalogue"])
        #expect(note.leaflets.map(\.isPrinted) == [true, false])
        #expect(note.leaflets[0].apiID == 5)
        #expect(note.leaflets[0].canOpenPdf)

        let details = try #require(note.packingDetails)
        #expect(details.packerName == "Kim")
        #expect(details.packerID == 8)
        #expect(details.packagingName == "Gift box")
        #expect(details.canPrintAllInserts)
        #expect(details.showsInserts == false)
        #expect(details.hasBoxPackingList)
        #expect(details.missingBoxesMessage == "Line SKO-2 has no box")
        #expect(details.directive.chosenShipperID == 38)
        #expect(details.canEdit(.shipping))
        #expect(!details.canEdit(.warehouse))
        #expect(details.address.postalCode == "BA1 1AA")
        #expect(details.returnReference == "RET-9")
        #expect(note.documents == [.deliveryNote, .packingList, .giftMessage])

        let packed = try #require(note.lines.first { $0.apiID == 21 })
        #expect(packed.isFullyPacked)
        #expect(packed.packed == 3)
        #expect(packed.packedInto == [1: 2, 2: 1])
        #expect(packed.parcelText == "Boxes 1, 2")
        let half = try #require(note.lines.first { $0.apiID == 22 })
        #expect(!half.isFullyPacked)
        #expect(half.packRemaining == 2)
        #expect(half.packing?.toPackText == "1.5")
        #expect(half.packing?.noteToPackers == "Wrap it")
        #expect(half.parcelText == "0.5 of 2 packed")
        #expect(note.nextLineToPack?.apiID == 22)
    }

    @Test func footerFollowsTheAvailableActionsLikeAndroid() throws {
        let packed = GoodsOutMapper.note(from: try decodePacking(GoodsOutDeliveryNoteDTO.self, packedNoteJSON()), currentUserID: 7, currentUser: "Artha", now: Date())
        #expect(packed.remoteFooterItems.count == 1)
        guard case .notice(let blocked) = packed.remoteFooterItems.first else {
            Issue.record("Finalise and Dispatch should be a notice")
            return
        }
        #expect(blocked.disabledReason == "Print every insert before continuing")
        #expect(packed.addAnotherShipment?.key == "add_shipment")
        #expect(packed.undoStep == .unpack)

        let empty = GoodsOutMapper.note(
            from: try decodePacking(GoodsOutDeliveryNoteDTO.self, packedNoteJSON(actions: #"[{"key": "add_shipment", "label": "Add shipment"}, {"key": "finalise", "label": "Finalise"}, {"key": "take_over", "label": "Unlock to pack"}]"#, shipments: "[]")),
            currentUserID: 7, currentUser: "Artha", now: Date()
        )
        #expect(empty.remoteFooterItems == [
            .button(GoodsOutAvailableAction(key: "add_shipment", label: "Add shipment"), isPrimary: true),
            .button(GoodsOutAvailableAction(key: "finalise", label: "Finalise"), isPrimary: false)
        ])
        #expect(empty.addAnotherShipment == nil)

        let packing = GoodsOutMapper.note(
            from: try decodePacking(GoodsOutDeliveryNoteDTO.self, packedNoteJSON(state: "packing", actions: #"[{"key": "set_as_packed", "label": "Set as packed"}, {"key": "undo_packing", "label": "Undo packing"}]"#)),
            currentUserID: 7, currentUser: "Artha", now: Date()
        )
        #expect(packing.canScanToPack)
        #expect(packing.canPackLines)
        #expect(packing.packingDetails?.showsInserts == true)
        #expect(packing.undoStep == .packing)
        #expect(packing.remoteFooterItems == [.button(GoodsOutAvailableAction(key: "set_as_packed", label: "Set as packed"), isPrimary: true)])
    }

    @Test func packingActionsSendTheApiFields() throws {
        #expect(Endpoint.goodsOutNoteAction(noteID: 700, .startPacking).path == "mobile/delivery-notes/700/start-packing")
        #expect(try bodyText(.parcels([GoodsOutParcelInput(weightKg: 1.5, dimensionsCm: [30, 20, 10], boxes: nil)])) == #"{"parcels":[{"boxes":null,"dimensions_cm":[30,20,10],"weight_kg":1.5}]}"#)
        #expect(try bodyText(.scanPack(barcode: "5001")) == #"{"barcode":"5001","delivery_note_item_id":null,"quantity":1}"#)
        #expect(Endpoint.goodsOutNoteAction(noteID: 700, .pack(line: 22, quantity: nil)).path == "mobile/delivery-notes/700/lines/22/pack")
        #expect(try bodyText(.pack(line: 22, quantity: nil, clientRequestID: "request-1")) == #"{"client_request_id":"request-1","quantity":null}"#)
        #expect(Endpoint.goodsOutNoteAction(noteID: 700, .unpack(line: 22)).path == "mobile/delivery-notes/700/lines/22/unpack")
        #expect(try bodyText(.boxes(line: 22, [GoodsOutLineBoxInput(box: 1, quantity: 0.5)])) == #"{"boxes":[{"box":1,"quantity":0.5}]}"#)
        #expect(try bodyText(.addShipment(shipperID: 39, tracking: " TRK1 ")) == #"{"cost":null,"override_lock":false,"sender_name":null,"shipper_id":39,"tracking":"TRK1"}"#)
        var form = GoodsOutAddressForm()
        form.contactName = "Jo"
        form.addressLine1 = "1 High St"
        form.countryCode = "gb"
        #expect(try bodyText(.retryShipment(shipperID: 38, address: form, overrideLock: true)) == #"{"address":{"address_line_1":"1 High St","address_line_2":null,"administrative_area":null,"country_code":"GB","dependent_locality":null,"locality":null,"postal_code":null,"sorting_code":null},"company_name":null,"contact_name":"Jo","email":null,"override_lock":true,"phone":null,"shipper_id":38}"#)
        #expect(try bodyText(.editAddress(form, includesContact: false)).hasPrefix(#"{"address":{"#))
        #expect(!(try bodyText(.editAddress(form, includesContact: false)).contains("contact_name")))
        let delete = Endpoint.goodsOutNoteAction(noteID: 700, .deleteShipment(41))
        #expect(delete.method == .delete)
        #expect(delete.path == "mobile/delivery-notes/700/shipments/41")
        #expect(Endpoint.goodsOutNoteAction(noteID: 700, .printLabel(41)).path == "mobile/delivery-notes/700/shipments/41/print-label")
        #expect(try bodyText(.cancel(createReturn: false)) == #"{"create_return":false}"#)
        #expect(try bodyText(.setNote(.shipping, text: " Ring ")) == #"{"field":"shipping","text":"Ring"}"#)
        #expect(try bodyText(.setPacker(userID: 8)) == #"{"user_id":8}"#)

        #expect(Endpoint.goodsOutDocument(noteID: 700, .deliveryNote).path == "mobile/delivery-notes/700/pdf")
        #expect(Endpoint.goodsOutDocument(noteID: 700, .packingList).path == "mobile/delivery-notes/700/packing-list.pdf")
        #expect(Endpoint.goodsOutDocument(noteID: 700, .giftMessage).path == "mobile/delivery-notes/700/gift-message.pdf")
        #expect(Endpoint.goodsOutDocument(noteID: 700, .label(shipmentID: 41)).path == "mobile/delivery-notes/700/shipments/41/label")
        let insert = Endpoint.goodsOutDocument(noteID: 700, .insert(leafletID: 5))
        #expect(insert.path == "mobile/delivery-notes/700/inserts/5/pdf")
        #expect(insert.method == .post)
        #expect(Endpoint.goodsOutShippers(noteID: 700).path == "mobile/delivery-notes/700/shippers")
        #expect(GoodsOutDocument.label(shipmentID: 41).fileName(reference: "AR/700") == "AR-700-label-41.pdf")
    }

    @Test func shipmentRefusalsLeadToTheRightFollowUp() {
        let add = GoodsOutShipmentRequest.add(shipperID: 39, tracking: "T", overrideLock: false)
        #expect(GoodsOutShipmentFollowUp.after(add, errors: ["override_lock": "Locked to GLS."]) == .confirmOverride(message: "Locked to GLS.", request: add))
        #expect(GoodsOutShipmentFollowUp.after(add, errors: ["address": "Postcode is wrong."]) == .fixAddress(message: "Postcode is wrong.", shipperID: 39, overrideLock: false))
        #expect(GoodsOutShipmentFollowUp.after(add, errors: ["tracking": "Enter it."]) == nil)
        #expect(add.overridingLock == .add(shipperID: 39, tracking: "T", overrideLock: true))

        let retry = GoodsOutShipmentRequest.retry(shipperID: 38, address: GoodsOutAddressForm(), overrideLock: true)
        #expect(GoodsOutShipmentFollowUp.after(retry, errors: ["message": "Carrier said no."]) == .fixAddress(message: "Carrier said no.", shipperID: 38, overrideLock: true))
        #expect(GoodsOutShipmentFollowUp.after(retry, errors: [:]) == nil)
    }

    @MainActor
    @Test func writeKeepsTheServerFieldErrorsAndWarnings() async throws {
        let calls = PackingCalls()
        let store = try packingStore(calls: calls) { action in
            if action.path == "shipments" {
                throw FormValidationError(message: "The carrier refused the address.", fields: ["address": "The carrier refused the address."])
            }
            return response(message: "Line packed.", warning: #""Not every line is packed yet.""#)
        }
        let refused = await store.write(.addShipment(shipperID: 38, tracking: nil), on: "AR700")
        #expect(refused.failureMessage == "The carrier refused the address.")
        #expect(refused.fieldErrors == ["address": "The carrier refused the address."])
        #expect(store.isWorking(on: "AR700") == false)

        let packed = await store.write(.pack(line: 22, quantity: 1), on: "AR700")
        #expect(packed.outcome == .done(message: "Line packed."))
        #expect(packed.warning == "Not every line is packed yet.")
        #expect(await calls.writes.count == 2)
    }

    @MainActor
    @Test func scanToPackSaysWhatHappened() async throws {
        let calls = PackingCalls()
        let store = try packingStore(calls: calls) { action in
            if case .string("5001") = action.body?["barcode"] {
                return response(status: "packed", message: "SKO-1 +1", item: #"{"id": 21, "code": "SKO-1", "quantity_to_pack": 0}"#)
            }
            return response(status: "not_found", message: "Not on this delivery note")
        }
        #expect(await store.scanToPack("AR700", barcode: " 5001 ") == .packed(code: "SKO-1", message: "SKO-1 +1", lineDone: true))
        #expect(store.scanMisses == 0)
        #expect(await store.scanToPack("AR700", barcode: "999") == .refused(message: "Not on this delivery note"))
        #expect(store.scanMisses == 1)
        #expect(await store.scanToPack("AR700", barcode: "  ") == .refused(message: "Scan a barcode first."))
        #expect(store.scanMisses == 1)
        #expect(await calls.writes.first == .scanPack(barcode: "5001"))
    }

    @MainActor
    @Test func shippersSplitIntoApiAndOtherAndKeepTheLock() async throws {
        let store = try packingStore(calls: PackingCalls())
        let list = try #require(try? await store.shipperList(for: "AR700").get())
        #expect(list.apiShippers.map(\.id) == [38])
        #expect(list.manualShippers.map(\.id) == [39])
        #expect(list.defaultChoice()?.id == 38)
        #expect(list.badge(for: list.apiShippers[0]) == "Locked")
        #expect(list.manualShippers[0].detail == "No API · tracking number required")
        #expect(list.lockNotice?.title == "Shipping rules lock this delivery note to General Logistics Systems")
        #expect(list.lockNotice?.message != nil)
    }

    @MainActor
    @Test func documentsDownloadToAPdfFile() async throws {
        let calls = PackingCalls()
        let store = try packingStore(calls: calls)
        let folder = FileManager.default.temporaryDirectory.appending(path: "goodsout-tests-\(UUID().uuidString)")
        defer { try? FileManager.default.removeItem(at: folder) }

        let file = try await store.download(.label(shipmentID: 41), of: "AR700", into: folder).get()
        #expect(file.lastPathComponent == "AR700-label-41.pdf")
        #expect(try Data(contentsOf: file).starts(with: Data("%PDF".utf8)))

        let notPdf = await store.download(.giftMessage, of: "AR700", into: folder)
        #expect(throws: GoodsOutProblem.self) { try notPdf.get() }
        #expect(await calls.documents == [.label(shipmentID: 41), .giftMessage])
    }

    @Test func parcelAndBoxDraftsCheckLikeTheApi() {
        let good = GoodsOutParcelDraft(id: 0, weight: "1,5", length: "30", width: "20", height: "10", boxes: "")
        #expect(good.input == GoodsOutParcelInput(weightKg: 1.5, dimensionsCm: [30, 20, 10], boxes: nil))
        #expect(GoodsOutParcelDraft(id: 1, weight: "", length: "1", width: "1", height: "1").problem == "Enter the weight.")
        #expect(GoodsOutParcelDraft(id: 1, weight: "2", length: "1", width: "", height: "1").problem == "Enter the length, width and height.")
        #expect(GoodsOutParcelDraft(id: 1, weight: "2", length: "1", width: "1", height: "1001").problem == "Each size must be more than 0 and at most 1000 cm.")
        #expect(GoodsOutParcelDraft(id: 1, weight: "2", length: "1", width: "1", height: "1", boxes: "0").problem == "Boxes must be a whole number of 1 or more.")
        #expect((try? GoodsOutParcelDraft.inputs([]).get()) == nil)
        #expect(throws: GoodsOutProblem(message: "Parcel 2: Enter the weight.")) {
            try GoodsOutParcelDraft.inputs([good, GoodsOutParcelDraft(id: 1, length: "1", width: "1", height: "1")]).get()
        }

        let boxes = [GoodsOutBoxDraft(id: 0, box: "1", quantity: "1"), GoodsOutBoxDraft(id: 1, box: "2", quantity: "0.5")]
        #expect((try? GoodsOutBoxDraft.inputs(boxes, picked: 2).get()) == [GoodsOutLineBoxInput(box: 1, quantity: 1), GoodsOutLineBoxInput(box: 2, quantity: 0.5)])
        #expect(throws: GoodsOutProblem(message: "The boxes hold more than the 1 picked.")) { try GoodsOutBoxDraft.inputs(boxes, picked: 1).get() }
        #expect(throws: GoodsOutProblem(message: "Each box number must be from 1 to 999.")) { try GoodsOutBoxDraft.inputs([GoodsOutBoxDraft(id: 0, box: "0", quantity: "1")], picked: 1).get() }
    }

    @Test func aParcelCanBeRemovedOnlyWhileAnotherIsLeftAsOnAndroid() {
        #expect(GoodsOutRemoteParcelsEditor.canRemoveParcel(parcelCount: 1) == false)
        #expect(GoodsOutRemoteParcelsEditor.canRemoveParcel(parcelCount: 2))
    }
}
