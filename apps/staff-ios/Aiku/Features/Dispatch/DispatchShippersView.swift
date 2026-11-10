import SwiftUI

struct DispatchShippersView: View {
    @Environment(Session.self) private var session
    private let store = DispatchStore.shared

    var body: some View {
        AppScreen(title: "Shippers", kicker: "Goods out · \(session.warehouseLabel)") {
            ModuleTabs(.goodsOut, current: .shippers)
        } content: {
            CardList(store.shippers) { shipper in
                ListRow(
                    shipper.name,
                    subtitle: shipper.subtitle,
                    icon: .deliveryNote,
                    iconTone: shipper.tone,
                    chips: [Chip(shipper.integration, .info)]
                )
            }
            DispatchNote("Read-only on mobile. Shippers and their API credentials are managed in the web app.")
        }
    }
}
