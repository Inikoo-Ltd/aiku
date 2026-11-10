import SwiftUI

struct FulfilmentReturnsView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var filter: FulfilmentReturnFilter = .all
    private let store = FulfilmentStore.shared

    var body: some View {
        let records = store.returns(matching: filter)
        AppScreen(title: "Fulfilment returns", kicker: "Goods out · \(session.warehouseLabel)") {
            ModuleTabs(.goodsOut, current: .fulfilmentReturns)
        } content: {
            FilterChipBar(options: FulfilmentReturnFilter.allCases, selection: $filter, title: \.title) { option in
                let count = store.returnCount(option)
                return count > 0 ? count : nil
            }
            if records.isEmpty {
                EmptyState(icon: .goodsOut, title: "No \(filter.title.lowercased()) returns")
            } else {
                CardList(records) { record in
                    ListRow(
                        record.reference,
                        subtitle: record.listSubtitle,
                        chips: [Chip(record.state.title, record.state.tone)],
                        action: { router.open(FulfilmentRoute.returnDetail(reference: record.reference)) }
                    )
                    .accessibilityIdentifier("fulfilment.return.\(record.reference)")
                }
            }
            FulfilmentNote("Each return is one type: whole pallets (wholesale), stored items, or dropshipping orders from the customer’s shop. Returns can also be picked in a fulfilment picking session.")
        }
    }
}
