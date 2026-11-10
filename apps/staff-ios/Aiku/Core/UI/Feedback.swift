import Observation
import SwiftUI

struct ProgressBar: View {
    let value: Double
    var height: CGFloat = 8
    var tint: Color?

    init(value: Double, height: CGFloat = 8, tint: Color? = nil) {
        self.value = value
        self.height = height
        self.tint = tint
    }

    var body: some View {
        GeometryReader { proxy in
            ZStack(alignment: .leading) {
                Capsule().fill(AikuColor.softBd)
                Capsule()
                    .fill(tint ?? AikuColor.primary)
                    .frame(width: proxy.size.width * min(max(value, 0), 1))
            }
        }
        .frame(height: height)
        .accessibilityElement()
        .accessibilityLabel("Progress")
        .accessibilityValue("\(Int((min(max(value, 0), 1) * 100).rounded())) percent")
    }
}

struct LabeledProgress: View {
    let label: String
    let detail: String
    let value: Double

    init(_ label: String, detail: String, value: Double) {
        self.label = label
        self.detail = detail
        self.value = value
    }

    var body: some View {
        VStack(spacing: 6) {
            HStack {
                Text(label)
                    .font(.aiku(13, .semibold))
                    .foregroundStyle(AikuColor.text2)
                Spacer()
                Text(detail)
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            }
            ProgressBar(value: value)
        }
    }
}

struct InlineAlert<Actions: View>: View {
    var tone: Tone = .danger
    var icon: AppIcon?
    let title: String
    var message: String?
    @ViewBuilder var actions: Actions

    init(
        tone: Tone = .danger,
        icon: AppIcon? = nil,
        title: String,
        message: String? = nil,
        @ViewBuilder actions: () -> Actions
    ) {
        self.tone = tone
        self.icon = icon
        self.title = title
        self.message = message
        self.actions = actions()
    }

    var body: some View {
        HStack(alignment: .top, spacing: 12) {
            (icon ?? defaultIcon).image
                .font(.system(size: 18))
                .foregroundStyle(tone.foreground)
                .accessibilityHidden(true)
            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(.aiku(14, .semibold))
                    .foregroundStyle(tone.ink)
                if let message {
                    Text(message)
                        .font(.aiku(13))
                        .foregroundStyle(tone.ink)
                }
                actions
            }
            .frame(maxWidth: .infinity, alignment: .leading)
        }
        .padding(12)
        .background(tone.background, in: RoundedRectangle(cornerRadius: 8))
        .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(tone.outline))
    }

    private var defaultIcon: AppIcon {
        switch tone {
        case .success: .checkCircle
        case .info, .neutral: .info
        case .warn, .danger, .orange, .lime: .warning
        }
    }
}

extension InlineAlert where Actions == EmptyView {
    init(tone: Tone = .danger, icon: AppIcon? = nil, title: String, message: String? = nil) {
        self.init(tone: tone, icon: icon, title: title, message: message) { EmptyView() }
    }
}

struct HelperText: View {
    let text: String

    init(_ text: String) {
        self.text = text
    }

    var body: some View {
        Text(text)
            .font(.aiku(12))
            .foregroundStyle(AikuColor.text4)
            .frame(maxWidth: .infinity, alignment: .leading)
            .padding(.horizontal, 4)
    }
}

struct EmptyState: View {
    let icon: AppIcon
    let title: String
    var message: String?
    var actionTitle: String?
    var action: (() -> Void)?

    init(icon: AppIcon, title: String, message: String? = nil, actionTitle: String? = nil, action: (() -> Void)? = nil) {
        self.icon = icon
        self.title = title
        self.message = message
        self.actionTitle = actionTitle
        self.action = action
    }

    var body: some View {
        VStack(spacing: 8) {
            icon.image
                .font(.system(size: 26))
                .foregroundStyle(AikuColor.text4)
                .accessibilityHidden(true)
            Text(title)
                .font(.aiku(14, .semibold))
                .foregroundStyle(AikuColor.text)
            if let message {
                Text(message)
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            }
            if let actionTitle, let action {
                AikuButton(actionTitle, kind: .secondary, size: .small, fullWidth: false, action: action)
                    .padding(.top, 4)
            }
        }
        .multilineTextAlignment(.center)
        .padding(20)
        .frame(maxWidth: .infinity)
        .overlay(
            RoundedRectangle(cornerRadius: 12)
                .strokeBorder(AikuColor.faint, style: StrokeStyle(lineWidth: 1, dash: [4, 4]))
        )
    }
}

struct FullScreenMessage<Actions: View>: View {
    let icon: AppIcon
    var tone: Tone = .info
    let title: String
    let message: String
    var detail: String?
    @ViewBuilder var actions: Actions

    init(
        icon: AppIcon,
        tone: Tone = .info,
        title: String,
        message: String,
        detail: String? = nil,
        @ViewBuilder actions: () -> Actions
    ) {
        self.icon = icon
        self.tone = tone
        self.title = title
        self.message = message
        self.detail = detail
        self.actions = actions()
    }

    var body: some View {
        VStack(spacing: 16) {
            icon.image
                .font(.system(size: 32))
                .foregroundStyle(tone.foreground)
                .frame(width: 72, height: 72)
                .background(tone.background, in: Circle())
                .accessibilityHidden(true)
            VStack(spacing: 6) {
                Text(title)
                    .font(.aiku(22, .bold))
                    .foregroundStyle(AikuColor.text)
                Text(message)
                    .font(.aiku(15))
                    .foregroundStyle(AikuColor.text3)
                if let detail {
                    Text(detail)
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text4)
                        .padding(.top, 2)
                }
            }
            .multilineTextAlignment(.center)
            VStack(spacing: 8) {
                actions
            }
            .padding(.top, 4)
        }
        .padding(24)
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }
}

struct SkeletonBlock: View {
    var width: CGFloat?
    var height: CGFloat = 12
    var radius: CGFloat = 6
    @State private var isDimmed = false

    init(width: CGFloat? = nil, height: CGFloat = 12, radius: CGFloat = 6) {
        self.width = width
        self.height = height
        self.radius = radius
    }

    var body: some View {
        RoundedRectangle(cornerRadius: radius)
            .fill(AikuColor.border)
            .frame(width: width, height: height)
            .frame(maxWidth: width == nil ? .infinity : nil, alignment: .leading)
            .opacity(isDimmed ? 0.45 : 1)
            .onAppear {
                withAnimation(.easeInOut(duration: 0.9).repeatForever(autoreverses: true)) {
                    isDimmed = true
                }
            }
            .accessibilityHidden(true)
    }
}

struct SkeletonRow: View {
    var body: some View {
        HStack(spacing: 12) {
            SkeletonBlock(width: 40, height: 40, radius: 8)
            VStack(alignment: .leading, spacing: 8) {
                SkeletonBlock(width: 180, height: 12)
                SkeletonBlock(width: 110, height: 10)
            }
            Spacer(minLength: 0)
        }
        .padding(14)
    }
}

struct ToastMessage: Identifiable, Equatable {
    let id = UUID()
    var text: String
    var tone: Tone
    var actionTitle: String?
    var isSticky: Bool

    static func == (lhs: ToastMessage, rhs: ToastMessage) -> Bool {
        lhs.id == rhs.id
    }
}

@MainActor
@Observable
final class ToastCenter {
    private(set) var current: ToastMessage?
    @ObservationIgnored private var action: (() -> Void)?
    @ObservationIgnored private var hideTask: Task<Void, Never>?

    func show(_ text: String, tone: Tone = .success, actionTitle: String? = nil, sticky: Bool? = nil, action: (() -> Void)? = nil) {
        hideTask?.cancel()
        let isSticky = sticky ?? (tone == .danger)
        let message = ToastMessage(text: text, tone: tone, actionTitle: actionTitle, isSticky: isSticky)
        self.action = action
        current = message
        guard !isSticky else { return }
        hideTask = Task { [weak self] in
            try? await Task.sleep(for: .seconds(4))
            guard !Task.isCancelled, self?.current?.id == message.id else { return }
            self?.dismiss()
        }
    }

    func performAction() {
        let pending = action
        dismiss()
        pending?()
    }

    func dismiss() {
        hideTask?.cancel()
        action = nil
        current = nil
    }
}

struct ToastView: View {
    let message: ToastMessage
    var onAction: () -> Void = {}
    var onDismiss: () -> Void = {}

    var body: some View {
        HStack(spacing: 12) {
            icon.image
                .font(.system(size: 13, weight: .bold))
                .foregroundStyle(.white)
                .frame(width: 26, height: 26)
                .background(badgeColor, in: Circle())
                .accessibilityHidden(true)
            Text(message.text)
                .font(.aiku(14))
                .foregroundStyle(AikuColor.toastText)
                .frame(maxWidth: .infinity, alignment: .leading)
            if let actionTitle = message.actionTitle {
                Button(action: onAction) {
                    Text(actionTitle)
                        .font(.aiku(14, .semibold))
                        .foregroundStyle(Color(hex: 0xFDE047))
                        .frame(minHeight: 32)
                }
                .buttonStyle(.plain)
            } else if message.isSticky {
                Button(action: onDismiss) {
                    AppIcon.close.image
                        .font(.system(size: 12, weight: .bold))
                        .foregroundStyle(AikuColor.toastText)
                        .frame(width: 32, height: 32)
                }
                .buttonStyle(.plain)
                .accessibilityLabel("Dismiss")
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .background(AikuColor.toastBg, in: RoundedRectangle(cornerRadius: 10))
        .shadow(color: .black.opacity(0.3), radius: 12, y: 8)
        .accessibilityElement(children: .combine)
    }

    private var icon: AppIcon {
        switch message.tone {
        case .success, .lime: .check
        case .danger: .close
        case .warn, .orange: .warning
        case .info, .neutral: .info
        }
    }

    private var badgeColor: Color {
        switch message.tone {
        case .success, .lime: Color(hex: 0x22C55E)
        case .danger: Color(hex: 0xEF4444)
        case .warn, .orange: Color(hex: 0xF59E0B)
        case .info, .neutral: Color(hex: 0x6B7280)
        }
    }
}

struct ScannerStatusBar: View {
    var isReady = true

    init(isReady: Bool = true) {
        self.isReady = isReady
    }

    var body: some View {
        HStack(spacing: 10) {
            (isReady ? AppIcon.scannerDevice : AppIcon.warning).image
                .font(.system(size: 17))
            Text(isReady ? "Scanner device ready" : "Scanner not detected · use camera")
                .font(.aiku(14, .semibold))
                .frame(maxWidth: .infinity, alignment: .leading)
            if isReady {
                Circle().fill(AikuColor.liveDot).frame(width: 10, height: 10)
            }
        }
        .foregroundStyle(isReady ? AikuColor.navAccent : AikuColor.warnInk)
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .background(isReady ? AikuColor.navAct : AikuColor.warnSoft, in: RoundedRectangle(cornerRadius: 8))
        .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(isReady ? AikuColor.navRing : AikuColor.warnBd))
        .accessibilityElement(children: .combine)
    }
}

struct ComingSoonView: View {
    let title: String

    var body: some View {
        AppScreen(title: title) {
            EmptyState(icon: .history, title: "Not built yet", message: "This screen is on the way.")
        }
    }
}
