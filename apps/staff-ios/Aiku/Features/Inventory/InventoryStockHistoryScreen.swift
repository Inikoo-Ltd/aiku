import SwiftUI

struct InventoryStockHistoryScreen: View {
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
            if skoReference == nil {
                ModuleTabs(.inventory, current: .stockHistory)
            }
        } content: {
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
                    InventoryMovementRow(movement: movement, action: skoReference == nil ? {
                        router.open(InventoryRoute.sko(reference: movement.skoReference))
                    } : nil)
                }
            }
        }
    }
}
