import SwiftUI

struct GoodsInDestination: View {
    let route: GoodsInRoute
    @State private var warehouseStore = WarehouseStore.shared
    @Environment(\.apiClient) private var apiClient

    var body: some View {
        if let warehouseID = warehouseStore.selected?.id {
            GoodsInRouteScreen(route: route, warehouseID: warehouseID, service: .live(apiClient))
                .id(GoodsInScreenKey(route: route, warehouseID: warehouseID))
        } else {
            AppScreen(title: "Goods in") {
                GoodsOutSkeletonCard(rows: 3)
            }
        }
    }
}

private struct GoodsInScreenKey: Hashable {
    let route: GoodsInRoute
    let warehouseID: Int
}

private struct GoodsInRouteScreen: View {
    let route: GoodsInRoute
    let warehouseID: Int
    let service: GoodsInService

    var body: some View {
        switch route {
        case .backlog:
            GoodsInBacklogScreen(feed: GoodsInBacklogFeed(warehouseID: warehouseID, service: service))
        case .stockDeliveries:
            GoodsInDeliveriesScreen(feed: GoodsInDeliveriesFeed(warehouseID: warehouseID, service: service))
        case .stockDeliveriesIn(let state):
            GoodsInDeliveriesScreen(feed: GoodsInDeliveriesFeed(warehouseID: warehouseID, filter: GoodsInDeliveryFilter(state: state), service: service))
        case .stockDeliveriesFiltered(let filter):
            GoodsInDeliveriesScreen(feed: GoodsInDeliveriesFeed(warehouseID: warehouseID, filter: filter, service: service))
        case let .stockDelivery(id, reference):
            GoodsInDeliveryScreen(reference: reference, feed: GoodsInFeeds.shared.delivery(warehouseID: warehouseID, id: id, service: service))
        case let .checkScan(deliveryID, reference):
            GoodsInCheckScanScreen(reference: reference, feed: GoodsInFeeds.shared.delivery(warehouseID: warehouseID, id: deliveryID, service: service))
        case let .placeItem(deliveryID, itemID, _, skoCode):
            GoodsInPlaceItemScreen(
                itemID: itemID,
                skoCode: skoCode,
                feed: GoodsInFeeds.shared.delivery(warehouseID: warehouseID, id: deliveryID, service: service),
                finder: GoodsInLocationFinder(warehouseID: warehouseID, service: service)
            )
        case .returns:
            GoodsInReturnsScreen(feed: GoodsInReturnsFeed(warehouseID: warehouseID, service: service))
        case .returnsIn(let state):
            GoodsInReturnsScreen(feed: GoodsInReturnsFeed(warehouseID: warehouseID, state: state, service: service))
        case let .returnDetail(id, reference):
            GoodsInReturnScreen(reference: reference, feed: GoodsInReturnFeed(warehouseID: warehouseID, returnID: id, service: service))
        }
    }
}
