import SwiftUI

enum BadgeTone {
    case indigo
    case neutral
    case success
    case warning
    case critical
    case danger
    case progress

    var foreground: Color {
        switch self {
        case .indigo: AikuColor.indigoText
        case .neutral: AikuColor.textBody
        case .success: AikuColor.success
        case .warning: AikuColor.warning
        case .critical: AikuColor.critical
        case .danger: AikuColor.danger
        case .progress: AikuColor.progress
        }
    }

    var background: Color {
        switch self {
        case .indigo: AikuColor.indigoSoft
        case .neutral: AikuColor.background
        case .success: AikuColor.successSoft
        case .warning: AikuColor.warningSoft
        case .critical: AikuColor.criticalSoft
        case .danger: AikuColor.dangerSoft
        case .progress: AikuColor.progressSoft
        }
    }
}

struct StatusBadge: View {
    let text: String
    var tone: BadgeTone = .neutral

    var body: some View {
        Text(text)
            .font(.caption2.weight(.semibold))
            .foregroundStyle(tone.foreground)
            .padding(.horizontal, 6)
            .padding(.vertical, 2)
            .background(tone.background, in: RoundedRectangle(cornerRadius: 6))
    }
}

extension StockLevel {
    var tone: BadgeTone {
        switch self {
        case .normal: .neutral
        case .low: .warning
        case .critical: .critical
        case .outOfStock: .danger
        }
    }

    var dot: Color {
        switch self {
        case .normal: AikuColor.success
        case .low: AikuColor.warningDot
        case .critical: AikuColor.criticalDot
        case .outOfStock: AikuColor.dangerDot
        }
    }
}

extension StockDeliveryState {
    var tone: BadgeTone {
        switch self {
        case .dispatched: .neutral
        case .received: .success
        case .checked: .indigo
        case .placed: .success
        }
    }
}

extension DeliveryNoteState {
    var tone: BadgeTone {
        switch self {
        case .unassigned: .danger
        case .inQueue: .indigo
        case .handling: .neutral
        case .packed, .finalised: .indigo
        case .dispatched: .success
        }
    }
}
