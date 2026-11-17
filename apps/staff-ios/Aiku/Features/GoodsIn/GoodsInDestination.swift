import SwiftUI

struct GoodsInDestination: View {
    let route: GoodsInRoute

    var body: some View {
        content
            .modifier(GoodsInRemoteLoading(route: route))
    }

    @ViewBuilder
    private var content: some View {
        switch route {
        case .backlog:
            GoodsInBacklogView()
        case .stockDeliveries:
            GoodsInDeliveriesView()
        case .stockDelivery(let reference):
            GoodsInRemoteGate(isWaiting: GoodsInStore.shared.isWaitingForDelivery(reference), title: reference) {
                GoodsInDeliveryView(reference: reference)
            }
        case .returns:
            GoodsInReturnsView()
        case .returnDetail(let reference):
            GoodsInRemoteGate(isWaiting: GoodsInStore.shared.isWaitingForReturn(reference), title: reference) {
                GoodsInReturnDetailView(reference: reference)
            }
        case .placeItem(let reference, let sko):
            GoodsInPlaceItemView(reference: reference, sko: sko)
        case .placeNewItem(let reference, let sko):
            GoodsInPlaceNewItemView(reference: reference, sko: sko)
        }
    }
}

private struct GoodsInRemoteGate<Content: View>: View {
    let isWaiting: Bool
    let title: String
    @ViewBuilder let content: Content

    var body: some View {
        if isWaiting {
            AppScreen(title: title, kicker: "Goods in") {
                if let message = GoodsInStore.shared.loadError {
                    InlineAlert(title: "Couldn’t load from Aiku", message: message)
                } else {
                    ProgressView()
                        .frame(maxWidth: .infinity, minHeight: 120)
                        .accessibilityLabel("Loading")
                }
            }
        } else {
            content
        }
    }
}

private struct GoodsInRemoteLoading: ViewModifier {
    let route: GoodsInRoute
    @State private var warehouseStore = WarehouseStore.shared
    @State private var store = GoodsInStore.shared
    @Environment(\.apiClient) private var apiClient
    @Environment(ToastCenter.self) private var toasts

    func body(content: Content) -> some View {
        content
            .onChange(of: store.actionError) { _, message in
                guard let message else { return }
                toasts.show(message, tone: .danger)
                store.clearActionError()
            }
            .task(id: GoodsInLoadKey(route: route, warehouseID: warehouseStore.selected?.id)) {
            let store = GoodsInStore.shared
            guard let warehouse = warehouseStore.selected else {
                if store.isRemote { store.resetToSample() }
                return
            }
            store.prepareRemote(warehouseID: warehouse.id, apiClient: apiClient)
            switch route {
            case .backlog:
                await store.loadHub(apiClient: apiClient)
            case .stockDeliveries:
                break
            case .stockDelivery(let reference), .placeItem(let reference, _), .placeNewItem(let reference, _):
                await store.loadDelivery(reference, apiClient: apiClient)
            case .returns:
                await store.reloadReturns(apiClient: apiClient)
            case .returnDetail(let reference):
                await store.loadReturn(reference, apiClient: apiClient)
            }
        }
    }
}

private struct GoodsInLoadKey: Hashable {
    let route: GoodsInRoute
    let warehouseID: Int?
}
