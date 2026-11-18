import SwiftUI

struct InventoryDestination: View {
    let route: InventoryRoute

    var body: some View {
        switch route {
        case .dashboard:
            InventoryDashboardScreen()
        case .families:
            InventoryFamiliesScreen()
        case .family(let code):
            InventoryFamilyDetailScreen(code: code)
        case .skos:
            InventorySkoListScreen(scope: .current)
        case .abnormalities:
            InventorySkoListScreen(scope: .abnormalities)
        case .sko(let reference):
            InventorySkoDetailScreen(target: .reference(reference))
        case .skoID(let id):
            InventorySkoDetailScreen(target: .id(id))
        case .skoHistory(let reference):
            InventoryStockHistoryScreen(target: .reference(reference), skoReference: reference)
        case .skoHistoryID(let id, let reference):
            InventoryStockHistoryScreen(target: .id(id), skoReference: reference)
        }
    }
}
