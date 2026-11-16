import SwiftUI

struct InventoryDashboardScreen: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var isScanning = false
    @State private var warehouseStore = WarehouseStore.shared
    @State private var feed = InventoryDashboardFeed.shared
    private let store = InventoryStore.shared

    private var warehouse: MobileWarehouse? { warehouseStore.selected }

    var body: some View {
        AppScreen(
            title: "Inventory dashboard",
            kicker: "Inventory · \(session.warehouseLabel)",
            actions: [HeaderAction(.scan, label: "Manage barcodes") { scan() }]
        ) {
            ModuleTabs(.inventory, current: .inventoryDashboard)
        } content: {
            if warehouse != nil {
                liveContent
            } else {
                blocks(store.dashboard)
                SectionHeading("Stock history · today", actionTitle: "All") {
                    router.open(InventoryRoute.stockHistory)
                }
                if store.recentMovements.isEmpty {
                    EmptyState(icon: .history, title: "No stock movements today")
                } else {
                    CardList(store.recentMovements) { movement in
                        InventoryMovementRow(movement: movement.display(usesTodaySummary: true)) {
                            router.open(InventoryRoute.sko(reference: movement.skoReference))
                        }
                    }
                }
            }
        }
        .screenRefreshable {
            await reload()
        }
        .task(id: warehouse?.id) {
            await reload()
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
                    router.open(InventoryRoute.skos)
                }
            )
        }
    }

    @ViewBuilder
    private var liveContent: some View {
        if let message = feed.loadError {
            InventoryLoadAlert(title: "Couldn’t load the inventory dashboard", message: message) {
                Task { await reload() }
            }
        }
        if let summary = feed.summary, feed.loadedWarehouseID == warehouse?.id {
            blocks(summary)
            HelperText("Same figures as the web inventory dashboard. Current SKOs and Abnormalities open the SKO list.")
        } else if feed.loadError == nil {
            InventorySkeletonCard()
        }
    }

    @ViewBuilder
    private func blocks(_ summary: InventoryDashboardSummary) -> some View {
        StatGrid(columns: 2) {
            StatTile(summary.currentSkos.grouped, label: "Current SKOs") {
                router.open(InventoryRoute.skos)
            }
            StatTile(summary.abnormalities.grouped, label: "Abnormalities", tone: summary.abnormalities > 0 ? .danger : nil) {
                router.open(InventoryRoute.abnormalities)
            }
        }
        StatGrid(columns: 2) {
            StatTile(summary.replenishmentsWholesale.grouped, label: summary.replenishmentsLabel, tone: .info)
            StatTile(summary.lowStockAudits.grouped, label: "Low stock audits")
            StatTile(summary.belowMinimum.grouped, label: summary.belowMinimumLabel)
            StatTile(summary.negativeStocks.grouped, label: "Negative stocks", tone: summary.negativeStocks > 0 ? .danger : nil)
            StatTile(summary.wrongWeights.grouped, label: "Wrong weights", tone: summary.wrongWeights > 0 ? .warn : nil)
            StatTile(summary.withoutProduct.grouped, label: "SKOs without product", tone: summary.withoutProduct > 0 ? .warn : nil)
        }
        InventoryInfoCard(title: session.warehouseLabel, lines: [
            InventoryInfoLine(icon: .sko, text: "Stock value \(summary.stockValueLabel)"),
            summary.utilisationLine.map { InventoryInfoLine(icon: .locations, text: $0) }
        ].compactMap { $0 })
    }

    private func scan() {
        if warehouse != nil {
            router.select(.scan)
        } else {
            isScanning = true
        }
    }

    private func reload() async {
        guard let warehouse else { return }
        await feed.load(warehouseID: warehouse.id)
    }
}
