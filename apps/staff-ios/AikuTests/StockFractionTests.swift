import Foundation
import Testing
@testable import Aiku

struct StockFractionTests {
    @Test("Quantities read as the web's fractions", arguments: [
        (2.875, 8.0, "2 7/8"),
        (28.625, 16.0, "28 10/16"),
        (2 + 87.0 / 95.0, 1.0, "2 87/95"),
        (921 + 31.0 / 46.0, 1.0, "921 31/46"),
        (0.75, 1.0, "3/4"),
        (-1.5, 2.0, "-1 1/2"),
        (12.0, 6.0, "12"),
        (0.0, 1.0, "0"),
    ])
    func labels(value: Double, packedIn: Double, label: String) {
        #expect(StockFraction.label(value, packedIn: packedIn) == label)
    }

    @Test("Totals round to three decimals like the web stores them")
    func totals() {
        #expect(StockFraction.total(100.12349) == 100.123)
        #expect(StockFraction.total(1.0005) == 1.001)
        #expect(StockFraction.label(100.5, packedIn: nil) == "100 1/2")
    }
}
