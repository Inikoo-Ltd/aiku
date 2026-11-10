import SwiftUI

struct DispatchPickedBaysView: View {
    @Environment(Session.self) private var session
    private let store = DispatchStore.shared

    var body: some View {
        AppScreen(title: "Picked bays", kicker: "Goods out · \(session.warehouseLabel)") {
            ModuleTabs(.goodsOut, current: .pickedBays)
        } content: {
            CardList(store.bays) { bay in
                ListRow(
                    bay.name,
                    subtitle: bay.subtitle,
                    icon: .warehouse,
                    iconTone: bay.isFree ? .success : .info,
                    chips: [bay.chip]
                )
            }
            DispatchNote("Picked goods wait here for packing, or until missing products arrive.")
        }
    }
}
