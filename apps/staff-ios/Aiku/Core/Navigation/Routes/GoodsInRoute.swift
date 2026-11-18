import Foundation

enum GoodsInRoute: Hashable, Sendable {
    case backlog
    case stockDeliveries
    case stockDeliveriesIn(state: GoodsInDeliveryState)
    case stockDelivery(reference: String)
    case scannedStockDelivery(id: Int, reference: String)
    case returns
    case returnsToProcess
    case returnDetail(reference: String)
    case returnID(id: Int, reference: String)
    case placeItem(reference: String, sko: String)
    case placeNewItem(reference: String, sko: String)
}
