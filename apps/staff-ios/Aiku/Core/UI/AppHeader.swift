import SwiftUI

enum ScreenBack {
    case none
    case pop
    case custom(@MainActor () -> Void)
}

struct HeaderAction: Identifiable {
    let icon: AppIcon
    let label: String
    var badge: Int?
    let action: @MainActor () -> Void

    var id: String { label }

    init(_ icon: AppIcon, label: String, badge: Int? = nil, action: @escaping @MainActor () -> Void) {
        self.icon = icon
        self.label = label
        self.badge = badge
        self.action = action
    }
}

struct HeaderContainer<Content: View>: View {
    var spacing: CGFloat = 12
    @ViewBuilder var content: Content

    init(spacing: CGFloat = 12, @ViewBuilder content: () -> Content) {
        self.spacing = spacing
        self.content = content()
    }

    var body: some View {
        VStack(alignment: .leading, spacing: spacing) {
            content
        }
        .padding(.horizontal, 16)
        .padding(.top, 8)
        .padding(.bottom, 12)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(AikuColor.nav.ignoresSafeArea(edges: .top))
    }
}

struct HeaderTitleRow: View {
    @Environment(AppRouter.self) private var router
    let title: String
    var kicker: String?
    var back: ScreenBack = .pop
    var actions: [HeaderAction] = []

    var body: some View {
        HStack(spacing: 4) {
            backButton
            VStack(alignment: .leading, spacing: 0) {
                if let kicker {
                    Text(kicker)
                        .font(.aiku(12))
                        .foregroundStyle(AikuColor.onNav2)
                        .lineLimit(1)
                }
                Text(title)
                    .font(.aiku(20, .bold))
                    .foregroundStyle(AikuColor.onNav)
                    .lineLimit(1)
                    .minimumScaleFactor(0.75)
                    .accessibilityAddTraits(.isHeader)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            .padding(.leading, hasBack ? 0 : 4)
            ForEach(actions) { item in
                Button(action: item.action) {
                    item.icon.image
                        .font(.system(size: 20, weight: .medium))
                        .foregroundStyle(AikuColor.onNav)
                        .frame(width: 44, height: 44)
                        .overlay(alignment: .topTrailing) {
                            if let badge = item.badge, badge > 0 {
                                CountBadge(count: badge).offset(x: -2, y: 4)
                            }
                        }
                        .contentShape(Rectangle())
                }
                .buttonStyle(.plain)
                .accessibilityLabel(item.label)
            }
        }
        .frame(minHeight: 44)
    }

    private var hasBack: Bool {
        if case .none = back { return false }
        return true
    }

    @ViewBuilder
    private var backButton: some View {
        if hasBack {
            Button {
                switch back {
                case .none: break
                case .pop: router.back()
                case .custom(let action): action()
                }
            } label: {
                AppIcon.back.image
                    .font(.system(size: 20, weight: .semibold))
                    .foregroundStyle(AikuColor.onNav)
                    .frame(width: 44, height: 44)
                    .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .accessibilityLabel("Back")
            .accessibilityIdentifier("header.back")
        }
    }
}

struct PillTab: Identifiable, Hashable {
    let id: String
    let title: String
    var badge: Int?

    init(id: String, title: String, badge: Int? = nil) {
        self.id = id
        self.title = title
        self.badge = badge
    }
}

struct PillTabs: View {
    let tabs: [PillTab]
    let selectedID: String?
    let onSelect: (PillTab) -> Void

    init(_ tabs: [PillTab], selectedID: String?, onSelect: @escaping (PillTab) -> Void) {
        self.tabs = tabs
        self.selectedID = selectedID
        self.onSelect = onSelect
    }

    var body: some View {
        ScrollViewReader { proxy in
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 6) {
                    ForEach(tabs) { tab in
                        let isSelected = tab.id == selectedID
                        Button {
                            onSelect(tab)
                        } label: {
                            HStack(spacing: 6) {
                                Text(tab.title)
                                    .font(.aiku(13, .semibold))
                                if let badge = tab.badge, badge > 0 {
                                    CountBadge(count: badge)
                                }
                            }
                            .foregroundStyle(isSelected ? AikuColor.navAccent : AikuColor.onNav)
                            .padding(.horizontal, 12)
                            .frame(height: 36)
                            .background(isSelected ? AikuColor.navAct : AikuColor.navPill, in: Capsule())
                            .overlay(Capsule().strokeBorder(isSelected ? AikuColor.navRing : .clear))
                            .contentShape(Capsule())
                        }
                        .buttonStyle(.plain)
                        .id(tab.id)
                        .accessibilityAddTraits(isSelected ? .isSelected : [])
                        .accessibilityIdentifier("section.\(tab.id)")
                    }
                }
                .padding(.horizontal, 16)
            }
            .scrollBounceBehavior(.basedOnSize, axes: .vertical)
            .padding(.horizontal, -16)
            .onAppear {
                if let selectedID {
                    proxy.scrollTo(selectedID, anchor: .center)
                }
            }
        }
    }
}

struct ModuleTabs: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    let module: AppModule
    let current: SectionID

    init(_ module: AppModule, current: SectionID) {
        self.module = module
        self.current = current
    }

    var body: some View {
        let sections = module.sections(includingFulfilment: session.hasFulfilmentAccess)
        PillTabs(sections.map { PillTab(id: $0.id.rawValue, title: $0.title) }, selectedID: current.rawValue) { tab in
            guard tab.id != current.rawValue, let section = sections.first(where: { $0.id.rawValue == tab.id }) else { return }
            section.show(router, true)
        }
    }
}
