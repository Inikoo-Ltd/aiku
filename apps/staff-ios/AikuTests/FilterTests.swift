import Testing
@testable import Aiku

struct SKUFilterTests {
    @Test func allLevelsSortedByReference() {
        let result = SKUFilter().apply(to: SampleData.skus.reversed())

        #expect(result.map(\.reference) == ["SKU-00412", "SKU-00733", "SKU-00953", "SKU-01187", "SKU-01402"])
    }

    @Test func filtersByStockLevel() {
        let result = SKUFilter(level: .outOfStock).apply(to: SampleData.skus)

        #expect(result.map(\.reference) == ["SKU-01402"])
    }

    @Test func searchesReferenceAndNameCaseInsensitively() {
        #expect(SKUFilter(query: "candle").apply(to: SampleData.skus).map(\.reference) == ["SKU-01187"])
        #expect(SKUFilter(query: " sku-007 ").apply(to: SampleData.skus).map(\.reference) == ["SKU-00733"])
    }

    @Test func combinesLevelAndQuery() {
        #expect(SKUFilter(level: .low, query: "candle").apply(to: SampleData.skus).isEmpty)
    }
}

struct LocationFilterTests {
    @Test func filtersByArea() {
        let result = LocationFilter(area: .area("Area B")).apply(to: SampleData.locations)

        #expect(result.map(\.code) == ["B-01-04", "B-02-01"])
    }

    @Test func filtersBroken() {
        #expect(LocationFilter(area: .broken).apply(to: SampleData.locations).map(\.code) == ["C-04-02"])
    }

    @Test func searchesCode() {
        #expect(LocationFilter(query: "a-12").apply(to: SampleData.locations).map(\.code) == ["A-12-03"])
    }
}

struct StockDeliveryFilterTests {
    @Test func openListsNextStepFirst() {
        let result = StockDeliveryFilter().apply(to: SampleData.stockDeliveries)

        #expect(result.map(\.reference) == ["SD-1088", "SD-1093", "SD-1091", "SD-1096", "SD-1097"])
    }

    @Test func filtersByState() {
        let result = StockDeliveryFilter(state: .state(.dispatched)).apply(to: SampleData.stockDeliveries)

        #expect(result.allSatisfy { $0.state == .dispatched })
        #expect(result.count == 2)
    }
}

struct DeliveryNoteFilterTests {
    @Test func defaultsToUnassigned() {
        let result = DeliveryNoteFilter().apply(to: SampleData.deliveryNotes)

        #expect(result.count == 5)
        #expect(result.allSatisfy { $0.state == .unassigned })
    }

    @Test func searchesReferenceWithinState() {
        let result = DeliveryNoteFilter(state: .handling, query: "24817").apply(to: SampleData.deliveryNotes)

        #expect(result.map(\.reference) == ["DN-24817"])
    }
}
