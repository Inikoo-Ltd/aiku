import SwiftUI

struct LocationsListView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(\.apiClient) private var apiClient
    @State private var store = LocationsStore.shared
    @State private var isFiltering = false
    @State private var isScanning = false

    var body: some View {
        @Bindable var store = store
        let locations = store.listedLocations
        AppScreen(
            title: "Locations",
            kicker: "Locations · \(session.warehouseLabel)",
            actions: [
                HeaderAction(.filter, label: "Filter", badge: store.listFilter.activeCount > 0 ? store.listFilter.activeCount : nil) {
                    isFiltering = true
                }
            ]
        ) {
            ModuleTabs(.locations, current: .locations)
        } content: {
            LocationsLoadErrorBanner(store: store)
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 6) {
                    ForEach(LocationsListTab.allCases, id: \.self) { tab in
                        LocationsListTabPill(
                            title: store.count(for: tab).map { "\(tab.title) \($0.locationsGrouped)" } ?? tab.title,
                            isSelected: store.listTab == tab
                        ) {
                            store.listTab = tab
                        }
                        .accessibilityIdentifier("locations.tab.\(tab.rawValue)")
                    }
                }
                .padding(.horizontal, 16)
            }
            .padding(.horizontal, -16)
            SearchField(text: $store.listQuery, placeholder: "Location code (starts with)", onScan: { isScanning = true }) {
                openTypedOrScannedCode(store.listQuery, onlyResult: locations.count == 1 ? locations.first : nil)
            }
            if locations.isEmpty, store.isLoadingList {
                ProgressView()
                    .frame(maxWidth: .infinity, minHeight: 88)
                    .accessibilityLabel("Loading locations")
            } else if locations.isEmpty {
                EmptyState(
                    icon: .locations,
                    title: "No locations match",
                    message: "Try another code, tab or filter.",
                    actionTitle: store.listFilter.activeCount > 0 ? "Clear filters" : nil
                ) {
                    store.listFilter = .none
                }
            } else {
                LocationsLocationList(locations: locations) { location in
                    router.open(LocationsRoute.location(code: location.code))
                }
                if store.hasMorePages {
                    LocationsLoadMoreRow(isLoading: store.isLoadingMore, page: store.currentPage) {
                        await store.loadNextPage(apiClient: apiClient)
                    }
                }
            }
            HelperText("Scan a location label (Code 128) to open it.")
        }
        .loadsRemoteLocations(into: store)
        .task(id: store.listRequestKey) {
            guard store.source == .remote else { return }
            try? await Task.sleep(for: .milliseconds(300))
            guard !Task.isCancelled else { return }
            await store.reloadList(apiClient: apiClient)
        }
        .aikuSheet(isPresented: $isFiltering) {
            LocationsFilterSheet(initial: store.listFilter, areas: store.warehouse.areas) { draft in
                await store.resultCount(for: draft, apiClient: apiClient)
            } onApply: { filter in
                store.listFilter = filter
                isFiltering = false
            }
        }
        .fullScreenCover(isPresented: $isScanning) {
            LocationsScanCover(title: "Scan location") {
                isScanning = false
            } onScan: {
                isScanning = false
                router.open(LocationsRoute.location(code: store.firstScannableCode))
            }
        }
    }

    private func openTypedOrScannedCode(_ code: String, onlyResult: LocationsLocation?) {
        Task {
            if let match = await store.locationMatching(scannedCode: code, apiClient: apiClient) {
                router.open(LocationsRoute.location(code: match.code))
            } else if let onlyResult {
                router.open(LocationsRoute.location(code: onlyResult.code))
            }
        }
    }
}
