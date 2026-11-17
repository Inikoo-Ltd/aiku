import SwiftUI

struct FulfilmentDestination: View {
    let route: FulfilmentRoute

    var body: some View {
        switch route {
        case .deliveries:
            FulfilmentDeliveriesView()
        case .delivery(let reference):
            FulfilmentDeliveryView(reference: reference)
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
        case .returnDetail(let reference):
            FulfilmentReturnView(reference: reference)
        case .returnShipment(let reference):
            FulfilmentReturnShipmentView(reference: reference)
        }
    }
}
