import SwiftUI

struct FulfilmentPalletsView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var filter: FulfilmentPalletFilter = .storing
    private let store = FulfilmentStore.shared

    var body: some View {
        let pallets = store.pallets(matching: filter)
        AppScreen(title: "Stored pallets", kicker: "Inventory · \(session.warehouseLabel)") {
            ModuleTabs(.inventory, current: .storedPallets)
        } content: {
            FilterChipBar(options: FulfilmentPalletFilter.allCases, selection: $filter, title: \.title) { option in
                let count = store.palletCount(option)
                return count > 0 ? count : nil
            }
            if pallets.isEmpty {
                EmptyState(icon: .pallet, title: "No pallets in \(filter.title)")
            } else {
                CardList(pallets) { pallet in
                    ListRow(
                        pallet.reference,
                        subtitle: pallet.listSubtitle,
                        chips: [Chip(pallet.state.title, pallet.state.tone)],
                        action: {
                            if pallet.state.isIncident {
                                router.open(FulfilmentRoute.palletIncidents)
                            } else {
                                router.open(FulfilmentRoute.pallet(reference: pallet.reference))
                            }
                        }
                    )
                    .accessibilityIdentifier("fulfilment.pallet.\(pallet.reference)")
                }
            }
            FulfilmentNote("Fulfilment customers’ pallets, boxes and oversize items in this warehouse. Only shown with fulfilment permission.")
        }
    }
}
