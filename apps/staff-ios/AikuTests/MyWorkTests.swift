import Foundation
import Testing
@testable import Aiku

struct MyWorkTests {
    private let pageJSON = """
    {"data": [
      {"kind": "delivery_note", "id": 501, "reference": "AR24811", "state": "handling", "state_label": "Handling", "step": "waiting", "step_label": "Waiting", "type_group": null, "items": 3, "customer": "Bath Bombs Ltd", "trolleys": ["T-03"], "since": "2026-11-18T08:00:00+00:00"},
      {"kind": "picking_session", "id": 44, "reference": "PS-44", "state": "handling", "state_label": "Picking", "step": "picking", "step_label": "Picking", "type_group": null, "items": 1, "customer": null, "trolleys": [], "since": null},
      {"kind": "return", "id": 9, "reference": "RET-9", "state": "in_process", "step": "to_start", "step_label": "To start", "items": 2, "trolleys": []},
      {"kind": "pallet_return", "id": 12, "reference": "PR-12", "state": "picking", "step": "picking", "type_group": "items", "items": 4, "trolleys": []}
    ],
    "counts": {"total": 4, "delivery_notes": 1, "picking_sessions": 1, "returns": 1, "pallet_returns": 1}}
    """

    private func page() throws -> MobileMyWorkPage {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        return try decoder.decode(MobileMyWorkPage.self, from: Data(pageJSON.utf8))
    }

    @MainActor
    @Test func myWorkLoadsTheWarehouseRowsAndCounts() async throws {
        let loaded = try page()
        let store = MyWorkStore()
        await store.load(warehouseID: 4, service: MyWorkService(fetch: { id in
            #expect(id == 4)
            return loaded
        }))

        #expect(store.hasLoaded)
        #expect(store.rows.map(\.reference) == ["AR24811", "PS-44", "RET-9", "PR-12"])
        #expect(store.counts.total == 4)
        #expect(store.filters() == [.all, .deliveryNotes, .pickingSessions, .returns, .palletReturns])
        #expect(store.rows(for: .pickingSessions).map(\.id) == [44])
    }

    @MainActor
    @Test func aFailedLoadKeepsAProblemToShow() async {
        let store = MyWorkStore()
        await store.load(warehouseID: 4, service: MyWorkService(fetch: { _ in throw APIError.offline }))

        #expect(!store.hasLoaded)
        #expect(store.loadError != nil)
    }

    @Test func eachKindOpensItsOwnScreenById() throws {
        let rows = try page().data

        #expect(MyWorkFormat.link(for: rows[0]) == .goodsOut(.deliveryNoteID(id: 501)))
        #expect(MyWorkFormat.link(for: rows[1]) == .dispatch(.pickingSessionID(id: 44)))
        #expect(MyWorkFormat.link(for: rows[2]) == .goodsIn(.returnID(id: 9, reference: "RET-9")))
        #expect(MyWorkFormat.link(for: rows[3]) == nil)
    }

    @Test func rowsReadLikeAndroid() throws {
        let rows = try page().data
        let now = try #require(ISO8601DateFormatter().date(from: "2026-11-18T10:30:00Z"))

        #expect(MyWorkFormat.subtitle(rows[0], now: now) == "Delivery note · 3 items · Bath Bombs Ltd · Trolley T-03 · 2 h ago")
        #expect(MyWorkFormat.subtitle(rows[3], now: now) == "Pallet return · 4 stored items")
        #expect(MyWorkFormat.stepTitle(rows[0]) == "Waiting")
        #expect(MyWorkFormat.tone(for: rows[0].step) == .warn)
        #expect(MyWorkFormat.tone(for: rows[2].step) == .info)
    }
}
