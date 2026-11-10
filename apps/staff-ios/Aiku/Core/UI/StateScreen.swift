import SwiftUI

enum StateTone {
    case neutral
    case info
    case success
    case warning
    case danger

    var background: Color {
        switch self {
        case .neutral: AikuColor.surfaceMuted
        case .info: AikuColor.lavender
        case .success: AikuColor.successSoft
        case .warning: AikuColor.warningSoft
        case .danger: AikuColor.dangerSoft
        }
    }

    var foreground: Color {
        switch self {
        case .neutral: AikuColor.textSecondary
        case .info: AikuColor.indigo
        case .success: AikuColor.success
        case .warning: AikuColor.warning
        case .danger: AikuColor.danger
        }
    }
}

struct StateAction {
    let title: String
    var systemImage: String?
    let perform: () -> Void
}

struct StateMessage<Extra: View>: View {
    let systemImage: String
    var tone: StateTone = .neutral
    let title: String
    let message: String
    var footnote: String?
    @ViewBuilder var extra: Extra

    var body: some View {
        VStack(spacing: 16) {
            Image(systemName: systemImage)
                .font(.system(size: 36, weight: .regular))
                .foregroundStyle(tone.foreground)
                .frame(width: 88, height: 88)
                .background(tone.background, in: Circle())
                .accessibilityHidden(true)
            Text(title)
                .font(.system(size: 22, weight: .bold))
                .foregroundStyle(AikuColor.textPrimary)
                .accessibilityAddTraits(.isHeader)
            Text(markdown: message)
                .font(.system(size: 15))
                .foregroundStyle(AikuColor.textSecondary)
                .frame(maxWidth: 300)
            if let footnote {
                Text(footnote)
                    .font(.caption)
                    .monospacedDigit()
                    .foregroundStyle(AikuColor.textMuted)
            }
            extra
        }
        .multilineTextAlignment(.center)
        .padding(.horizontal, 28)
        .frame(maxWidth: .infinity)
    }
}

extension StateMessage where Extra == EmptyView {
    init(systemImage: String, tone: StateTone = .neutral, title: String, message: String, footnote: String? = nil) {
        self.init(systemImage: systemImage, tone: tone, title: title, message: message, footnote: footnote) { EmptyView() }
    }
}

struct StateScreen<Extra: View>: View {
    var headerTitle: String?
    var headerEyebrow: String?
    let systemImage: String
    var tone: StateTone = .neutral
    let title: String
    let message: String
    var footnote: String?
    var actions: [StateAction] = []
    var link: StateAction?
    @ViewBuilder var extra: Extra

    var body: some View {
        VStack(spacing: 0) {
            if let headerTitle {
                ScreenHeader(title: headerTitle, eyebrow: headerEyebrow, trailing: .none)
            }
            GeometryReader { proxy in
                ScrollView {
                    StateMessage(systemImage: systemImage, tone: tone, title: title, message: message, footnote: footnote) {
                        extra
                    }
                    .padding(.vertical, 24)
                    .frame(minHeight: proxy.size.height)
                }
                .scrollBounceBehavior(.basedOnSize)
            }
            actionStack
        }
        .aikuScreen()
    }

    private var actionStack: some View {
        VStack(spacing: 10) {
            ForEach(Array(actions.enumerated()), id: \.offset) { index, action in
                ActionButton(title: action.title, systemImage: action.systemImage, kind: index == 0 ? .primary : .outline, action: action.perform)
            }
            if let link {
                Button(action: link.perform) {
                    Text(link.title)
                        .font(.subheadline.weight(.semibold))
                        .foregroundStyle(AikuColor.indigo)
                        .frame(minHeight: 44)
                }
            }
        }
        .padding(.horizontal, 16)
        .padding(.top, 12)
        .padding(.bottom, 16)
    }
}

extension StateScreen where Extra == EmptyView {
    init(
        headerTitle: String? = nil,
        headerEyebrow: String? = nil,
        systemImage: String,
        tone: StateTone = .neutral,
        title: String,
        message: String,
        footnote: String? = nil,
        actions: [StateAction] = [],
        link: StateAction? = nil
    ) {
        self.init(
            headerTitle: headerTitle,
            headerEyebrow: headerEyebrow,
            systemImage: systemImage,
            tone: tone,
            title: title,
            message: message,
            footnote: footnote,
            actions: actions,
            link: link
        ) { EmptyView() }
    }
}

extension Text {
    init(markdown: String) {
        self.init((try? AttributedString(markdown: markdown)) ?? AttributedString(markdown))
    }
}
