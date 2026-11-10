import SwiftUI

struct InventoryDashboardScreen: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var isScanning = false
    private let store = InventoryStore.shared

    var body: some View {
        let summary = store.dashboard
        AppScreen(
            title: "Inventory dashboard",
            kicker: "Inventory · \(session.warehouseLabel)",
            actions: [HeaderAction(.scan, label: "Manage barcodes") { isScanning = true }]
        ) {
            ModuleTabs(.inventory, current: .inventoryDashboard)
        } content: {
            StatGrid(columns: 2) {
                StatTile("\(summary.urgentAudits)", label: "Urgent audit · low stock", tone: .warn) { openSkos() }
                StatTile(
                    "\(summary.toReplenish)",
                    label: "To replenish · wholesale (dropshipping \(summary.toReplenishDropshipping))",
                    tone: .info
                ) { openSkos() }
            }
            StatGrid(columns: 2) {
                StatTile("\(summary.replenishments)", label: "Replenishments")
                StatTile("\(summary.lowStockAudits)", label: "Low stock audits")
                StatTile("\(summary.belowMinimum)", label: "Below minimum stock")
                StatTile("\(summary.negativeStocks)", label: "Negative stocks", tone: .danger) { openSkos() }
                StatTile("\(summary.wrongWeights)", label: "Wrong weights", tone: .warn)
                StatTile("\(summary.withoutProduct)", label: "SKOs without product", tone: .warn)
            }
            InventoryInfoCard(title: session.warehouseLabel, lines: [
                InventoryInfoLine(icon: .sko, text: "Stock value \(summary.stockValueLabel)"),
                InventoryInfoLine(icon: .locations, text: summary.utilisationLine)
            ])
            SectionHeading("Stock history · today", actionTitle: "All") {
                router.open(InventoryRoute.stockHistory)
            }
            if store.recentMovements.isEmpty {
                EmptyState(icon: .history, title: "No stock movements today")
            } else {
                CardList(store.recentMovements) { movement in
                    InventoryMovementRow(movement: movement, usesTodaySummary: true) {
                        router.open(InventoryRoute.sko(reference: movement.skoReference))
                    }
                }
            }
        }
        .fullScreenCover(isPresented: $isScanning) {
            InventoryScanCover(
                kicker: "Inventory · \(session.warehouseLabel)",
                onClose: { isScanning = false },
                onScan: {
                    isScanning = false
                    router.open(InventoryRoute.sko(reference: store.skos[0].reference))
                },
                onTypeCode: {
                    isScanning = false
                    openSkos()
                }
            )
        }
    }

    private func openSkos() {
        router.open(InventoryRoute.skos)
    }
}
