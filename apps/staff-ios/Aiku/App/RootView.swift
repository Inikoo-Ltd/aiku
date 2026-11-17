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

    var body: some View {
        Group {
            if authStore.isSignedIn {
                MainView()
                    .environment(\.signOut, SignOutAction { signOut() })
            } else {
                AuthFlowView()
            }
        }
        .preferredColorScheme(theme.mode.colorScheme)
        .onChange(of: colorScheme, initial: true) { _, scheme in
            theme.systemIsDark = scheme == .dark
        }
        .task(id: authStore.isSignedIn) {
            await authStore.refreshProfile()
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
            }
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
            guard phase == .active else { return }
            Task {
                await authStore.refreshProfile()
                await refreshPermissionsIfSignedIn()
                await refreshWarehousesIfSignedIn()
            }
        }
    }

    private var resolvedModules: ModuleAccess? {
        ModuleAccess.resolve(warehouse: warehouseStore.selected, permissions: permissionStore.current)
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
        session.modules = .everything
        LocationsStore.shared.resetToSample()
        InventoryFamiliesFeed.shared.reset()
        GoodsInStore.shared.resetToSample()
        PalletDeliveriesFeed.shared.reset()
        Task { await authStore.signOut() }
    }
}
