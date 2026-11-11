import Foundation
import Observation

enum LocationsEmptySlotsKind: Hashable, Sendable {
    case toRemove
    case removedLastWeek

    var title: String {
        switch self {
        case .toRemove: "To remove now"
        case .removedLastWeek: "Removed automatically"
        }
    }

    var remoteKind: MobileEmptyLocationSlotsKind {
        switch self {
        case .toRemove: .toRemove
        case .removedLastWeek: .removed
        }
    }

    var emptyTitle: String {
        switch self {
        case .toRemove: "No empty locations to remove"
        case .removedLastWeek: "Nothing removed automatically yet"
        }
    }
}

struct LocationsEmptySlot: Identifiable, Hashable, Sendable {
    var id: Int
    var skoReference: String
    var name: String
    var locationCode: String
    var removedOn: Date?
}

struct LocationsEmptySlotsRequest: Hashable, Sendable {
    var warehouseID: Int
    var search: String
}

@MainActor
@Observable
final class LocationsEmptySlotsFeed {
    static let pageSize = 50

    let kind: LocationsEmptySlotsKind
    private(set) var slots: [LocationsEmptySlot] = []
    private(set) var total: Int?
    private(set) var currentPage = 0
    private(set) var lastPage = 0
    private(set) var isLoading = false
    private(set) var isLoadingMore = false
    private(set) var loadError: String?
    @ObservationIgnored private var loadedRequest: LocationsEmptySlotsRequest?

    init(kind: LocationsEmptySlotsKind) {
        self.kind = kind
    }

    var hasMorePages: Bool {
        currentPage > 0 && currentPage < lastPage
    }

    func reload(_ request: LocationsEmptySlotsRequest, apiClient: APIClient) async {
        isLoading = true
        defer { isLoading = false }
        do {
            let page = try await apiClient.fetchEmptyLocationSlots(warehouseID: request.warehouseID, kind: kind.remoteKind, search: request.search, perPage: Self.pageSize)
            slots = page.data.map(LocationsEmptySlot.init(remote:))
            apply(page.meta)
            loadedRequest = request
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            loadError = error.localizedDescription
        }
    }

    func loadNextPage(apiClient: APIClient) async {
        guard hasMorePages, !isLoading, !isLoadingMore, let request = loadedRequest else { return }
        isLoadingMore = true
        defer { isLoadingMore = false }
        do {
            let page = try await apiClient.fetchEmptyLocationSlots(warehouseID: request.warehouseID, kind: kind.remoteKind, search: request.search, page: currentPage + 1, perPage: Self.pageSize)
            guard loadedRequest == request else { return }
            let known = Set(slots.map(\.id))
            slots.append(contentsOf: page.data.map(LocationsEmptySlot.init(remote:)).filter { !known.contains($0.id) })
            apply(page.meta)
        } catch where error.isCancellation {
            return
        } catch {
            loadError = error.localizedDescription
        }
    }

    private func apply(_ meta: MobileLocationsPage.Meta) {
        total = meta.total
        currentPage = meta.currentPage
        lastPage = meta.lastPage
    }
}
