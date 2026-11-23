import Foundation
import Testing
@testable import Aiku

struct SavedListsTests {
    private struct Row: Decodable, Sendable, Equatable {
        let id: Int
        let reference: String
        var createdAt: String? = nil
    }

    private static func page(_ ids: ClosedRange<Int>, prefix: String = "R") -> Data {
        let rows = ids.map { #"{"id":\#($0),"reference":"\#(prefix)\#($0)","created_at":"2026-11-\#(String(format: "%02d", min($0, 28)))T10:00:00Z"}"# }
        return Data(#"{"data":[\#(rows.joined(separator: ","))],"meta":{}}"#.utf8)
    }

    @Test("Newer pages come first, rows are not repeated and the list keeps at most 500")
    func mergesAndCaps() throws {
        let lists = SavedLists(root: OfflineTestData.temporaryDirectory())

        lists.remember(userID: 7, warehouseID: 1, kind: .returns, page: Self.page(1...400))
        lists.remember(userID: 7, warehouseID: 1, kind: .returns, page: Self.page(350...550, prefix: "N"))
        let saved = try #require(lists.rows(Row.self, userID: 7, warehouseID: 1, kind: .returns))

        #expect(saved.rows.count == SavedLists.maxRows)
        #expect(saved.rows.first?.reference == "N350")
        #expect(Set(saved.rows.map(\.id)).count == saved.rows.count)
        #expect(saved.rows.first { $0.id == 1 }?.reference == "R1")
        #expect(saved.rows.first?.createdAt == "2026-11-28T10:00:00Z")
    }

    @Test("Delivery note pages keep their rows under data.delivery_notes")
    func deliveryNotesAreNested() throws {
        let lists = SavedLists(root: OfflineTestData.temporaryDirectory())
        let page = Data(#"{"data":{"delivery_notes":[{"id":5,"reference":"AR1"}],"can_work_in_warehouse":true}}"#.utf8)

        lists.remember(userID: 7, warehouseID: 1, kind: .deliveryNotes, page: page)
        lists.remember(userID: 7, warehouseID: 1, kind: .returns, page: page)

        #expect(try #require(lists.rows(Row.self, userID: 7, warehouseID: 1, kind: .deliveryNotes)).rows.map(\.reference) == ["AR1"])
        #expect(lists.rows(Row.self, userID: 7, warehouseID: 1, kind: .returns) == nil)
    }

    @Test("Lists belong to one user and one warehouse and are wiped on clear")
    func scopedAndCleared() {
        let lists = SavedLists(root: OfflineTestData.temporaryDirectory())
        lists.remember(userID: 7, warehouseID: 1, kind: .stockDeliveries, page: Self.page(1...3))

        #expect(lists.rows(Row.self, userID: 7, warehouseID: 1, kind: .stockDeliveries)?.rows.count == 3)
        #expect(lists.rows(Row.self, userID: 7, warehouseID: 2, kind: .stockDeliveries) == nil)
        #expect(lists.rows(Row.self, userID: 7, warehouseID: 1, kind: .returns) == nil)
        #expect(lists.rows(Row.self, userID: 8, warehouseID: 1, kind: .stockDeliveries) == nil)
        #expect(lists.rows(Row.self, userID: 7, warehouseID: 1, kind: .stockDeliveries) == nil, "reading as another user forgets this user's lists")

        lists.remember(userID: 7, warehouseID: 1, kind: .stockDeliveries, page: Self.page(1...3))
        lists.clear()
        #expect(lists.rows(Row.self, userID: 7, warehouseID: 1, kind: .stockDeliveries) == nil)
    }

    @Test("Saved rows are searched, kept within dates and sorted with empty values last")
    func localFiltering() {
        let utc = TimeZone(identifier: "UTC")!
        let rows = [
            Row(id: 1, reference: "AW-1", createdAt: "2026-11-18T23:30:00Z"),
            Row(id: 2, reference: "aw-2", createdAt: "2026-11-20T08:00:00Z"),
            Row(id: 3, reference: "XX-3", createdAt: nil),
        ]
        let range = ListDateRange(from: ListDates.day("2026-11-19", in: utc), to: ListDates.day("2026-11-20", in: utc))

        #expect(rows.filter { SavedListFilter.matches("aw", $0.reference) }.map(\.id) == [1, 2])
        #expect(rows.filter { SavedListFilter.within($0.createdAt, range: range, in: utc) }.map(\.id) == [2])
        #expect(SavedListFilter.sorted(rows, descending: true, id: \.id, key: \.createdAt).map(\.id) == [2, 1, 3])
        #expect(SavedListFilter.sorted(rows, descending: false, id: \.id, key: \.createdAt).map(\.id) == [1, 2, 3])
    }

    @Test("Date ranges send whole days and never end before they start")
    func dateRanges() throws {
        let utc = TimeZone(identifier: "UTC")!
        let from = try #require(ListDates.day("2026-11-10", in: utc))
        let to = try #require(ListDates.day("2026-11-12", in: utc))
        let range = ListDateRange(from: from, to: to)

        #expect(range.withFrom(try #require(ListDates.day("2026-11-15", in: utc))).to == nil)
        #expect(range.withTo(try #require(ListDates.day("2026-11-01", in: utc))).from == nil)
        #expect(ListDates.apiDay(from, in: utc) == "2026-11-10")
        #expect(ListDates.text(from, in: utc) == "10 Nov 2026")
        #expect(ListDates.recordDate("2026-11-18T10:00:00+00:00", in: utc) == "18 Nov 2026")
        #expect(ListDates.recordDate("2026-11-18", in: utc) == "18 Nov 2026")
        #expect(RecordDateCaption.unlessRepeating(RecordDateCaption.received, status: "Received") == nil)
        #expect(RecordDateCaption.unlessRepeating(RecordDateCaption.received, status: "Checked") == "Received")
    }
}
