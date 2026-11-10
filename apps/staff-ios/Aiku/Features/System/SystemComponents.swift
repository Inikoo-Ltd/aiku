import SwiftUI

struct SystemStatusBanner: View {
    let text: String
    var tone: Tone = .warn
    var actionTitle: String?
    var action: (() -> Void)?

    init(_ text: String, tone: Tone = .warn, actionTitle: String? = nil, action: (() -> Void)? = nil) {
        self.text = text
        self.tone = tone
        self.actionTitle = actionTitle
        self.action = action
    }

    var body: some View {
        HStack(spacing: 8) {
            Circle()
                .fill(tone.foreground)
                .frame(width: 8, height: 8)
                .accessibilityHidden(true)
            Text(text)
                .font(.aiku(13, .semibold))
                .foregroundStyle(tone.foreground)
                .frame(maxWidth: .infinity, alignment: .leading)
                .fixedSize(horizontal: false, vertical: true)
            if let actionTitle, let action {
                Button(action: action) {
                    Text(actionTitle)
                        .font(.aiku(13, .bold))
                        .foregroundStyle(tone.foreground)
                        .frame(minWidth: 44, minHeight: 44)
                        .contentShape(Rectangle())
                }
                .buttonStyle(.plain)
                .accessibilityIdentifier("system.banner.action")
            }
        }
        .padding(.horizontal, 12)
        .padding(.vertical, actionTitle == nil ? 8 : 0)
        .frame(minHeight: 36)
        .background(tone.background, in: RoundedRectangle(cornerRadius: 10))
        .accessibilityElement(children: actionTitle == nil ? .combine : .contain)
    }
}

struct SystemStateIcon: View {
    let icon: AppIcon
    let foreground: Color
    let background: Color
    var size: CGFloat = 88
    var glyph: CGFloat = 40

    var body: some View {
        icon.image
            .font(.system(size: glyph * 0.85))
            .foregroundStyle(foreground)
            .frame(width: size, height: size)
            .background(background, in: Circle())
            .accessibilityHidden(true)
    }
}

struct SystemStateBody<Extra: View>: View {
    let icon: SystemStateIcon
    let title: String
    let message: Text
    var spacing: CGFloat = 16
    var titleSize: CGFloat = 22
    var messageSize: CGFloat = 15
    @ViewBuilder var extra: Extra

    init(
        icon: SystemStateIcon,
        title: String,
        message: Text,
        spacing: CGFloat = 16,
        titleSize: CGFloat = 22,
        messageSize: CGFloat = 15,
        @ViewBuilder extra: () -> Extra
    ) {
        self.icon = icon
        self.title = title
        self.message = message
        self.spacing = spacing
        self.titleSize = titleSize
        self.messageSize = messageSize
        self.extra = extra()
    }

    var body: some View {
        VStack(spacing: spacing) {
            icon
            Text(title)
                .font(.aiku(titleSize, .bold))
                .foregroundStyle(AikuColor.text)
                .accessibilityAddTraits(.isHeader)
            message
                .font(.aiku(messageSize))
                .foregroundStyle(AikuColor.text3)
                .frame(maxWidth: 320)
            extra
        }
        .multilineTextAlignment(.center)
        .fixedSize(horizontal: false, vertical: true)
        .frame(maxWidth: .infinity)
    }
}

extension SystemStateBody where Extra == EmptyView {
    init(icon: SystemStateIcon, title: String, message: Text, spacing: CGFloat = 16, titleSize: CGFloat = 22, messageSize: CGFloat = 15) {
        self.init(icon: icon, title: title, message: message, spacing: spacing, titleSize: titleSize, messageSize: messageSize) { EmptyView() }
    }
}

struct SystemStateScreen<Header: View, StateBody: View, Actions: View>: View {
    var showsTabBar = false
    @ViewBuilder var header: Header
    @ViewBuilder var stateBody: StateBody
    @ViewBuilder var actions: Actions

    init(
        showsTabBar: Bool = false,
        @ViewBuilder header: () -> Header,
        @ViewBuilder body: () -> StateBody,
        @ViewBuilder actions: () -> Actions
    ) {
        self.showsTabBar = showsTabBar
        self.header = header()
        self.stateBody = body()
        self.actions = actions()
    }

    var body: some View {
        ScreenScaffold(showsTabBar: showsTabBar, scrolls: false) {
            header
        } content: {
            VStack(spacing: 0) {
                GeometryReader { proxy in
                    ScrollView {
                        stateBody
                            .padding(.horizontal, 28)
                            .padding(.vertical, 24)
                            .frame(maxWidth: .infinity, minHeight: proxy.size.height)
                    }
                    .scrollBounceBehavior(.basedOnSize)
                }
                VStack(spacing: 10) {
                    actions
                }
                .padding(.horizontal, 16)
                .padding(.top, 12)
                .padding(.bottom, 16)
            }
        } footer: {
            EmptyView()
        }
    }
}

extension SystemStateScreen where Header == EmptyView {
    init(showsTabBar: Bool = false, @ViewBuilder body: () -> StateBody, @ViewBuilder actions: () -> Actions) {
        self.init(showsTabBar: showsTabBar, header: { EmptyView() }, body: body, actions: actions)
    }
}

struct SystemStateHeader: View {
    let title: String
    let kicker: String
    var back: ScreenBack = .pop

    var body: some View {
        HeaderContainer {
            HeaderTitleRow(title: title, kicker: kicker, back: back)
        }
    }
}
