import Foundation
import Testing
@testable import Aiku

struct LocationsTests {
    private var warehouse: LocationsWarehouse { LocationsSampleData.warehouse }

    private func location(_ code: String, in warehouse: LocationsWarehouse) -> LocationsLocation {
        warehouse.location(code: code)!
    }

    @Test func locationWithEveryStockedSlotIsNeitherEmptyNorPartiallyEmpty() {
        let location = location("A-03-02", in: warehouse)

        #expect(location.occupancy == .stocked)
        #expect(location.slotSummary == "4 slots, 0 empty")
    }

    @Test func locationWithSomeEmptySlotsIsPartiallyEmpty() {
        let location = location("A-07-01", in: warehouse)

        #expect(location.isPartiallyEmpty)
        #expect(!location.isEmpty)
        #expect(location.slotSummary == "3 slots, 1 empty")
    }

    @Test func locationWithoutStockOrPalletsIsEmpty() {
        #expect(location("A-09-04", in: warehouse).isEmpty)
        #expect(location("B-09-05", in: warehouse).isEmpty)
    }

    @Test func fulfilmentLocationWithAPalletIsNotEmpty() {
        let location = location("D-02-01", in: warehouse)

        #expect(!location.isEmpty)
        #expect(location.listSubtitle == "Area D · 1 pallet")
    }

    @Test func capacityMathsMatchesTheDesignedListLine() {
        let location = location("A-03-02", in: warehouse)

        #expect(abs(location.usedWeightKg - 32) < 0.001)
        #expect(abs(location.usedVolumeM3 - 0.4) < 0.001)
        #expect(location.listSubtitle == "Area A · 4 slots, 0 empty · 32 / 120 kg · 0.4 / 1.2 m³")
        #expect(location.capacitySummary == "Max 120 kg · 1.2 m³ · 4 slots, 0 empty")
        #expect(abs((location.weightUtilisation ?? 0) - 32.0 / 120.0) < 0.001)
    }

    @Test func weightLimitIsExceededOnlyAboveTheMaximum() {
        let stocked = location("A-03-02", in: warehouse)

        #expect(!stocked.exceedsWeight(adding: 88))
        #expect(stocked.exceedsWeight(adding: 88.5))
        #expect(!location("B-09-05", in: warehouse).exceedsWeight(adding: 10_000))
    }

    @Test func scopeSummaryListsAllowedAndDeniedScopes() {
        #expect(location("A-03-02", in: warehouse).scopeSummary == "Allow stocks · dropshipping · not fulfilment")
    }

    @Test func listTabsFilterEmptyAndPartiallyEmptyLocations() {
        let empty = warehouse.list(tab: .empty, query: "", filter: .none).map(\.code)
        let partial = warehouse.list(tab: .partiallyEmpty, query: "", filter: .none).map(\.code)

        #expect(empty == ["A-09-04", "B-09-05", "D-01-01"])
        #expect(partial == ["A-07-01", "A-12-03", "C-04-02"])
        #expect(warehouse.count(tab: .all, query: "", filter: .none) == warehouse.locations.count)
    }

    @Test func searchMatchesLocationCodesThatStartWithTheQuery() {
        let codes = warehouse.list(tab: .all, query: "a-0", filter: .none).map(\.code)

        #expect(codes == ["A-03-02", "A-07-01", "A-09-04"])
        #expect(warehouse.list(tab: .all, query: "03-02", filter: .none).isEmpty)
    }

    @Test func filterSectionsCombineWithAndWhileOptionsCombineWithOr() {
        var filter = LocationsFilter()
        filter.areas = ["A", "B"]
        filter.statuses = [.operational]
        filter.occupancies = [.empty, .neverRemoveEmptySkos]

        let codes = warehouse.list(tab: .all, query: "", filter: filter).map(\.code)

        #expect(codes == ["A-09-04", "A-12-03"])
        #expect(filter.activeCount == 5)
        #expect(filter.summary == "5 filters · sorted by code")
    }

    @Test func brokenStatusFilterFindsBrokenLocations() {
        var filter = LocationsFilter()
        filter.statuses = [.broken]

        #expect(warehouse.list(tab: .all, query: "", filter: filter).map(\.code) == ["B-09-05"])
    }

    @Test func sortingByWeightPutsTheHeaviestFirst() {
        var filter = LocationsFilter()
        filter.sort = .weight

        #expect(warehouse.list(tab: .all, query: "", filter: filter).first?.code == "A-03-02")
    }

    @Test func auditUpdatesCountedQuantitiesAndCountsDifferences() {
        var warehouse = warehouse
        let date = LocationsSampleData.date(10, 11)

        let differences = warehouse.audit(
            location: "A-03-02",
            counts: [
                LocationsAuditCount(skoReference: "SKO-00733", counted: 28),
                LocationsAuditCount(skoReference: "SKO-00412", counted: 48),
                LocationsAuditCount(skoReference: "SKO-01402", counted: 124)
            ],
            on: date
        )

        let audited = location("A-03-02", in: warehouse)
        #expect(differences == 2)
        #expect(audited.slot("SKO-00733")?.quantity == 28)
        #expect(audited.slot("SKO-01402")?.quantity == 124)
        #expect(audited.auditedOn == date)
    }

    @Test func auditReasonsFollowTheDirectionOfTheChange() {
        #expect(LocationsAuditReason.options(forChange: -2) == [.countShortage, .damagedStock])
        #expect(LocationsAuditReason.options(forChange: 4) == [.countGain])
        #expect(LocationsAuditReason.options(forChange: 0).isEmpty)
    }

    @Test func movingAllSkosEmptiesTheSourceAndFillsTheTarget() {
        var warehouse = warehouse

        let moved = warehouse.move(
            from: "A-03-02", to: "A-09-04",
            quantities: warehouse.allQuantities(of: "A-03-02"),
            removeFromSource: false
        )

        #expect(moved == 206)
        #expect(location("A-03-02", in: warehouse).isEmpty)
        #expect(location("A-03-02", in: warehouse).slots.count == 4)
        #expect(location("A-09-04", in: warehouse).slot("SKO-00733")?.quantity == 30)
        #expect(location("A-09-04", in: warehouse).slot("SKO-00733")?.type == .storing)
    }

    @Test func movingWithRemoveTakesTheSourceOffTheSkos() {
        var warehouse = warehouse

        warehouse.move(from: "A-03-02", to: "A-09-04", quantities: warehouse.allQuantities(of: "A-03-02"), removeFromSource: true)

        #expect(location("A-03-02", in: warehouse).slots.isEmpty)
    }

    @Test func movingSomeAddsToAnExistingSlotAndCapsAtStock() {
        var warehouse = warehouse

        let moved = warehouse.move(from: "A-03-02", to: "B-01-04", quantities: ["SKO-00733": 50], removeFromSource: true)

        #expect(moved == 30)
        #expect(location("B-01-04", in: warehouse).slot("SKO-00733")?.quantity == 36)
        #expect(location("A-03-02", in: warehouse).slot("SKO-00733") == nil)
        #expect(location("A-03-02", in: warehouse).slots.count == 3)
    }

    @Test func movedWeightFeedsTheTargetCapacityCheck() {
        let weight = warehouse.movedWeight(from: "A-03-02", quantities: ["SKO-00733": 10, "SKO-00412": 5])

        #expect(abs(weight - 5) < 0.001)
    }

    @Test func moveTargetsExcludeTheSourceBrokenAndNonStockLocations() {
        let codes = warehouse.moveTargets(from: "A-03-02", query: "").map(\.code)

        #expect(codes.first == "A-09-04")
        #expect(!codes.contains("A-03-02"))
        #expect(!codes.contains("B-09-05"))
        #expect(!codes.contains("D-02-01"))
    }

    @Test func removingSkosTakesThemOutOfTheLocation() {
        var warehouse = warehouse

        let removed = warehouse.remove(skos: ["SKO-01402"], from: "A-03-02")

        #expect(removed == 1)
        #expect(location("A-03-02", in: warehouse).slot("SKO-01402") == nil)
        #expect(location("A-03-02", in: warehouse).slotSummary == "3 slots, 0 empty")
    }

    @Test func savingAPickingRoleTakesItFromTheOtherPickingLocation() {
        var warehouse = warehouse
        var settings = location("A-03-02", in: warehouse).slot("SKO-00733")!.settings
        settings.roles = [.wholesale, .dropshipping]
        settings.note = "  Moved to the front  "

        warehouse.update(sko: "SKO-00733", in: "A-03-02", settings: settings)

        let other = location("B-02-01", in: warehouse).slot("SKO-00733")
        #expect(other?.type == .storing)
        #expect(other?.roles.isEmpty == true)
        #expect(location("A-03-02", in: warehouse).slot("SKO-00733")?.note == "Moved to the front")
    }

    @Test func savingAsStoringClearsPickingRoles() {
        var warehouse = warehouse
        var settings = location("A-03-02", in: warehouse).slot("SKO-00733")!.settings
        settings.type = .storing

        warehouse.update(sko: "SKO-00733", in: "A-03-02", settings: settings)

        #expect(location("A-03-02", in: warehouse).slot("SKO-00733")?.roles.isEmpty == true)
        #expect(location("B-02-01", in: warehouse).slot("SKO-00733")?.type == .picking)
    }

    @Test func otherLocationHoldingARoleIsFound() {
        #expect(warehouse.otherLocation(holding: .dropshipping, sko: "SKO-00733", excluding: "A-03-02") == "B-02-01")
        #expect(warehouse.otherLocation(holding: .wholesale, sko: "SKO-00733", excluding: "A-03-02") == nil)
    }

    @Test func unknownCodesFallBackToTheFirstSampleRecord() {
        #expect(warehouse.locationOrFallback(code: "Z-99-99")?.code == "A-03-02")
        #expect(warehouse.areaOrFallback(code: "Z")?.code == "A")
        #expect(warehouse.location(code: "a-03-02")?.code == "A-03-02")
    }

    @Test func areaSearchMatchesCodeOrName() {
        #expect(warehouse.areas(matching: "over").map(\.code) == ["B"])
        #expect(warehouse.areas(matching: "d").map(\.code) == ["D"])
        #expect(warehouse.area(code: "A")?.subtitle == "Picking order 1 · 412 locations · 12 empty · [£ 96k]")
    }

    @MainActor
    @Test func storeUndoRestoresTheWarehouseBeforeAMove() {
        let store = LocationsStore()

        let result = store.move(from: "A-03-02", to: "A-09-04", quantities: ["SKO-00733": 10], removeFromSource: false)
        #expect(store.warehouse.location(code: "A-03-02")?.slot("SKO-00733")?.quantity == 20)

        store.restore(result.snapshot)
        #expect(store.warehouse.location(code: "A-03-02")?.slot("SKO-00733")?.quantity == 30)
        #expect(store.warehouse.location(code: "A-09-04")?.slots.isEmpty == true)
    }
}
