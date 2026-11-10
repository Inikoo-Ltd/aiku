import SwiftUI

struct MainView: View {
    @State private var router = AppRouter()
    @State private var toasts = ToastCenter()

    var body: some View {
        NavigationStack(path: $router.path) {
            tabRoot
                .navigationDestination(for: ShellRoute.self) { ShellDestination(route: $0) }
                .navigationDestination(for: SystemRoute.self) { SystemDestination(route: $0) }
                .navigationDestination(for: InventoryRoute.self) { InventoryDestination(route: $0) }
                .navigationDestination(for: LocationsRoute.self) { LocationsDestination(route: $0) }
                .navigationDestination(for: GoodsInRoute.self) { GoodsInDestination(route: $0) }
                .navigationDestination(for: GoodsOutRoute.self) { GoodsOutDestination(route: $0) }
                .navigationDestination(for: DispatchRoute.self) { DispatchDestination(route: $0) }
                .navigationDestination(for: FulfilmentRoute.self) { FulfilmentDestination(route: $0) }
                .navigationDestination(for: ClockingRoute.self) { ClockingDestination(route: $0) }
        }
        .environment(router)
        .environment(toasts)
    }

    @ViewBuilder
    private var tabRoot: some View {
        switch router.selectedTab {
        case .home: HomeView()
        case .work: MyWorkView()
        case .scan: ScanTabView()
        case .inbox: InboxView()
        case .menu: MenuView()
        }
    }
}
