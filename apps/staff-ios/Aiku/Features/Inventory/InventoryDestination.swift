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
            InventorySkoDetailScreen(reference: reference)
        case .stockHistory:
            InventoryStockHistoryScreen()
        case .skoHistory(let reference):
            InventoryStockHistoryScreen(skoReference: reference)
        }
    }
}
