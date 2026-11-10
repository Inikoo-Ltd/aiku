import SwiftUI

struct HomeView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var inbox = ShellInboxStore.shared

    var body: some View {
        ScreenScaffold(spacing: 20) {
            HeaderContainer {
                header
            }
        } content: {
            clockingCard
            if session.hasWarehouseAccess {
                shortcuts
            }
            modules
            profileLink
        } footer: {
            EmptyView()
        }
        .onAppear {
            session.unreadCount = inbox.unreadCount
        }
    }

    private var header: some View {
        HStack(spacing: 12) {
            Button {
                router.open(ShellRoute.profile)
            } label: {
                Avatar(session.profile.initials, size: 44)
                    .overlay(Circle().strokeBorder(Color.white.opacity(0.25), lineWidth: 2))
            }
            .buttonStyle(.plain)
            .accessibilityLabel("Your profile and settings")
            .accessibilityIdentifier("shell.home.profile")
            VStack(alignment: .leading, spacing: 2) {
                Text(ShellHomeContent.context(
                    hasWarehouseAccess: session.hasWarehouseAccess,
                    organisation: session.profile.organisation,
                    warehouseName: session.profile.warehouseName
                ))
                .font(.aiku(12))
                .foregroundStyle(AikuColor.onNav2)
                .lineLimit(1)
                Text(ShellHomeContent.greeting(hour: Calendar.current.component(.hour, from: .now), firstName: session.profile.firstName))
                    .font(.aiku(20, .bold))
                    .foregroundStyle(AikuColor.onNav)
                    .lineLimit(1)
                    .minimumScaleFactor(0.75)
                    .accessibilityAddTraits(.isHeader)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
        }
        .padding(.top, 4)
        .padding(.bottom, 8)
    }

    private var clockingCard: some View {
        let status = ShellHomeContent.clockStatus(hasWarehouseAccess: session.hasWarehouseAccess, warehouseName: session.profile.warehouseName)
        return HStack(spacing: 14) {
            AppIcon.clock.image
                .font(.system(size: 20))
                .foregroundStyle(status.isClockedIn ? AikuColor.success : AikuColor.text3)
                .frame(width: 44, height: 44)
                .background(status.isClockedIn ? AikuColor.successSoft : AikuColor.bg, in: Circle())
                .accessibilityHidden(true)
            VStack(alignment: .leading, spacing: 2) {
                Text(status.title)
                    .font(.aiku(16, .bold))
                    .foregroundStyle(AikuColor.text)
                Text(status.detail)
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            AikuButton("Clocking", size: .small, fullWidth: false) {
                router.open(ClockingRoute.clocking)
            }
            .accessibilityIdentifier("shell.home.clocking")
        }
        .padding(16)
        .cardSurface()
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Clocking")
    }

    private var shortcuts: some View {
        VStack(alignment: .leading, spacing: 10) {
            SectionHeading("Shortcuts")
            LazyVGrid(columns: Array(repeating: GridItem(.flexible(), spacing: 8), count: 4), spacing: 8) {
                ForEach(ShellHomeContent.shortcuts) { shortcut in
                    Button {
                        router.shellFollow(shortcut.link)
                    } label: {
                        VStack(spacing: 6) {
                            IconTile(shortcut.icon, tone: .info, size: 40, radius: 10)
                            Text(shortcut.title)
                                .font(.aiku(12, .semibold))
                                .foregroundStyle(AikuColor.text2)
                                .lineLimit(1)
                                .minimumScaleFactor(0.8)
                        }
                        .padding(.top, 12)
                        .padding(.bottom, 10)
                        .padding(.horizontal, 4)
                        .frame(maxWidth: .infinity)
                        .cardSurface()
                        .overlay(alignment: .topTrailing) {
                            if let badge = shortcut.badge {
                                CountBadge(count: badge).padding(.top, 6).padding(.trailing, 8)
                            }
                        }
                        .contentShape(Rectangle())
                    }
                    .buttonStyle(.plain)
                    .accessibilityLabel(shortcut.badge.map { "\(shortcut.title), \($0)" } ?? shortcut.title)
                    .accessibilityIdentifier("shell.home.shortcut.\(shortcut.id)")
                }
            }
        }
    }

    private var modules: some View {
        VStack(alignment: .leading, spacing: 10) {
            SectionHeading("Your modules")
            LazyVGrid(columns: Array(repeating: GridItem(.flexible(), spacing: 10, alignment: .top), count: 2), spacing: 10) {
                ForEach(ShellHomeContent.modules(hasWarehouseAccess: session.hasWarehouseAccess, warehouseLabel: session.warehouseLabel)) { module in
                    Button {
                        router.shellFollow(module.link)
                    } label: {
                        VStack(alignment: .leading, spacing: 10) {
                            IconTile(module.icon, tone: .info, size: 40, radius: 10)
                            VStack(alignment: .leading, spacing: 2) {
                                Text(module.title)
                                    .font(.aiku(15, .bold))
                                    .foregroundStyle(AikuColor.text)
                                Text(module.subtitle)
                                    .font(.aiku(12))
                                    .foregroundStyle(AikuColor.text3)
                            }
                            .multilineTextAlignment(.leading)
                        }
                        .padding(14)
                        .frame(maxWidth: .infinity, minHeight: 112, alignment: .topLeading)
                        .cardSurface()
                        .contentShape(Rectangle())
                    }
                    .buttonStyle(.plain)
                    .accessibilityIdentifier("shell.home.module.\(module.id)")
                }
            }
            if !session.hasWarehouseAccess {
                Text("Warehouse appears here when your account has access to it.")
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
                    .padding(.horizontal, 4)
            }
        }
    }

    private var profileLink: some View {
        Button {
            router.open(ShellRoute.profile)
        } label: {
            HStack(spacing: 12) {
                Text("Profile & settings")
                    .font(.aiku(15, .semibold))
                    .foregroundStyle(AikuColor.text)
                    .frame(maxWidth: .infinity, alignment: .leading)
                AppIcon.chevronRight.image
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundStyle(AikuColor.faint)
                    .accessibilityHidden(true)
            }
            .padding(.horizontal, 14)
            .frame(minHeight: 56)
            .cardSurface()
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
    }
}
