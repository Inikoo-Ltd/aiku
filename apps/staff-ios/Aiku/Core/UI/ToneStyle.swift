import SwiftUI

extension Tone {
    @MainActor var foreground: Color {
        switch self {
        case .neutral: AikuColor.text2
        case .info: AikuColor.ink
        case .success: AikuColor.success
        case .warn: AikuColor.warn
        case .danger: AikuColor.danger
        case .orange: AikuColor.orange
        case .lime: AikuColor.lime
        }
    }

    @MainActor var background: Color {
        switch self {
        case .neutral: AikuColor.bg
        case .info: AikuColor.soft
        case .success: AikuColor.successSoft
        case .warn: AikuColor.warnSoft
        case .danger: AikuColor.dangerSoft
        case .orange: AikuColor.orangeSoft
        case .lime: AikuColor.limeSoft
        }
    }

    @MainActor var outline: Color {
        switch self {
        case .neutral: AikuColor.border
        case .info: AikuColor.softBd
        case .warn: AikuColor.warnBd
        case .danger: AikuColor.dangerBd
        case .success, .orange, .lime: foreground.opacity(0.35)
        }
    }

    @MainActor var ink: Color {
        switch self {
        case .warn: AikuColor.warnInk
        case .danger: AikuColor.dangerInk
        case .neutral: AikuColor.text
        case .info, .success, .orange, .lime: foreground
        }
    }
}
