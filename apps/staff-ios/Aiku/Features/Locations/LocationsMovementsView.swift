import SwiftUI

struct LocationsMovementsView: View {
    let code: String
    @Environment(\.apiClient) private var apiClient
    @State private var store = LocationsStore.shared

    var body: some View {
        if let id = store.warehouse.location(code: code)?.remote?.id {
            LocationsMovementsList(code: code, feed: LocationsMovementsFeed(locationID: id))
        } else {
            AppScreen(title: "Stock movements", kicker: "Location \(code)") {
                EmptyState(icon: .history, title: "Location not loaded", message: "Open the location again to see its stock movements.")
            }
        }
    }
}

private struct LocationsMovementsList: View {
    let code: String
    @State var feed: LocationsMovementsFeed
    @Environment(\.apiClient) private var apiClient

    var body: some View {
        AppScreen(title: "Stock movements", kicker: feed.total.map { "Location \(code) · \($0.locationsGrouped)" } ?? "Location \(code)") {
            if let message = feed.loadError {
                InlineAlert(title: "Couldn’t load stock movements", message: message)
            }
            if feed.movements.isEmpty {
                if feed.isLoading || feed.total == nil {
                    ProgressView()
                        .frame(maxWidth: .infinity, minHeight: 88)
                        .accessibilityLabel("Loading stock movements")
                } else {
                    EmptyState(icon: .history, title: "No stock movements yet")
                }
            } else {
                CardList(feed.movements) { movement in
                    ListRow(movement.skoReference, subtitle: movement.detail, icon: .history, iconTone: movement.quantity < 0 ? .warn : .neutral) {
                        Text(movement.quantityLabel)
                            .font(.aiku(15, .bold))
                            .foregroundStyle(movement.quantity < 0 ? AikuColor.danger : AikuColor.success)
                            .monospacedDigit()
                    }
                    .accessibilityIdentifier("locations.movement.\(movement.id)")
                }
                if feed.hasMorePages {
                    LocationsLoadMoreRow(isLoading: feed.isLoadingMore, page: feed.currentPage) {
                        await feed.loadNextPage(apiClient: apiClient)
                    }
                }
            }
            HelperText("The same history as the Stock movements tab of this location on the web, newest first.")
        }
        .task {
            await feed.reload(apiClient: apiClient)
        }
    }
}
