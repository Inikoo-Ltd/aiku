import SwiftUI

struct InventoryFamiliesScreen: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var state: InventoryFamilyState = .active
    @State private var search = ""
    @State private var isScanning = false
    @State private var focusAfterScan = false
    @FocusState private var isSearchFocused: Bool
    @Environment(\.apiClient) private var apiClient
    @State private var warehouseStore = WarehouseStore.shared
    @State private var feed = InventoryFamiliesFeed.shared
    private let store = InventoryStore.shared

    private var request: InventoryFamiliesRequest? {
        warehouseStore.selected.map { InventoryFamiliesRequest(warehouseID: $0.id, state: state, search: search.trimmingCharacters(in: .whitespacesAndNewlines)) }
    }

    private var isRemote: Bool { warehouseStore.selected != nil }

    var body: some View {
        let families = isRemote ? feed.families : store.families(in: state, search: search)
        AppScreen(title: "SKO families", kicker: L10n.format("Inventory · %@", session.warehouseLabel)) {
            ModuleTabs(.inventory, current: .skoFamilies)
        } content: {
            InventoryScopeChips(
                options: InventoryFamilyState.allCases,
                selection: state,
                title: \.title,
                count: { option in
                    let count = isRemote ? (feed.counts[option] ?? 0) : store.familyCount(option)
                    return count > 0 ? count : nil
                },
                onSelect: { state = $0 }
            )
            InventorySearchField(text: $search, placeholder: "Code or name", isFocused: $isSearchFocused) {
                isScanning = true
            }
            if isRemote, let message = feed.loadError {
                InlineAlert(title: "Couldn’t load SKO families", message: message)
            }
            if families.isEmpty, isRemote, feed.isLoading || feed.loadedRequest != request {
                ProgressView()
                    .frame(maxWidth: .infinity, minHeight: 88)
                    .accessibilityLabel("Loading SKO families")
            } else if families.isEmpty {
                EmptyState(
                    icon: .skoFamilies,
                    title: search.isEmpty ? state.emptyTitle : L10n.format("No families match “%@”", search),
                    message: search.isEmpty ? nil : "Try a different code or name."
                )
            } else {
                CardList(families) { family in
                    ListRow(family.title, subtitle: family.subtitle, icon: .sko, iconTone: .info) {
                        router.open(InventoryRoute.family(code: family.code))
                    }
                    .accessibilityIdentifier("inventory.family.\(family.code)")
                }
                if isRemote, feed.hasMorePages {
                    LocationsLoadMoreRow(isLoading: feed.isLoadingMore, page: feed.currentPage) {
                        await feed.loadNextPage(apiClient: apiClient)
                    }
                }
            }
        }
        .task(id: request) {
            guard let request else { return }
            if feed.loadedRequest != nil {
                try? await Task.sleep(for: .milliseconds(300))
                guard !Task.isCancelled else { return }
            }
            await feed.reload(request, apiClient: apiClient)
        }
        .fullScreenCover(isPresented: $isScanning, onDismiss: focusSearchIfNeeded) {
            InventoryScanCover(
                kicker: L10n.format("SKO families · %@", session.warehouseLabel),
                onClose: { isScanning = false },
                onScan: {
                    isScanning = false
                    router.open(InventoryRoute.sko(reference: store.skos[0].reference))
                },
                onTypeCode: {
                    focusAfterScan = true
                    isScanning = false
                }
            )
        }
    }

    private func focusSearchIfNeeded() {
        guard focusAfterScan else { return }
        focusAfterScan = false
        isSearchFocused = true
    }
}

struct InventoryFamilyDetailScreen: View {
    let code: String
    @State private var warehouseStore = WarehouseStore.shared
    @State private var feed = InventoryFamiliesFeed.shared
    @State private var isLooking = true
    @Environment(\.apiClient) private var apiClient

    var body: some View {
        if let warehouse = warehouseStore.selected {
            if let family = feed.family(code: code), let familyID = family.remoteID {
                InventoryRemoteFamilyDetail(family: family, feed: InventoryFamilySkosFeed(warehouseID: warehouse.id, familyID: familyID))
            } else {
                AppScreen(title: code, kicker: "Stock family") {
                    if isLooking {
                        SkeletonRow()
                    } else {
                        EmptyState(icon: .skoFamilies, title: "Family not found", message: "Open it again from SKO families.")
                    }
                }
                .task(id: code) {
                    await lookUp(warehouseID: warehouse.id)
                }
            }
        } else {
            InventorySampleFamilyDetail(code: code)
        }
    }

    private func lookUp(warehouseID: Int) async {
        guard feed.family(code: code) == nil else { return }
        isLooking = true
        for state in InventoryFamilyState.allCases where feed.family(code: code) == nil {
            await feed.reload(InventoryFamiliesRequest(warehouseID: warehouseID, state: state, search: code), apiClient: apiClient)
        }
        isLooking = false
    }
}

private struct InventoryRemoteFamilyDetail: View {
    let family: InventoryFamily
    @State var feed: InventoryFamilySkosFeed
    @Environment(AppRouter.self) private var router
    @Environment(\.apiClient) private var apiClient

    var body: some View {
        let summary = feed.summary
        AppScreen(title: family.title, kicker: L10n.format("Stock family · %@", L10n.text(family.state.title))) {
            StatGrid(columns: 3) {
                StatTile("\(summary?.numberCurrentOrgStocks ?? family.skoCount)", label: "SKOs")
                StatTile(summary.map { InventoryMoney.label($0.stockValue, currencyCode: $0.currencyCode) } ?? family.stockValueLabel, label: "Stock value")
                StatTile(family.coverLabel, label: "Cover")
            }
            if let summary {
                InventoryFamilyStatusSections(skoStatus: summary.skoStatusItems, quantityStatus: summary.quantityStatusItems)
            }
            SectionHeading("SKOs")
            if let message = feed.loadError {
                InlineAlert(title: "Couldn’t load SKOs", message: message)
            }
            if feed.skos.isEmpty {
                if feed.isLoading || feed.total == nil {
                    ProgressView()
                        .frame(maxWidth: .infinity, minHeight: 88)
                        .accessibilityLabel("Loading SKOs")
                } else {
                    EmptyState(icon: .sko, title: "No current SKOs in this family")
                }
            } else {
                CardList(feed.skos) { sko in
                    InventorySkoRow(
                        reference: sko.code,
                        chip: sko.stateChip,
                        subtitle: [sko.name ?? "", sko.stockValue.flatMap(\.value).map { L10n.format("stock value %@", InventoryMoney.label($0, currencyCode: summary?.currencyCode)) } ?? ""]
                            .filter { !$0.isEmpty }
                            .joined(separator: " · "),
                        quantityText: sko.quantityText
                    ) {
                        router.open(InventoryRoute.skoID(id: sko.id))
                    }
                }
                if feed.hasMorePages {
                    LocationsLoadMoreRow(isLoading: feed.isLoadingMore, page: feed.currentPage) {
                        await feed.loadNextPage(apiClient: apiClient)
                    }
                }
            }
        }
        .task {
            await feed.reload(apiClient: apiClient)
        }
    }
}

private struct InventorySampleFamilyDetail: View {
    @Environment(AppRouter.self) private var router
    let code: String
    private let store = InventoryStore.shared

    var body: some View {
        let family = store.family(code: code)
        let skos = InventorySkoQuery.sorted(store.skos(inFamily: family.code), by: .reference)
        AppScreen(title: family.title, kicker: L10n.format("Stock family · %@", L10n.text(family.state.title))) {
            StatGrid(columns: 3) {
                StatTile("\(family.skoCount)", label: "SKOs")
                StatTile(family.stockValueLabel, label: "Stock value")
                StatTile(family.coverLabel, label: "Cover")
            }
            SectionHeading("SKOs")
            if skos.isEmpty {
                EmptyState(icon: .sko, title: "No SKOs in this family yet")
            } else {
                CardList(skos) { sko in
                    InventorySkoRow(reference: sko.reference, chip: sko.listChip, subtitle: sko.name, quantity: sko.stock) {
                        router.open(InventoryRoute.sko(reference: sko.reference))
                    }
                }
            }
        }
    }
}
