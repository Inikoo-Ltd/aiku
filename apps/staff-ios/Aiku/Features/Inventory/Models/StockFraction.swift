import Foundation

struct StockFractionParts: Equatable, Sendable {
    let whole: Double
    let numerator: Double
    let denominator: Double
}

enum StockFraction {
    static func parts(_ value: Double, packedIn: Double?) -> StockFractionParts {
        let (dividend, divisor) = smallestFactors(abs(value), epsilon: startEpsilon, retry: 0)
        return riseDivisor(divideWithRemainder(dividend, divisor), raiser: packedIn ?? 1)
    }

    static func label(_ value: Double, packedIn: Double?) -> String {
        let split = parts(value, packedIn: packedIn)
        let sign = value < 0 ? "-" : ""
        let whole = plain(split.whole)
        let fraction = "\(plain(split.numerator))/\(plain(split.denominator))"
        switch (split.whole != 0, split.numerator != 0) {
        case (true, true): return "\(sign)\(whole) \(fraction)"
        case (true, false): return "\(sign)\(whole)"
        case (false, true): return "\(sign)\(fraction)"
        case (false, false): return "0"
        }
    }

    static func total(_ value: Double) -> Double {
        halfUp(value, decimals: 3)
    }

    private static func plain(_ value: Double) -> String {
        let rounded = halfUp(value, decimals: 3)
        if rounded == rounded.rounded(), abs(rounded) < 1e15 {
            return String(Int64(rounded))
        }
        var text = String(format: "%.3f", rounded)
        while text.hasSuffix("0") { text.removeLast() }
        if text.hasSuffix(".") { text.removeLast() }
        return text
    }

    private static func halfUp(_ value: Double, decimals: Int) -> Double {
        guard value.isFinite else { return value }
        var exact = Decimal(string: "\(value)", locale: Locale(identifier: "en_US_POSIX")) ?? Decimal(value)
        var rounded = Decimal()
        NSDecimalRound(&rounded, &exact, decimals, .plain)
        return Double(NSDecimalNumber(decimal: rounded).stringValue) ?? value
    }

    private static func phpRound(_ value: Double) -> Double {
        value < 0 ? -(-value + 0.5).rounded(.down) : (value + 0.5).rounded(.down)
    }

    private static func smallestFactors(_ number: Double, epsilon: Double, retry: Int) -> (Double, Double) {
        if number == 0 { return (0, 1) }
        if abs(number - tinyNumber) < 0.000001 { return (1, tinyDenominator) }
        if abs(number - 2.0001) < startEpsilon { return (2, 1) }
        let absolute = abs(number)
        if absolute < smallNumber { return smallNumberFactors(number) }
        let sign: Double = number < 0 ? -1 : 1
        let nearest = phpRound(absolute)
        if abs(absolute - nearest) < epsilon { return (sign * nearest, 1) }
        if absolute < 1 {
            var best = (numerator: 1.0, denominator: 1.0, difference: Double.greatestFiniteMagnitude)
            for denominator in 1...maxDenominator {
                var numerator = 1
                while Double(numerator) / Double(denominator) <= absolute + epsilon {
                    let difference = abs(Double(numerator) / Double(denominator) - absolute)
                    if difference < best.difference {
                        best = (Double(numerator), Double(denominator), difference)
                    }
                    numerator += 1
                }
            }
            if best.difference < epsilon { return (sign * best.numerator, best.denominator) }
        } else {
            if absolute - absolute.rounded(.down) < epsilon { return (sign * absolute, 1) }
            let whole = absolute.rounded(.down)
            let (numerator, denominator) = smallestFactors(absolute - whole, epsilon: epsilon, retry: 0)
            return (sign * (numerator + whole * denominator), denominator)
        }
        return retry < maxRetries ? smallestFactors(number, epsilon: epsilon * epsilonGrowth, retry: retry + 1) : (sign, 1)
    }

    private static func smallNumberFactors(_ number: Double) -> (Double, Double) {
        let absolute = abs(number)
        let sign: Double = number < 0 ? -1 : 1
        if absolute < tinyNumber { return (sign, tinyDenominator) }
        let nearest = phpRound(absolute)
        if abs(absolute - nearest) < nearInteger { return (sign * nearest, 1) }
        let special: [(at: Double, denominator: Double)] = [(smallNumber, 100), (0.001, 1000), (0.002, 500)]
        if let match = special.first(where: { abs(absolute - $0.at) < ($0.at == smallNumber ? tinyNumber : startEpsilon) }) {
            return (sign, match.denominator)
        }
        let denominator = phpRound(1 / absolute)
        return denominator > 0 ? (sign, denominator) : (sign, 1)
    }

    private static func divideWithRemainder(_ dividend: Double, _ divisor: Double) -> StockFractionParts {
        guard divisor != 0 else { return StockFractionParts(whole: 0, numerator: dividend, denominator: divisor) }
        let wholeDividend = Int64(dividend)
        let wholeDivisor = Int64(divisor)
        guard wholeDivisor != 0 else { return StockFractionParts(whole: 0, numerator: dividend, denominator: divisor) }
        return StockFractionParts(whole: Double(wholeDividend / wholeDivisor), numerator: Double(wholeDividend % wholeDivisor), denominator: divisor)
    }

    private static func riseDivisor(_ split: StockFractionParts, raiser: Double) -> StockFractionParts {
        guard split.denominator != 0, raiser != 0 else { return split }
        let numerator = split.numerator * raiser / split.denominator
        guard numerator == phpRound(numerator) else { return split }
        return StockFractionParts(whole: split.whole, numerator: phpRound(numerator), denominator: raiser)
    }

    private static let startEpsilon = 0.00001
    private static let maxRetries = 3
    private static let epsilonGrowth = 10.0
    private static let maxDenominator = 100
    private static let smallNumber = 0.01
    private static let tinyNumber = 0.0001
    private static let tinyDenominator = 10_000.0
    private static let nearInteger = 0.001
}
