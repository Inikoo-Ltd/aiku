import Foundation
import Testing
@testable import Aiku

struct GoodsOutOfflineTests {
    private static func note(state: String = "handling", allowWaiting: Bool = true, inPickingSession: Bool = false) throws -> GoodsOutDeliveryNoteDTO {
        let json = """
        {"id": 501, "reference": "AR24811", "state": "\(state)", "allow_actions": true,
         "picking_settings": {"allow_waiting": \(allowWaiting), "allow_picker_set_not_picked": true},
         "line_counts": {"all": 2, "todo": 2, "done": 0},
         "lines": [
           {"id": 11, "quantity_required": 3, "quantity_picked": 1, "quantity_to_pick": 2, "quantity_to_pack": 1,
            "in_picking_session": \(inPickingSession), "barcodes": ["5001", null], "org_stock": {"code": "SKO-1", "barcode": "5002"},
            "locations": [{"location_org_stock_id": 90, "stock": 0, "quantity_picked": 0, "version": 4},
                          {"location_org_stock_id": 91, "stock": 10, "quantity_picked": 1, "version": 7}]},
           {"id": 12, "quantity_required": 1, "quantity_picked": 0, "quantity_to_pick": 1}
         ]}
        """
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        return try decoder.decode(GoodsOutDeliveryNoteDTO.self, from: Data(json.utf8))
    }

    private static func work(_ action: GoodsOutNoteAction) throws -> GoodsOutLineWork {
        try #require(GoodsOutLineWork(action))
    }

    @Test("Line actions are read back from the request they would send")
    func lineWork() throws {
        let pick = try Self.work(.pick(line: 11, locationOrgStockID: 91, quantity: 2, clientRequestID: "r1"))
        let waiting = try Self.work(.waiting(line: 11, kind: .crm, quantity: 1, note: "short", clientRequestID: "r2"))

        #expect(pick == GoodsOutLineWork(lineID: 11, kind: .pick(locationOrgStockID: 91, quantity: 2), clientRequestID: "r1"))
        #expect(waiting.kind == .waiting(kind: .crm, quantity: 1, note: "short"))
        #expect(GoodsOutLineWork(.setAsPicked(pickedBayID: nil)) == nil)
        #expect(GoodsOutLineWork(.undoPicking(line: 11, pickingID: 3)) == nil)
    }

    @Test("The note carries barcodes, location versions and picking settings")
    func noteFields() throws {
        let note = try Self.note()
        let line = try #require(note.lines?.first)

        #expect(line.scanCodes == ["5001", "5002"])
        #expect(line.locations?.last?.version == 7)
        #expect(note.effectiveSettings?.allowWaiting == true)
    }

    @Test("Pick all becomes an explicit pick of what is left at a location with stock")
    func pickAllIsExplicit() throws {
        let plan = GoodsOutOfflinePlanner.plan(note: try Self.note(), work: try Self.work(.pickAll(line: 11, locationOrgStockID: nil, clientRequestID: "r1")), queued: [])

        guard case .queue(let operation, let payload, let replaces) = plan else {
            Issue.record("Expected a queued pick, got \(plan)")
            return
        }
        #expect(operation == .pick)
        #expect(replaces == nil)
        #expect(payload["location_org_stock_id"] == .int(91))
        #expect(payload["quantity"] == .number(2))
        #expect(payload["delivery_note_id"] == .int(501))
        #expect(payload["delivery_note_item_id"] == .int(11))
        #expect(payload["client_request_id"] == .string("r1"))
    }

    @Test("Changing an unsent pick changes the queued action, and back to zero undoes it")
    func pickChangesTheUnsentAction() throws {
        let note = try Self.note()
        let unsent = OfflineTestData.entry(5, payload: ["delivery_note_item_id": .int(11), "location_org_stock_id": .int(91), "quantity": .number(1)])
        let shown = GoodsOutOfflineOverlay.apply([unsent], to: note, copySeenAt: nil)

        let more = GoodsOutOfflinePlanner.plan(note: shown, work: try Self.work(.pick(line: 11, locationOrgStockID: 91, quantity: 3)), queued: [unsent])
        let none = GoodsOutOfflinePlanner.plan(note: shown, work: try Self.work(.pick(line: 11, locationOrgStockID: 91, quantity: 1)), queued: [unsent])

        guard case .queue(_, let payload, let replaces) = more else {
            Issue.record("Expected a replaced pick, got \(more)")
            return
        }
        #expect(replaces == 5)
        #expect(payload["quantity"] == .number(2))
        #expect(none == .undo(5))
    }

    @Test("Status rules decide what can be saved offline")
    func onlineOnly() throws {
        let pick = try Self.work(.pickAll(line: 11, locationOrgStockID: nil))

        #expect(GoodsOutOfflinePlanner.plan(note: try Self.note(state: "picked"), work: pick, queued: []) == .onlineOnly)
        #expect(GoodsOutOfflinePlanner.plan(note: try Self.note(inPickingSession: true), work: pick, queued: []) == .onlineOnly)
        #expect(GoodsOutOfflinePlanner.plan(note: try Self.note(), work: try Self.work(.pack(line: 11, quantity: 1)), queued: []) == .onlineOnly)
        #expect(GoodsOutOfflinePlanner.plan(
            note: try Self.note(allowWaiting: false),
            work: try Self.work(.waiting(line: 11, kind: .warehouse, quantity: 1, note: nil)),
            queued: []
        ) == .nothingToDo)
    }

    @Test("Queued picks show on the line and its location until Aiku has them")
    func overlay() throws {
        let note = try Self.note()
        let seenAt = Date(timeIntervalSince1970: 2_000)
        let waiting = OfflineTestData.entry(1)
        var applied = OfflineTestData.entry(2, payload: ["delivery_note_item_id": .int(12), "quantity": .number(1)], state: .applied)
        applied.syncedAt = seenAt

        let shown = GoodsOutOfflineOverlay.apply([waiting, applied], to: note, copySeenAt: seenAt)
        let line = try #require(shown.lines?.first)

        #expect(line.quantityPicked == 3)
        #expect(line.quantityToPick == 0)
        #expect(line.isPicked == true)
        #expect(line.locations?.last?.quantityPicked == 3)
        #expect(line.locations?.last?.stock == 8)
        #expect(shown.lines?.last?.quantityPicked == 0)
        #expect(shown.lineCounts?.done == 1)
        #expect(shown.lineCounts?.todo == 1)
    }

    @Test("Waiting for customer services moves the quantity out of what is left to pick")
    func waitingOverlay() throws {
        let crm = OfflineTestData.entry(1, operation: .setWaitingCRM, payload: ["delivery_note_item_id": .int(11), "quantity": .number(1)])

        let line = try #require(GoodsOutOfflineOverlay.apply([crm], to: try Self.note(), copySeenAt: nil).lines?.first)

        #expect(line.quantityWaitingCrm == 1)
        #expect(line.quantityToPick == 1)
    }
}
