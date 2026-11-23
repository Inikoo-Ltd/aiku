import Foundation

enum FractionGlyphs {
    static func apply(_ text: String) -> String {
        let range = NSRange(text.startIndex..., in: text)
        var shown = text
        for match in pattern.matches(in: text, range: range).reversed() {
            guard let whole = Range(match.range, in: text) else { continue }
            let replacement: String
            if let unit = group(1, of: match, in: text), let numerator = group(2, of: match, in: text), let denominator = group(3, of: match, in: text) {
                replacement = "\(unit)\u{202F}\(raised(numerator))\u{2044}\(lowered(denominator))"
            } else if let numerator = group(4, of: match, in: text), let denominator = group(5, of: match, in: text) {
                replacement = "\(raised(numerator))\u{2044}\(lowered(denominator))"
            } else {
                continue
            }
            shown.replaceSubrange(whole, with: replacement)
        }
        return shown
    }

    static func apply(_ text: String?) -> String? {
        text.map { apply($0) }
    }

    private static func group(_ index: Int, of match: NSTextCheckingResult, in text: String) -> String? {
        let range = match.range(at: index)
        guard range.location != NSNotFound, let bounds = Range(range, in: text) else { return nil }
        return String(text[bounds])
    }

    private static func raised(_ digits: String) -> String {
        String(digits.compactMap { $0.wholeNumberValue.map { superscripts[$0] } })
    }

    private static func lowered(_ digits: String) -> String {
        String(digits.compactMap { $0.wholeNumberValue.map { subscripts[$0] } })
    }

    private static let superscripts: [Character] = ["⁰", "¹", "²", "³", "⁴", "⁵", "⁶", "⁷", "⁸", "⁹"]
    private static let subscripts: [Character] = ["₀", "₁", "₂", "₃", "₄", "₅", "₆", "₇", "₈", "₉"]
    nonisolated(unsafe) private static let pattern = try! NSRegularExpression(pattern: #"(\d) (\d+)/(\d+)(?![\d/])|(?<![\d/.])(\d+)/(\d+)(?![\d/])"#)
}
