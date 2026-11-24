import Foundation
import Testing
@testable import Aiku

@MainActor
struct InventoryStockConfirmTests {
    private var rose: InventorySko {
        InventorySampleData.skos.first { $0.reference == "SKO-00733" }!
    }

    private var fractionalRose: InventorySko {
        var sko = rose
        sko.locations[0].quantity = 32
        sko.locations[0].link = InventoryLocationLink(linkID: 1, locationID: 10, exactQuantity: 32.75, quantityLabel: FractionGlyphs.apply("32 3/4"))
        return sko
    }

    @Test func auditConfirmListsEachCountedLocationWithWebFractions() {
        let summary = StockConfirmText.auditSummary(fractionalRose, counts: ["A-03-02": 33, "C-04-02": 6])

        #expect(summary == "A-03-02: 32\u{202F}³⁄₄ → 33\nC-04-02: 6, no change")
    }

    @Test func auditConfirmOnlyNamesTheLocationsBeingCounted() {
        #expect(StockConfirmText.auditSummary(rose, counts: ["C-04-02": 4]) == "C-04-02: 6 → 4")
    }

    @Test func aCountLeftAsItWasShowsTheStockLabel() {
        let location = fractionalRose.locations[0]

        #expect(StockConfirmText.countedText(32, was: location.quantity, wasText: location.quantityText, packedIn: 4) == "32\u{202F}³⁄₄")
        #expect(StockConfirmText.countedText(34, was: location.quantity, wasText: location.quantityText, packedIn: 4) == "34")
    }

    @Test func moveConfirmSaysHowMuchGoesWhere() {
        #expect(StockConfirmText.moveSummary(quantity: 1, from: "W9C2", to: "58A7", packedIn: nil) == "1 from W9C2 to 58A7.")
    }

    @Test func removingALocationWithStockStatesTheWriteOff() {
        let location = InventorySkoLocation(code: "58A7", type: .storing, quantity: 1)

        #expect(StockConfirmText.removeTitle(location) == "Remove 58A7?")
        #expect(StockConfirmText.removeMessage(location, skoReference: "SKO-00733") == "The 1 still in 58A7 will be written off.")
    }

    @Test func removingAnEmptyLocationOnlyUnlinksIt() {
        let location = InventorySkoLocation(code: "B-02-01", type: .picking, quantity: 0)

        #expect(StockConfirmText.removeMessage(location, skoReference: "SKO-00733") == "B-02-01 will no longer be a location of SKO-00733.")
    }

    @Test func auditingOneLocationLeavesTheOthersUntouched() throws {
        var draft = InventoryAuditDraft(sko: rose, only: ["C-04-02"])
        draft.counts["C-04-02"] = 4
        draft.counts["A-03-02"] = 10
        draft.reasons["C-04-02"] = .damagedStock

        #expect(draft.missingReasons(in: rose).isEmpty)
        let result = try InventoryStockOperations.audit(rose, draft: draft, auditedOn: "24 Nov", time: "10:00")

        #expect(result.sko.location("C-04-02")?.quantity == 4)
        #expect(result.sko.location("C-04-02")?.lastAudited == "24 Nov")
        #expect(result.sko.location("A-03-02")?.quantity == 30)
        #expect(result.sko.location("A-03-02")?.lastAudited == "28 Oct")
        #expect(result.movements.count == 1)
    }
}
