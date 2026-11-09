import Foundation

extension Decimal {
    var poundsFormatted: String {
        formatted(.currency(code: "GBP").locale(Locale(identifier: "en_GB")).precision(.fractionLength(2)))
    }

    var wholePoundsFormatted: String {
        formatted(.currency(code: "GBP").locale(Locale(identifier: "en_GB")).precision(.fractionLength(0)))
    }
}

extension Int {
    var grouped: String { formatted(.number.locale(Locale(identifier: "en_GB"))) }

    var signedChange: String {
        self > 0 ? "+\(self)" : self < 0 ? "−\(-self)" : "\(self)"
    }
}

extension Double {
    var trimmed: String { formatted(.number.precision(.fractionLength(0...1))) }
}
