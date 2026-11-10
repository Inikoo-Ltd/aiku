import SwiftUI

struct ShellProfileView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(AuthStore.self) private var authStore
    @Environment(\.signOut) private var signOut
    @AppStorage(ScanSettingsKey.inputMode) private var inputMode = ScanInputMode.automatic
    @AppStorage(ScanSettingsKey.sound) private var playsSound = true
    @AppStorage(ScanSettingsKey.vibrate) private var vibrates = true
    @AppStorage(ShellLanguages.storageKey) private var languageID = ShellLanguages.defaultID
    @State private var isChoosingWarehouse = false

    var body: some View {
        AppScreen(title: "Profile", kicker: "Account", spacing: 16) {
            userCard
            if session.hasWarehouseAccess {
                section("Organisation & warehouse") {
                    ShellWarehouseRow(label: "Current", value: session.contextLabel) {
                        isChoosingWarehouse = true
                    }
                    .cardSurface()
                }
            }
            if session.canScan {
                section("Scanning") {
                    Card {
                        ForEach(ScanInputMode.allCases) { mode in
                            scanModeRow(mode)
                            RowDivider()
                        }
                        ToggleRow("Sound on scan", isOn: $playsSound)
                        RowDivider()
                        ToggleRow("Vibrate on scan", isOn: $vibrates)
                    }
                }
            }
            section("Work") {
                Card {
                    ShellValueRow(title: "Clocking", value: session.hasWarehouseAccess ? "Clocked in" : "Clocked out", valueTone: session.hasWarehouseAccess ? .success : nil) {
                        router.open(ClockingRoute.clocking)
                    }
                    RowDivider()
                    ShellValueRow(title: "Timesheets", value: ShellProfileContent.timesheetSummary) {
                        router.open(ClockingRoute.timesheets)
                    }
                }
            }
            section("App") {
                Card {
                    ShellValueRow(title: "Appearance", value: "Theme & dark mode") {
                        router.open(ShellRoute.appearance)
                    }
                    RowDivider()
                    ShellValueRow(title: "Language", value: ShellLanguages.language(id: languageID).nativeName) {
                        router.open(ShellRoute.language)
                    }
                    RowDivider()
                    ShellValueRow(title: "Offline & sync", value: ShellProfileContent.syncWaiting) {
                        router.open(SystemRoute.syncQueue)
                    }
                    RowDivider()
                    ShellValueRow(title: "Notifications", value: ShellProfileContent.notifications) {
                        router.select(.inbox)
                    }
                    RowDivider()
                    ShellValueRow(title: "Version", value: appVersion)
                }
            }
            AikuButton("Log out", icon: .logout, kind: .danger) {
                signOut()
            }
            .accessibilityIdentifier("shell.profile.logout")
            #if DEBUG
            ShellDeveloperCard()
            #endif
        }
        .shellWarehouseSheet(isPresented: $isChoosingWarehouse)
        .task { await authStore.refreshProfile() }
        .refreshable { await authStore.refreshProfile() }
    }

    private var userCard: some View {
        HStack(spacing: 14) {
            Avatar(session.profile.initials, size: 56)
            VStack(alignment: .leading, spacing: 2) {
                Text(session.profile.name)
                    .font(.aiku(17, .bold))
                    .foregroundStyle(AikuColor.text)
                Text(session.profile.username)
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
                if !session.profile.jobPositions.isEmpty {
                    FlowLayout(spacing: 6, lineSpacing: 6) {
                        ForEach(session.profile.jobPositions, id: \.self) { position in
                            StatusChip(position, tone: .info)
                        }
                    }
                    .padding(.vertical, 2)
                } else {
                    Text(session.profile.role)
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                }
                if let email = session.profile.email {
                    Text(email)
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                }
                if let error = authStore.lastProfileError {
                    Text("Couldn’t refresh: \(error)")
                        .font(.aiku(12))
                        .foregroundStyle(AikuColor.danger)
                }
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            if authStore.isRefreshingProfile {
                ProgressView()
            }
        }
        .padding(16)
        .cardSurface()
        .accessibilityElement(children: .combine)
    }

    private func section<Content: View>(_ title: String, @ViewBuilder content: () -> Content) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            SectionHeading(title)
            content()
        }
    }

    private func scanModeRow(_ mode: ScanInputMode) -> some View {
        Button {
            inputMode = mode
        } label: {
            HStack(spacing: 12) {
                ShellRadioDot(isOn: inputMode == mode)
                VStack(alignment: .leading, spacing: 0) {
                    Text(ShellProfileContent.scanModeTitle(mode))
                        .font(.aiku(15, .semibold))
                        .foregroundStyle(AikuColor.text)
                    if let detail = ShellProfileContent.scanModeDetail(mode) {
                        Text(detail)
                            .font(.aiku(12))
                            .foregroundStyle(AikuColor.text3)
                    }
                }
                .multilineTextAlignment(.leading)
                .frame(maxWidth: .infinity, alignment: .leading)
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 6)
            .frame(minHeight: 56)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(inputMode == mode ? .isSelected : [])
        .accessibilityIdentifier("shell.profile.scan.\(mode.rawValue)")
    }

    private var appVersion: String {
        Bundle.main.object(forInfoDictionaryKey: "CFBundleShortVersionString") as? String ?? ShellProfileContent.fallbackVersion
    }
}

#if DEBUG
private struct ShellDeveloperCard: View {
    @Environment(AppRouter.self) private var router

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            SectionHeading("Developer", detail: "Debug only")
            Card {
                ShellValueRow(title: "Design states") {
                    router.open(SystemRoute.statesGallery)
                }
                .accessibilityIdentifier("shell.profile.designStates")
            }
        }
    }
}

#endif
