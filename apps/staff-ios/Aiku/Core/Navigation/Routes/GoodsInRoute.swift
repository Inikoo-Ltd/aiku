import Foundation

enum GoodsInRoute: Hashable, Sendable {
    case backlog
    case stockDeliveries
    case stockDeliveriesIn(state: GoodsInDeliveryStateFilter)
    case stockDeliveriesFiltered(GoodsInDeliveryFilter)
    case stockDelivery(id: Int, reference: String)
    case checkScan(deliveryID: Int, reference: String)
    case placeItem(deliveryID: Int, itemID: Int, reference: String, skoCode: String)
    case returns
    case returnsIn(state: GoodsInReturnStateFilter)
    case returnDetail(id: Int, reference: String)
}
