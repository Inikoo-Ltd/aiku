import SwiftUI

struct GoodsOutDestination: View {
    let route: GoodsOutRoute

    var body: some View {
        switch route {
        case .backlog:
            GoodsOutBacklogView()
        case .deliveryNotes:
            GoodsOutDeliveryNotesView()
        case .deliveryNote(let reference):
            GoodsOutDeliveryNoteScreen(reference: reference)
        case .fastPicking(let reference):
            GoodsOutFastPickingView(reference: reference)
        case .packingQueue:
            GoodsOutPackingQueueView()
        case .scanToPick(let reference):
            GoodsOutScanToPickView(reference: reference)
        case .addShipment(let reference):
            GoodsOutAddShipmentView(reference: reference)
        case .manualShipment(let reference):
            GoodsOutManualShipmentView(reference: reference)
        case .askCRM(let reference):
            GoodsOutAskCRMView(reference: reference)
        case .tariffCodes(let reference):
            GoodsOutTariffView(reference: reference)
        case .customsFile(let reference):
            GoodsOutCustomsView(reference: reference)
        case .dispatchedEmails(let reference):
            GoodsOutDispatchedEmailsView(reference: reference)
        }
    }
}
