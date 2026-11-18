import Foundation

enum FulfilmentRoute: Hashable, Sendable {
    case deliveries
    case delivery(reference: String)
    case palletDelivery(id: Int, reference: String)
    case pallets
    case pallet(id: Int, reference: String)
    case palletIncidents
    case storedItems
    case storedItemAudit(id: Int, reference: String)
    case returns
    case palletReturn(id: Int, reference: String)
    case palletReturnShipment(id: Int, reference: String)
}
