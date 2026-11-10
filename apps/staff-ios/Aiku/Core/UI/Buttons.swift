import SwiftUI

enum AikuButtonKind: Sendable {
    case primary
    case secondary
    case tertiary
    case danger
    case scan
}

enum AikuButtonSize: Sendable {
    case large
    case regular
    case small

    var height: CGFloat {
        switch self {
        case .large: 52
        case .regular: 48
        case .small: 44
        }
    }

    var fontSize: CGFloat {
        switch self {
        case .large: 15
        case .regular: 15
        case .small: 14
        }
    }
}

struct AikuButtonStyle: ButtonStyle {
    var kind: AikuButtonKind = .primary
    var size: AikuButtonSize = .large
    var fullWidth = true
    var radius: CGFloat = 8
    @Environment(\.isEnabled) private var isEnabled

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.aiku(size.fontSize, .semibold))
            .multilineTextAlignment(.center)
            .lineLimit(2)
            .minimumScaleFactor(0.85)
            .foregroundStyle(foreground)
            .padding(.horizontal, 14)
            .frame(maxWidth: fullWidth ? .infinity : nil, minHeight: size.height)
            .background(background, in: RoundedRectangle(cornerRadius: radius))
            .overlay(RoundedRectangle(cornerRadius: radius).strokeBorder(border))
            .opacity(isEnabled ? (configuration.isPressed ? 0.85 : 1) : 0.55)
            .contentShape(RoundedRectangle(cornerRadius: radius))
    }

    private var foreground: Color {
        switch kind {
        case .primary: AikuColor.onPrimary
        case .secondary: AikuColor.ink
        case .tertiary: AikuColor.text2
        case .danger: AikuColor.danger
        case .scan: AikuColor.onScan
        }
    }

    private var background: Color {
        switch kind {
        case .primary: AikuColor.primary
        case .secondary: AikuColor.soft
        case .tertiary, .danger: AikuColor.surface
        case .scan: AikuColor.scan
        }
    }

    private var border: Color {
        switch kind {
        case .primary: AikuColor.primaryBd
        case .secondary: AikuColor.softBd
        case .tertiary: AikuColor.borderStrong
        case .danger: AikuColor.dangerBd
        case .scan: .clear
        }
    }
}

struct AikuButton: View {
    let title: String
    var icon: AppIcon?
    var kind: AikuButtonKind = .primary
    var size: AikuButtonSize = .large
    var isLoading = false
    var fullWidth = true
    let action: () -> Void

    init(
        _ title: String,
        icon: AppIcon? = nil,
        kind: AikuButtonKind = .primary,
        size: AikuButtonSize = .large,
        isLoading: Bool = false,
        fullWidth: Bool = true,
        action: @escaping () -> Void
    ) {
        self.title = title
        self.icon = icon
        self.kind = kind
        self.size = size
        self.isLoading = isLoading
        self.fullWidth = fullWidth
        self.action = action
    }

    var body: some View {
        Button(action: action) {
            HStack(spacing: 8) {
                if isLoading {
                    ProgressView()
                        .tint(kind == .primary ? AikuColor.onPrimary : AikuColor.ink)
                } else if let icon {
                    icon.image.font(.system(size: 17, weight: .medium))
                }
                Text(title)
            }
        }
        .buttonStyle(AikuButtonStyle(kind: kind, size: size, fullWidth: fullWidth))
        .disabled(isLoading)
    }
}

struct LinkButton: View {
    let title: String
    var size: CGFloat = 14
    let action: () -> Void

    init(_ title: String, size: CGFloat = 14, action: @escaping () -> Void) {
        self.title = title
        self.size = size
        self.action = action
    }

    var body: some View {
        Button(action: action) {
            Text(title)
                .font(.aiku(size, .semibold))
                .foregroundStyle(AikuColor.link)
                .frame(minHeight: 44)
                .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
    }
}

struct IconButton: View {
    let icon: AppIcon
    let label: String
    var tint: Color?
    var size: CGFloat = 44
    let action: () -> Void

    init(_ icon: AppIcon, label: String, tint: Color? = nil, size: CGFloat = 44, action: @escaping () -> Void) {
        self.icon = icon
        self.label = label
        self.tint = tint
        self.size = size
        self.action = action
    }

    var body: some View {
        Button(action: action) {
            icon.image
                .font(.system(size: 20, weight: .medium))
                .foregroundStyle(tint ?? AikuColor.text2)
                .frame(width: size, height: size)
                .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityLabel(label)
    }
}

struct ScanButton: View {
    var size: CGFloat = 46
    var label = "Scan"
    let action: () -> Void

    init(size: CGFloat = 46, label: String = "Scan", action: @escaping () -> Void) {
        self.size = size
        self.label = label
        self.action = action
    }

    var body: some View {
        Button(action: action) {
            AppIcon.scan.image
                .font(.system(size: size * 0.45, weight: .medium))
                .foregroundStyle(AikuColor.onScan)
                .frame(width: size, height: size)
                .background(AikuColor.scan, in: RoundedRectangle(cornerRadius: 8))
        }
        .buttonStyle(.plain)
        .accessibilityLabel(label)
    }
}
