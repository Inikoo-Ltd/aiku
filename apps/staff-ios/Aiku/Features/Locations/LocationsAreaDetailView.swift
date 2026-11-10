import SwiftUI

struct LocationsAreaDetailView: View {
    let code: String
    @Environment(AppRouter.self) private var router
    @State private var store = LocationsStore.shared

    var body: some View {
        if let area = store.warehouse.areaOrFallback(code: code) {
            let locations = store.warehouse.locations(inArea: area.code)
            AppScreen(title: area.title, kicker: "Warehouse area · code \(area.code)") {
                StatGrid(columns: 3) {
                    StatTile(area.locationCount.locationsGrouped, label: "Locations")
                    StatTile(area.emptyCount.locationsGrouped, label: "Empty", tone: .warn)
                    StatTile("\(area.pickingOrder)", label: "Picking order")
                }
                SectionHeading("Locations")
                if locations.isEmpty {
                    EmptyState(icon: .locations, title: "No locations in this area")
                } else {
                    LocationsLocationList(locations: locations) { location in
                        router.open(LocationsRoute.location(code: location.code))
                    }
                }
            }
        } else {
            AppScreen(title: "Area") {
                EmptyState(icon: .areas, title: "Area not found")
            }
        }
    }
}
