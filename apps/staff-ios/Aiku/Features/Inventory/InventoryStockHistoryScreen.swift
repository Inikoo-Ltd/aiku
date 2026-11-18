import SwiftUI

struct InventoryStockHistoryScreen: View {
    var target: InventorySkoFeed.Target?
    var skoReference: String?
    @State private var warehouseStore = WarehouseStore.shared

    var body: some View {
        if let warehouse = warehouseStore.selected {
            if let target, let skoReference {
                InventoryLiveStockHistory(
                    feed: InventoryMovementsFeed(warehouseID: warehouse.id, target: target, skoCode: skoReference),
                    skoReference: skoReference
                )
            } else {
                InventorySampleStockHistory(skoReference: skoReference)
            }
        } else {
            InventorySampleStockHistory(skoReference: skoReference)
        }
    }
}

private struct InventoryLiveStockHistory: View {
    @State var feed: InventoryMovementsFeed
    let skoReference: String
    @State private var filter: InventoryHistoryFilter = .all

    var body: some View {
        AppScreen(title: "Stock history", kicker: "SKO · \(skoReference)") {
            InventoryScopeChips(
                options: InventoryHistoryFilter.allCases,
                selection: filter,
                title: \.title,
                count: { _ in nil },
                onSelect: { filter = $0 }
            )
            if let message = feed.loadError {
                InventoryLoadAlert(title: "Couldn’t load the stock movements", message: message) {
                    Task { await feed.reload(filter) }
                }
            }
            if feed.isNotFound {
                EmptyState(icon: .sko, title: "SKO not found", message: "\(skoReference) is not an SKO of this warehouse.")
            } else if feed.loadedFilter != filter || (feed.isLoading && feed.items.isEmpty) {
                if feed.loadError == nil {
                    InventorySkeletonCard()
                }
            } else if feed.items.isEmpty {
                EmptyState(icon: .history, title: "No stock movements", message: filter == .all ? nil : "Nothing in “\(filter.title)” yet.")
            } else {
                CardList(feed.items) { movement in
                    InventoryMovementRow(movement: movement)
                }
                if feed.hasMorePages {
                    LocationsLoadMoreRow(isLoading: feed.isLoadingMore, page: feed.currentPage) {
                        await feed.loadNextPage()
                    }
                }
            }
            HelperText("Newest first. A transfer shows as two movements: out of one location and into the other.")
        }
        .screenRefreshable {
            await feed.reload(filter)
        }
        .task(id: filter) {
            await feed.reload(filter)
        }
    }
}

private struct InventorySampleStockHistory: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    var skoReference: String?
    @State private var filter: InventoryHistoryFilter = .all
    private let store = InventoryStore.shared

    var body: some View {
        let movements = store.movements(filter: filter, skoReference: skoReference)
        AppScreen(
            title: "Stock history",
            kicker: skoReference.map { "SKO · \($0)" } ?? "Inventory · \(session.warehouseLabel)"
        ) {
            InventoryScopeChips(
                options: InventoryHistoryFilter.allCases,
                selection: filter,
                title: \.title,
                count: { _ in nil },
                onSelect: { filter = $0 }
            )
            if movements.isEmpty {
                EmptyState(icon: .history, title: "No stock movements", message: filter == .all ? nil : "Nothing in “\(filter.title)” yet.")
            } else {
                CardList(movements) { movement in
                    InventoryMovementRow(movement: movement.display(), action: skoReference == nil ? {
                        router.open(InventoryRoute.sko(reference: movement.skoReference))
                    } : nil)
                }
            }
        }
    }
}
