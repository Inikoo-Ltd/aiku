import Foundation

enum FulfilmentRoute: Hashable, Sendable {
    case deliveries
    case delivery(reference: String)
    case palletDelivery(id: Int, reference: String)
    case pallets
    case pallet(reference: String)
    case palletIncidents
    case storedItems
    case storedItemAudit(reference: String)
    case returns
    case returnDetail(reference: String)
    case returnShipment(reference: String)
}
