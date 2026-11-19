import SwiftUI

struct FulfilmentDestination: View {
    let route: FulfilmentRoute

    var body: some View {
        switch route {
        case .deliveries:
            FulfilmentDeliveriesView()
        case .palletDelivery(let id, let reference):
            FulfilmentPalletDeliveryView(deliveryID: id, reference: reference)
        case .pallets:
            FulfilmentPalletsView()
        case .pallet(let id, let reference):
            FulfilmentPalletView(palletID: id, reference: reference)
        case .palletIncidents:
            FulfilmentIncidentsView()
        case .storedItems:
            FulfilmentStoredItemsView()
        case .storedItemAudit(let id, let reference):
            FulfilmentAuditView(auditID: id, reference: reference)
        case .returns:
            FulfilmentReturnsView()
        case .palletReturn(let id, let reference):
            FulfilmentReturnView(returnID: id, reference: reference)
        case .palletReturnShipment(let id, let reference):
            FulfilmentReturnShipmentView(returnID: id, reference: reference)
        }
    }
}
