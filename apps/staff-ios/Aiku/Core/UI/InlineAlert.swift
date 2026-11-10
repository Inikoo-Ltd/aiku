import SwiftUI

enum InlineAlertKind {
    case danger
    case warning
    case info
    case success

    var symbol: String {
        switch self {
        case .danger, .warning: "exclamationmark.triangle"
        case .info: "info.circle"
        case .success: "checkmark"
        }
    }

    var iconColor: Color {
        switch self {
        case .danger: AikuColor.danger
        case .warning: AikuColor.warning
        case .info: AikuColor.indigo
        case .success: AikuColor.success
        }
    }

    var titleColor: Color {
        switch self {
        case .danger: AikuColor.dangerInk
        case .warning: AikuColor.warningInk
        case .info: AikuColor.indigo
        case .success: AikuColor.success
        }
    }

    var background: Color {
        switch self {
        case .danger: AikuColor.dangerSoft
        case .warning: AikuColor.warningSoft
        case .info: AikuColor.lavender
        case .success: AikuColor.successSoft
        }
    }

    var border: Color {
        switch self {
        case .danger: AikuColor.dangerBorder
        case .warning: AikuColor.warningBorder
        case .info: AikuColor.lavenderBorder
        case .success: AikuColor.successSoft
        }
    }
}

struct InlineAlert: View {
    let kind: InlineAlertKind
    let title: String
    var message: String?
    var actionTitle: String?
    var action: (() -> Void)?

    var body: some View {
        HStack(alignment: .top, spacing: 12) {
            Image(systemName: kind.symbol)
                .font(.system(size: 17, weight: .semibold))
                .foregroundStyle(kind.iconColor)
                .padding(.top, 1)
                .accessibilityHidden(true)
            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(.subheadline.weight(.semibold))
                    .foregroundStyle(kind.titleColor)
                if let message {
                    Text(message)
                        .font(.footnote)
                        .foregroundStyle(AikuColor.textBody)
                }
                if let actionTitle, let action {
                    Button(action: action) {
                        Text(actionTitle)
                            .font(.footnote.bold())
                            .foregroundStyle(kind.titleColor)
                            .frame(minHeight: 32)
                    }
                    .padding(.top, 2)
                }
            }
            .frame(maxWidth: .infinity, alignment: .leading)
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .background(kind.background, in: RoundedRectangle(cornerRadius: 8))
        .overlay(RoundedRectangle(cornerRadius: 8).stroke(kind.border))
        .accessibilityElement(children: .contain)
    }
}

struct OfflineBar: View {
    let message: String
    var actionTitle: String?
    var action: (() -> Void)?

    var body: some View {
        HStack(spacing: 10) {
            Image(systemName: "wifi.slash")
                .font(.system(size: 14, weight: .medium))
                .accessibilityHidden(true)
            Text(message)
                .font(.footnote)
                .frame(maxWidth: .infinity, alignment: .leading)
            if let actionTitle, let action {
                Button(action: action) {
                    Text(actionTitle)
                        .font(.footnote.bold())
                        .foregroundStyle(AikuColor.yellow)
                        .frame(minWidth: 44, minHeight: 36)
                }
            }
        }
        .foregroundStyle(AikuColor.toastText)
        .padding(.leading, 12)
        .padding(.trailing, actionTitle == nil ? 12 : 4)
        .padding(.vertical, actionTitle == nil ? 8 : 0)
        .background(AikuColor.toastBackground, in: RoundedRectangle(cornerRadius: 8))
        .accessibilityElement(children: .contain)
    }
}
