import SwiftUI

struct FulfilmentPalletsView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(ToastCenter.self) private var toasts
    @Environment(\.apiClient) private var apiClient
    @State private var feed = StoredPalletsFeed()
    @State private var warehouseStore = WarehouseStore.shared
    @State private var lookingUpCode: String?

    @State private var appearance = 0

    private var warehouseID: Int? { warehouseStore.selected?.id }

    var body: some View {
        @Bindable var feed = feed
        AppScreen(title: "Stored pallets", kicker: "Inventory · \(session.warehouseLabel)") {
            ModuleTabs(.inventory, current: .storedPallets)
        } content: {
            FilterChipBar(options: StoredPalletFilter.allCases, selection: $feed.filter, title: \.title) { filter in
                filter.count(in: feed.counts)
            }
            SearchField(text: $feed.search, placeholder: "Search pallet, customer ref or location")
            if feed.filter == .incidents {
                Card {
                    ListRow("Incident reports", subtitle: "What was reported, by whom and when", icon: .warning, iconTone: .danger) {
                        router.open(FulfilmentRoute.palletIncidents)
                    }
                    .accessibilityIdentifier("fulfilment.pallets.incidentReports")
                }
            }
            if let lookingUpCode {
                InlineAlert(tone: .info, title: "Looking up \(lookingUpCode)…")
            }
            if let message = feed.loadError {
                InlineAlert(title: "Couldn’t load the pallets", message: message)
            }
            StoredPalletRows(feed: feed, warehouseID: warehouseID, showsIncident: false, emptyTitle: "No pallets here.") { pallet in
                router.open(FulfilmentRoute.pallet(id: pallet.id, reference: pallet.reference))
            }
            FulfilmentNote("Fulfilment customers’ pallets, boxes and oversize items in this warehouse. Only shown with fulfilment permission.")
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
        .hardwareScanner { code in
            Task { await openScanned(code) }
        }
    }

    private func openScanned(_ code: String) async {
        let scanned = code.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !scanned.isEmpty, lookingUpCode == nil, let warehouseID else { return }
        if let listed = feed.rows.first(where: { $0.reference.caseInsensitiveCompare(scanned) == .orderedSame }) {
            router.open(FulfilmentRoute.pallet(id: listed.id, reference: listed.reference))
            return
        }
        lookingUpCode = scanned
        defer { lookingUpCode = nil }
        do {
            let result = try await apiClient.scan(warehouseID: warehouseID, code: scanned)
            if result.scanType == .pallet, let pallet = result.pallet {
                router.open(FulfilmentRoute.pallet(id: pallet.id, reference: pallet.reference))
            } else {
                toasts.show("\(scanned) is not a pallet in this warehouse", tone: .info)
            }
        } catch {
            toasts.show(ShellScanCatalog.problem(error), tone: .danger)
        }
    }
}

struct StoredPalletRows: View {
    let feed: StoredPalletsFeed
    let warehouseID: Int?
    let showsIncident: Bool
    let emptyTitle: String
    let onOpen: (MobileStoredPallet) -> Void
    @Environment(\.apiClient) private var apiClient

    var body: some View {
        let search = feed.search.trimmingCharacters(in: .whitespacesAndNewlines)
        let isCurrent = warehouseID.map { feed.loadedRequest == feed.request(warehouseID: $0) } ?? false
        if !feed.rows.isEmpty, isCurrent {
            CardList(feed.rows) { pallet in
                ListRow(
                    pallet.reference,
                    subtitle: showsIncident ? FulfilmentStorageText.incidentLine(pallet, includeCustomer: true) : FulfilmentStorageText.subtitle(pallet, showCustomer: true),
                    chips: FulfilmentStorageText.chip(pallet).map { [Chip($0, showsIncident ? FulfilmentStorageText.incidentTone(pallet.incident?.state ?? pallet.state) : FulfilmentStorageText.tone(pallet))] } ?? [],
                    action: { onOpen(pallet) }
                )
                .accessibilityIdentifier("fulfilment.pallet.\(pallet.reference)")
            }
            if feed.hasMorePages {
                LocationsLoadMoreRow(isLoading: feed.isLoadingMore, page: feed.currentPage) {
                    await feed.loadNextPage(apiClient: apiClient)
                }
            }
        } else if isCurrent, feed.loadError == nil {
            EmptyState(icon: .pallet, title: search.isEmpty ? emptyTitle : "No pallet matches “\(search)”")
        } else if feed.loadError == nil {
            ProgressView()
                .frame(maxWidth: .infinity, minHeight: 88)
                .accessibilityLabel("Loading pallets")
        }
    }
}
