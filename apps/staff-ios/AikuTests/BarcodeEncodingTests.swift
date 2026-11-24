import Foundation
import Testing
@testable import Aiku

struct BarcodeEncodingTests {
    private static func bits(_ pattern: String) -> [Bool] {
        pattern.map { $0 == "1" }
    }

    @Test func thirteenDigitCodesAreDrawnAsEAN13LikeTheWebsite() throws {
        #expect(BarcodeEncoding.format(of: "5055796532315") == .ean13)
        let modules = try #require(BarcodeEncoding.ean13Modules("5055796532315"))
        #expect(modules.count == BarcodeEncoding.ean13ModuleCount)
        #expect(Array(modules[0..<3]) == Self.bits("101"))
        #expect(Array(modules[3..<10]) == Self.bits("0001101"))
        #expect(Array(modules[10..<17]) == Self.bits("0111001"))
        #expect(Array(modules[45..<50]) == Self.bits("01010"))
        #expect(Array(modules[50..<57]) == Self.bits("1001110"))
        #expect(Array(modules[92..<95]) == Self.bits("101"))
    }

    @Test func otherCodesAreDrawnAsCode128() {
        #expect(BarcodeEncoding.format(of: "5055796579259N") == .code128)
        #expect(BarcodeEncoding.format(of: "12345") == .code128)
        #expect(BarcodeEncoding.format(of: "50557965323150") == .code128)
        #expect(BarcodeEncoding.code128Message(" ABC-12 ") == Data("ABC-12".utf8))
    }

    @Test func anEAN13WithAWrongCheckDigitOrABlankCodeIsNotDrawn() {
        #expect(BarcodeEncoding.ean13Modules("5055796532316") == nil)
        #expect(BarcodeEncoding.ean13Modules("12345") == nil)
        #expect(BarcodeEncoding.code128Message("  ") == nil)
        #expect(BarcodeEncoding.code128Message("Crème") == nil)
        #expect(LinearBarcodeModules.of("5055796532316") == nil)
        #expect(LinearBarcodeModules.of("  ") == nil)
    }

    @Test func theCodeIsTrimmedBeforeItIsEncoded() {
        #expect(BarcodeEncoding.format(of: " 5055796532315\n") == .ean13)
        #expect(BarcodeEncoding.ean13Modules(" 5055796532315\n") == BarcodeEncoding.ean13Modules("5055796532315"))
    }

    @Test func theCheckDigitFollowsTheEANWeights() {
        #expect(BarcodeEncoding.ean13CheckDigit([5, 0, 5, 5, 7, 9, 6, 5, 3, 2, 3, 1]) == 5)
        #expect(BarcodeEncoding.ean13CheckDigit([4, 0, 0, 6, 3, 8, 1, 3, 3, 3, 9, 3]) == 1)
    }

    @Test func quietSpaceAroundTheBarsIsDropped() {
        #expect(BarcodeEncoding.modules(fromDarkness: [false, true, false, true, false]) == [true, false, true])
        #expect(BarcodeEncoding.modules(fromDarkness: [false, false]) == nil)
    }

    @Test func code128StartsAndEndsWithABar() throws {
        let modules = try #require(LinearBarcodeModules.of("5055796579259N"))
        #expect(modules.first == true)
        #expect(modules.last == true)
        #expect(modules.contains(false))
    }
}
