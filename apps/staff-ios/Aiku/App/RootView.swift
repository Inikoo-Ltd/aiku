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
        .task(id: authStore.profile?.id) {
            guard let userID = authStore.profile?.id, userID > 0 else { return }
            LocationsStore.shared.beginRemoteLoad()
            await warehouseStore.load(for: userID, apiClient: apiClient)
            if let warehouse = warehouseStore.selected {
                session.profile.warehouseName = warehouse.name
                session.profile.warehouseCode = warehouse.code
                session.profile.organisation = warehouse.organisation.name
            }
        }
        .onChange(of: scenePhase) { _, phase in
            guard phase == .active else { return }
            Task {
                await authStore.refreshProfile()
                await refreshPermissionsIfSignedIn()
            }
        }
    }

    private func refreshPermissionsIfSignedIn() async {
        guard authStore.isSignedIn else { return }
        await permissionStore.refresh()
    }

    private func signOut() {
        permissionStore.clear()
        warehouseStore.reset()
        LocationsStore.shared.resetToSample()
        InventoryFamiliesFeed.shared.reset()
        Task { await authStore.signOut() }
    }
}
