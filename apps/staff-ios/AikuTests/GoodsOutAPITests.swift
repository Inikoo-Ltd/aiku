import Foundation
import Testing
@testable import Aiku

private actor GoodsOutCalls {
    var listRequests: [GoodsOutListRequest] = []
    var actions: [(Int, GoodsOutNoteAction)] = []
    var noteLoads: [Int] = []

    func recordList(_ request: GoodsOutListRequest) { listRequests.append(request) }
    func recordAction(_ id: Int, _ action: GoodsOutNoteAction) { actions.append((id, action)) }
    func recordNoteLoad(_ id: Int) { noteLoads.append(id) }
}

private func decode<T: Decodable>(_ type: T.Type, _ json: String) throws -> T {
    let decoder = JSONDecoder()
    decoder.keyDecodingStrategy = .convertFromSnakeCase
    return try decoder.decode(T.self, from: Data(json.utf8))
}

private let handlingNoteJSON = """
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

private func rowJSON(id: Int, reference: String, state: String) -> String {
    """
    {"id": \(id), "slug": "x", "reference": "\(reference)", "type": "order", "state": "\(state)", "state_label": "S", "date": "2026-11-10T08:00:00+00:00",
     "last_modified_at": null, "customer_name": "Bath Bombs Ltd", "is_customer_vip": false, "shop": {"id": 1, "name": "AW", "type": "b2b"},
     "channel": "partners", "number_items": 3, "estimated_weight_kg": 2.5, "parcels": 0, "is_premium_dispatch": false, "is_priority": true,
     "is_collection": false, "is_export": false, "has_extra_packing": false, "picker": null, "packer": null, "trolleys": [], "picked_bays": [],
     "waiting_warehouse_count": 0, "waiting_crm_count": 2, "number_of_days_in_warehouse": 1, "can_add_to_picking_session": true}
    """
}

private let countsJSON = """
{"data": {"buckets": {"unassigned": 29, "queued": 2, "handling": 22, "handling_blocked": 39, "picked": 1, "packing": 0, "packed": 12, "finalised": 5, "dispatched": 20592, "in_warehouse": 110},
 "channels": [{"channel": "b2b", "label": "Wholesale", "buckets": {"unassigned": 7, "queued": 1, "handling": 19, "handling_blocked": 36, "picked": 1, "packing": 0, "packed": 4, "finalised": 0, "dispatched": 13207, "in_warehouse": 68}},
              {"channel": "partners", "label": "Partners", "buckets": {"unassigned": 0, "queued": 0, "handling": 0, "handling_blocked": 0, "picked": 0, "packing": 0, "packed": 0, "finalised": 0, "dispatched": 6725, "in_warehouse": 0}}]}}
"""

private let warehouseJSON = """
{"id": 4, "code": "PAR", "name": "Paris", "organisation": {"id": 1, "name": "AW"},
 "can": {"view_locations": true, "edit_locations": false, "view_goods_out": true, "pick_and_pack": true, "supervise_goods_out": false}}
"""

private func service(calls: GoodsOutCalls, actionResult: Result<String, APIError> = .success(handlingNoteJSON), actionDelay: Duration = .zero) -> GoodsOutService {
    GoodsOutService(
        deliveryNotes: { _, request in
            await calls.recordList(request)
            let rows = request.page == 1
                ? [rowJSON(id: 501, reference: "AR24811", state: "unassigned"), rowJSON(id: 502, reference: "AR24812", state: "queued")]
                : [rowJSON(id: 503, reference: "AR24813", state: "unassigned")]
            return try decode(GoodsOutDeliveryNotesPageDTO.self, """
            {"data": {"delivery_notes": [\(rows.joined(separator: ","))], "can_work_in_warehouse": true},
             "pagination": {"current_page": \(request.page), "last_page": 2, "per_page": 50, "total": 3}}
            """)
        },
        counts: { _, _ in try decode(DataEnvelope<GoodsOutCountsDTO>.self, countsJSON).value },
        deliveryNote: { id in
            await calls.recordNoteLoad(id)
            return try decode(GoodsOutDeliveryNoteDTO.self, handlingNoteJSON)
        },
        perform: { id, action in
            await calls.recordAction(id, action)
            try await Task.sleep(for: actionDelay)
            switch actionResult {
            case .success(let note):
                return try decode(GoodsOutActionResponseDTO.self, #"{"data": \#(note), "result": {"status": "done", "message": "Trolley changed."}}"#)
            case .failure(let error):
                throw error
            }
        },
        trolleys: { _ in
            try decode([GoodsOutTrolleyDTO].self, """
            [{"id": 3, "name": "T-03", "current_delivery_note": {"id": 501, "reference": "AR24811"}, "current_picking_session": null},
             {"id": 4, "name": "T-04", "current_delivery_note": {"id": 600, "reference": "AR30000"}, "current_picking_session": null},
             {"id": 5, "name": "T-05", "current_delivery_note": null, "current_picking_session": {"id": 2, "reference": "PS-2"}},
             {"id": 6, "name": "T-06", "current_delivery_note": null, "current_picking_session": null}]
            """)
        },
        pickedBays: { _ in
            try decode([GoodsOutPickedBayDTO].self, #"[{"id": 1, "code": "Bay 1", "number_delivery_notes": 2, "number_waiting": 0, "delivery_notes": [{"id": 501, "reference": "AR24811"}, {"id": 9, "reference": "AR1"}]}]"#)
        },
        pickers: { _ in try decode([GoodsOutPickerDTO].self, #"[{"user_id": 7, "name": "Artha", "alias": "ar"}, {"user_id": 8, "name": null, "alias": "kim"}]"#) },
        dispatchPersonnel: { _, role in
            role == .pickers
                ? try decode([GoodsOutDispatchPersonDTO].self, #"[{"user": {"id": 8, "name": "Kim"}, "delivery_notes": [{"id": 1, "reference": "A"}, {"id": 2, "reference": "B"}], "trolleys": [{"id": 3, "name": "T-03"}], "picking_sessions": [{"id": 44, "reference": "PS-44"}]}]"#)
                : []
        }
    )
}

@MainActor
private func remoteStore(calls: GoodsOutCalls, actionResult: Result<String, APIError> = .success(handlingNoteJSON), actionDelay: Duration = .zero) throws -> GoodsOutStore {
    let store = GoodsOutStore(service: service(calls: calls, actionResult: actionResult, actionDelay: actionDelay), now: { GoodsOutDateFormat.date(day: 11, hour: 10, minute: 0) })
    store.configure(userID: 7, warehouse: try decode(MobileWarehouse.self, warehouseJSON), currentUser: "Artha")
    return store
}

@MainActor
struct GoodsOutAPITests {
    @MainActor
    @Test func fastPickShowsThePickAtOnceAndSendsItInTheBackground() async throws {
        let calls = GoodsOutCalls()
        let store = try remoteStore(calls: calls, actionDelay: .milliseconds(200))
        _ = await store.loadNote(id: 501, errorKey: "AR24811")
        let line = try #require(store.note("AR24811")?.lines.first { $0.apiID == 11 })

        let sending = Task { await store.quickLine(.pickAll(line: 11, locationOrgStockID: 91, clientRequestID: "request-1"), line: line.id, change: .pickAll, on: "AR24811") }
        try await Task.sleep(for: .milliseconds(50))

        #expect(store.note("AR24811")?.lines.first { $0.id == line.id }?.isHandled == true)
        #expect(store.isSending(line.id, on: "AR24811"))
        #expect(store.isWorking(on: "AR24811"))

        #expect(await sending.value == .done(message: "Trolley changed."))
        #expect(!store.isSending(line.id, on: "AR24811"))
        #expect(!store.isWorking(on: "AR24811"))
        #expect(await calls.actions.map(\.1) == [.pickAll(line: 11, locationOrgStockID: 91, clientRequestID: "request-1")])
    }

    @MainActor
    @Test func fastPickPutsTheLineBackWhenTheServerRefuses() async throws {
        let store = try remoteStore(calls: GoodsOutCalls(), actionResult: .failure(.validation(message: "No stock at B-02.", code: nil)))
        _ = await store.loadNote(id: 501, errorKey: "AR24811")
        let line = try #require(store.note("AR24811")?.lines.first { $0.apiID == 11 })

        let outcome = await store.quickLine(.pickAll(line: 11, locationOrgStockID: 91), line: line.id, change: .pickAll, on: "AR24811")

        #expect(outcome == .failed(message: "No stock at B-02."))
        #expect(store.note("AR24811")?.lines.first { $0.id == line.id } == line)
        #expect(!store.isSending(line.id, on: "AR24811"))
    }

    @MainActor
    @Test func swipesOnSeveralLinesAreSentOneAfterAnother() async throws {
        let calls = GoodsOutCalls()
        let store = try remoteStore(calls: calls, actionDelay: .milliseconds(50))
        _ = await store.loadNote(id: 501, errorKey: "AR24811")
        let lines = try #require(store.note("AR24811")?.lines)
        let first = try #require(lines.first { $0.apiID == 11 })
        let second = try #require(lines.first { $0.apiID == 13 })

        async let one = store.quickLine(.pickAll(line: 11, locationOrgStockID: nil), line: first.id, change: .pickAll, on: "AR24811")
        async let two = store.quickLine(.notPicked(line: 13, quantity: nil, reason: .outOfStock, note: nil), line: second.id, change: .notPicked(.outOfStock), on: "AR24811")
        _ = await (one, two)

        #expect(await calls.actions.count == 2)
        #expect(store.pendingLines.isEmpty)
    }

    @Test func warehouseAbilitiesIncludeGoodsOutAndDefaultToFalse() throws {
        let warehouse = try decode(MobileWarehouse.self, warehouseJSON)
        #expect(warehouse.can.viewGoodsOut)
        #expect(warehouse.can.pickAndPack)
        #expect(warehouse.can.superviseGoodsOut == false)

        let older = try decode(MobileWarehouse.self, #"{"id": 1, "code": "W", "name": "W", "organisation": {"id": 1, "name": "AW"}, "can": {"view_locations": true, "edit_locations": true}}"#)
        #expect(older.can.viewGoodsOut == false)
    }

    @Test func detailDecodesAndMapsLinesActionsAndAddress() throws {
        let dto = try decode(GoodsOutDeliveryNoteDTO.self, handlingNoteJSON)
        #expect(dto.shipments?.first?.trackingNumbers == ["T1"])
        #expect(dto.lines?.first?.unNumbers?.text == "UN1, UN2")
        #expect(dto.lines?.first?.warehouseArea?.text == "Area A")

        let note = GoodsOutMapper.note(from: dto, currentUserID: 7, currentUser: "Artha (me)", now: GoodsOutDateFormat.date(day: 11, hour: 10, minute: 0))
        #expect(note.apiID == 501)
        #expect(note.state == .picking)
        #expect(note.channel == .wholesale)
        #expect(note.picker == "Artha (me)")
        #expect(note.trolley == "T-03")
        #expect(note.address == "1 High St, Bath BA1 1AA, United Kingdom")
        #expect(note.customerInstructions == "Leave at the back")
        #expect(note.warehouseNote == nil)
        #expect(note.estimate == "~12 min picking")
        #expect(note.stateDates[.picking] != nil)
        #expect(note.stateDates[.picked] == nil)
        #expect(note.age == .yesterday)
        #expect(note.isDetailed)
        #expect(note.canWorkLines)
        #expect(note.canScanToPick)
        #expect(note.allows(.changeTrolley))
        #expect(note.allows(.cancel) == false)
        #expect(note.action(.cancel)?.disabledReason == "Not now")
        #expect(note.remote?.warning == nil)
        #expect(note.remote?.settings.allowPickerSetNotPicked == false)

        let first = try #require(note.lines.first)
        #expect(first.id == "line-11")
        #expect(first.sko == "SKO-1")
        #expect(first.location == "A-01")
        #expect(first.pickedFrom == "B-02")
        #expect(first.status == .partlyPicked)
        #expect(first.defaultStockLocation?.locationOrgStockID == 91)
        #expect(first.barcode == "500001")
        #expect(first.noteToPickers == "Fragile")
        #expect(note.lines[1].status == .notPicked(.cancelledByCustomer))
        #expect(note.lines[2].status == .waiting(.warehouse))
        #expect(note.allLinesHandled == false)
        #expect(note.linesStillToHandle == 1)
    }

    @Test func listRequestFollowsTheTabFilterAndSearch() {
        var query = GoodsOutListQuery()
        let today = GoodsOutDateFormat.date(day: 11, hour: 10, minute: 0)
        var request = GoodsOutMapper.listRequest(for: query, page: 1, today: today)
        #expect(request.bucket == "unassigned")
        #expect(request.channel == "all")
        #expect(request.sort == nil)

        query.select(tab: .waiting)
        query.search = "  AR248 "
        query.filter.channels = [.marketplaces]
        query.filter.pickers = [.otherPickers]
        query.filter.dates = [.last7Days]
        query.filter.sort = .weight
        request = GoodsOutMapper.listRequest(for: query, page: 2, today: today)
        #expect(request.bucket == "handling_blocked")
        #expect(request.channel == "external")
        #expect(request.picker == "others")
        #expect(request.from == "2026-11-05")
        #expect(request.to == "2026-11-11")
        #expect(request.sort == "-effective_weight")
        #expect(request.queryItems.map(\.name) == ["bucket", "channel", "search", "from", "to", "picker", "sort", "page", "per_page"])
        #expect(request.queryItems.first { $0.name == "search" }?.value == "AR248")
        #expect(request.queryItems.first { $0.name == "page" }?.value == "2")
    }

    @Test func actionsPostToTheirPathWithExplicitNulls() throws {
        let start = Endpoint.goodsOutNoteAction(noteID: 501, .startPicking(trolleyID: nil))
        #expect(start.path == "mobile/delivery-notes/501/start-picking")
        #expect(start.method == .post)
        #expect(String(decoding: try #require(start.body), as: UTF8.self) == #"{"trolley_id":null}"#)

        let pick = Endpoint.goodsOutNoteAction(noteID: 501, .pick(line: 11, locationOrgStockID: 91, quantity: 2, clientRequestID: "request-1"))
        #expect(pick.path == "mobile/delivery-notes/501/lines/11/pick")
        #expect(String(decoding: try #require(pick.body), as: UTF8.self) == #"{"client_request_id":"request-1","location_org_stock_id":91,"quantity":2}"#)

        let waiting = Endpoint.goodsOutNoteAction(noteID: 501, .waiting(line: 11, kind: .crm, quantity: 1.5, note: "  ", clientRequestID: "request-2"))
        #expect(String(decoding: try #require(waiting.body), as: UTF8.self) == #"{"client_request_id":"request-2","kind":"crm","note":null,"quantity":1.5}"#)

        let undo = Endpoint.goodsOutNoteAction(noteID: 501, .undoPicking(line: 11, pickingID: 300))
        #expect(undo.method == .delete)
        #expect(undo.path == "mobile/delivery-notes/501/lines/11/pickings/300")
        #expect(undo.body == nil)

        #expect(Endpoint.goodsOutDeliveryNotes(warehouseID: 4, request: GoodsOutListRequest()).path == "mobile/warehouses/4/delivery-notes")
        #expect(Endpoint.goodsOutCounts(warehouseID: 4, request: GoodsOutCountsRequest(picker: "me")).queryItems == [URLQueryItem(name: "picker", value: "me")])
        #expect(Endpoint.goodsOutCounts(warehouseID: 4, request: GoodsOutCountsRequest()).queryItems.isEmpty)
    }

    @Test func waitingForTheWarehouseAddsWhatIsAlreadyWaiting() throws {
        let dto = try decode(GoodsOutDeliveryNoteDTO.self, handlingNoteJSON)
        let note = GoodsOutMapper.note(from: dto, currentUserID: nil, currentUser: "", now: .now)
        let action = try #require(note.lines[2].waitingAction(kind: .warehouse, note: "Location empty"))
        #expect(action.body?["quantity"] == .int(2))
        #expect(action.body?["note"] == .string("Location empty"))
    }

    @MainActor
    @Test func remoteListLoadsPagesAndCounts() async throws {
        let calls = GoodsOutCalls()
        let store = try remoteStore(calls: calls)
        #expect(store.isRemote)
        #expect(store.rights.isDispatchingSupervisor == false)
        #expect(store.notes.isEmpty)

        await store.loadList()
        await store.loadCounts()
        #expect(store.listResults.map(\.reference) == ["AR24811", "AR24812"])
        #expect(store.listResults[1].state == .todo)
        #expect(store.listResults[0].channel == .partners)
        #expect(store.listResults[0].remote?.isPriority == true)
        #expect(store.listResults[0].remote?.waitingCount == 2)
        #expect(store.listCount(.todo) == 29)
        #expect(store.listCount(.waiting) == 39)
        #expect(store.listTabs.contains(.cancelled) == false)
        #expect(store.hasMoreList)
        #expect(store.noteID(for: "AR24812") == 502)

        await store.loadMoreList()
        #expect(store.listResults.map(\.reference) == ["AR24811", "AR24812", "AR24813"])
        #expect(store.hasMoreList == false)
        let pages = await calls.listRequests.map(\.page)
        #expect(pages == [1, 2])

        store.showChannel(.wholesale)
        #expect(store.listCount(.todo) == 7)

        let rows = store.backlogRows
        #expect(rows.map(\.channel) == [.wholesale])
        #expect(rows.first?.count(.todo) == 8)
        #expect(rows.first?.count(.waiting) == 36)
    }

    @MainActor
    @Test func remoteActionReplacesTheNoteWithTheServerCopy() async throws {
        let calls = GoodsOutCalls()
        let store = try remoteStore(calls: calls)
        await store.loadList()
        let outcome = await store.perform(.changeTrolley(trolleyID: 6), on: "AR24811")
        #expect(outcome == .done(message: "Trolley changed."))
        #expect(store.note("AR24811")?.state == .picking)
        #expect(store.note("AR24811")?.isDetailed == true)
        #expect(store.listResults.first?.state == .picking)
        let actions = await calls.actions
        #expect(actions.first?.0 == 501)
        #expect(actions.first?.1 == .changeTrolley(trolleyID: 6))
        #expect(store.isWorking(on: "AR24811") == false)
        #expect(store.update("AR24811") { _ in true } == nil)
    }

    @MainActor
    @Test func remoteActionShowsTheServerRefusal() async throws {
        let store = try remoteStore(calls: GoodsOutCalls(), actionResult: .failure(.validation(message: "That trolley is in use.", code: nil)))
        await store.loadList()
        #expect(await store.perform(.changeTrolley(trolleyID: 4), on: "AR24811") == .failed(message: "That trolley is in use."))

        let offline = try remoteStore(calls: GoodsOutCalls(), actionResult: .failure(.offline))
        await offline.loadList()
        let outcome = await offline.perform(.takeOver, on: "AR24811")
        #expect(outcome == .failed(message: GoodsOutMapper.message(for: APIError.offline)))
        #expect(await offline.perform(.takeOver, on: "UNKNOWN") == .failed(message: "This delivery note is not in the selected warehouse."))
    }

    @Test func platformStepsWaitUpToTwoMinutes() {
        for action in [GoodsOutNoteAction.setAsPacked, .externalShipment, .finalise, .finaliseAndDispatch, .dispatch] {
            #expect(Endpoint.goodsOutNoteAction(noteID: 501, action).timeout == 120)
        }
        #expect(Endpoint.goodsOutNoteAction(noteID: 501, .takeOver).timeout == nil)
        #expect(Endpoint.goodsOutNoteAction(noteID: 501, .addShipment(shipperID: 1, tracking: nil)).timeout == nil)
    }

    @MainActor
    @Test func anUnansweredPlatformStepReloadsTheNote() async throws {
        let calls = GoodsOutCalls()
        let store = try remoteStore(calls: calls, actionResult: .failure(.offline))
        await store.loadList()

        let outcome = await store.perform(.setAsPacked, on: "AR24811")

        #expect(outcome == .failed(message: GoodsOutMapper.message(for: APIError.offline)))
        #expect(await calls.noteLoads == [501])
        #expect(store.note("AR24811")?.isDetailed == true)
        #expect(store.isWorking(on: "AR24811") == false)
    }

    @MainActor
    @Test func anUnansweredWriteThroughTheFormPathReloadsTheNote() async throws {
        let calls = GoodsOutCalls()
        let store = try remoteStore(calls: calls, actionResult: .failure(.offline))
        await store.loadList()

        _ = await store.write(.dispatch, on: "AR24811")

        #expect(await calls.noteLoads == [501])
    }

    @MainActor
    @Test func anUnansweredLineStepOrRefusedPlatformStepKeepsTheNote() async throws {
        let offlineCalls = GoodsOutCalls()
        let offline = try remoteStore(calls: offlineCalls, actionResult: .failure(.offline))
        await offline.loadList()
        _ = await offline.perform(.pickAll(line: 11, locationOrgStockID: nil), on: "AR24811")
        #expect(await offlineCalls.noteLoads.isEmpty)

        let refusedCalls = GoodsOutCalls()
        let refused = try remoteStore(calls: refusedCalls, actionResult: .failure(.validation(message: "Add a parcel first.", code: nil)))
        await refused.loadList()
        _ = await refused.perform(.setAsPacked, on: "AR24811")
        #expect(await refusedCalls.noteLoads.isEmpty)
    }

    @MainActor
    @Test func remoteNoteLoadsByIDAndKnowsItsReference() async throws {
        let store = try remoteStore(calls: GoodsOutCalls())
        let note = await store.loadNote(id: 501)
        #expect(note?.reference == "AR24811")
        #expect(store.knownIDs["AR24811"] == 501)
        #expect(store.note("AR24811")?.lines.count == 3)
        #expect(store.note("NOPE") == nil)
    }

    @MainActor
    @Test func remoteChoicesShowWhoUsesTrolleysBaysAndPickers() async throws {
        let store = try remoteStore(calls: GoodsOutCalls())
        await store.loadTrolleys()
        await store.loadPickedBays()
        await store.loadPickers()
        await store.loadPersonnel()

        let trolleys = store.trolleyChoices(for: "AR24811")
        #expect(trolleys.map(\.isAvailable) == [true, false, false, true])
        #expect(trolleys[0].subtitle == "On this delivery note")
        #expect(trolleys[1].subtitle == "Delivery note AR30000")
        #expect(trolleys[2].subtitle == "Picking session PS-2")
        #expect(store.trolleyID(named: "T-06") == 6)

        #expect(store.bayChoices(for: "AR24811").first?.subtitle == "1 delivery note")
        #expect(store.pickedBayID(named: "Bay 1") == 1)

        let pickers = store.pickerChoices()
        #expect(pickers.map(\.id) == ["7", "8"])
        #expect(pickers[0].title == "Artha (you)")
        #expect(pickers[1].title == "kim")
        #expect(pickers[1].subtitle == "Picking session PS-44 · 2 orders · 1 trolley")
        #expect(store.pickersNow.first?.sessionID == 44)
    }

    @MainActor
    @Test func signingOutGoesBackToSampleData() throws {
        let store = try remoteStore(calls: GoodsOutCalls())
        store.configure(userID: nil, warehouse: nil, currentUser: "")
        #expect(store.isRemote == false)
        #expect(store.notes == GoodsOutSampleData.notes)
    }
}
