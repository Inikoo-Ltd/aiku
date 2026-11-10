import SwiftUI

struct DispatchTrolleysView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    private let store = DispatchStore.shared

    var body: some View {
        AppScreen(title: "Trolleys", kicker: "Goods out · \(session.warehouseLabel)") {
            ModuleTabs(.goodsOut, current: .trolleys)
        } content: {
            CardList(store.trolleys) { trolley in
                ListRow(
                    trolley.code,
                    subtitle: trolley.holder.subtitle,
                    icon: .trolley,
                    iconTone: trolley.holder.tone,
                    chips: [Chip(trolley.holder.title, trolley.holder.tone)],
                    action: action(for: trolley.holder)
                )
                .accessibilityIdentifier("dispatch.trolley.\(trolley.code)")
            }
            DispatchNote("Trolleys are created in the web app.")
        }
    }

    private func action(for holder: DispatchTrolleyHolder) -> (() -> Void)? {
        switch holder {
        case .free:
            nil
        case .deliveryNote(let reference):
            { router.open(GoodsOutRoute.deliveryNote(reference: reference)) }
        case .session(let reference):
            { router.open(DispatchRoute.pickingSession(reference: reference)) }
        }
    }
}
