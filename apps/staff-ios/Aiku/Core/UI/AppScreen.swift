import SwiftUI

struct ScreenScaffold<Header: View, Content: View, Footer: View>: View {
    @Environment(ToastCenter.self) private var toasts
    var showsTabBar = true
    var scrolls = true
    var spacing: CGFloat = 14
    @ViewBuilder var header: Header
    @ViewBuilder var content: Content
    @ViewBuilder var footer: Footer
    @State private var isKeyboardVisible = false

    init(
        showsTabBar: Bool = true,
        scrolls: Bool = true,
        spacing: CGFloat = 14,
        @ViewBuilder header: () -> Header,
        @ViewBuilder content: () -> Content,
        @ViewBuilder footer: () -> Footer
    ) {
        self.showsTabBar = showsTabBar
        self.scrolls = scrolls
        self.spacing = spacing
        self.header = header()
        self.content = content()
        self.footer = footer()
    }

    var body: some View {
        VStack(spacing: 0) {
            header
            if scrolls {
                ScrollView {
                    VStack(alignment: .leading, spacing: spacing) {
                        content
                    }
                    .padding(16)
                    .frame(maxWidth: .infinity, alignment: .leading)
                }
                .scrollDismissesKeyboard(.interactively)
            } else {
                content
                    .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .top)
            }
        }
        .background(AikuColor.bg.ignoresSafeArea())
        .safeAreaInset(edge: .bottom, spacing: 0) {
            VStack(spacing: 0) {
                if let toast = toasts.current {
                    ToastView(message: toast, onAction: toasts.performAction, onDismiss: toasts.dismiss)
                        .padding(.horizontal, 16)
                        .padding(.bottom, 12)
                        .transition(.move(edge: .bottom).combined(with: .opacity))
                }
                if Footer.self != EmptyView.self {
                    VStack(spacing: 8) {
                        footer
                    }
                    .padding(.horizontal, 16)
                    .padding(.vertical, 12)
                    .frame(maxWidth: .infinity)
                    .background(AikuColor.surface.ignoresSafeArea(edges: showsTabBar ? [] : .bottom))
                    .overlay(alignment: .top) {
                        Rectangle().fill(AikuColor.border).frame(height: 1)
                    }
                }
                if showsTabBar, !isKeyboardVisible {
                    MainTabBar()
                }
            }
            .animation(.easeOut(duration: 0.2), value: toasts.current)
        }
        .toolbar(.hidden, for: .navigationBar)
        .onReceive(NotificationCenter.default.publisher(for: UIResponder.keyboardWillShowNotification)) { _ in
            isKeyboardVisible = true
        }
        .onReceive(NotificationCenter.default.publisher(for: UIResponder.keyboardWillHideNotification)) { _ in
            isKeyboardVisible = false
        }
    }
}

struct AppScreen<Accessory: View, Content: View, Footer: View>: View {
    let title: String
    var kicker: String?
    var back: ScreenBack = .pop
    var actions: [HeaderAction] = []
    var showsTabBar = true
    var scrolls = true
    var spacing: CGFloat = 14
    @ViewBuilder var accessory: Accessory
    @ViewBuilder var content: Content
    @ViewBuilder var footer: Footer

    init(
        title: String,
        kicker: String? = nil,
        back: ScreenBack = .pop,
        actions: [HeaderAction] = [],
        showsTabBar: Bool = true,
        scrolls: Bool = true,
        spacing: CGFloat = 14,
        @ViewBuilder accessory: () -> Accessory,
        @ViewBuilder content: () -> Content,
        @ViewBuilder footer: () -> Footer
    ) {
        self.title = title
        self.kicker = kicker
        self.back = back
        self.actions = actions
        self.showsTabBar = showsTabBar
        self.scrolls = scrolls
        self.spacing = spacing
        self.accessory = accessory()
        self.content = content()
        self.footer = footer()
    }

    var body: some View {
        ScreenScaffold(showsTabBar: showsTabBar, scrolls: scrolls, spacing: spacing) {
            HeaderContainer {
                HeaderTitleRow(title: title, kicker: kicker, back: back, actions: actions)
                accessory
            }
        } content: {
            content
        } footer: {
            footer
        }
    }
}

extension AppScreen where Accessory == EmptyView, Footer == EmptyView {
    init(
        title: String,
        kicker: String? = nil,
        back: ScreenBack = .pop,
        actions: [HeaderAction] = [],
        showsTabBar: Bool = true,
        scrolls: Bool = true,
        spacing: CGFloat = 14,
        @ViewBuilder content: () -> Content
    ) {
        self.init(
            title: title, kicker: kicker, back: back, actions: actions,
            showsTabBar: showsTabBar, scrolls: scrolls, spacing: spacing,
            accessory: { EmptyView() }, content: content, footer: { EmptyView() }
        )
    }
}

extension AppScreen where Footer == EmptyView {
    init(
        title: String,
        kicker: String? = nil,
        back: ScreenBack = .pop,
        actions: [HeaderAction] = [],
        showsTabBar: Bool = true,
        scrolls: Bool = true,
        spacing: CGFloat = 14,
        @ViewBuilder accessory: () -> Accessory,
        @ViewBuilder content: () -> Content
    ) {
        self.init(
            title: title, kicker: kicker, back: back, actions: actions,
            showsTabBar: showsTabBar, scrolls: scrolls, spacing: spacing,
            accessory: accessory, content: content, footer: { EmptyView() }
        )
    }
}

extension AppScreen where Accessory == EmptyView {
    init(
        title: String,
        kicker: String? = nil,
        back: ScreenBack = .pop,
        actions: [HeaderAction] = [],
        showsTabBar: Bool = true,
        scrolls: Bool = true,
        spacing: CGFloat = 14,
        @ViewBuilder content: () -> Content,
        @ViewBuilder footer: () -> Footer
    ) {
        self.init(
            title: title, kicker: kicker, back: back, actions: actions,
            showsTabBar: showsTabBar, scrolls: scrolls, spacing: spacing,
            accessory: { EmptyView() }, content: content, footer: footer
        )
    }
}

extension UINavigationController: @retroactive UIGestureRecognizerDelegate {
    override open func viewDidLoad() {
        super.viewDidLoad()
        interactivePopGestureRecognizer?.delegate = self
    }

    public func gestureRecognizerShouldBegin(_ gestureRecognizer: UIGestureRecognizer) -> Bool {
        viewControllers.count > 1
    }
}
