import SwiftUI

struct FulfilmentReturnsView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(\.apiClient) private var apiClient
    @State private var feed = PalletReturnsFeed()
    @State private var warehouseStore = WarehouseStore.shared
    @State private var appearance = 0

    private var warehouseID: Int? { warehouseStore.selected?.id }

    var body: some View {
        @Bindable var feed = feed
        AppScreen(title: "Fulfilment returns", kicker: "Goods out · \(session.warehouseLabel)") {
            ModuleTabs(.goodsOut, current: .fulfilmentReturns)
        } content: {
            FilterChipBar(options: PalletReturnStateFilter.allCases, selection: $feed.state, title: \.title) { state in
                state.count(in: feed.counts)
            }
            FilterChipBar(options: PalletReturnTypeFilter.allCases, selection: $feed.type, title: \.title) { type in
                type.count(in: feed.counts)
            }
            SearchField(text: $feed.search, placeholder: "Search reference or customer")
            if let message = feed.loadError {
                GoodsOutLoadProblem(title: "Couldn’t load the fulfilment returns", message: message) {
                    Task {
                        guard let warehouseID else { return }
                        await feed.reload(warehouseID: warehouseID, apiClient: apiClient)
                    }
                }
            }
            rows
            FulfilmentNote("Each return is whole pallets or stored items. Stored item returns from a customer’s shop show the platform.")
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
                ListRow(
                    row.reference,
                    subtitle: PalletReturnText.rowSubtitle(row),
                    chips: [Chip(row.stateLabel ?? row.state, PalletReturnText.stateTone(row.state))],
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
            EmptyState(icon: .goodsOut, title: search.isEmpty ? "No fulfilment returns here." : "No return matches “\(search)”.")
        } else if feed.loadError == nil {
            ProgressView()
                .frame(maxWidth: .infinity, minHeight: 88)
                .accessibilityLabel("Loading fulfilment returns")
        }
    }
}
