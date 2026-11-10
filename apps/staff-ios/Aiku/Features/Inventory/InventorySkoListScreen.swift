import SwiftUI

struct InventorySkoListScreen: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var scope: InventorySkoScope
    @State private var search = ""
    @State private var isFiltering = false
    @State private var isScanning = false
    @State private var focusAfterScan = false
    @FocusState private var isSearchFocused: Bool
    private let store = InventoryStore.shared

    init(scope: InventorySkoScope = .current) {
        _scope = State(initialValue: scope)
    }

    var body: some View {
        let skos = store.results(scope: scope, search: isAbnormalities ? "" : search)
        AppScreen(
            title: isAbnormalities ? "Abnormalities" : "SKOs",
            kicker: isAbnormalities ? "SKOs · Abnormalities" : "Inventory · \(session.warehouseLabel)",
            actions: isAbnormalities ? [] : [
                HeaderAction(.filter, label: "Filter", badge: store.filter.activeCount) { isFiltering = true }
            ]
        ) {
            ModuleTabs(.inventory, current: .skos)
        } content: {
            InventoryScopeChips(
                options: InventorySkoScope.allCases,
                selection: scope,
                title: \.title,
                count: { option in
                    option == .all ? nil : nonZero(store.count(scope: option))
                },
                onSelect: { scope = $0 }
            )
            if !isAbnormalities {
                InventorySearchField(
                    text: $search,
                    placeholder: "Code, name or supplier product code",
                    isFocused: $isSearchFocused
                ) {
                    isScanning = true
                }
            }
            if skos.isEmpty {
                emptyState
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
            HelperText(isAbnormalities
                ? "SKOs in the Abnormality state. Negative stocks are listed from the inventory dashboard."
                : "Stock is in SKOs (stock keeping outers). Quantity status: excess, ideal, low, critical, out of stock, error.")
        }
        .aikuSheet(isPresented: $isFiltering) {
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
        .fullScreenCover(isPresented: $isScanning, onDismiss: focusSearchIfNeeded) {
            InventoryScanCover(
                kicker: "SKOs · \(session.warehouseLabel)",
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

    private var isAbnormalities: Bool { scope == .abnormalities }

    @ViewBuilder
    private var emptyState: some View {
        if !search.isEmpty, !isAbnormalities {
            EmptyState(icon: .search, title: "No SKOs match “\(search)”", message: "Try a code, name or supplier product code.")
        } else if store.filter.activeCount > 0 {
            EmptyState(icon: .filter, title: "No SKOs match the filters", actionTitle: "Reset filters") {
                store.filter = InventorySkoFilter()
            }
        } else {
            EmptyState(icon: .sko, title: isAbnormalities ? "No abnormalities" : "No \(scope.title.lowercased()) SKOs")
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
