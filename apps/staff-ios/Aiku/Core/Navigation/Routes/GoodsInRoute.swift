import Foundation

enum GoodsInRoute: Hashable, Sendable {
    case backlog
    case stockDeliveries
    case stockDelivery(reference: String)
    case returns
    case returnDetail(reference: String)
    case placeItem(reference: String, sko: String)
    case placeNewItem(reference: String, sko: String)
}
