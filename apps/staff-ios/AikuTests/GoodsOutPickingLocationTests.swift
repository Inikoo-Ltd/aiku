import Foundation
import Testing
@testable import Aiku

private actor PickingLocationCalls {
    var orgStockLoads: [(warehouseID: Int, orgStockID: Int)] = []
    var updates: [GoodsOutPickingLocationUpdate] = []
    var noteLoads: [Int] = []

    func recordOrgStockLoad(_ warehouseID: Int, _ orgStockID: Int) { orgStockLoads.append((warehouseID, orgStockID)) }
    func recordUpdate(_ linkID: Int, _ body: MobileLocationOrgStockPickingDefaults) { updates.append(GoodsOutPickingLocationUpdate(linkID: linkID, body: body)) }
    func recordNoteLoad(_ id: Int) { noteLoads.append(id) }
}

private func decode<T: Decodable>(_ type: T.Type, _ json: String) throws -> T {
    let decoder = JSONDecoder()
    decoder.keyDecodingStrategy = .convertFromSnakeCase
    return try decoder.decode(T.self, from: Data(json.utf8))
}

private let pickingNoteJSON = """
{
  "id": 501, "slug": "ar24811", "reference": "AR24811", "type": "order", "state": "handling", "state_label": "Handling",
  "warehouse_id": 4, "shop": {"id": 1, "name": "AW Wholesale", "type": "b2b"}, "channel": "b2b", "platform": null,
  "order": {"id": 9, "reference": "AR24811"},
  "customer": {"name": "Bath Bombs Ltd", "reference": "C-0042", "contact_name": "Jo", "company_name": "Bath Bombs Ltd", "email": "jo@example.com", "phone": null, "is_vip": true},
  "delivery_address": {"lines": ["1 High St", "Bath BA1 1AA", "United Kingdom"], "country_code": "GB",
    "fields": {"contact_name": "Jo Smith", "company_name": "Bath Bombs Ltd", "email": "jo@example.com", "phone": "0123", "address_line_1": "1 High St", "address_line_2": null, "sorting_code": null, "postal_code": "BA1 1AA", "locality": "Bath", "dependent_locality": null, "administrative_area": null, "country_code": "GB"}},
  "is_collection": false, "is_shipping_by_external": false, "is_premium_dispatch": false, "is_cash_on_delivery": false,
  "has_extra_packing": false, "has_gift_message": false, "is_export": false,
  "summary": {"number_items": 3, "number_skos": 4.0, "number_units": 9, "estimated_weight_kg": 2.5, "estimated_picking_minutes": 12.4, "estimated_packing_minutes": 0},
  "picker": {"id": 7, "name": "Artha"}, "packer": null,
  "trolleys": [{"id": 3, "name": "T-03"}], "picked_bays": [],
  "parcels": [{"weight_kg": 1.5, "dimensions_cm": [30, 20, 10], "boxes": 1}], "parcels_editable": false,
  "consumables": [], "packaging": {"current": null}, "inserts": null, "box_packing_list": null,
  "shipping": {"can_add": false, "can_print_labels": true, "locked_shipper_id": null, "preferred_shipper_id": null, "locked_by": null, "can_override_lock": false, "tracking_channel": null, "external": null},
  "notes": {"shipping": {"text": "", "editable": false}, "customer": {"text": "Leave at the back", "editable": false}, "warehouse": {"text": "", "editable": true}},
  "timeline": [{"state": "unassigned", "label": "Created", "at": "2026-11-10T08:00:00+00:00"}, {"state": "handling", "label": "Handling", "at": "2026-11-10T09:30:00+00:00"}, {"state": "picked", "label": "Picked", "at": null}],
  "shipments": [{"id": 1, "shipper": "DPD", "shipper_code": "dpd", "tracking": "T1", "trackings": ["T1", null], "tracking_urls": [null], "is_printable": true, "label": {"kind": "download", "url": "https://x"}, "can_delete": true}],
  "documents": {"delivery_note_pdf_url": "https://x/pdf", "gift_message_pdf_url": null},
  "waiting_warehouse_count": 1, "waiting_crm_count": 0,
  "is_editable": true, "can_handle": true, "is_taken_over": false, "allow_actions": true, "can_edit_contact": true,
  "available_actions": [{"key": "change_trolley", "label": "Change trolley"}, {"key": "change_picker", "label": "Change picker"}, {"key": "cancel", "label": "Cancel delivery note", "disabled_reason": "Not now"}],
  "settings": {"allow_scan_to_pick": true, "allow_waiting": true, "allow_picker_set_not_picked": false, "allow_picker_choose_location": false, "allow_scan_to_pack": false},
  "incomplete_sets": [], "read_only_notice": null, "warning": "", "return_delivery_note": null,
  "line_counts": {"all": 3, "todo": 1, "done": 2},
  "lines": [
    {"id": 11, "state": "handling", "state_label": "Handling", "org_stock": {"id": 1, "code": "SKO-1", "name": "Bath bomb", "barcode": "500001", "packed_in": 6, "image_url": null},
     "quantity_required": 6, "quantity_picked": 2, "quantity_to_pick": 4, "quantity_to_pick_label": "4", "quantity_not_picked": 0, "quantity_packed": 0, "quantity_to_pack": 0,
     "quantity_waiting_warehouse": 0, "quantity_waiting_crm": 0, "is_handled": false, "is_picked": false, "is_done_packing": false, "is_packed": false,
     "locations": [{"location_org_stock_id": 90, "location_id": 5, "code": "A-01", "stock": 0, "stock_label": "0", "type": "picking", "quantity_picked": 0, "picking_id": null},
                   {"location_org_stock_id": 91, "location_id": 6, "code": "B-02", "stock": 12, "stock_label": "12", "type": "storing", "quantity_picked": 2, "picking_id": 300}],
     "pickings": [{"id": 300, "type": "pick", "location_org_stock_id": 91, "location_code": "B-02", "quantity": 2, "quantity_label": "2", "not_picked_reason": null, "not_picked_reason_label": null}],
     "boxes": [], "warehouse_area": {"id": 1, "name": "Area A"}, "note_to_pickers": "Fragile", "note_to_packers": null, "un_numbers": ["UN1", "UN2"], "replacement_reason": null, "indivisible_set": false},
    {"id": 12, "state": "handling", "org_stock": {"id": 2, "code": "SKO-2", "name": "Candle"}, "quantity_required": 2, "quantity_picked": 0, "quantity_to_pick": 0,
     "quantity_not_picked": 2, "quantity_waiting_warehouse": 0, "quantity_waiting_crm": 0, "locations": [],
     "pickings": [{"id": 301, "type": "not-pick", "quantity": 2, "not_picked_reason": "cancelled-by-customer", "not_picked_reason_label": "Cancelled by customer"}]},
    {"id": 13, "state": "handling", "org_stock": {"id": 3, "code": "SKO-3", "name": "Incense"}, "quantity_required": 1, "quantity_picked": 0, "quantity_to_pick": 1,
     "quantity_not_picked": 0, "quantity_waiting_warehouse": 1, "quantity_waiting_crm": 0, "locations": [], "pickings": []}
  ]
}
"""

private let skoDetailJSON = """
{"id": 1, "code": "SKO-1", "name": "Bath bomb", "state": "active",
 "picking_location": {"id": 6, "code": "B-02"}, "picking_dropshipping_location": {"id": 5, "code": "A-01"},
 "locations": [
   {"location_org_stock_id": 90, "type": "picking", "quantity": 0, "quantity_label": "0", "is_default_wholesale": false, "is_default_dropshipping": false, "location": {"id": 5, "code": "A-01"}},
   {"location_org_stock_id": 91, "type": "storing", "quantity": 12, "quantity_label": "12", "is_default_wholesale": true, "is_default_dropshipping": false, "location": {"id": 6, "code": "B-02"}}
 ],
 "can": {"edit_stocks": true, "edit_locations": false}}
"""

private let warehouseJSON = """
{"id": 4, "code": "PAR", "name": "Paris", "organisation": {"id": 1, "name": "AW"},
 "can": {"view_locations": true, "edit_locations": false, "view_goods_out": true, "pick_and_pack": true, "supervise_goods_out": false, "move_stock": true}}
"""

private func pickingService(calls: PickingLocationCalls, saveError: APIError? = nil, detailJSON: String = skoDetailJSON) -> GoodsOutService {
    GoodsOutService(
        deliveryNotes: { _, _ in throw APIError.unexpectedBody },
        counts: { _, _ in throw APIError.unexpectedBody },
        deliveryNote: { id in
            await calls.recordNoteLoad(id)
            return try decode(GoodsOutDeliveryNoteDTO.self, pickingNoteJSON)
        },
        perform: { _, _ in throw APIError.unexpectedBody },
        trolleys: { _ in [] },
        pickedBays: { _ in [] },
        pickers: { _ in [] },
        dispatchPersonnel: { _, _ in [] },
        orgStock: { warehouseID, orgStockID in
            await calls.recordOrgStockLoad(warehouseID, orgStockID)
            return try decode(MobileOrgStockDetail.self, detailJSON)
        },
        setPickingDefaults: { linkID, body in
            await calls.recordUpdate(linkID, body)
            if let saveError {
                throw saveError
            }
        }
    )
}

@MainActor
private func pickingStore(calls: PickingLocationCalls, saveError: APIError? = nil) async throws -> GoodsOutStore {
    let store = GoodsOutStore(service: pickingService(calls: calls, saveError: saveError))
    store.configure(userID: 7, warehouse: try decode(MobileWarehouse.self, warehouseJSON), currentUser: "Artha")
    await store.loadNote(id: 501)
    return store
}

@MainActor
private func skoLine(in store: GoodsOutStore) throws -> GoodsOutLine {
    try #require(store.note("AR24811")?.lines.first { $0.apiID == 11 })
}

@MainActor
struct GoodsOutPickingLocationTests {
    private func client() -> APIClient {
        APIClient(
            configuration: AppConfiguration(apiBaseURL: URL(string: "https://app.aiku.test/app/api")!, mobileAppKey: "key"),
            tokenProvider: { "1|tok" }
        )
    }

    private func mapped(_ json: String = skoDetailJSON, warehouseCanEditStock: Bool = true) throws -> GoodsOutPickingLocations {
        GoodsOutPickingLocations(detail: try decode(MobileOrgStockDetail.self, json), warehouseCanEditStock: warehouseCanEditStock)
    }

    @Test func savingADefaultPatchesTheSkoInLocationWithOnlyThePriorityFlag() throws {
        let request = client().makeRequest(for: .mobileLocationOrgStockPickingDefaults(linkID: 90, defaults: MobileLocationOrgStockPickingDefaults(setAsPriorityWholesale: true)))
        let body = try JSONSerialization.jsonObject(with: try #require(request.httpBody)) as? [String: Bool]

        #expect(request.httpMethod == "PATCH")
        #expect(request.url?.path == "/app/api/mobile/location-org-stocks/90")
        #expect(body == ["set_as_priority_wholesale": true])
    }

    @Test func bothChannelsOnOneLocationGoInOneRequest() throws {
        let request = client().makeRequest(for: .mobileLocationOrgStockPickingDefaults(linkID: 90, defaults: MobileLocationOrgStockPickingDefaults(setAsPriorityWholesale: true, setAsPriorityDropshipping: true)))
        let body = try JSONSerialization.jsonObject(with: try #require(request.httpBody)) as? [String: Bool]

        #expect(body == ["set_as_priority_wholesale": true, "set_as_priority_dropshipping": true])
    }

    @Test func theCurrentDefaultIsChosenPerChannelFallingBackToThePickingLocation() throws {
        let locations = try mapped()

        #expect(locations.current == GoodsOutPickingDefaults(wholesale: 91, dropshipping: 90))
        #expect(locations.selection(for: .wholesale, chosen: GoodsOutPickingDefaults()) == 91)
        #expect(locations.selection(for: .dropshipping, chosen: GoodsOutPickingDefaults(dropshipping: 91)) == 91)
        #expect(locations.options.map(\.code) == ["A-01", "B-02"])
        #expect(locations.options.map(\.stockText) == ["Empty", "12 in stock"])
        #expect(locations.canEdit)
    }

    @Test func withoutStockEditRightsOnlyTheChosenLocationIsShown() throws {
        let readOnly = try mapped(skoDetailJSON.replacingOccurrences(of: #""edit_stocks": true"#, with: #""edit_stocks": false"#))

        #expect(readOnly.canEdit == false)
        #expect(readOnly.visibleOptions(for: .wholesale, chosen: GoodsOutPickingDefaults()).map(\.linkID) == [91])
        #expect(readOnly.visibleOptions(for: .dropshipping, chosen: GoodsOutPickingDefaults()).map(\.linkID) == [90])
    }

    @Test func theWarehouseRightDecidesWhenTheSkoSendsNoAbilities() throws {
        let withoutAbilities = skoDetailJSON.replacingOccurrences(of: #""can": {"edit_stocks": true, "edit_locations": false}"#, with: #""can": null"#)

        #expect(try mapped(withoutAbilities, warehouseCanEditStock: false).canEdit == false)
        #expect(try mapped(withoutAbilities, warehouseCanEditStock: true).canEdit)
    }

    @Test func onlyChangedDefaultsAreSent() throws {
        let locations = try mapped()

        #expect(locations.updates(to: GoodsOutPickingDefaults(wholesale: 91)).isEmpty)
        #expect(locations.updates(to: GoodsOutPickingDefaults(wholesale: 90, dropshipping: 90)) == [
            GoodsOutPickingLocationUpdate(linkID: 90, body: MobileLocationOrgStockPickingDefaults(setAsPriorityWholesale: true, setAsPriorityDropshipping: true))
        ])
        #expect(locations.updates(to: GoodsOutPickingDefaults(wholesale: 90, dropshipping: 91)) == [
            GoodsOutPickingLocationUpdate(linkID: 90, body: MobileLocationOrgStockPickingDefaults(setAsPriorityWholesale: true)),
            GoodsOutPickingLocationUpdate(linkID: 91, body: MobileLocationOrgStockPickingDefaults(setAsPriorityDropshipping: true))
        ])
        #expect(locations.updates(to: GoodsOutPickingDefaults(wholesale: 999)).isEmpty)
    }

    @Test func theLineKnowsItsSkoAndStockEditFollowsTheWarehouse() async throws {
        let store = try await pickingStore(calls: PickingLocationCalls())

        #expect(try skoLine(in: store).orgStockID == 1)
        #expect(store.rights.canEditStock)
    }

    @Test func theSheetLoadsTheSkoOfTheLineInThisWarehouse() async throws {
        let calls = PickingLocationCalls()
        let store = try await pickingStore(calls: calls)

        let loaded = try await store.pickingLocations(for: try skoLine(in: store)).get()

        #expect(loaded.code == "SKO-1")
        #expect(loaded.current == GoodsOutPickingDefaults(wholesale: 91, dropshipping: 90))
        let loads = await calls.orgStockLoads
        #expect(loads.map { $0.warehouseID } == [4])
        #expect(loads.map { $0.orgStockID } == [1])
    }

    @Test func savingSendsTheChangeAndReloadsTheDeliveryNote() async throws {
        let calls = PickingLocationCalls()
        let store = try await pickingStore(calls: calls)
        let loaded = try await store.pickingLocations(for: try skoLine(in: store)).get()

        let outcome = await store.savePickingLocations(loaded, chosen: GoodsOutPickingDefaults(wholesale: 90), on: "AR24811")

        #expect(outcome == .saved)
        #expect(await calls.updates == [GoodsOutPickingLocationUpdate(linkID: 90, body: MobileLocationOrgStockPickingDefaults(setAsPriorityWholesale: true))])
        #expect(await calls.noteLoads == [501, 501])
    }

    @Test func nothingIsSentWhenTheDefaultsAreUnchanged() async throws {
        let calls = PickingLocationCalls()
        let store = try await pickingStore(calls: calls)
        let loaded = try await store.pickingLocations(for: try skoLine(in: store)).get()

        let outcome = await store.savePickingLocations(loaded, chosen: GoodsOutPickingDefaults(wholesale: 91), on: "AR24811")

        #expect(outcome == .unchanged)
        #expect(await calls.updates.isEmpty)
        #expect(await calls.noteLoads == [501])
    }

    @Test func aRefusalKeepsTheServerMessageAndDoesNotReload() async throws {
        let calls = PickingLocationCalls()
        let store = try await pickingStore(calls: calls, saveError: .rejected(message: "You can’t edit stock in Paris."))
        let loaded = try await store.pickingLocations(for: try skoLine(in: store)).get()

        let outcome = await store.savePickingLocations(loaded, chosen: GoodsOutPickingDefaults(dropshipping: 91), on: "AR24811")

        #expect(outcome == .failed(message: "You can’t edit stock in Paris."))
        #expect(await calls.updates.count == 1)
        #expect(await calls.noteLoads == [501])
    }

    @Test func sampleNotesStillChangeTheLocationLocally() throws {
        let store = GoodsOutStore()
        let note = try #require(store.notes.first { $0.state == .todo && !$0.lines.isEmpty })
        let line = try #require(note.lines.first)

        store.setPickingLocation(note.reference, line: line.id, location: "Z-99")

        #expect(store.note(note.reference)?.line(line.id)?.location == "Z-99")
    }
}
