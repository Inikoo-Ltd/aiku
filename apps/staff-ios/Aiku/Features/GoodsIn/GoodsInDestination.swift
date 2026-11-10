import SwiftUI

struct GoodsInDestination: View {
    let route: GoodsInRoute

    var body: some View {
        switch route {
        case .backlog:
            GoodsInBacklogView()
        case .stockDeliveries:
            GoodsInDeliveriesView()
        case .stockDelivery(let reference):
            GoodsInDeliveryView(reference: reference)
        case .returns:
            GoodsInReturnsView()
        case .returnDetail(let reference):
            GoodsInReturnDetailView(reference: reference)
        case .placeItem(let reference, let sko):
            GoodsInPlaceItemView(reference: reference, sko: sko)
        case .placeNewItem(let reference, let sko):
            GoodsInPlaceNewItemView(reference: reference, sko: sko)
        }
    }
}
