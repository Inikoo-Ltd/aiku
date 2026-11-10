import SwiftUI

struct MainTabBar: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session

    var body: some View {
        HStack(alignment: .center, spacing: 0) {
            item(.home, icon: .home, title: "Home")
            item(.work, icon: .work, title: "My work", badge: session.assignedCount)
            if session.canScan {
                scanButton
            }
            item(.inbox, icon: .bell, title: "Inbox", badge: session.unreadCount)
            item(.menu, icon: .menu, title: "Menu")
        }
        .padding(.horizontal, 8)
        .padding(.top, 8)
        .padding(.bottom, 4)
        .frame(maxWidth: .infinity)
        .background(AikuColor.nav.ignoresSafeArea(edges: .bottom))
    }

    private func item(_ tab: MainTab, icon: AppIcon, title: String, badge: Int = 0) -> some View {
        let isSelected = router.selectedTab == tab
        return Button {
            router.select(tab)
        } label: {
            VStack(spacing: 4) {
                icon.image
                    .font(.system(size: 21, weight: .regular))
                    .frame(height: 24)
                    .overlay(alignment: .topTrailing) {
                        if badge > 0 {
                            CountBadge(count: badge).offset(x: 14, y: -6)
                        }
                    }
                Text(title)
                    .font(.aiku(11, .semibold))
                    .lineLimit(1)
            }
            .foregroundStyle(isSelected ? AikuColor.navAccent : AikuColor.onNav2)
            .frame(maxWidth: .infinity, minHeight: 52)
            .background(isSelected ? AikuColor.navAct : .clear, in: RoundedRectangle(cornerRadius: 8))
            .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(isSelected ? AikuColor.navRing : .clear))
            .padding(.horizontal, 4)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .frame(maxWidth: .infinity)
        .accessibilityLabel(badge > 0 ? "\(title), \(badge)" : title)
        .accessibilityAddTraits(isSelected ? .isSelected : [])
        .accessibilityIdentifier("tab.\(title)")
    }

    private var scanButton: some View {
        Button {
            router.select(.scan)
        } label: {
            AppIcon.scan.image
                .font(.system(size: 26, weight: .medium))
                .foregroundStyle(AikuColor.onScan)
                .frame(width: 60, height: 60)
                .background(AikuColor.scan, in: RoundedRectangle(cornerRadius: 12))
                .overlay(RoundedRectangle(cornerRadius: 12).strokeBorder(AikuColor.divider, lineWidth: 4))
                .shadow(color: Color(hex: 0x1E1B4B, opacity: 0.45), radius: 10, y: 8)
        }
        .buttonStyle(.plain)
        .frame(maxWidth: .infinity)
        .offset(y: -22)
        .accessibilityLabel("Scan barcode")
        .accessibilityIdentifier("tab.Scan")
    }
}
