import SwiftUI

struct MenuView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(\.signOut) private var signOut
    @State private var isChoosingWarehouse = false

    var body: some View {
        ScreenScaffold(spacing: 18) {
            HeaderContainer {
                VStack(alignment: .leading, spacing: 0) {
                    Text("Everything you can open in Aiku")
                        .font(.aiku(12))
                        .foregroundStyle(AikuColor.onNav2)
                    Text("Menu")
                        .font(.aiku(20, .bold))
                        .foregroundStyle(AikuColor.onNav)
                        .accessibilityAddTraits(.isHeader)
                }
                .padding(.leading, 4)
                .frame(minHeight: 44)
                if session.hasWarehouseAccess {
                    searchButton
                }
            }
        } content: {
            profileCard
            if session.hasWarehouseAccess {
                ShellWarehouseRow(label: "Warehouse", value: session.fullContextLabel) {
                    isChoosingWarehouse = true
                }
                .background(AikuColor.soft, in: RoundedRectangle(cornerRadius: 12))
                .overlay(RoundedRectangle(cornerRadius: 12).strokeBorder(AikuColor.softBd))
                Card {
                    ShellMenuRow(title: "Warehouse dashboard", subtitle: "Backlogs, deliveries, stock movements", icon: .dashboard) {
                        router.open(ShellRoute.warehouseDashboard)
                    }
                }
            }
            ForEach(ShellMenuCatalog.groups(hasWarehouseAccess: session.hasWarehouseAccess, hasFulfilmentAccess: session.hasFulfilmentAccess)) { group in
                VStack(alignment: .leading, spacing: 8) {
                    ShellGroupHeading(title: group.title, icon: group.icon, note: group.note)
                    CardList(group.items) { item in
                        ShellMenuRow(
                            title: item.title,
                            subtitle: item.subtitle,
                            subtitleTone: item.subtitleTone,
                            icon: item.icon,
                            iconTone: item.iconTone,
                            badge: item.badge
                        ) {
                            router.shellFollow(item.link)
                        }
                        .accessibilityIdentifier("shell.menu.\(item.id)")
                    }
                }
            }
            Card {
                ShellMenuRow(title: "Appearance", subtitle: "Theme colour · light or dark", icon: .palette) {
                    router.open(ShellRoute.appearance)
                }
            }
            AikuButton("Log out", icon: .logout, kind: .danger) {
                signOut()
            }
            .accessibilityIdentifier("shell.menu.logout")
        } footer: {
            EmptyView()
        }
        .shellWarehouseSheet(isPresented: $isChoosingWarehouse)
    }

    private var searchButton: some View {
        Button {
            router.open(ShellRoute.search)
        } label: {
            HStack(spacing: 10) {
                AppIcon.search.image
                    .font(.system(size: 17))
                    .accessibilityHidden(true)
                Text("Search SKO, location, delivery note")
                    .font(.aiku(15))
                    .lineLimit(1)
                    .minimumScaleFactor(0.85)
                    .frame(maxWidth: .infinity, alignment: .leading)
            }
            .foregroundStyle(AikuColor.text4)
            .padding(.horizontal, 14)
            .frame(height: 46)
            .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 8))
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityLabel("Search SKOs, locations or delivery notes")
        .accessibilityIdentifier("shell.menu.search")
    }

    private var profileCard: some View {
        Button {
            router.open(ShellRoute.profile)
        } label: {
            HStack(spacing: 12) {
                Avatar(session.profile.initials, size: 44)
                VStack(alignment: .leading, spacing: 2) {
                    Text(session.profile.name)
                        .font(.aiku(16, .bold))
                        .foregroundStyle(AikuColor.text)
                    Text("Profile & settings")
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                AppIcon.chevronRight.image
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundStyle(AikuColor.faint)
                    .accessibilityHidden(true)
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 12)
            .cardSurface()
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityIdentifier("shell.menu.profile")
    }
}
