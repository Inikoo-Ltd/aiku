import SwiftUI

struct InventorySkoListScreen: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var scope: InventorySkoScope
    @State private var search = ""
    @State private var isFiltering = false
    @State private var isScanning = false
    @State private var focusAfterScan = false
    @State private var lookupMessage: String?
    @FocusState private var isSearchFocused: Bool
    @State private var warehouseStore = WarehouseStore.shared
    @State private var feed = InventorySkoListFeed()
    private let store = InventoryStore.shared

    init(scope: InventorySkoScope = .current) {
        _scope = State(initialValue: scope)
    }

    private var warehouse: MobileWarehouse? { warehouseStore.selected }

    private var request: InventorySkoListRequest? {
        warehouse.map {
            InventorySkoListRequest(warehouseID: $0.id, scope: scope, search: search.trimmingCharacters(in: .whitespacesAndNewlines), filter: feed.filter)
        }
    }

    var body: some View {
        AppScreen(
            title: scope.listTitle,
            kicker: isAbnormalities ? "SKOs · Abnormalities" : L10n.format("Inventory · %@", session.warehouseLabel),
            actions: isAbnormalities ? [] : [
                HeaderAction(.filter, label: "Filter", badge: warehouse == nil ? store.filter.activeCount : feed.filter.activeCount) { isFiltering = true }
            ]
        ) {
            ModuleTabs(.inventory, current: .skos)
        } content: {
            InventoryScopeChips(
                options: InventorySkoScope.chipScopes,
                selection: scope,
                title: \.title,
                count: { option in
                    guard option != .all else { return nil }
                    if warehouse != nil {
                        return feed.counts.map { option.count(in: $0) }.flatMap(nonZero)
                    }
                    return nonZero(store.count(scope: option))
                },
                onSelect: { scope = $0 }
            )
            if !isAbnormalities {
                InventorySearchField(
                    text: $search,
                    placeholder: "Code, name or supplier product code",
                    isFocused: $isSearchFocused,
                    onScan: { scan() },
                    onSubmit: { lookUpTypedCode() }
                )
            }
            if let lookupMessage {
                InlineAlert(tone: .warn, title: lookupMessage) { EmptyView() }
            }
            if warehouse != nil {
                liveList
            } else {
                sampleList
            }
            HelperText(isAbnormalities
                ? "SKOs in the Abnormality state. Negative stocks are listed from the inventory dashboard."
                : warehouse == nil
                    ? "Stock is in SKOs (stock keeping outers). Quantity status: excess, ideal, low, critical, out of stock, error."
                    : "Stock is in SKOs (stock keeping outers). The figure on the right is the stock available.")
        }
        .screenRefreshable {
            guard let request else { return }
            await feed.reload(request)
        }
        .task(id: request) {
            guard let request else { return }
            if feed.loadedRequest != nil {
                try? await Task.sleep(for: .milliseconds(300))
                guard !Task.isCancelled else { return }
            }
            await feed.reload(request)
        }
        .onChange(of: search) {
            lookupMessage = nil
        }
        .aikuSheet(isPresented: $isFiltering) {
            if let warehouse {
                InventoryLiveFilterSheet(
                    initial: feed.filter,
                    families: feed.familyOptions,
                    resultCount: { filter in
                        await feed.total(for: InventorySkoListRequest(warehouseID: warehouse.id, scope: scope, search: search, filter: filter))
                    },
                    onApply: { filter in
                        feed.filter = filter
                        isFiltering = false
                    }
                )
                .task {
                    await feed.loadFamilyOptions(warehouseID: warehouse.id)
                }
            } else {
                InventoryFilterSheet(
                    initial: store.filter,
                    families: store.families,
                    resultCount: { filter in
                        InventorySkoQuery.results(store.skos, scope: scope, filter: filter, search: search).count
                    },
                    onApply: { filter in
                        store.filter = filter
                        isFiltering = false
                    }
                )
            }
        }
        .fullScreenCover(isPresented: $isScanning, onDismiss: focusSearchIfNeeded) {
            InventoryScanCover(
                kicker: L10n.format("SKOs · %@", session.warehouseLabel),
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

    @ViewBuilder
    private var sampleList: some View {
        let skos = store.results(scope: scope, search: isAbnormalities ? "" : search)
        if skos.isEmpty {
            emptyState(filterCount: store.filter.activeCount) {
                store.filter = InventorySkoFilter()
            }
        } else {
            CardList(skos) { sko in
                InventorySkoRow(
                    reference: sko.reference,
                    chip: sko.listChip,
                    subtitle: sko.listSubtitle,
                    quantity: sko.isAbnormal ? nil : sko.stock
                ) {
                    router.open(InventoryRoute.sko(reference: sko.reference))
                }
            }
        }
    }

    @ViewBuilder
    private var liveList: some View {
        if let message = feed.loadError {
            InventoryLoadAlert(title: "Couldn’t load the SKOs", message: message) {
                Task {
                    if let request { await feed.reload(request) }
                }
            }
        }
        if feed.items.isEmpty || feed.loadedRequest != request {
            if feed.isLoading || (feed.loadedRequest != request && feed.loadError == nil) {
                InventorySkeletonCard()
            } else if feed.loadError == nil {
                emptyState(filterCount: feed.filter.activeCount) {
                    feed.filter = InventoryLiveSkoFilter()
                }
            }
        } else {
            CardList(feed.items) { item in
                InventorySkoRow(reference: item.reference, chip: item.chip, subtitle: item.subtitle, quantityText: item.quantityText, location: item.location) {
                    open(item)
                }
            }
            if feed.hasMorePages {
                LocationsLoadMoreRow(isLoading: feed.isLoadingMore, page: feed.currentPage) {
                    await feed.loadNextPage()
                }
            }
        }
    }

    private var isAbnormalities: Bool { scope == .abnormalities }

    private func open(_ item: InventorySkoListItem) {
        if let location = item.location, warehouse?.can.viewLocations == true {
            router.open(LocationsRoute.skoInLocation(sko: item.reference, location: location.code))
        } else {
            router.open(InventoryRoute.skoID(id: item.skoID))
        }
    }

    @ViewBuilder
    private func emptyState(filterCount: Int, reset: @escaping () -> Void) -> some View {
        if !search.isEmpty, !isAbnormalities {
            EmptyState(icon: .search, title: L10n.format("No SKOs match “%@”", search), message: "Try a code, name or supplier product code.")
        } else if filterCount > 0 {
            EmptyState(icon: .filter, title: "No SKOs match the filters", actionTitle: "Reset filters", action: reset)
        } else {
            EmptyState(icon: .sko, title: scope.emptyTitle)
        }
    }

    private func scan() {
        if warehouse != nil {
            router.select(.scan)
        } else {
            isScanning = true
        }
    }

    private func lookUpTypedCode() {
        guard let warehouse else { return }
        let code = search.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !code.isEmpty else { return }
        Task {
            if let id = await feed.skoID(scanned: code, warehouseID: warehouse.id) {
                router.open(InventoryRoute.skoID(id: id))
            } else if feed.items.count == 1, let only = feed.items.first {
                router.open(InventoryRoute.skoID(id: only.skoID))
            } else if feed.items.isEmpty {
                lookupMessage = L10n.format("No SKO for %@", code)
            }
        }
    }

    private func nonZero(_ value: Int) -> Int? {
        value > 0 ? value : nil
    }

    private func focusSearchIfNeeded() {
        guard focusAfterScan else { return }
        focusAfterScan = false
        isSearchFocused = true
    }
}
