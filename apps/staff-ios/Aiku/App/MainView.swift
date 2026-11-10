import SwiftUI

struct MainView: View {
    @State private var router = AppRouter()
    @State private var toasts = ToastCenter()

    var body: some View {
        NavigationStack(path: $router.path) {
            tabRoot
                .navigationDestination(for: Route.self) { route in
                    RouteDestination(route: route)
                }
        }
        .environment(router)
        .environment(toasts)
        .toastHost(toasts)
        .fullScreenCover(item: $router.scanContext) { context in
            ScanCameraView(context: context)
                .environment(router)
        }
    }

    @ViewBuilder
    private var tabRoot: some View {
        switch router.selectedTab {
        case .dashboard: DashboardView()
        case .inventory: InventoryView()
        case .goodsIn: GoodsInView()
        case .goodsOut: GoodsOutView()
        }
    }
}

struct RouteDestination: View {
    let route: Route

    var body: some View {
        switch route {
        case .warehouseMenu:
            WarehouseMenuView()
        case .locations:
            LocationsView()
        case .profile:
            ProfileView()
        case .sku(let reference):
            if let sku = SampleData.sku(reference) {
                SkuDetailView(sku: sku)
            } else {
                PlaceholderView(title: reference)
            }
        case .location(let code):
            if let location = SampleData.location(code) {
                LocationDetailView(location: location)
            } else {
                PlaceholderView(title: code)
            }
        case .stockDelivery(let reference):
            if let delivery = SampleData.stockDelivery(reference) {
                StockDeliveryDetailView(delivery: delivery)
            } else {
                PlaceholderView(title: reference)
            }
        case .deliveryNote(let reference):
            if let note = SampleData.deliveryNote(reference) {
                DeliveryNoteDetailView(note: note)
            } else {
                PlaceholderView(title: reference)
            }
        case .scannerDevice(let reference):
            if let note = SampleData.deliveryNote(reference) {
                ScannerDevicePickingView(note: note)
            } else {
                PlaceholderView(title: reference)
            }
        case .placeholder(let title):
            PlaceholderView(title: title)
        case .componentGallery:
            ComponentGalleryView()
        }
    }
}
