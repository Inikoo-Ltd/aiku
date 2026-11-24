import Foundation

enum LinearBarcodeFormat: Equatable, Sendable {
    case ean13
    case code128
}

enum BarcodeEncoding {
    static let ean13ModuleCount = 95

    static func trimmed(_ value: String) -> String {
        value.trimmingCharacters(in: .whitespacesAndNewlines)
    }

    static func format(of value: String) -> LinearBarcodeFormat {
        let text = trimmed(value)
        let isThirteenDigits = text.count == 13 && text.allSatisfy { $0.isASCII && $0.isNumber }
        return isThirteenDigits ? .ean13 : .code128
    }

    static func ean13Modules(_ value: String) -> [Bool]? {
        let text = trimmed(value)
        guard format(of: text) == .ean13 else { return nil }
        let digits = text.compactMap { $0.wholeNumberValue }
        guard digits.count == 13, digits[12] == ean13CheckDigit(Array(digits.prefix(12))) else { return nil }
        let parity = Array(leftParity[digits[0]])
        var pattern = startGuard
        for (index, digit) in digits[1...6].enumerated() {
            pattern += parity[index] == "G" ? evenCodes[digit] : oddCodes[digit]
        }
        pattern += middleGuard
        for digit in digits[7...12] {
            pattern += rightCodes[digit]
        }
        pattern += endGuard
        return pattern.map { $0 == "1" }
    }

    static func ean13CheckDigit(_ firstTwelve: [Int]) -> Int {
        let sum = firstTwelve.enumerated().reduce(0) { total, item in
            total + item.element * (item.offset.isMultiple(of: 2) ? 1 : 3)
        }
        return (10 - sum % 10) % 10
    }

    static func code128Message(_ value: String) -> Data? {
        let text = trimmed(value)
        guard !text.isEmpty, text.allSatisfy({ $0.isASCII }) else { return nil }
        return text.data(using: .ascii)
    }

    static func modules(fromDarkness row: [Bool]) -> [Bool]? {
        guard let first = row.firstIndex(of: true), let last = row.lastIndex(of: true) else { return nil }
        return Array(row[first...last])
    }

    private static let startGuard = "101"
    private static let middleGuard = "01010"
    private static let endGuard = "101"

    private static let oddCodes = [
        "0001101", "0011001", "0010011", "0111101", "0100011",
        "0110001", "0101111", "0111011", "0110111", "0001011",
    ]

    private static let evenCodes = [
        "0100111", "0110011", "0011011", "0100001", "0011101",
        "0111001", "0000101", "0010001", "0001001", "0010111",
    ]

    private static let rightCodes = [
        "1110010", "1100110", "1101100", "1000010", "1011100",
        "1001110", "1010000", "1000100", "1001000", "1110100",
    ]

    private static let leftParity = [
        "LLLLLL", "LLGLGG", "LLGGLG", "LLGGGL", "LGLLGG",
        "LGGLLG", "LGGGLL", "LGLGLG", "LGLGGL", "LGGLGL",
    ]
}
