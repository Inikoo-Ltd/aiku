import Foundation
import Testing
@testable import Aiku

struct InventoryTests {
    private var rose: InventorySko {
        InventorySampleData.skos.first { $0.reference == "SKO-00733" }!
    }

    @Test func stockIsTheSumOfLocationQuantities() {
        #expect(rose.stock == 36)
        #expect(rose.locations.count == 3)
    }

    @Test func sampleStatusesMatchTheDesign() {
        let statuses = Dictionary(uniqueKeysWithValues: InventorySampleData.skos.map { ($0.reference, $0.status) })
        #expect(statuses["SKO-00733"] == .low)
        #expect(statuses["SKO-00412"] == .ideal)
        #expect(statuses["SKO-01187"] == .critical)
        #expect(statuses["SKO-00953"] == .outOfStock)
        #expect(statuses["SKO-01402"] == .excess)
    }

    @Test func negativeLocationStockIsAnError() {
        var sko = rose
        sko.locations[1].quantity = -3
        #expect(sko.hasNegativeStock)
        #expect(sko.status == .error)
    }

    @Test func abnormalitiesAreSkosInTheAbnormalityState() {
        let abnormal = InventorySkoQuery.results(InventorySampleData.skos, scope: .abnormalities)
        #expect(abnormal.map(\.reference) == ["SKO-X-0192", "SKO-X-0207"])
        #expect(abnormal.allSatisfy { $0.listChip == Chip("Abnormality", .danger) })
        #expect(InventorySkoQuery.results(InventorySampleData.skos, scope: .current).allSatisfy { !$0.isAbnormal })
    }

    @Test func searchMatchesReferenceNameAndSupplierCode() {
        let skos = InventorySampleData.skos
        #expect(InventorySkoQuery.results(skos, search: "00733").map(\.reference) == ["SKO-00733"])
        #expect(InventorySkoQuery.results(skos, search: "eucalyptus").map(\.reference) == ["SKO-01402"])
        #expect(InventorySkoQuery.results(skos, search: "AWCA-VAN").map(\.reference) == ["SKO-01187"])
        #expect(InventorySkoQuery.results(skos, search: "nothing like this").isEmpty)
    }

    @Test func filtersCombineAcrossSections() {
        var filter = InventorySkoFilter()
        filter.familyCodes = ["BB"]
        #expect(InventorySkoQuery.results(InventorySampleData.skos, filter: filter).count == 3)
        filter.statuses = [.low]
        #expect(InventorySkoQuery.results(InventorySampleData.skos, filter: filter).map(\.reference) == ["SKO-00733"])
        #expect(filter.activeCount == 2)
        #expect(filter.summary == "2 filters · sorted by reference")
    }

    @Test func areaAndLocationTypeFiltersLookAtEveryLocation() {
        var filter = InventorySkoFilter()
        filter.areas = [.d]
        #expect(InventorySkoQuery.results(InventorySampleData.skos, filter: filter).map(\.reference) == ["SKO-01402"])
        filter = InventorySkoFilter(locationTypes: [.storing])
        #expect(InventorySkoQuery.results(InventorySampleData.skos, filter: filter).allSatisfy { sko in
            sko.locations.contains { $0.type == .storing }
        })
    }

    @Test func stockLowFirstSortPutsEmptySkosFirst() {
        let sorted = InventorySkoQuery.sorted(InventorySampleData.skos.filter { !$0.isAbnormal }, by: .stockLowFirst)
        #expect(sorted.first?.reference == "SKO-00953")
        #expect(sorted.last?.reference == "SKO-01402")
    }

    @Test func emptyFilterSummarySaysNoFilters() {
        #expect(InventorySkoFilter().summary == "No filters · sorted by reference")
    }

    @Test func auditNeedsAReasonForEveryChangedLocation() {
        var draft = InventoryAuditDraft(sko: rose)
        #expect(draft.canSave(rose))
        draft.counts["A-03-02"] = 28
        #expect(draft.missingReasons(in: rose) == ["A-03-02"])
        draft.reasons["A-03-02"] = .countGain
        #expect(draft.canSave(rose) == false)
        draft.reasons["A-03-02"] = .damagedStock
        #expect(draft.canSave(rose))
    }

    @Test func auditUpdatesQuantitiesAndRecordsTheChange() throws {
        var draft = InventoryAuditDraft(sko: rose)
        draft.counts["A-03-02"] = 28
        draft.reasons["A-03-02"] = .damagedStock
        let result = try InventoryStockOperations.audit(rose, draft: draft, auditedOn: "10 Nov", time: "14:05")
        #expect(result.sko.location("A-03-02")?.quantity == 28)
        #expect(result.sko.location("A-03-02")?.lastAudited == "10 Nov")
        #expect(result.sko.stock == 34)
        #expect(result.movements.count == 1)
        #expect(result.movements[0].chip == Chip("Audit −2", .warn))
        #expect(result.movements[0].summary == "A-03-02 · Damaged stock · you · 14:05 · running 34")
        #expect(result.message == "Audit saved · −2")
    }

    @Test func auditWithoutReasonIsRejected() {
        var draft = InventoryAuditDraft(sko: rose)
        draft.counts["C-04-02"] = 9
        #expect(throws: InventoryStockError.missingReason("C-04-02")) {
            try InventoryStockOperations.audit(rose, draft: draft, auditedOn: "10 Nov", time: "14:05")
        }
    }

    @Test func moveShiftsQuantityBetweenLocations() throws {
        let result = try InventoryStockOperations.move(rose, from: "A-03-02", to: "C-04-02", quantity: 12, reason: .warehouseTransfer, time: "14:05")
        #expect(result.sko.location("A-03-02")?.quantity == 18)
        #expect(result.sko.location("C-04-02")?.quantity == 18)
        #expect(result.sko.stock == rose.stock)
        #expect(result.message == "12 units moved to C-04-02")
        #expect(result.movements[0].chip == Chip("Location transfer 12", .info))
    }

    @Test func moveCannotTakeMoreThanAvailable() {
        #expect(throws: InventoryStockError.notEnoughStock(available: 30)) {
            try InventoryStockOperations.move(rose, from: "A-03-02", to: "C-04-02", quantity: 31, reason: .wrongBinLocation, time: "14:05")
        }
    }

    @Test func moveRejectsSameLocationAndZeroQuantity() {
        #expect(throws: InventoryStockError.sameLocation) {
            try InventoryStockOperations.move(rose, from: "A-03-02", to: "A-03-02", quantity: 1, reason: .warehouseTransfer, time: "14:05")
        }
        #expect(throws: InventoryStockError.quantityMustBePositive) {
            try InventoryStockOperations.move(rose, from: "A-03-02", to: "C-04-02", quantity: 0, reason: .warehouseTransfer, time: "14:05")
        }
    }

    @Test func consumptionReducesStock() throws {
        let result = try InventoryStockOperations.consume(rose, location: "A-03-02", quantity: 1, note: "Showroom display", time: "14:05")
        #expect(result.sko.stock == 35)
        #expect(result.movements[0].quantity == -1)
        #expect(result.movements[0].chip == Chip("Consumption −1", .neutral))
        #expect(result.message == "1 unit consumed from A-03-02")
    }

    @Test func consumptionCannotExceedTheLocation() {
        #expect(throws: InventoryStockError.notEnoughStock(available: 6)) {
            try InventoryStockOperations.consume(rose, location: "C-04-02", quantity: 7, note: "", time: "14:05")
        }
    }

    @Test func addingAPickingLocationTurnsTheOtherPickingLocationIntoStoring() throws {
        let result = try InventoryStockOperations.addLocation(rose, location: "A-09-04", type: .picking, time: "14:05")
        #expect(result.sko.locations.count == 4)
        #expect(result.sko.location("A-09-04")?.type == .picking)
        #expect(result.sko.location("A-03-02")?.type == .storing)
        #expect(result.sko.location("B-02-01")?.type == .picking)
    }

    @Test func addingAnExistingLocationIsRejected() {
        #expect(throws: InventoryStockError.locationAlreadyAssigned("A-03-02")) {
            try InventoryStockOperations.addLocation(rose, location: "a-03-02", type: .storing, time: "14:05")
        }
    }

    @Test func removingALocationWithStockMarksItAsLost() throws {
        let result = try InventoryStockOperations.removeLocations(rose, codes: ["B-02-01", "C-04-02"], time: "14:05")
        #expect(result.sko.locations.map(\.code) == ["A-03-02"])
        #expect(result.sko.stock == 30)
        #expect(result.movements.contains { $0.kind == .audit && $0.quantity == -6 })
        #expect(result.message == "2 locations removed")
    }

    @Test func historyFiltersSplitInOutAndAudit() {
        let movements = InventorySampleData.movements
        #expect(movements.filter(InventoryHistoryFilter.stockIn.includes).map(\.skoReference) == ["SKO-00412"])
        #expect(movements.filter(InventoryHistoryFilter.stockOut.includes).map(\.skoReference) == ["SKO-00733", "SKO-00740"])
        #expect(movements.filter(InventoryHistoryFilter.audit.includes).map(\.skoReference) == ["SKO-00953", "SKO-01402"])
        #expect(movements.filter(InventoryHistoryFilter.all.includes).count == 6)
    }

    @MainActor
    @Test func storeAppliesMovesAddsHistoryAndUndoes() throws {
        let store = InventoryStore()
        let message = try store.move("SKO-00733", from: "A-03-02", to: "C-04-02", quantity: 10, reason: .warehouseTransfer)
        #expect(message == "10 units moved to C-04-02")
        #expect(store.sko(reference: "SKO-00733").location("C-04-02")?.quantity == 16)
        #expect(store.movements.count == 7)
        #expect(store.movements[0].kind == .locationTransfer)
        store.undo()
        #expect(store.sko(reference: "SKO-00733").location("C-04-02")?.quantity == 6)
        #expect(store.movements.count == 6)
        #expect(store.canUndo == false)
    }

    @MainActor
    @Test func storeFallsBackToTheFirstSampleForUnknownReferences() {
        let store = InventoryStore()
        #expect(store.sko(reference: "SKO-99999").reference == InventorySampleData.skos[0].reference)
        #expect(store.family(code: "ZZ").code == "BB")
    }

    @MainActor
    @Test func storeListsOnlyFreeLocationsToAdd() {
        let store = InventoryStore()
        let options = store.availableLocations(for: store.sko(reference: "SKO-00733"), search: "")
        #expect(!options.contains { $0.code == "A-03-02" || $0.code == "C-04-02" })
        #expect(options.first?.code == "A-09-04")
    }
}
