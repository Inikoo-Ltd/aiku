import Foundation
import Testing
@testable import Aiku

private actor PickingSessionCalls {
    var listed: [(Int, String?, Int)] = []
    var writes: [PickingSessionWrite] = []
    var picks: [MobileSessionScanBody] = []
    var packs: [MobileSessionScanBody] = []
    var created: [[Int]] = []

    func list(_ warehouse: Int, _ state: String?, _ page: Int) { listed.append((warehouse, state, page)) }
    func write(_ write: PickingSessionWrite) { writes.append(write) }
    func pick(_ body: MobileSessionScanBody) { picks.append(body) }
    func pack(_ body: MobileSessionScanBody) { packs.append(body) }
    func create(_ ids: [Int]) { created.append(ids) }
}

private let sessionJSON = """
{
  "data": {
    "id": 42, "reference": "PS-0042", "state": "handling", "state_label": "Picking",
    "picker": {"id": 7, "name": "Sam Picker"},
    "number_delivery_notes": 2, "number_items": 5, "number_trolleys": 1,
    "picking_percentage": 40.0, "packing_percentage": 0, "is_waiting_ready": false,
    "start_at": "2026-11-16T08:00:00+00:00", "end_at": null,
    "can_work": true,
    "trolleys": [{"id": 3, "name": "T-03"}],
    "can_change_trolleys": true,
    "settings": {"allow_scan_to_pick": true, "allow_scan_to_pack": true, "allow_waiting": true, "allow_picker_set_not_picked": false},
    "available_actions": [{"key": "scan_pick", "label": "Scan to pick"}],
    "delivery_notes": [
      {"id": 100, "reference": "AR100", "state": "handling", "state_label": "Picking", "number_items": 3, "picking_percentage": 66.7, "packing_percentage": 0, "has_waiting_items": false, "is_for_collection": false},
      {"id": 101, "reference": "AR101", "state": "handling", "state_label": "Picking", "number_items": 2, "picking_percentage": 0, "packing_percentage": 0, "has_waiting_items": true, "is_for_collection": true}
    ],
    "pick_list": [
      {"key": "9-50", "location": {"id": 9, "code": "A-01"}, "org_stock": {"id": 50, "code": "SKO-50", "name": "Rose soap", "barcode": "5050", "image_url": null},
       "quantity_required": 3, "quantity_picked": 2, "quantity_to_pick": 1, "quantity_to_pick_label": "1",
       "lines": [{"delivery_note_item_id": 500, "delivery_note_id": 100, "delivery_note_reference": "AR100", "quantity_required": 3, "quantity_picked": 2, "quantity_to_pick": 1}]},
      {"key": "0-60", "location": null, "org_stock": {"id": 60, "code": "SKO-60", "name": null, "barcode": null, "image_url": null},
       "quantity_required": 2, "quantity_picked": 0, "quantity_to_pick": 2, "quantity_to_pick_label": null,
       "lines": [{"delivery_note_item_id": 600, "delivery_note_id": 101, "delivery_note_reference": "AR101", "quantity_required": 2, "quantity_picked": 0, "quantity_to_pick": 2}]}
    ]
  }
}
"""

private func decoder() -> JSONDecoder {
    let decoder = JSONDecoder()
    decoder.keyDecodingStrategy = .convertFromSnakeCase
    return decoder
}

private func loadedSession() throws -> MobilePickingSession {
    try decoder().decode(DataEnvelope<MobilePickingSession>.self, from: Data(sessionJSON.utf8)).value
}

private func entry(_ key: String, location: String, toPick: Double, itemID: Int, noteID: Int) -> MobilePickListEntry {
    MobilePickListEntry(
        key: key,
        location: MobilePickListLocation(id: itemID, code: location),
        orgStock: MobilePickListOrgStock(id: itemID, code: "SKO-\(itemID)"),
        quantityRequired: 2,
        quantityPicked: 2 - toPick,
        quantityToPick: toPick,
        lines: [MobilePickListLine(deliveryNoteItemId: itemID, deliveryNoteId: noteID, deliveryNoteReference: "AR\(noteID)", quantityRequired: 2, quantityPicked: 2 - toPick, quantityToPick: toPick)]
    )
}

private func session(state: String = "handling", pickList: [MobilePickListEntry], actions: [String] = ["scan_pick"]) -> MobilePickingSession {
    MobilePickingSession(
        id: 42,
        reference: "PS-0042",
        state: state,
        canWork: true,
        availableActions: actions.map { MobilePickingSessionAction(key: $0, label: $0) },
        deliveryNotes: [MobileSessionDeliveryNote(id: 100, reference: "AR100", state: "handling")],
        pickList: pickList
    )
}

private func service(
    calls: PickingSessionCalls = PickingSessionCalls(),
    pages: [Int: MobilePickingSessionsPage] = [:],
    detail: MobilePickingSession? = nil,
    writeOutcome: Result<MobilePickingSessionOutcome, APIError> = .failure(.offline),
    pickOutcomes: [MobilePickingSessionOutcome] = [],
    packOutcome: MobilePickingSessionOutcome? = nil
) -> PickingSessionService {
    let pickQueue = PickOutcomes(pickOutcomes)
    return PickingSessionService(
        sessions: { warehouse, state, page in
            await calls.list(warehouse, state, page)
            return pages[page] ?? MobilePickingSessionsPage(data: [])
        },
        session: { _ in
            guard let detail else { throw APIError.rejected(message: "Not found") }
            return detail
        },
        create: { _, ids in
            await calls.create(ids)
            return MobilePickingSession(id: 77, reference: "PS-0077", state: "in_process")
        },
        write: { _, write in
            await calls.write(write)
            return try writeOutcome.get()
        },
        scanPick: { _, body in
            await calls.pick(body)
            return try await pickQueue.next()
        },
        scanPack: { _, body in
            await calls.pack(body)
            guard let packOutcome else { throw APIError.offline }
            return packOutcome
        },
        trolleys: { _, _ in [] },
        pickedBays: { _ in [] },
        candidates: { _ in [] }
    )
}

private actor PickOutcomes {
    private var outcomes: [MobilePickingSessionOutcome]

    init(_ outcomes: [MobilePickingSessionOutcome]) {
        self.outcomes = outcomes
    }

    func next() throws -> MobilePickingSessionOutcome {
        guard !outcomes.isEmpty else { throw APIError.offline }
        return outcomes.removeFirst()
    }
}

@MainActor
struct PickingSessionTests {
    @Test("A picking session payload decodes with its trolleys, actions, delivery notes and pick list")
    func decodesSession() throws {
        let decoded = try loadedSession()

        #expect(decoded.reference == "PS-0042")
        #expect(decoded.sessionState == .handling)
        #expect(decoded.trolleys.first?.name == "T-03")
        #expect(decoded.canRun(PickingSessionActionKey.scanPick))
        #expect(!decoded.canRun(PickingSessionActionKey.startPicking))
        #expect(decoded.settings.allowScanToPick)
        #expect(decoded.pickList.count == 2)
        #expect(decoded.pickList[1].location == nil)
        #expect(decoded.pickList[1].title == "SKO-60")
        #expect(decoded.pickList[0].detail == "SKO-50 × 1 · 1 delivery note")
        #expect(decoded.deliveryNotes[1].subtitle(isPacking: false) == "2 items · 0% picked · Collection · waiting")
        #expect(decoded.waitingDeliveryNoteCount == 1)
    }

    @Test("Session rows read like the Android list and mark the signed-in picker")
    func rowSubtitle() throws {
        let json = """
        {"data": [{"id": 1, "reference": "PS-1", "state": "picking_finished", "state_label": "Picking finished", "picker": {"id": 7, "name": "Sam"},
          "number_delivery_notes": 1, "number_items": 4, "number_trolleys": 2, "picking_percentage": 100, "packing_percentage": 49.6}],
         "counts": {"in_process": 2, "handling": 1}, "pagination": {"current_page": 1, "last_page": 3, "per_page": 25, "total": 60}}
        """
        let page = try decoder().decode(MobilePickingSessionsPage.self, from: Data(json.utf8))

        #expect(page.data[0].subtitle(currentUserID: 7) == "Sam (you) · 1 delivery note · 4 items · 2 trolleys · packing 50%")
        #expect(page.data[0].subtitle(currentUserID: 8) == "Sam · 1 delivery note · 4 items · 2 trolleys · packing 50%")
        #expect(page.data[0].tone == .success)
        #expect(page.counts["in_process"] == 2)
        #expect(page.pagination.lastPage == 3)
    }

    @Test("The list loads by state, keeps the counts and appends the next page")
    func listPaging() async {
        let calls = PickingSessionCalls()
        let first = MobilePickingSessionsPage(
            data: [MobilePickingSessionRow(id: 1, reference: "PS-1", state: "handling")],
            counts: ["handling": 3],
            pagination: MobileSessionPagination(currentPage: 1, lastPage: 2)
        )
        let second = MobilePickingSessionsPage(
            data: [MobilePickingSessionRow(id: 1, reference: "PS-1", state: "handling"), MobilePickingSessionRow(id: 2, reference: "PS-2", state: "handling")],
            pagination: MobileSessionPagination(currentPage: 2, lastPage: 2)
        )
        let store = PickingSessionsStore(service: service(calls: calls, pages: [1: first, 2: second]))

        await store.load(warehouseID: 5)
        #expect(store.rows.map(\.id) == [1])
        #expect(store.canLoadMore)
        #expect(store.count(for: .state(.handling)) == 3)

        await store.loadMore()
        #expect(store.rows.map(\.id) == [1, 2])
        #expect(!store.canLoadMore)
        #expect(store.counts == ["handling": 3])

        await store.select(.state(.handlingBlocked))
        let listed = await calls.listed
        #expect(listed.last?.1 == "handling_blocked")
        #expect(listed.last?.2 == 1)
    }

    @Test("A reference opens the session found in the list pages")
    func findsByReference() async throws {
        let page = MobilePickingSessionsPage(
            data: [MobilePickingSessionRow(id: 9, reference: "PS-9", state: "in_process")],
            pagination: MobileSessionPagination(currentPage: 1, lastPage: 1)
        )
        let store = PickingSessionsStore(service: service(pages: [1: page]))
        await store.load(warehouseID: 5)

        #expect(try await store.sessionID(reference: "PS-9") == 9)
        #expect(try await store.sessionID(reference: "PS-404") == nil)
    }

    @Test("Creating a session sends the chosen delivery notes and keeps the new session ready to open")
    func createsSession() async throws {
        let calls = PickingSessionCalls()
        let store = PickingSessionsStore(service: service(calls: calls))
        await store.load(warehouseID: 5)

        let result = await store.createSession(deliveryNoteIDs: [100, 101])

        #expect(try result.get().id == 77)
        #expect(await calls.created == [[100, 101]])
        #expect(store.store(for: 77).session?.reference == "PS-0077")
    }

    @Test("A write shows the server message on success and the refusal otherwise")
    func writes() async throws {
        let started = MobilePickingSessionOutcome(
            session: session(pickList: [entry("a", location: "A-01", toPick: 2, itemID: 1, noteID: 100)]),
            result: MobilePickingSessionResult(status: "done", message: "Picking started.")
        )
        let calls = PickingSessionCalls()
        let store = PickingSessionStore(sessionID: 42, service: service(calls: calls, writeOutcome: .success(started)))

        #expect(try await store.act(.startPicking).get() == "Picking started.")
        #expect(store.session?.sessionState == .handling)
        #expect(store.currentPick?.key == "a")
        #expect(await calls.writes == [.startPicking])

        let refused = PickingSessionStore(sessionID: 42, service: service(writeOutcome: .failure(.validation(message: "This can not be done while the picking session is Picking.", code: nil))))
        let failure = await refused.act(.backToPicking)
        #expect(failure == .failure(PickingSessionFailure(message: "This can not be done while the picking session is Picking.")))
    }

    @Test("A scan picks 1, opens the scan panel and moves on when the location is done")
    func scanPicking() async throws {
        let before = session(pickList: [
            entry("a", location: "A-01", toPick: 2, itemID: 1, noteID: 100),
            entry("b", location: "B-02", toPick: 1, itemID: 2, noteID: 100)
        ])
        let afterOne = MobilePickingSessionOutcome(
            session: session(pickList: [
                entry("a", location: "A-01", toPick: 1, itemID: 1, noteID: 100),
                entry("b", location: "B-02", toPick: 1, itemID: 2, noteID: 100)
            ]),
            result: MobilePickingSessionResult(
                status: "picked",
                message: "Picked 1 x SKO-1",
                item: MobileSessionScanItem(id: 1, code: "SKO-1", name: "Soap", quantityPicked: 1, quantityToPick: 1, locationCode: "A-01", locationOrgStockId: 11),
                deliveryNote: MobileSessionScanDeliveryNote(id: 100, reference: "AR100")
            )
        )
        let afterAll = MobilePickingSessionOutcome(
            session: session(pickList: [
                entry("a", location: "A-01", toPick: 0, itemID: 1, noteID: 100),
                entry("b", location: "B-02", toPick: 1, itemID: 2, noteID: 100)
            ]),
            result: MobilePickingSessionResult(status: "picked", item: MobileSessionScanItem(id: 1, code: "SKO-1", quantityPicked: 2, quantityToPick: 0))
        )
        let calls = PickingSessionCalls()
        let store = PickingSessionStore(sessionID: 42, service: service(calls: calls, pickOutcomes: [afterOne, afterAll]))
        store.adopt(before)

        store.scanToPick(" 5050 ")
        await store.waitForScans()
        #expect(store.scanTarget?.remaining == 1)
        #expect(store.scanTarget?.place == "A-01 · Delivery note AR100")
        #expect(store.currentPick?.key == "a")
        #expect(store.event == nil)

        store.pickAllScanned()
        await store.waitForScans()
        let picks = await calls.picks
        #expect(picks[0] == MobileSessionScanBody(barcode: "5050", quantity: 1))
        #expect(picks[1] == MobileSessionScanBody(barcode: "5050", quantity: nil, deliveryNoteItemId: 1, locationOrgStockId: 11))
        #expect(store.scanTarget == nil)
        #expect(store.currentPick?.key == "b")
        #expect(store.event == .locationDone("A-01"))
        #expect(store.scanLog.count == 2)
        #expect(store.queuedScans == 0)
    }

    @Test("A scan the server refuses keeps the panel and reports its message")
    func refusedScan() async {
        let current = session(pickList: [entry("a", location: "A-01", toPick: 2, itemID: 1, noteID: 100)])
        let refused = MobilePickingSessionOutcome(session: current, result: MobilePickingSessionResult(status: "not_found", message: "9999 is not in this picking session."))
        let store = PickingSessionStore(sessionID: 42, service: service(pickOutcomes: [refused]))
        store.adopt(current)

        store.scanToPick("9999")
        await store.waitForScans()

        #expect(store.event == .feedback("9999 is not in this picking session.", .danger))
        #expect(store.scanLog.first?.result == .refused)
    }

    @Test("Waiting and finished picking end the pick screen")
    func finishingEvents() async {
        let start = session(pickList: [entry("a", location: "A-01", toPick: 1, itemID: 1, noteID: 100)])
        let waiting = MobilePickingSessionOutcome(
            session: session(state: "handling_blocked", pickList: [entry("a", location: "A-01", toPick: 0, itemID: 1, noteID: 100)]),
            result: MobilePickingSessionResult(status: "picked", item: MobileSessionScanItem(id: 1, quantityToPick: 0))
        )
        let store = PickingSessionStore(sessionID: 42, service: service(pickOutcomes: [waiting]))
        store.adopt(start)

        store.scanToPick("5050")
        await store.waitForScans()

        #expect(store.event == .nowWaiting)
        #expect(store.event?.isFinishing == true)
    }

    @Test("Packing the last item announces that every delivery note is packed")
    func packing() async {
        let packed = MobilePickingSessionOutcome(
            session: session(state: "packing_finished", pickList: [], actions: []),
            result: MobilePickingSessionResult(status: "packed", item: MobileSessionScanItem(id: 1, code: "SKO-1", quantityPacked: 2, quantityToPack: 0))
        )
        let calls = PickingSessionCalls()
        let store = PickingSessionStore(sessionID: 42, service: service(calls: calls, packOutcome: packed))
        store.adopt(session(state: "picking_finished", pickList: [], actions: ["scan_pack"]))

        store.scanToPack("5050")
        await store.waitForScans()

        #expect(await calls.packs == [MobileSessionScanBody(barcode: "5050", quantity: 1)])
        #expect(store.event == .allPacked)
        #expect(store.scanTarget == nil)
    }

    @Test("An offline scan is logged as failed with the internet message")
    func offlineScan() async {
        let store = PickingSessionStore(sessionID: 42, service: service())
        store.adopt(session(state: "picking_finished", pickList: [], actions: ["scan_pack"]))

        store.scanToPack("5050")
        await store.waitForScans()

        #expect(store.scanLog.first?.result == .failed)
        #expect(store.event == .feedback("This needs internet. Check Wi-Fi and try again.", .danger))
    }

    @Test("Footer actions follow the Android order and only what the API allows")
    func footerOrder() {
        let item = session(state: "in_process", pickList: [], actions: ["remove_delivery_notes", "add_delivery_notes", "start_picking"])

        #expect(item.footerActions.map(\.key) == ["start_picking", "add_delivery_notes", "remove_delivery_notes"])
    }

    @Test("Trolleys and picked bays describe who holds them")
    func equipment() throws {
        let trolleys = try decoder().decode(DataEnvelope<[MobileSessionTrolley]>.self, from: Data("""
        {"data": [
          {"id": 1, "name": "T-01", "current_delivery_note": {"id": 100, "reference": "AR100"}, "current_picking_session": null},
          {"id": 2, "name": "T-02", "current_delivery_note": null, "current_picking_session": {"id": 42, "reference": "PS-0042"}},
          {"id": 3, "name": "T-03", "current_delivery_note": null, "current_picking_session": null}
        ]}
        """.utf8)).value
        let bays = try decoder().decode(DataEnvelope<[MobileSessionPickedBay]>.self, from: Data("""
        {"data": [
          {"id": 1, "code": "Bay 1", "number_delivery_notes": 0, "number_waiting": 0, "delivery_notes": []},
          {"id": 2, "code": "Bay 2", "number_delivery_notes": 2, "number_waiting": 1, "delivery_notes": [
            {"id": 100, "reference": "AR100", "state": "handling_blocked", "state_label": "Waiting"},
            {"id": 101, "reference": "AR101", "state": "picked", "state_label": "Picked"}]}
        ]}
        """.utf8)).value

        #expect(trolleys.map(\.holderSubtitle) == ["Delivery note AR100", "Picking session PS-0042", nil])
        #expect(trolleys.map(\.holderTitle) == ["In use", "In use", "Free"])
        #expect(bays.map(\.subtitle) == [nil, "2 delivery notes · 1 waiting"])
        #expect(bays[0].chip == Chip("Free", .success))
    }

    @Test("Endpoints use the mobile picking session paths and bodies")
    func endpoints() throws {
        let client = APIClient(configuration: AppConfiguration(apiBaseURL: URL(string: "http://localhost/app/api")!))

        let list = client.makeRequest(for: .pickingSessions(warehouseID: 5, state: "handling", page: 2))
        #expect(list.url?.absoluteString == "http://localhost/app/api/mobile/warehouses/5/picking-sessions?state=handling&page=2")

        let detach = client.makeRequest(for: .pickingSessionWrite(id: 42, .detachTrolley(3)))
        #expect(detach.httpMethod == "DELETE")
        #expect(detach.url?.path == "/app/api/mobile/picking-sessions/42/trolleys/3")

        let remove = client.makeRequest(for: .pickingSessionWrite(id: 42, .removeDeliveryNotes([100, 101])))
        #expect(remove.url?.path == "/app/api/mobile/picking-sessions/42/delivery-notes/remove")
        #expect(String(decoding: remove.httpBody ?? Data(), as: UTF8.self) == #"{"delivery_note_ids":[100,101]}"#)

        let attach = client.makeRequest(for: .pickingSessionWrite(id: 42, .attachTrolley(3)))
        #expect(String(decoding: attach.httpBody ?? Data(), as: UTF8.self) == #"{"trolley_id":3}"#)

        let pick = client.makeRequest(for: .pickingSessionScanPick(id: 42, MobileSessionScanBody(barcode: "5050", quantity: 2, deliveryNoteItemId: 1, locationOrgStockId: 11)))
        let body = try JSONSerialization.jsonObject(with: pick.httpBody ?? Data()) as? [String: Any]
        #expect(body?["delivery_note_item_id"] as? Int == 1)
        #expect(body?["location_org_stock_id"] as? Int == 11)
        #expect(pick.url?.path == "/app/api/mobile/picking-sessions/42/scan-pick")

        let create = client.makeRequest(for: .createPickingSession(warehouseID: 5, deliveryNoteIDs: [100]))
        #expect(create.httpMethod == "POST")
        #expect(create.url?.path == "/app/api/mobile/warehouses/5/picking-sessions")
    }

    @Test("Shippers keep describing today's shipments")
    func shippers() {
        let store = DispatchStore(service: service())

        #expect(store.shippers.map(\.subtitle) == ["48 shipments today", "21 shipments today", "No shipments today"])
    }
}
