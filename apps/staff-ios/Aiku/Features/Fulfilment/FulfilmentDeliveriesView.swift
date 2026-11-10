import SwiftUI

struct FulfilmentDeliveriesView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var filter: FulfilmentDeliveryState = .received
    private let store = FulfilmentStore.shared

    var body: some View {
        let selected = store.deliveries(in: filter)
        let others = store.deliveries.filter { $0.state != filter }
        AppScreen(title: "Fulfilment deliveries", kicker: "Goods in · \(session.warehouseLabel)") {
            ModuleTabs(.goodsIn, current: .fulfilmentDeliveries)
        } content: {
            FilterChipBar(options: FulfilmentDeliveryState.allCases, selection: $filter, title: \.title) { state in
                let count = store.deliveryCount(state)
                return count > 0 ? count : nil
            }
            if selected.isEmpty {
                EmptyState(icon: .stockDelivery, title: "No deliveries in \(filter.title)")
            } else {
                CardList(selected) { row($0) }
            }
            if !others.isEmpty {
                SectionHeading("Other states")
                CardList(others) { row($0) }
            }
            FulfilmentNote("Fulfilment customers’ goods arrive as pallets, boxes or oversize items. References are per customer (customer code + number).")
        }
    }

    private func row(_ delivery: FulfilmentDelivery) -> some View {
        ListRow(
            delivery.reference,
            subtitle: store.deliverySubtitle(delivery),
            chips: [Chip(delivery.state.title, delivery.state.tone)],
            action: { router.open(FulfilmentRoute.delivery(reference: delivery.reference)) }
        )
        .accessibilityIdentifier("fulfilment.delivery.\(delivery.reference)")
    }
}
