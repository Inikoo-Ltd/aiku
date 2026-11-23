import SwiftUI

struct HomeView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(AuthStore.self) private var authStore
    @Environment(\.apiClient) private var apiClient
    @State private var warehouseStore = WarehouseStore.shared
    private let workBadges = WorkBadgesStore.shared
    private let clocking = ClockingStore.shared
    private let inbox = ShellInboxStore.shared
    private let appVersions = AppVersionStore.shared
    private let offline = OfflineWork.shared

    var body: some View {
        ScreenScaffold(spacing: 20) {
            HeaderContainer {
                header
            }
        } content: {
            if case .available(_, let latestVersionName, let updateURL) = appVersions.banner {
                AppUpdateBanner(latestVersionName: latestVersionName, updateURL: updateURL) {
                    appVersions.dismissBanner()
                }
            }
            clockingCard
            conflictsRow
            if session.hasWarehouseAccess {
                shortcuts
            }
            modules
            profileLink
        } footer: {
            EmptyView()
        }
        .task(id: warehouseStore.selectedID) {
            await workBadges.refresh(warehouseID: warehouseStore.selectedID, apiClient: apiClient)
            if warehouseStore.selectedID == nil {
                await inbox.refreshUnread()
            }
        }
        .task {
            await clocking.load()
        }
        .screenRefreshable {
            if authStore.mobileProfile == nil {
                await authStore.refreshProfile()
            }
            await clocking.load()
            await workBadges.refresh(warehouseID: warehouseStore.selectedID, apiClient: apiClient)
            if warehouseStore.selectedID == nil {
                await inbox.refreshUnread()
            }
        }
    }

    private var header: some View {
        HStack(spacing: 12) {
            Button {
                router.open(ShellRoute.profile)
            } label: {
                if session.hasProfile {
                    Avatar(session.profile.initials, size: 44)
                        .overlay(Circle().strokeBorder(Color.white.opacity(0.25), lineWidth: 2))
                } else {
                    SkeletonBlock(width: 44, height: 44, radius: 22, color: AikuColor.onNav.opacity(0.25))
                }
            }
            .buttonStyle(.plain)
            .accessibilityLabel("Your profile and settings")
            .accessibilityIdentifier("shell.home.profile")
            VStack(alignment: .leading, spacing: 2) {
                if let context = ShellHomeContent.context(
                    hasWarehouseAccess: session.hasWarehouseAccess,
                    organisation: session.profile.organisation,
                    warehouseName: session.profile.warehouseName
                ) {
                    Text(context)
                        .font(.aiku(12))
                        .foregroundStyle(AikuColor.onNav2)
                        .lineLimit(1)
                } else {
                    SkeletonBlock(width: 160, height: 10, radius: 5, color: AikuColor.onNav.opacity(0.25))
                        .padding(.vertical, 3)
                }
                if session.hasProfile {
                    Text(ShellHomeContent.greeting(hour: Calendar.current.component(.hour, from: .now), firstName: session.profile.firstName))
                        .font(.aiku(20, .bold))
                        .foregroundStyle(AikuColor.onNav)
                        .lineLimit(1)
                        .minimumScaleFactor(0.75)
                        .accessibilityAddTraits(.isHeader)
                } else {
                    SkeletonBlock(width: 200, height: 20, radius: 6, color: AikuColor.onNav.opacity(0.25))
                        .padding(.vertical, 4)
                }
            }
            .frame(maxWidth: .infinity, alignment: .leading)
        }
        .padding(.top, 4)
        .padding(.bottom, 8)
    }

    private var clockingCard: some View {
        TimelineView(.everyMinute) { _ in
            clockingCardContent(ShellHomeContent.clockStatus(clocking))
        }
    }

    private func clockingCardContent(_ status: ShellClockStatus?) -> some View {
        let isClockedIn = status?.isClockedIn == true
        return HStack(spacing: 14) {
            AppIcon.faUserClock.image
                .font(.system(size: 20))
                .foregroundStyle(isClockedIn ? AikuColor.success : AikuColor.text3)
                .frame(width: 44, height: 44)
                .background(isClockedIn ? AikuColor.successSoft : AikuColor.bg, in: Circle())
                .accessibilityHidden(true)
            VStack(alignment: .leading, spacing: status == nil ? 8 : 2) {
                if let status {
                    Text(status.title)
                        .font(.aiku(16, .bold))
                        .foregroundStyle(AikuColor.text)
                    Text(status.detail)
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                    if let weekTotal = ShellHomeContent.weekTotal(clocking) {
                        Text("This week \(weekTotal)")
                            .font(.aiku(13))
                            .foregroundStyle(AikuColor.text3)
                    }
                    if let updatedAt = clocking.lastUpdatedAt {
                        Text(LastUpdatedNote.text(updatedAt))
                            .font(.aiku(12))
                            .foregroundStyle(AikuColor.text4)
                    }
                } else {
                    SkeletonBlock(width: 150, height: 14)
                    SkeletonBlock(width: 110, height: 11)
                }
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
                ForEach(ShellHomeContent.shortcuts(for: session.visibleModules, goodsOutUnassigned: workBadges.goodsOutUnassignedCount)) { shortcut in
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

    private var conflictsRow: some View {
        let needsYou = offline.needsYouCount
        let needsAttention = needsYou > 0
        return Button {
            router.open(SystemRoute.conflicts)
        } label: {
            HStack(spacing: 14) {
                AppIcon.faExclamationTriangle.image
                    .font(.system(size: 20))
                    .foregroundStyle(needsAttention ? AikuColor.warnInk : AikuColor.link)
                    .frame(width: 44, height: 44)
                    .background(needsAttention ? AikuColor.surface : AikuColor.soft, in: RoundedRectangle(cornerRadius: 10))
                    .accessibilityHidden(true)
                VStack(alignment: .leading, spacing: 2) {
                    Text("Resolve conflicts")
                        .font(.aiku(16, .bold))
                        .foregroundStyle(needsAttention ? AikuColor.warnInk : AikuColor.text)
                    Text(ShellHomeContent.conflictsText(needsYou: needsYou))
                        .font(.aiku(13, needsAttention ? .semibold : .regular))
                        .foregroundStyle(needsAttention ? AikuColor.warnInk : AikuColor.text3)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                AppIcon.chevronRight.image
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundStyle(needsAttention ? AikuColor.warnInk : AikuColor.faint)
                    .accessibilityHidden(true)
            }
            .padding(14)
            .background(needsAttention ? AikuColor.warnSoft : AikuColor.surface, in: RoundedRectangle(cornerRadius: 12))
            .overlay(RoundedRectangle(cornerRadius: 12).strokeBorder(needsAttention ? AikuColor.warnInk.opacity(0.35) : AikuColor.border))
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityElement(children: .combine)
        .accessibilityIdentifier("shell.home.conflicts")
    }

    @ViewBuilder
    private var modules: some View {
        if session.hasWarehouseAccess {
            ForEach(ShellHomeContent.modules(hasWarehouseAccess: true, warehouseLabel: session.warehouseLabel)) { module in
                Button {
                    router.shellFollow(module.link)
                } label: {
                    HStack(spacing: 14) {
                        IconTile(module.icon, tone: .info, size: 44, radius: 10)
                        VStack(alignment: .leading, spacing: 2) {
                            Text(module.title)
                                .font(.aiku(16, .bold))
                                .foregroundStyle(AikuColor.text)
                            if module.subtitle.isEmpty {
                                SkeletonBlock(width: 110, height: 10, radius: 5)
                                    .padding(.vertical, 3)
                            } else {
                                Text(module.subtitle)
                                    .font(.aiku(13))
                                    .foregroundStyle(AikuColor.text3)
                            }
                        }
                        .frame(maxWidth: .infinity, alignment: .leading)
                        AppIcon.chevronRight.image
                            .font(.system(size: 14, weight: .semibold))
                            .foregroundStyle(AikuColor.faint)
                            .accessibilityHidden(true)
                    }
                    .padding(14)
                    .cardSurface()
                    .contentShape(Rectangle())
                }
                .buttonStyle(.plain)
                .accessibilityIdentifier("shell.home.module.\(module.id)")
            }
        } else {
            Text("Warehouse appears here when your account has access to it.")
                .font(.aiku(13))
                .foregroundStyle(AikuColor.text3)
                .padding(.horizontal, 4)
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
