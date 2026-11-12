import SwiftUI

struct LocationsRemoteLoading: ViewModifier {
    let store: LocationsStore
    @State private var warehouseStore = WarehouseStore.shared
    @Environment(AuthStore.self) private var authStore
    @Environment(Session.self) private var session
    @Environment(\.apiClient) private var apiClient

    func body(content: Content) -> some View {
        content.task(id: LocationsLoadKey(userID: authStore.profile?.id ?? 0, warehouseID: warehouseStore.selectedID)) {
            guard let userID = authStore.profile?.id, userID > 0 else {
                if store.source == .remote {
                    store.resetToSample()
                    warehouseStore.reset()
                }
                return
            }
            await warehouseStore.load(for: userID, apiClient: apiClient)
            if let warehouse = warehouseStore.selected {
                session.profile.warehouseName = warehouse.name
                session.profile.warehouseCode = warehouse.code
                session.profile.organisation = warehouse.organisation.name
            }
            await store.loadRemote(for: userID, warehouse: warehouseStore.selected, apiClient: apiClient)
        }
    }
}

struct LocationsLoadErrorBanner: View {
    let store: LocationsStore

    var body: some View {
        if let message = store.loadError {
            InlineAlert(title: "Couldn’t load locations from Aiku", message: message)
        }
    }
}

struct LocationsLoadMoreRow: View {
    let isLoading: Bool
    let page: Int
    let loadMore: () async -> Void
    @State private var isNearScreen = false

    var body: some View {
        let triggerLine = UIScreen.main.bounds.height + 240
        HStack(spacing: 8) {
            ProgressView()
            Text("Loading more locations…")
                .font(.aiku(13))
                .foregroundStyle(AikuColor.text3)
        }
        .frame(maxWidth: .infinity, minHeight: 44)
        .opacity(isLoading ? 1 : 0.6)
        .onGeometryChange(for: Bool.self) { proxy in
            proxy.frame(in: .global).minY < triggerLine
        } action: { isNear in
            isNearScreen = isNear
        }
        .task(id: LoadTrigger(isNearScreen: isNearScreen, page: page)) {
            guard isNearScreen else { return }
            await loadMore()
        }
        .accessibilityElement(children: .combine)
        .accessibilityLabel("Loading more locations")
    }

    private struct LoadTrigger: Hashable {
        let isNearScreen: Bool
        let page: Int
    }
}

extension View {
    func loadsRemoteLocations(into store: LocationsStore) -> some View {
        modifier(LocationsRemoteLoading(store: store))
    }
}
