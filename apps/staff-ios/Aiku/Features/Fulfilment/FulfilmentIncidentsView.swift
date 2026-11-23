import SwiftUI

struct FulfilmentIncidentsView: View {
    @Environment(AppRouter.self) private var router
    @Environment(\.apiClient) private var apiClient
    @State private var feed = StoredPalletsFeed(filter: .incidents)
    @State private var warehouseStore = WarehouseStore.shared

    @State private var appearance = 0

    private var warehouseID: Int? { warehouseStore.selected?.id }

    var body: some View {
        @Bindable var feed = feed
        AppScreen(title: "Incidents", kicker: "Stored pallets") {
            FilterChipBar(options: PalletIncidentFilter.allCases, selection: $feed.incidentFilter, title: \.title) { filter in
                filter.count(in: feed.counts)
            }
            if let message = feed.loadError {
                GoodsOutLoadProblem(title: "Couldn’t load the incidents", message: message) {
                    Task {
                        guard let warehouseID else { return }
                        await feed.reload(warehouseID: warehouseID, apiClient: apiClient)
                    }
                }
            }
            StoredPalletRows(feed: feed, warehouseID: warehouseID, showsIncident: true, emptyTitle: "No pallet incidents.") { pallet in
                router.open(FulfilmentRoute.pallet(id: pallet.id, reference: pallet.reference))
            }
            FulfilmentNote("Damaged and lost are reported from the pallet page. Other incidents come from pallets not picked in a return.")
        }
        .onAppear { appearance += 1 }
        .task(id: ListLoadKey(request: warehouseID.map { feed.request(warehouseID: $0) }, appearance: appearance)) {
            guard let warehouseID else { return }
            await feed.reload(warehouseID: warehouseID, apiClient: apiClient)
        }
        .screenRefreshable {
            guard let warehouseID else { return }
            await feed.reload(warehouseID: warehouseID, apiClient: apiClient)
        }
    }
}
