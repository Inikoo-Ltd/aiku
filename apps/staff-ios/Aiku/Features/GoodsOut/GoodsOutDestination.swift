import SwiftUI

struct GoodsOutDestination: View {
    let route: GoodsOutRoute

    var body: some View {
        switch route {
        case .deliveryNoteID(let id):
            GoodsOutDeliveryNoteByIDScreen(id: id)
        case .backlog:
            GoodsOutBacklogView()
        case .deliveryNotes:
            GoodsOutDeliveryNotesView()
        case .deliveryNotesIn(let state):
            GoodsOutDeliveryNotesView(initialTab: state)
        case .deliveryNote(let reference):
            GoodsOutDeliveryNoteScreen(reference: reference)
        case .fastPicking(let reference):
            GoodsOutFastPickingView(reference: reference)
        case .scanToPick(let reference):
            GoodsOutScanToPickView(reference: reference)
        case .addShipment(let reference):
            GoodsOutAddShipmentView(reference: reference)
        case .askCRM(let reference):
            GoodsOutAskCRMView(reference: reference, deliveryNoteID: GoodsOutStore.shared.noteID(for: reference))
        }
    }
}
