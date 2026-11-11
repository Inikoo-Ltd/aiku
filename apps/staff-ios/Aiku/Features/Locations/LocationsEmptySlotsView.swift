import SwiftUI

struct LocationsEmptySlotsView: View {
    @Environment(Session.self) private var session
    @Environment(\.apiClient) private var apiClient
    @State private var store = LocationsStore.shared
    @State private var feed: LocationsEmptySlotsFeed
    @State private var query = ""

    init(kind: LocationsEmptySlotsKind) {
        _feed = State(initialValue: LocationsEmptySlotsFeed(kind: kind))
    }

    var body: some View {
        AppScreen(title: feed.total.map { "\(feed.kind.title) · \($0.locationsGrouped)" } ?? feed.kind.title, kicker: "Locations · \(session.warehouseLabel)") {
            ModuleTabs(.locations, current: .warehouse)
        } content: {
            LocationsLoadErrorBanner(store: store)
            if let message = feed.loadError {
                InlineAlert(title: "Couldn’t load empty locations", message: message)
            }
            SearchField(text: $query, placeholder: "SKO or location code (starts with)")
            if store.source != .remote {
                EmptyState(icon: .locations, title: feed.kind.emptyTitle, message: "This list comes from Aiku once you are signed in.")
            } else if feed.slots.isEmpty {
                if feed.isLoading {
                    ProgressView()
                        .frame(maxWidth: .infinity, minHeight: 88)
                } else {
                    EmptyState(icon: .locations, title: query.isEmpty ? feed.kind.emptyTitle : "Nothing matches “\(query)”")
                }
            } else {
                CardList(feed.slots) { slot in
                    ListRow(slot.skoReference, subtitle: subtitle(for: slot), icon: .locations, iconTone: feed.kind == .toRemove ? .warn : .neutral)
                }
                if feed.hasMorePages {
                    LocationsLoadMoreRow(isLoading: feed.isLoadingMore, page: feed.currentPage) {
                        await feed.loadNextPage(apiClient: apiClient)
                    }
                }
            }
            HelperText(feed.kind == .toRemove
                ? "SKOs that ran out in a location. The hourly run removes up to 200 of these links while automatic removal is on."
                : "Links removed by the hourly run because the SKO ran out in that location.")
        }
        .loadsRemoteLocations(into: store)
        .task(id: request) {
            guard let request else { return }
            if feed.currentPage > 0 {
                try? await Task.sleep(for: .milliseconds(300))
                guard !Task.isCancelled else { return }
            }
            await feed.reload(request, apiClient: apiClient)
        }
    }

    private var request: LocationsEmptySlotsRequest? {
        guard store.source == .remote, let warehouseID = store.remoteWarehouse?.id else { return nil }
        return LocationsEmptySlotsRequest(warehouseID: warehouseID, search: query.trimmingCharacters(in: .whitespacesAndNewlines))
    }

    private func subtitle(for slot: LocationsEmptySlot) -> String {
        [
            slot.name,
            "Location \(slot.locationCode)",
            slot.removedOn.map { "Removed \(LocationsFormat.shortDate($0))" },
        ]
        .compactMap { $0 }
        .filter { !$0.isEmpty }
        .joined(separator: " · ")
    }
}
