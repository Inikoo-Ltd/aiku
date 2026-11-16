import SwiftUI

struct DispatchPickedBaysView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var warehouseStore = WarehouseStore.shared
    private let store = DispatchStore.shared

    var body: some View {
        AppScreen(title: "Picked bays", kicker: "Goods out · \(session.warehouseLabel)") {
            ModuleTabs(.goodsOut, current: .pickedBays)
        } content: {
            if warehouseStore.selectedID == nil {
                DispatchNoWarehouse()
            } else {
                if let problem = store.baysState.error {
                    DispatchLoadProblem(title: "Couldn’t load the picked bays", message: problem) { await reload() }
                }
                if !store.bays.isEmpty {
                    CardList(store.bays) { bay in
                        ListRow(
                            bay.code,
                            subtitle: bay.subtitle,
                            icon: .warehouse,
                            iconTone: bay.isFree ? .success : .info,
                            chips: [bay.chip],
                            action: bay.deliveryNotes.count == 1 ? { router.open(GoodsOutRoute.deliveryNoteID(id: bay.deliveryNotes[0].id)) } : nil
                        )
                        if bay.deliveryNotes.count > 1 {
                            ForEach(bay.deliveryNotes) { note in
                                RowDivider()
                                ListRow(
                                    note.reference,
                                    chips: [Chip(note.stateLabel ?? note.state ?? "", note.state == "handling_blocked" ? .warn : .info)],
                                    showsChevron: true,
                                    action: { router.open(GoodsOutRoute.deliveryNoteID(id: note.id)) }
                                )
                            }
                        }
                    }
                } else if store.baysState.hasLoaded {
                    EmptyState(icon: .warehouse, title: "No picked bays in this warehouse")
                } else if store.baysState.error == nil {
                    DispatchSkeletonList()
                }
            }
            DispatchNote("Picked goods wait here for packing, or until missing products arrive.")
        }
        .screenRefreshable { await reload() }
        .task(id: warehouseStore.selectedID) { await reload() }
    }

    private func reload() async {
        guard let warehouseID = warehouseStore.selectedID else { return }
        await store.loadBays(warehouseID: warehouseID)
    }
}
