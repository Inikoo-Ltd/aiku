import SwiftUI

struct MainTabBar: View {
    @Environment(AppRouter.self) private var router
    var highlightsSelection = true

    var body: some View {
        HStack(alignment: .center, spacing: 0) {
            tabButton(.dashboard, title: "Dashboard", icon: AppIcon.dashboard)
            tabButton(.inventory, title: "Inventory", icon: AppIcon.inventory)
            scanButton
            tabButton(.goodsIn, title: "Goods in", icon: AppIcon.goodsIn)
            tabButton(.goodsOut, title: "Goods out", icon: AppIcon.goodsOut, badge: SampleData.goodsOut.unassigned)
        }
        .padding(.horizontal, 8)
        .padding(.top, 8)
        .padding(.bottom, 4)
        .background(AikuColor.indigo.ignoresSafeArea(edges: .bottom))
    }

    private func tabButton(_ tab: MainTab, title: String, icon: String, badge: Int? = nil) -> some View {
        let isSelected = highlightsSelection && router.selectedTab == tab && router.path.isEmpty
        return Button {
            router.select(tab)
        } label: {
            VStack(spacing: 4) {
                Image(systemName: icon)
                    .font(.system(size: 20))
                    .overlay(alignment: .topTrailing) {
                        if let badge {
                            Text("\(badge)")
                                .font(.system(size: 10, weight: .bold))
                                .foregroundStyle(.white)
                                .padding(.horizontal, 5)
                                .frame(minWidth: 18, minHeight: 18)
                                .background(AikuColor.dangerDot, in: Capsule())
                                .offset(x: 14, y: -8)
                        }
                    }
                Text(title)
                    .font(.system(size: 11, weight: isSelected ? .semibold : .medium))
            }
            .foregroundStyle(isSelected ? AikuColor.yellow : AikuColor.indigoMuted)
            .frame(maxWidth: .infinity, minHeight: 52)
            .background {
                if isSelected {
                    RoundedRectangle(cornerRadius: 8)
                        .fill(AikuColor.indigoDeep)
                        .overlay(RoundedRectangle(cornerRadius: 8).stroke(AikuColor.indigoRing))
                        .padding(.horizontal, 4)
                }
            }
        }
        .accessibilityLabel(badge.map { "\(title), \($0) unassigned" } ?? title)
        .accessibilityAddTraits(isSelected ? .isSelected : [])
        .accessibilityIdentifier("tab.\(title)")
    }

    private var scanButton: some View {
        Button {
            router.scan()
        } label: {
            Image(systemName: AppIcon.scan)
                .font(.system(size: 24, weight: .semibold))
                .foregroundStyle(AikuColor.navy)
                .frame(width: 60, height: 60)
                .background(AikuColor.yellow, in: RoundedRectangle(cornerRadius: 12))
                .overlay(RoundedRectangle(cornerRadius: 12).stroke(AikuColor.background, lineWidth: 4))
                .shadow(color: AikuColor.navy.opacity(0.4), radius: 10, y: 6)
        }
        .frame(maxWidth: .infinity)
        .offset(y: -18)
        .accessibilityLabel("Scan barcode")
        .accessibilityIdentifier("tab.scan")
    }
}

extension View {
    func mainTabBar(highlightsSelection: Bool = true) -> some View {
        safeAreaInset(edge: .bottom, spacing: 0) {
            MainTabBar(highlightsSelection: highlightsSelection)
        }
    }

    func bottomActionBar<Content: View>(@ViewBuilder content: () -> Content) -> some View {
        safeAreaInset(edge: .bottom, spacing: 0) {
            HStack(spacing: 10) {
                content()
            }
            .padding(.horizontal, 16)
            .padding(.top, 12)
            .padding(.bottom, 8)
            .background(
                AikuColor.surface
                    .shadow(color: .black.opacity(0.06), radius: 8, y: -4)
                    .ignoresSafeArea(edges: .bottom)
            )
        }
    }
}

enum ActionButtonStyleKind {
    case primary
    case secondary
    case scan
    case outline
    case destructiveOutline
}

struct ActionButton: View {
    @Environment(\.isEnabled) private var isEnabled

    let title: String
    var systemImage: String?
    var kind: ActionButtonStyleKind = .primary
    var expands = true
    var isLoading = false
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack(spacing: 8) {
                if isLoading {
                    ProgressView().tint(foreground)
                } else if let systemImage {
                    Image(systemName: systemImage).font(.system(size: 18, weight: .semibold))
                }
                if !title.isEmpty {
                    Text(title).font(.headline).lineLimit(1).minimumScaleFactor(0.8)
                }
            }
            .foregroundStyle(foreground)
            .padding(.horizontal, title.isEmpty ? 0 : 14)
            .frame(maxWidth: expands ? .infinity : nil, minHeight: 52)
            .frame(width: title.isEmpty ? 52 : nil)
            .background(background, in: RoundedRectangle(cornerRadius: 8))
            .overlay {
                switch kind {
                case .outline: RoundedRectangle(cornerRadius: 8).stroke(AikuColor.borderStrong)
                case .destructiveOutline: RoundedRectangle(cornerRadius: 8).stroke(AikuColor.dangerBorder)
                case .primary, .secondary, .scan: EmptyView()
                }
            }
            .opacity(isEnabled || isLoading ? 1 : 0.5)
        }
        .disabled(isLoading)
        .accessibilityLabel(title.isEmpty ? "Scan barcode" : title)
    }

    private var foreground: Color {
        switch kind {
        case .primary: .white
        case .secondary, .scan: AikuColor.navy
        case .outline: AikuColor.textBody
        case .destructiveOutline: AikuColor.danger
        }
    }

    private var background: Color {
        switch kind {
        case .primary: AikuColor.indigo
        case .secondary: AikuColor.lavender
        case .scan: AikuColor.yellow
        case .outline, .destructiveOutline: .white
        }
    }
}
