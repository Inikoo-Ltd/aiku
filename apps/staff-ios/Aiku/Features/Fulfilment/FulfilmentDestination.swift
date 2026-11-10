import SwiftUI

struct FulfilmentDestination: View {
    let route: FulfilmentRoute

    var body: some View {
        switch route {
        case .deliveries:
            FulfilmentDeliveriesView()
        case .delivery(let reference):
            FulfilmentDeliveryView(reference: reference)
        case .pallets:
            FulfilmentPalletsView()
        case .pallet(let reference):
            FulfilmentPalletView(reference: reference)
        case .palletIncidents:
            FulfilmentIncidentsView()
        case .storedItems:
            FulfilmentStoredItemsView()
        case .storedItemAudit(let reference):
            FulfilmentAuditView(palletReference: reference)
        case .returns:
            FulfilmentReturnsView()
        case .returnDetail(let reference):
            FulfilmentReturnView(reference: reference)
        case .returnShipment(let reference):
            FulfilmentReturnShipmentView(reference: reference)
        }
    }
}
