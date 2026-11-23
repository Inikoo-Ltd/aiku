import Foundation
import Testing
@testable import Aiku

struct ConflictDifferencesTests {
    private static func entry(_ json: String) throws -> OfflineOperationEntry {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        return try decoder.decode(OfflineOperationEntry.self, from: Data(json.utf8))
    }

    private static let utc = TimeZone(identifier: "UTC") ?? .gmt

    private static let refusedPick = #"""
    {"operation_id": 41, "status": "refused", "state": "refused", "operation": "pick", "reason": "not_enough_stock", "instruction": "put_back",
     "requested_quantity": 1, "applied_quantity": null, "performed_at": "2026-11-20T09:15:00.000Z",
     "conflicting_user": {"id": 3, "name": " Ana Lopez "},
     "labels": {"title": "AR24811 · line 2", "subtitle": "BBUG-06 Bug spray 500ml", "location_code": "20A19"},
     "record": {"type": "delivery_note", "id": 501},
     "item": {"sko_code": "BBUG-06", "sko_name": "Bug spray 500ml", "image_url": "https://aiku.test/bbug-06.jpg", "packed_in": "8",
              "record_type": "delivery_note", "record_reference": "AR24811", "line": "2",
              "quantities": {"required": 2.875, "picked": "1", "not_picked": 0, "packed": 0}}}
    """#

    private static let locationAudit = #"""
    {"operation_id": 52, "status": "conflict", "operation": "audit_location", "reason": "other_change", "instruction": "recount",
     "labels": {"title": "", "subtitle": null, "location_code": "57B2"},
     "record": {"type": "location", "id": 7},
     "counted_rows": [
        {"location_code": "57B2", "sko_code": "BBUG-06", "sko_name": "Bug spray 500ml", "counted": 9, "quantity": "9"},
        {"location_code": "57B2", "sko_code": "BBUG-07", "sko_name": "Bug spray 1l", "counted": "4", "quantity": 6}
     ],
     "item": {"sko_code": null, "sko_name": null, "image_url": null, "packed_in": null,
              "record_type": "location", "record_reference": "57B2", "line": null, "quantities": {"stock_now": 9}}}
    """#

    @Test("The item and the SKO of each counted row are read, numbers given as text included")
    func itemDecodes() throws {
        let pick = try Self.entry(Self.refusedPick)
        let item = try #require(pick.item)

        #expect(item.skoCode == "BBUG-06")
        #expect(item.skoName == "Bug spray 500ml")
        #expect(item.imageUrl == "https://aiku.test/bbug-06.jpg")
        #expect(item.packedIn == 8)
        #expect(item.recordType == "delivery_note")
        #expect(item.recordReference == "AR24811")
        #expect(item.line == 2)
        #expect(item.quantities?.required == 2.875)
        #expect(item.quantities?.picked == 1)
        #expect(item.quantities?.notPicked == 0)
        #expect(item.quantities?.stockNow == nil)

        let audit = try Self.entry(Self.locationAudit)
        #expect(audit.countedRows.map(\.skoCode) == ["BBUG-06", "BBUG-07"])
        #expect(audit.countedRows.map(\.skoName) == ["Bug spray 500ml", "Bug spray 1l"])
        #expect(audit.countedRows.map(\.counted) == [9, 4])
        #expect(audit.countedRows.map(\.quantity) == [9, 6])
        #expect(audit.item?.skoCode == nil)
        #expect(audit.item?.quantities?.stockNow == 9)
    }

    @Test("An older server without the item or the counted rows' SKO still decodes")
    func olderServerDecodes() throws {
        let entry = try Self.entry(#"""
        {"operation_id": 60, "status": "conflict", "operation": "audit_org_stock",
         "labels": {"title": "BBUG-06", "subtitle": "BBUG-06 Bug spray 500ml"},
         "counted_rows": [{"location_code": "A-01", "counted": 3, "quantity": 2}]}
        """#)

        #expect(entry.item == nil)
        #expect(entry.countedRows.count == 1)
        #expect(entry.countedRows.first?.skoCode == nil)
        #expect(entry.countedRows.first?.locationCode == "A-01")
        let summary = ConflictDetails.summary(entry)
        #expect(summary.code == "BBUG-06")
        #expect(summary.name == "Bug spray 500ml")
        #expect(summary.record == "BBUG-06")
    }

    @Test("A malformed item is dropped instead of failing the whole operation")
    func malformedItemIsIgnored() throws {
        let entry = try Self.entry(#"{"operation_id": 61, "status": "refused", "operation": "pick", "item": [], "counted_rows": null}"#)

        #expect(entry.key == 61)
        #expect(entry.item == nil)
        #expect(entry.countedRows.isEmpty)
    }

    @Test("A refused pick compares what you did with what Aiku recorded")
    func refusedPickDifference() throws {
        let pick = try Self.entry(Self.refusedPick)

        let rows = ConflictDetails.differences(pick)

        #expect(rows == [ConflictDifference(title: nil, subtitle: "20A19", yours: 1, aiku: 0)])
        #expect(rows.first?.difference == -1)
    }

    @Test("A partly applied action shows the part Aiku kept")
    func partlyAppliedDifference() throws {
        let move = try Self.entry(#"{"operation_id": 70, "status": "partly_applied", "operation": "move_stock", "requested_quantity": 5, "applied_quantity": 3}"#)

        #expect(ConflictDetails.differences(move).map(\.difference) == [-2])
    }

    @Test("A pack keeps its quantities and an action without any has nothing to compare")
    func packDifference() throws {
        let pack = try Self.entry(#"{"operation_id": 71, "status": "partly_applied", "operation": "pack", "requested_quantity": 4, "applied_quantity": 4}"#)
        let waiting = try Self.entry(#"{"operation_id": 72, "status": "refused", "operation": "set_waiting_warehouse"}"#)

        #expect(ConflictDetails.differences(pack).map(\.difference) == [0])
        #expect(ConflictDetails.differences(waiting).contains(where: \.hasQuantity) == false)
    }

    @Test("A location audit lists each SKO with its count and what Aiku has")
    func locationAuditDifferences() throws {
        let audit = try Self.entry(Self.locationAudit)

        let rows = ConflictDetails.differences(audit)

        #expect(rows == [
            ConflictDifference(title: "BBUG-06", subtitle: "57B2", yours: 9, aiku: 9),
            ConflictDifference(title: "BBUG-07", subtitle: "57B2", yours: 4, aiku: 6)
        ])
        #expect(rows.map(\.difference) == [0, 2])
    }

    @Test("An SKO audit across several locations is listed one row per location")
    func skoAuditDifferences() throws {
        let audit = try Self.entry(#"""
        {"operation_id": 53, "status": "conflict", "operation": "audit_org_stock",
         "counted_rows": [
            {"location_code": "A-01", "sko_code": "BBUG-06", "counted": 3, "quantity": 2},
            {"location_code": "B-02", "sko_code": "BBUG-06", "counted": 1, "quantity": 1},
            {"location_code": "C-03", "sko_code": "BBUG-06", "counted": 0.5, "quantity": null}
         ]}
        """#)

        let rows = ConflictDetails.differences(audit)

        #expect(rows.map(\.title) == ["A-01", "B-02", "C-03"])
        #expect(rows.map(\.subtitle) == [nil, nil, nil])
        #expect(rows.map(\.difference) == [-1, 0, nil])
    }

    @Test("Quantities and differences are drawn like the web's FractionDisplay")
    func fractions() {
        #expect(ConflictDetails.quantity(2.875, packedIn: 8) == "2\u{202F}⁷⁄₈")
        #expect(ConflictDetails.quantity(28.625, packedIn: 16) == "28\u{202F}¹⁰⁄₁₆")
        #expect(ConflictDetails.quantity(12, packedIn: 6) == "12")
        #expect(ConflictDetails.quantity(100.5, packedIn: nil) == "100\u{202F}¹⁄₂")
        #expect(ConflictDetails.signedQuantity(1.875, packedIn: 8) == "+1\u{202F}⁷⁄₈")
        #expect(ConflictDetails.signedQuantity(-0.875, packedIn: 8) == "\u{2212}⁷⁄₈")
        #expect(ConflictDetails.signedQuantity(0, packedIn: 8) == "0")
        #expect(ConflictDifference(title: nil, subtitle: nil, yours: 0.1, aiku: 0.3).difference == 0.2)
    }

    @Test("The item card names the record and line, the quantities now, when it was done and who changed it")
    func pickFacts() throws {
        let pick = try Self.entry(Self.refusedPick)

        let doneOffline = try #require(InventoryLive.momentText("2026-11-20T09:15:00.000Z", timeZone: Self.utc))

        let summary = ConflictDetails.summary(pick)
        let facts = ConflictDetails.facts(pick, timeZone: Self.utc)

        #expect(summary == ConflictItemSummary(code: "BBUG-06", name: "Bug spray 500ml", imageUrl: "https://aiku.test/bbug-06.jpg", record: "AR24811 · Line 2"))
        #expect(summary.itemText == "BBUG-06 · Bug spray 500ml")
        #expect(facts == [
            ConflictFact(label: "Delivery note", value: "AR24811 · Line 2"),
            ConflictFact(label: "Location", value: "20A19"),
            ConflictFact(label: "Required", value: "2\u{202F}⁷⁄₈"),
            ConflictFact(label: "Picked", value: "1"),
            ConflictFact(label: "Done offline", value: doneOffline),
            ConflictFact(label: "Changed in Aiku by", value: "Ana Lopez")
        ])
        #expect(ConflictDetails.summary(pick, lineWord: "Línea").record == "AR24811 · Línea 2")
    }

    @Test("A location count shows the location as the record and its stock now, without repeating the location")
    func locationAuditFacts() throws {
        let audit = try Self.entry(Self.locationAudit)

        let summary = ConflictDetails.summary(audit)

        #expect(summary.code == nil)
        #expect(summary.record == "57B2")
        #expect(summary.itemText == nil)
        #expect(ConflictDetails.facts(audit, timeZone: Self.utc) == [
            ConflictFact(label: "Location", value: "57B2"),
            ConflictFact(label: "Stock now", value: "9")
        ])
    }

    @Test("The record reference comes from the item only when it names the same record")
    func recordReference() throws {
        let pick = try Self.entry(Self.refusedPick)
        let audit = try Self.entry(Self.locationAudit)
        let skoCount = try Self.entry(#"{"operation_id": 80, "status": "conflict", "operation": "audit_org_stock", "record": {"type": "org_stock", "id": 9}, "item": {"sko_code": "BBUG-06", "record_type": "sko", "record_reference": "BBUG-06"}}"#)
        let move = try Self.entry(#"{"operation_id": 81, "status": "refused", "operation": "move_stock", "record": {"type": "org_stock", "id": 9}, "item": {"sko_code": "BBUG-06", "record_type": "location", "record_reference": "57B2"}}"#)

        #expect(ConflictDetails.recordReference(pick) == "AR24811")
        #expect(ConflictDetails.recordReference(audit) == "57B2")
        #expect(ConflictDetails.recordReference(skoCount) == "BBUG-06")
        #expect(ConflictDetails.recordReference(move) == nil)
    }

    @Test("Counted since, the stock check and the earlier action it waits for are kept as notes")
    func notes() throws {
        let entry = try Self.entry(#"{"operation_id": 90, "status": "blocked", "operation": "pick", "counted_since": true, "task_reference": "TASK-12", "blocked_by_sequence": 4}"#)

        #expect(ConflictDetails.notes(entry) == [
            ConflictFact(label: "Counted since", value: "Recorded without a stock movement"),
            ConflictFact(label: "Stock check", value: "TASK-12"),
            ConflictFact(label: "Waits for", value: "Your earlier action 4")
        ])
    }
}
