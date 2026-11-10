import SwiftUI

extension RGBA {
    var color: Color {
        Color(.sRGB, red: red, green: green, blue: blue, opacity: alpha)
    }
}

extension Color {
    init(hex: UInt32, opacity: Double = 1) {
        self = RGBA(hex, alpha: opacity).color
    }
}

extension AppearanceMode {
    var colorScheme: ColorScheme? {
        switch self {
        case .light: .light
        case .dark: .dark
        case .system: nil
        }
    }
}

@MainActor
enum AikuColor {
    private static var palette: Palette { ThemeStore.shared.palette }

    static var nav: Color { palette.nav.color }
    static var onNav: Color { palette.onNav.color }
    static var onNav2: Color { palette.onNav2.color }
    static var navAct: Color { palette.navAct.color }
    static var navAccent: Color { palette.navAccent.color }
    static var navRing: Color { palette.navRing.color }
    static var primary: Color { palette.primary.color }
    static var onPrimary: Color { palette.onPrimary.color }
    static var primaryBd: Color { palette.primaryBd.color }
    static var scan: Color { palette.scan.color }
    static var onScan: Color { palette.onScan.color }
    static var link: Color { palette.link.color }
    static var ink: Color { palette.ink.color }
    static var soft: Color { palette.soft.color }
    static var softBd: Color { palette.softBd.color }
    static var bg: Color { palette.bg.color }
    static var surface: Color { palette.surface.color }
    static var surface2: Color { palette.surface2.color }
    static var border: Color { palette.border.color }
    static var borderStrong: Color { palette.borderStrong.color }
    static var divider: Color { palette.divider.color }
    static var text: Color { palette.text.color }
    static var text2: Color { palette.text2.color }
    static var text3: Color { palette.text3.color }
    static var text4: Color { palette.text4.color }
    static var faint: Color { palette.faint.color }
    static var danger: Color { palette.danger.color }
    static var dangerSoft: Color { palette.dangerSoft.color }
    static var dangerBd: Color { palette.dangerBd.color }
    static var dangerInk: Color { palette.dangerInk.color }
    static var dangerDot: Color { palette.dangerDot.color }
    static var success: Color { palette.success.color }
    static var successSoft: Color { palette.successSoft.color }
    static var warn: Color { palette.warn.color }
    static var warnSoft: Color { palette.warnSoft.color }
    static var warnBd: Color { palette.warnBd.color }
    static var warnInk: Color { palette.warnInk.color }
    static var orange: Color { palette.orange.color }
    static var orangeSoft: Color { palette.orangeSoft.color }
    static var lime: Color { palette.lime.color }
    static var limeSoft: Color { palette.limeSoft.color }
    static var shadow: Color { palette.shadow.color }

    static let toastBg = Color(hex: 0x1F2937)
    static let toastText = Color(hex: 0xF9FAFB)
    static let badge = Color(hex: 0xEF4444)
    static let onBadge = Color.white
    static let liveDot = Color(hex: 0x22C55E)
    static let scrim = Color(hex: 0x111827, opacity: 0.55)
    static var navPill: Color { Color.white.opacity(0.12) }
}

extension Font {
    static func aiku(_ size: CGFloat, _ weight: Font.Weight = .regular) -> Font {
        .system(size: size, weight: weight)
    }

    static func aikuWordmark(_ size: CGFloat) -> Font {
        .system(size: size, weight: .bold, design: .serif)
    }
}
