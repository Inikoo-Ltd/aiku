import SwiftUI

struct DispatchTrolleysView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var warehouseStore = WarehouseStore.shared
    private let store = DispatchStore.shared

    var body: some View {
        AppScreen(title: "Trolleys", kicker: "Goods out · \(session.warehouseLabel)") {
            ModuleTabs(.goodsOut, current: .trolleys)
        } content: {
            if warehouseStore.selectedID == nil {
                DispatchNoWarehouse()
            } else {
                if let problem = store.trolleysState.error {
                    DispatchLoadProblem(title: "Couldn’t load the trolleys", message: problem) { await reload() }
                }
                if !store.trolleys.isEmpty {
                    CardList(store.trolleys) { trolley in
                        ListRow(
                            trolley.name,
                            subtitle: trolley.holderSubtitle,
                            icon: .trolley,
                            iconTone: trolley.holderTone,
                            chips: [Chip(trolley.holderTitle, trolley.holderTone)],
                            action: action(for: trolley)
                        )
                        .accessibilityIdentifier("dispatch.trolley.\(trolley.name)")
                    }
                } else if store.trolleysState.hasLoaded {
                    EmptyState(icon: .trolley, title: "No trolleys in this warehouse")
                } else if store.trolleysState.error == nil {
                    DispatchSkeletonList()
                }
            }
            DispatchNote("Trolleys are created in the web app.")
        }
        .screenRefreshable { await reload() }
        .task(id: warehouseStore.selectedID) { await reload() }
    }

    private func action(for trolley: MobileSessionTrolley) -> (() -> Void)? {
        if let note = trolley.currentDeliveryNote {
            return { router.open(GoodsOutRoute.deliveryNoteID(id: note.id)) }
        }
        if let pickingSession = trolley.currentPickingSession {
            return { router.open(DispatchRoute.pickingSessionID(id: pickingSession.id)) }
        }
        return nil
    }

    private func reload() async {
        guard let warehouseID = warehouseStore.selectedID else { return }
        await store.loadTrolleys(warehouseID: warehouseID)
    }
}
