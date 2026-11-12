import Foundation
import Observation

struct LocationsMovement: Identifiable, Hashable, Sendable {
    var id: Int
    var date: Date?
    var typeLabel: String
    var reasonLabel: String?
    var note: String?
    var skoReference: String
    var quantity: Double
    var runningQuantity: Double?
    var userName: String?

    var quantityLabel: String {
        guard quantity != 0 else { return "" }
        let amount = LocationsFormat.number(abs(quantity))
        return quantity > 0 ? "+\(amount)" : "−\(amount)"
    }

    var detail: String {
        [
            typeLabel,
            reasonLabel,
            date.map(LocationsFormat.dateTime),
            userName ?? "System",
            runningQuantity.map { "\(LocationsFormat.number($0)) left" },
            note,
        ]
        .compactMap { $0 }
        .filter { !$0.isEmpty }
        .joined(separator: " · ")
    }
}

@MainActor
@Observable
final class LocationsMovementsFeed {
    static let pageSize = 50

    let locationID: Int
    private(set) var movements: [LocationsMovement] = []
    private(set) var total: Int?
    private(set) var currentPage = 0
    private(set) var lastPage = 0
    private(set) var isLoading = false
    private(set) var isLoadingMore = false
    private(set) var loadError: String?

    init(locationID: Int) {
        self.locationID = locationID
    }

    var hasMorePages: Bool {
        currentPage > 0 && currentPage < lastPage
    }

    func reload(apiClient: APIClient) async {
        isLoading = true
        defer { isLoading = false }
        do {
            let page = try await apiClient.fetchLocationStockMovements(id: locationID, perPage: Self.pageSize)
            movements = page.data.map(LocationsMovement.init(remote:))
            apply(page.meta)
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            loadError = error.localizedDescription
        }
    }

    func loadNextPage(apiClient: APIClient) async {
        guard hasMorePages, !isLoading, !isLoadingMore else { return }
        isLoadingMore = true
        defer { isLoadingMore = false }
        do {
            let page = try await apiClient.fetchLocationStockMovements(id: locationID, page: currentPage + 1, perPage: Self.pageSize)
            let known = Set(movements.map(\.id))
            movements.append(contentsOf: page.data.map(LocationsMovement.init(remote:)).filter { !known.contains($0.id) })
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
