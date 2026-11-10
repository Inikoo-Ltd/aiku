import Foundation

struct RGBA: Equatable, Sendable {
    var red: Double
    var green: Double
    var blue: Double
    var alpha: Double

    init(red: Double, green: Double, blue: Double, alpha: Double = 1) {
        self.red = red
        self.green = green
        self.blue = blue
        self.alpha = alpha
    }

    init(_ hex: UInt32, alpha: Double = 1) {
        self.init(
            red: Double((hex >> 16) & 0xFF) / 255,
            green: Double((hex >> 8) & 0xFF) / 255,
            blue: Double(hex & 0xFF) / 255,
            alpha: alpha
        )
    }

    static let white = RGBA(0xFFFFFF)
    static let black = RGBA(0x000000)

    func mixed(_ weight: Double, with other: RGBA) -> RGBA {
        RGBA(
            red: red * weight + other.red * (1 - weight),
            green: green * weight + other.green * (1 - weight),
            blue: blue * weight + other.blue * (1 - weight),
            alpha: alpha * weight + other.alpha * (1 - weight)
        )
    }

    func opacity(_ value: Double) -> RGBA {
        RGBA(red: red, green: green, blue: blue, alpha: value)
    }

    var hex: String {
        let channels = [red, green, blue].map { Int(($0 * 255).rounded()) }
        return String(format: "#%02X%02X%02X", channels[0], channels[1], channels[2])
    }
}
