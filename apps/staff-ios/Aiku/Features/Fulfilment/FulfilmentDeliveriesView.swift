import SwiftUI

struct FulfilmentDeliveriesView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(ToastCenter.self) private var toasts
    @Environment(\.apiClient) private var apiClient
    @State private var feed = PalletDeliveriesFeed.shared
    @State private var warehouseStore = WarehouseStore.shared
    @State private var lookingUpCode: String?

    @State private var appearance = 0

    private var warehouseID: Int? { warehouseStore.selected?.id }

    var body: some View {
        @Bindable var feed = feed
        AppScreen(title: "Fulfilment deliveries", kicker: "Goods in · \(session.warehouseLabel)") {
            ModuleTabs(.goodsIn, current: .fulfilmentDeliveries)
        } content: {
            FilterChipBar(options: PalletDeliveryFilter.allCases, selection: $feed.filter, title: \.title) { filter in
                filter.count(in: feed.counts)
            }
            SearchField(text: $feed.search, placeholder: "Search reference or customer", onScan: { router.select(.scan) })
            if let lookingUpCode {
                InlineAlert(tone: .info, title: "Looking up \(lookingUpCode)…")
            }
            if let message = feed.loadError {
                InlineAlert(title: "Couldn’t load the fulfilment deliveries", message: message)
            }
            listContent
            FulfilmentNote("Fulfilment customers’ goods arrive as pallets, boxes or oversize items. References are per customer (customer code + number).")
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
        .hardwareScanner { code in
            Task { await openScanned(code) }
        }
    }

    @ViewBuilder
    private var listContent: some View {
        let search = feed.search.trimmingCharacters(in: .whitespacesAndNewlines)
        let isCurrent = warehouseID.map { feed.loadedRequest == feed.request(warehouseID: $0) } ?? false
        if !feed.rows.isEmpty, isCurrent {
            CardList(feed.rows.map(PalletDeliveryListItem.init)) { item in
                ListRow(
                    item.row.reference,
                    subtitle: PalletDeliveryText.rowSubtitle(item.row),
                    chips: [Chip(item.row.stateLabel ?? item.row.state, PalletDeliveryText.deliveryTone(item.row.state))],
                    action: { open(item.row.id, item.row.reference) }
                )
                .accessibilityIdentifier("fulfilment.delivery.\(item.row.reference)")
            }
            if feed.hasMorePages {
                LocationsLoadMoreRow(isLoading: feed.isLoadingMore, page: feed.currentPage) {
                    await feed.loadNextPage(apiClient: apiClient)
                }
            }
        } else if isCurrent, feed.loadError == nil {
            EmptyState(
                icon: .stockDelivery,
                title: search.isEmpty ? "No fulfilment deliveries in this state." : "No delivery matches “\(search)”"
            )
        } else if feed.loadError == nil {
            ProgressView()
                .frame(maxWidth: .infinity, minHeight: 88)
                .accessibilityLabel("Loading fulfilment deliveries")
        }
    }

    private func open(_ id: Int, _ reference: String) {
        router.open(FulfilmentRoute.palletDelivery(id: id, reference: reference))
    }

    private func openScanned(_ code: String) async {
        let scanned = code.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !scanned.isEmpty, lookingUpCode == nil, let warehouseID else { return }
        if let listed = feed.rows.first(where: { $0.reference.caseInsensitiveCompare(scanned) == .orderedSame }) {
            open(listed.id, listed.reference)
            return
        }
        lookingUpCode = scanned
        defer { lookingUpCode = nil }
        do {
            let result = try await apiClient.scan(warehouseID: warehouseID, code: scanned)
            if result.scanType == .palletDelivery, let delivery = result.palletDelivery {
                open(delivery.id, delivery.reference)
            } else {
                toasts.show("\(scanned) is not a fulfilment delivery in this warehouse", tone: .info)
            }
        } catch {
            toasts.show(ShellScanCatalog.problem(error), tone: .danger)
        }
    }
}

private struct PalletDeliveryListItem: Identifiable {
    let row: MobilePalletDeliveryRow
    var id: Int { row.id }
}
