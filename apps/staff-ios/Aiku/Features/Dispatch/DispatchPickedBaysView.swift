import SwiftUI

struct DispatchPickedBaysView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var warehouseStore = WarehouseStore.shared
    private let store = DispatchStore.shared
    @State private var appearance = 0
    @State private var openBay: MobileSessionPickedBay?

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
                            action: bay.deliveryNotes.isEmpty ? nil : { openBay = bay }
                        )
                        .accessibilityIdentifier("dispatch.pickedBay.\(bay.code)")
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
        .onAppear { appearance += 1 }
        .task(id: ListLoadKey(request: warehouseStore.selectedID, appearance: appearance)) { await reload() }
        .aikuSheet(item: $openBay) { bay in
            SheetScaffold(title: bay.code, subtitle: bay.subtitle) {
                CardList(bay.deliveryNotes) { note in
                    ListRow(
                        note.reference,
                        icon: .deliveryNote,
                        iconTone: .info,
                        chips: [note.chip],
                        showsChevron: true,
                        action: {
                            openBay = nil
                            router.open(GoodsOutRoute.deliveryNoteID(id: note.id))
                        }
                    )
                    .accessibilityIdentifier("dispatch.pickedBay.note.\(note.reference)")
                }
            } footer: {
                AikuButton("Close", kind: .tertiary) { openBay = nil }
            }
        }
    }

    private func reload() async {
        guard let warehouseID = warehouseStore.selectedID else { return }
        await store.loadBays(warehouseID: warehouseID)
    }
}
