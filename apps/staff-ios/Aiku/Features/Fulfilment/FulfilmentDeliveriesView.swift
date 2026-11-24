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
    @State private var isFiltering = false

    private var warehouseID: Int? { warehouseStore.selected?.id }

    var body: some View {
        @Bindable var feed = feed
        AppScreen(title: "Fulfilment deliveries", kicker: L10n.format("Goods in · %@", session.warehouseLabel)) {
            ModuleTabs(.goodsIn, current: .fulfilmentDeliveries)
        } content: {
            FilterChipBar(options: PalletDeliveryFilter.allCases, selection: $feed.filter, title: \.title) { filter in
                filter.count(in: feed.counts)
            }
            SearchWithFilter(filterCount: feed.listFilter.activeCount, onFilter: { isFiltering = true }) {
                SearchField(text: $feed.search, placeholder: "Search reference or customer", onScan: { router.select(.scan) })
            }
            ActiveFiltersLine(
                parts: feed.listFilter.summary(sorts: PalletDeliverySort.options, dateLabel: PalletDeliverySort.dateLabel),
                onClear: { feed.listFilter = ListFilter() }
            )
            if let lookingUpCode {
                InlineAlert(tone: .info, title: L10n.format("Looking up %@…", lookingUpCode))
            }
            if let message = feed.loadError {
                GoodsOutLoadProblem(title: "Couldn’t load the fulfilment deliveries", message: message) {
                    Task {
                        guard let warehouseID else { return }
                        await feed.reload(warehouseID: warehouseID, apiClient: apiClient)
                    }
                }
            }
            if let savedAt = feed.savedAt {
                SavedCopyNote(savedAt: savedAt)
            }
            listContent
            FulfilmentNote("Fulfilment customers’ goods arrive as pallets, boxes or oversize items. References are per customer (customer code + number).")
        }
        .sidePanel(isPresented: $isFiltering) {
            ListFilterPanel(
                title: "Filter deliveries",
                filter: feed.listFilter,
                sorts: PalletDeliverySort.options,
                dateLabel: PalletDeliverySort.dateLabel,
                total: { draft in warehouseID.flatMap { feed.previewTotal(for: draft, warehouseID: $0) } },
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
        .hardwareScanner(isEnabled: !isFiltering) { code in
            Task { await openScanned(code) }
        }
    }

    @ViewBuilder
    private var listContent: some View {
        let search = feed.search.trimmingCharacters(in: .whitespacesAndNewlines)
        let isCurrent = warehouseID.map { feed.loadedRequest == feed.request(warehouseID: $0) } ?? false
        if !feed.rows.isEmpty, isCurrent {
            CardList(feed.rows.map(PalletDeliveryListItem.init)) { item in
                let moment = PalletDeliveryText.rowMoment(item.row)
                RecordRow(
                    item.row.reference,
                    date: moment.date,
                    dateCaption: moment.caption,
                    party: item.row.customer?.name,
                    badges: [PalletDeliveryText.rowChip(item.row)],
                    details: PalletDeliveryText.rowDetails(item.row),
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
                title: search.isEmpty ? "No fulfilment deliveries in this state." : L10n.format("No delivery matches “%@”", search)
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
                ScanFeedback.shared.rejected()
                toasts.show(L10n.format("%@ is not a fulfilment delivery in this warehouse", scanned), tone: .info)
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
