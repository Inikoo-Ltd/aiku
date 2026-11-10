import SwiftUI

struct FulfilmentStoredItemsView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    private let store = FulfilmentStore.shared

    var body: some View {
        let summaries = store.storedItemSummaries
        AppScreen(title: "Stored items", kicker: "Inventory · \(session.warehouseLabel)") {
            ModuleTabs(.inventory, current: .storedItems)
        } content: {
            if summaries.isEmpty {
                EmptyState(icon: .sko, title: "No stored items")
            } else {
                CardList(summaries) { summary in
                    ListRow(
                        summary.item.code,
                        subtitle: summary.subtitle,
                        chips: [summary.item.status.chip],
                        action: {
                            guard let first = summary.palletReferences.first else { return }
                            router.open(FulfilmentRoute.storedItemAudit(reference: first))
                        }
                    ) {
                        ProductThumb(size: 48, label: "Product photo of \(summary.item.code)")
                    } trailing: {
                        Text(summary.quantity.grouped)
                            .font(.aiku(15, .bold))
                            .foregroundStyle(AikuColor.text)
                            .monospacedDigit()
                    }
                    .accessibilityIdentifier("fulfilment.storedItem.\(summary.item.code)")
                }
            }
            FulfilmentNote("Stored items are the customer’s own SKOs, counted per pallet. The barcode is usually the manufacturer’s EAN.")
        }
    }
}
