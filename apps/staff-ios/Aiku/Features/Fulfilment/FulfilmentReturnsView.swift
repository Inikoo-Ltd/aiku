import SwiftUI

struct FulfilmentReturnsView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(\.apiClient) private var apiClient
    @State private var feed = PalletReturnsFeed()
    @State private var warehouseStore = WarehouseStore.shared
    @State private var appearance = 0
    @State private var isFiltering = false

    private var warehouseID: Int? { warehouseStore.selected?.id }

    var body: some View {
        @Bindable var feed = feed
        AppScreen(title: "Fulfilment returns", kicker: L10n.format("Goods out · %@", session.warehouseLabel)) {
            ModuleTabs(.goodsOut, current: .fulfilmentReturns)
        } content: {
            FilterChipBar(options: PalletReturnStateFilter.allCases, selection: $feed.state, title: \.title) { state in
                state.count(in: feed.counts)
            }
            FilterChipBar(options: PalletReturnTypeFilter.allCases, selection: $feed.type, title: \.title) { type in
                type.count(in: feed.counts)
            }
            SearchWithFilter(filterCount: feed.listFilter.activeCount, onFilter: { isFiltering = true }) {
                SearchField(text: $feed.search, placeholder: "Search reference or customer")
            }
            ActiveFiltersLine(
                parts: feed.listFilter.summary(sorts: PalletReturnSort.options, onlineOnlySorts: feed.onlineOnlySorts),
                onClear: { feed.listFilter = ListFilter() }
            )
            if let message = feed.loadError {
                GoodsOutLoadProblem(title: "Couldn’t load the fulfilment returns", message: message) {
                    Task {
                        guard let warehouseID else { return }
                        await feed.reload(warehouseID: warehouseID, apiClient: apiClient)
                    }
                }
            }
            if let savedAt = feed.savedAt {
                SavedCopyNote(savedAt: savedAt)
            }
            rows
            FulfilmentNote("Each return is whole pallets or stored items. Stored item returns from a customer’s shop show the platform.")
        }
        .sidePanel(isPresented: $isFiltering) {
            ListFilterPanel(
                title: "Filter returns",
                filter: feed.listFilter,
                sorts: PalletReturnSort.options,
                total: { _ in warehouseID.flatMap { feed.previewTotal(warehouseID: $0) } },
                onApply: { applied in
                    isFiltering = false
                    feed.listFilter = applied
                },
                onClose: { isFiltering = false }
            )
        }
        .onAppear { appearance += 1 }
        .task(id: ListLoadKey(request: warehouseID.map { feed.request(warehouseID: $0) }, appearance: appearance)) {
            guard let warehouseID else { return }
            if !feed.search.isEmpty {
                try? await Task.sleep(for: .milliseconds(300))
                guard !Task.isCancelled else { return }
            }
            await feed.reload(warehouseID: warehouseID, apiClient: apiClient)
        }
        .screenRefreshable {
            guard let warehouseID else { return }
            await feed.reload(warehouseID: warehouseID, apiClient: apiClient)
        }
    }

    @ViewBuilder
    private var rows: some View {
        let search = feed.search.trimmingCharacters(in: .whitespacesAndNewlines)
        let isCurrent = warehouseID.map { feed.loadedRequest == feed.request(warehouseID: $0) } ?? false
        if !feed.rows.isEmpty, isCurrent {
            CardList(feed.rows) { row in
                RecordRow(
                    row.reference,
                    date: PalletReturnText.rowDate(row),
                    party: row.customer?.name,
                    badges: [PalletReturnText.rowChip(row)],
                    details: PalletReturnText.rowDetails(row),
                    action: { router.open(FulfilmentRoute.palletReturn(id: row.id, reference: row.reference)) }
                )
                .accessibilityIdentifier("fulfilment.return.\(row.reference)")
            }
            if feed.hasMorePages {
                LocationsLoadMoreRow(isLoading: feed.isLoadingMore, page: feed.currentPage) {
                    await feed.loadNextPage(apiClient: apiClient)
                }
            }
        } else if isCurrent, feed.loadError == nil {
            EmptyState(icon: .goodsOut, title: search.isEmpty ? "No fulfilment returns here." : L10n.format("No return matches “%@”.", search))
        } else if feed.loadError == nil {
            ProgressView()
                .frame(maxWidth: .infinity, minHeight: 88)
                .accessibilityLabel("Loading fulfilment returns")
        }
    }
}
