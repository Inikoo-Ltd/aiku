import SwiftUI

struct LocationsAreasView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var store = LocationsStore.shared
    @State private var isScanning = false

    var body: some View {
        @Bindable var store = store
        let areas = store.warehouse.areas(matching: store.areaQuery)
        AppScreen(title: "Areas", kicker: "Locations · \(session.warehouseLabel)") {
            ModuleTabs(.locations, current: .areas)
        } content: {
            SearchField(text: $store.areaQuery, placeholder: "Code or name", onScan: { isScanning = true })
            if areas.isEmpty {
                EmptyState(icon: .search, title: "No areas found", message: "Try another code or name.")
            } else {
                CardList(areas) { area in
                    ListRow(area.title, subtitle: area.subtitle, icon: .warehouse) {
                        router.open(LocationsRoute.area(code: area.code))
                    }
                    .accessibilityIdentifier("locations.area.\(area.code)")
                }
            }
        }
        .fullScreenCover(isPresented: $isScanning) {
            LocationsScanCover(title: "Scan location") {
                isScanning = false
            } onScan: {
                isScanning = false
                router.open(LocationsRoute.location(code: store.firstScannableCode))
            }
        }
    }
}
