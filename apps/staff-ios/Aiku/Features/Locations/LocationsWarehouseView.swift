import SwiftUI

struct LocationsWarehouseView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(ToastCenter.self) private var toasts
    @State private var store = LocationsStore.shared

    var body: some View {
        let summary = store.warehouse.summary
        AppScreen(title: session.profile.warehouseName, kicker: "Locations · \(session.warehouseLabel)") {
            ModuleTabs(.locations, current: .warehouse)
        } content: {
            StatGrid(columns: 2) {
                StatTile(summary.areaCount.locationsGrouped, label: "Areas") {
                    router.open(LocationsRoute.areas)
                }
                .accessibilityIdentifier("locations.stat.areas")
                StatTile(summary.locationCount.locationsGrouped, label: "Locations") {
                    store.listTab = .all
                    router.open(LocationsRoute.locations)
                }
                StatTile(summary.emptyCount.locationsGrouped, label: "Empty locations", tone: .warn) {
                    store.listTab = .empty
                    router.open(LocationsRoute.locations)
                }
                .accessibilityIdentifier("locations.stat.empty")
                StatTile("\(summary.utilisationPercent)%", label: "Utilisation")
            }
            SectionHeading("Empty locations")
            Card(padding: 0, spacing: 0) {
                ToggleRow(
                    "Remove empty locations automatically",
                    subtitle: store.warehouse.autoRemoval.detail,
                    isOn: Binding(
                        get: { store.warehouse.autoRemoval.isOn },
                        set: { isOn in
                            store.setAutoRemoval(isOn, by: session.profile.name)
                            toasts.show(isOn ? "Empty locations are removed automatically" : "Automatic removal turned off")
                        }
                    )
                )
                .accessibilityIdentifier("locations.autoRemove")
                StatGrid(columns: 2) {
                    StatTile(summary.toRemoveNow.locationsGrouped, label: "To remove now")
                    StatTile(summary.removedLastWeek.locationsGrouped, label: "Removed, last 7 days")
                }
                .padding(.horizontal, 16)
                .padding(.bottom, 16)
                .padding(.top, 4)
            }
            HelperText("Every hour, an SKO that has run out in a location loses that location. Active picking locations, current pick locations, locations marked “never remove empty SKOs”, SKOs with goods waiting to be placed and anything moved in the last hour are kept. Changing it needs stock edit rights.")
        }
    }
}
