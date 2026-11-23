import SwiftUI

struct RootView: View {
    @Environment(PermissionStore.self) private var permissionStore
    @Environment(\.scenePhase) private var scenePhase
    @Environment(\.colorScheme) private var colorScheme
    @Environment(AuthStore.self) private var authStore
    @Environment(Session.self) private var session
    @Environment(\.apiClient) private var apiClient
    private let theme = ThemeStore.shared
    private let warehouseStore = WarehouseStore.shared
    private let languages = LanguageStore.shared
    private let appVersions = AppVersionStore.shared

    var body: some View {
        Group {
            if case .required(let latestVersionName, let updateURL) = appVersions.update {
                UpdateRequiredView(
                    latestVersionName: latestVersionName,
                    updateURL: updateURL,
                    installedVersionName: appVersions.installed.name
                )
            } else if authStore.isSignedIn {
                MainView()
                    .environment(\.signOut, SignOutAction { signOut() })
            } else {
                AuthFlowView()
            }
        }
        .environment(\.locale, languages.locale)
        .preferredColorScheme(theme.mode.colorScheme)
        .onChange(of: colorScheme, initial: true) { _, scheme in
            theme.systemIsDark = scheme == .dark
        }
        .task {
            await appVersions.check()
        }
        .task(id: authStore.isSignedIn) {
            await authStore.refreshProfile()
            await sendPendingLanguage()
            await refreshPermissionsIfSignedIn()
        }
        .onChange(of: authStore.profile, initial: true) { _, profile in
            if let profile {
                session.apply(profile)
            }
        }
        .onChange(of: authStore.mobileProfile, initial: true) { _, profile in
            if let profile {
                session.apply(profile)
                languages.applyAccountLanguage(profile.language?.code)
            }
        }
        .onChange(of: languages.pendingID, initial: true) { _, pending in
            guard pending != nil, authStore.isSignedIn else { return }
            Task { await sendPendingLanguage() }
        }
        .onChange(of: authStore.userID, initial: true) { _, userID in
            CrashReporting.identify(userID: userID)
            startOfflineWork(userID: userID)
        }
        .onChange(of: authStore.isSignedIn) { _, isSignedIn in
            if !isSignedIn {
                OfflineWork.shared.signedOut()
                session.forgetProfile()
            }
        }
        .onChange(of: warehouseStore.selected, initial: true) { _, _ in
            applySelectedWarehouse()
        }
        .onChange(of: OfflineWork.shared.isOnline) { _, isOnline in
            guard isOnline, authStore.isSignedIn, authStore.mobileProfile == nil else { return }
            Task { await authStore.refreshProfile() }
        }
        .task(id: authStore.userID) {
            guard let userID = authStore.userID, userID > 0 else { return }
            LocationsStore.shared.beginRemoteLoad()
            await warehouseStore.load(for: userID, apiClient: apiClient)
            applySelectedWarehouse()
        }
        .onChange(of: resolvedModules, initial: true) { _, modules in
            if let modules {
                session.modules = modules
            }
        }
        .onChange(of: scenePhase) { _, phase in
            if phase == .background {
                OfflineBackgroundSync.scheduleIfWaiting()
            }
            guard phase == .active else { return }
            OfflineWork.shared.syncNow(.foreground)
            Task { await appVersions.check() }
            Task {
                await authStore.refreshProfile()
                await sendPendingLanguage()
                await refreshPermissionsIfSignedIn()
                await refreshWarehousesIfSignedIn()
                await WorkBadgesStore.shared.refresh(warehouseID: warehouseStore.selectedID, apiClient: apiClient)
                if warehouseStore.selectedID == nil {
                    await ShellInboxStore.shared.refreshUnread()
                }
            }
        }
    }

    private var resolvedModules: ModuleAccess? {
        ModuleAccess.resolve(warehouse: warehouseStore.selected, isSignedIn: (authStore.userID ?? 0) > 0)
    }

    private func startOfflineWork(userID: Int?) {
        guard authStore.isSignedIn, let token = KeychainTokenStore.shared.load() else { return }
        if let userID, userID > 0 {
            OfflineWork.shared.signedIn(userID: userID, token: token)
        } else {
            OfflineWork.shared.resume(token: token)
        }
    }

    private func sendPendingLanguage() async {
        guard authStore.isSignedIn else { return }
        await languages.sendPending { code in
            try await apiClient.updateProfileLanguage(code).language?.code
        }
    }

    private func refreshPermissionsIfSignedIn() async {
        guard authStore.isSignedIn else { return }
        await permissionStore.refresh()
    }

    private func refreshWarehousesIfSignedIn() async {
        guard authStore.isSignedIn, let userID = authStore.userID, userID > 0 else { return }
        await warehouseStore.refresh(for: userID, apiClient: apiClient)
        applySelectedWarehouse()
    }

    private func applySelectedWarehouse() {
        guard let warehouse = warehouseStore.selected else { return }
        session.apply(warehouse: warehouse.name, code: warehouse.code, organisation: warehouse.organisation.name)
    }

    private func signOut() {
        permissionStore.clear()
        warehouseStore.reset()
        ClockingStore.shared.reset()
        session.modules = .everything
        LocationsStore.shared.resetToSample()
        InventoryFamiliesFeed.shared.reset()
        GoodsInFeeds.shared.reset()
        PalletDeliveriesFeed.shared.reset()
        WorkBadgesStore.shared.reset()
        ShellInboxStore.shared.reset()
        RecentSearches().clear()
        languages.forgetAccount()
        Task { await authStore.signOut() }
    }
}
