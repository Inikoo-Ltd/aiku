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
    case returnDetail(reference: String)
    case returnShipment(reference: String)
}
