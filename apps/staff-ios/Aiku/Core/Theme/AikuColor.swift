import SwiftUI

extension Color {
    init(hex: UInt32) {
        self.init(
            .sRGB,
            red: Double((hex >> 16) & 0xFF) / 255,
            green: Double((hex >> 8) & 0xFF) / 255,
            blue: Double(hex & 0xFF) / 255
        )
    }
}

enum AikuColor {
    static let indigo = Color(hex: 0x4F46E5)
    static let indigoDeep = Color(hex: 0x362CDB)
    static let indigoRing = Color(hex: 0x5E56E2)
    static let indigoText = Color(hex: 0x3730A3)
    static let indigoSoft = Color(hex: 0xEEF2FF)
    static let indigoMuted = Color(hex: 0xE0E7FF)
    static let lavender = Color(hex: 0xEDECFC)
    static let navy = Color(hex: 0x282373)
    static let yellow = Color(hex: 0xFDE047)
    static let underline = Color(hex: 0xFCD34D)

    static let background = Color(hex: 0xF3F4F6)
    static let surface = Color.white
    static let surfaceMuted = Color(hex: 0xF9FAFB)
    static let border = Color(hex: 0xE5E7EB)
    static let borderStrong = Color(hex: 0xD1D5DB)
    static let divider = Color(hex: 0xF3F4F6)

    static let textPrimary = Color(hex: 0x111827)
    static let textBody = Color(hex: 0x374151)
    static let textSecondary = Color(hex: 0x4B5563)
    static let textMuted = Color(hex: 0x6B7280)
    static let textFaint = Color(hex: 0x9CA3AF)

    static let success = Color(hex: 0x15803D)
    static let successSoft = Color(hex: 0xF0FDF4)
    static let warning = Color(hex: 0xB45309)
    static let warningSoft = Color(hex: 0xFFFBEB)
    static let warningDot = Color(hex: 0xF59E0B)
    static let critical = Color(hex: 0xC2410C)
    static let criticalSoft = Color(hex: 0xFFF7ED)
    static let criticalDot = Color(hex: 0xF97316)
    static let danger = Color(hex: 0xDC2626)
    static let dangerSoft = Color(hex: 0xFEF2F2)
    static let dangerDot = Color(hex: 0xEF4444)
    static let progress = Color(hex: 0x4D7C0F)
    static let progressSoft = Color(hex: 0xF7FEE7)
}
