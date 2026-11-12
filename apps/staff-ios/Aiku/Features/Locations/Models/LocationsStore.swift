import Foundation
import Observation

@MainActor
@Observable
final class LocationsStore {
    static let shared = LocationsStore()

    var warehouse: LocationsWarehouse
    var listTab: LocationsListTab = .all
    var listQuery = ""
    var listFilter = LocationsFilter.none
    var areaQuery = ""
    private(set) var source: LocationsDataSource = .sample
    private(set) var remoteWarehouse: MobileWarehouse?
    private(set) var remoteOverview: MobileLocationsOverview?
    private(set) var listTotal: Int?
    private(set) var listCodes: [String] = []
    @ObservationIgnored private var listTotalRequest: LocationsListRequest?
    private(set) var isLoadingList = false
    private(set) var isLoadingMore = false
    private(set) var currentPage = 0
    private(set) var lastPage = 0
    private(set) var loadError: String?
    private(set) var detailLoadedCodes: Set<String> = []
    @ObservationIgnored private var loadedKey: LocationsLoadKey?

    init(warehouse: LocationsWarehouse = LocationsSampleData.warehouse) {
        self.warehouse = warehouse
    }

    var listedLocations: [LocationsLocation] {
        guard source == .remote else {
            return warehouse.list(tab: listTab, query: listQuery, filter: listFilter)
        }
        return listCodes.compactMap(warehouse.location(code:))
    }

    func count(for tab: LocationsListTab) -> Int? {
        guard source == .remote else {
            return warehouse.count(tab: tab, query: listQuery, filter: listFilter)
        }
        var request = listRequestKey
        request.tab = tab
        if let listTotal, listTotalRequest == request {
            return listTotal
        }
        if request.query.isEmpty, listFilter.activeCount == 0, let remoteOverview {
            switch tab {
            case .all: return remoteOverview.numberLocations
            case .empty: return remoteOverview.numberLocationsEmpty
            case .partiallyEmpty: return remoteOverview.numberLocationsPartiallyEmpty
            }
        }
        return nil
    }

    var listRequestKey: LocationsListRequest {
        LocationsListRequest(tab: listTab, query: listQuery.trimmingCharacters(in: .whitespacesAndNewlines), filter: listFilter)
    }

    func loadRemote(for userID: Int, warehouse chosen: MobileWarehouse?, apiClient: APIClient) async {
        let key = LocationsLoadKey(userID: userID, warehouseID: chosen?.id)
        guard loadedKey != key else { return }
        loadedKey = key
        loadError = nil
        do {
            guard let chosen else {
                loadError = "Your account has no warehouse in Aiku."
                return
            }
            guard chosen.can.viewLocations else {
                loadError = "You don’t have access to locations in \(chosen.name)."
                return
            }
            async let overview = apiClient.fetchLocationsOverview(warehouseID: chosen.id)
            async let areas = apiClient.fetchWarehouseAreas(warehouseID: chosen.id)
            let (loadedOverview, loadedAreas) = try await (overview, areas)
            remoteWarehouse = chosen
            remoteOverview = loadedOverview
            warehouse = LocationsWarehouse(
                areas: loadedAreas.map(LocationsArea.init(remote:)),
                locations: [],
                summary: LocationsWarehouseSummary(remote: loadedOverview),
                autoRemoval: loadedOverview.emptyLocationsRemoval.map(LocationsAutoRemoval.init(remote:)) ?? warehouse.autoRemoval
            )
            source = .remote
            await reloadList(apiClient: apiClient)
        } catch where error.isCancellation {
            loadedKey = nil
        } catch {
            loadedKey = nil
            loadError = error.localizedDescription
        }
    }

    func reloadList(apiClient: APIClient) async {
        guard let remoteWarehouse else { return }
        let request = listRequestKey
        let query = remoteQuery(for: request, page: 1)
        isLoadingList = true
        defer { isLoadingList = false }
        do {
            let page = try await apiClient.fetchLocations(warehouseID: remoteWarehouse.id, query: query)
            guard request == listRequestKey else { return }
            let fetched = page.data.map(LocationsLocation.init(remote:))
            merge(fetched, replacingAll: true)
            listCodes = fetched.map(\.code)
            listTotal = page.meta.total
            listTotalRequest = request
            currentPage = page.meta.currentPage
            lastPage = page.meta.lastPage
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            loadError = error.localizedDescription
        }
    }

    var hasMorePages: Bool {
        source == .remote && currentPage > 0 && currentPage < lastPage
    }

    func loadNextPage(apiClient: APIClient) async {
        guard hasMorePages, !isLoadingMore, !isLoadingList, let remoteWarehouse else { return }
        let request = listRequestKey
        let query = remoteQuery(for: request, page: currentPage + 1)
        isLoadingMore = true
        defer { isLoadingMore = false }
        do {
            let page = try await apiClient.fetchLocations(warehouseID: remoteWarehouse.id, query: query)
            guard request == listRequestKey else { return }
            let fetched = page.data.map(LocationsLocation.init(remote:))
            merge(fetched, replacingAll: false)
            let known = Set(listCodes)
            listCodes += fetched.map(\.code).filter { !known.contains($0) }
            listTotal = page.meta.total
            listTotalRequest = request
            currentPage = page.meta.currentPage
            lastPage = page.meta.lastPage
        } catch where error.isCancellation {
            return
        } catch {
            loadError = error.localizedDescription
        }
    }

    func remoteQuery(for request: LocationsListRequest, page: Int, perPage: Int = LocationsListRequest.pageSize) -> MobileLocationsQuery {
        var query = MobileLocationsQuery(search: request.query, page: page, perPage: perPage)
        query.occupancy = request.tab.remoteOccupancy
        query.areaIDs = request.filter.areas.compactMap { warehouse.area(code: $0)?.remoteID }
        query.statuses = request.filter.statuses.map(\.rawValue)
        query.scopes = request.filter.scopes.map(\.rawValue)
        query.occupancies = request.filter.occupancies.map(\.remoteValue)
        query.sort = request.filter.sort.remoteValue
        return query
    }

    func resultCount(for filter: LocationsFilter, apiClient: APIClient) async -> Int? {
        guard source == .remote, let remoteWarehouse else {
            return resultCount(for: filter)
        }
        var request = listRequestKey
        request.filter = filter
        if request == listTotalRequest, let listTotal {
            return listTotal
        }
        do {
            return try await apiClient.fetchLocations(warehouseID: remoteWarehouse.id, query: remoteQuery(for: request, page: 1, perPage: 1)).meta.total
        } catch {
            return nil
        }
    }

    func loadArea(code: String, apiClient: APIClient) async {
        guard let remoteWarehouse, let areaID = warehouse.area(code: code)?.remoteID else { return }
        var query = MobileLocationsQuery(areaID: areaID, perPage: LocationsListRequest.areaPageSize)
        do {
            repeat {
                let page = try await apiClient.fetchLocations(warehouseID: remoteWarehouse.id, query: query)
                merge(page.data.map(LocationsLocation.init(remote:)), replacingAll: false)
                guard page.meta.currentPage < page.meta.lastPage, query.page < LocationsListRequest.areaPageLimit else { break }
                query.page += 1
            } while true
        } catch {
            loadError = error.isCancellation ? loadError : error.localizedDescription
        }
    }

    func loadLocation(code: String, apiClient: APIClient) async {
        guard source == .remote, let id = warehouse.location(code: code)?.remote?.id else { return }
        do {
            let detail = try await apiClient.fetchLocation(id: id)
            merge([LocationsLocation(remote: detail)], replacingAll: false)
            detailLoadedCodes.insert(code)
        } catch where error.isCancellation {
            return
        } catch {
            detailLoadedCodes.insert(code)
            loadError = error.localizedDescription
        }
    }

    func isWaitingForSkos(in location: LocationsLocation) -> Bool {
        source == .remote
            && !detailLoadedCodes.contains(location.code)
            && (location.remote?.slotCount ?? 0) > 0
    }

    func locationMatching(scannedCode: String, apiClient: APIClient) async -> LocationsLocation? {
        let wanted = scannedCode.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !wanted.isEmpty else { return nil }
        if let known = warehouse.location(code: wanted) {
            return known
        }
        guard source == .remote, let remoteWarehouse else { return nil }
        do {
            let page = try await apiClient.fetchLocations(warehouseID: remoteWarehouse.id, query: MobileLocationsQuery(search: wanted, perPage: 10))
            guard let match = page.data.first(where: { $0.code.caseInsensitiveCompare(wanted) == .orderedSame }) else { return nil }
            let location = LocationsLocation(remote: match)
            merge([location], replacingAll: false)
            return location
        } catch {
            return nil
        }
    }

    func resetToSample() {
        warehouse = LocationsSampleData.warehouse
        source = .sample
        remoteWarehouse = nil
        remoteOverview = nil
        listTotal = nil
        listTotalRequest = nil
        listCodes = []
        detailLoadedCodes = []
        currentPage = 0
        lastPage = 0
        loadError = nil
        loadedKey = nil
    }

    private func merge(_ fetched: [LocationsLocation], replacingAll: Bool) {
        if replacingAll {
            let detailed = Dictionary(warehouse.locations.filter { !$0.slots.isEmpty }.map { ($0.code, $0) }, uniquingKeysWith: { first, _ in first })
            warehouse.locations = fetched.map { location in
                guard location.slots.isEmpty, let known = detailed[location.code] else { return location }
                var kept = known
                kept.remote = location.remote
                return kept
            }
            return
        }
        for location in fetched {
            if let index = warehouse.locations.firstIndex(where: { $0.code == location.code }) {
                if location.slots.isEmpty, !warehouse.locations[index].slots.isEmpty {
                    warehouse.locations[index].remote = location.remote
                } else {
                    warehouse.locations[index] = location
                }
            } else {
                warehouse.locations.append(location)
            }
        }
    }

    func resultCount(for filter: LocationsFilter) -> Int {
        warehouse.list(tab: listTab, query: listQuery, filter: filter).count
    }

    var firstScannableCode: String {
        warehouse.locations.first?.code ?? ""
    }

    func restore(_ snapshot: LocationsWarehouse) {
        warehouse = snapshot
    }

    @discardableResult
    func audit(location code: String, counts: [LocationsAuditCount], on date: Date = Date()) -> Int {
        warehouse.audit(location: code, counts: counts, on: date)
    }

    func move(from source: String, to target: String, quantities: [String: Int], removeFromSource: Bool) -> (moved: Int, snapshot: LocationsWarehouse) {
        let snapshot = warehouse
        let moved = warehouse.move(from: source, to: target, quantities: quantities, removeFromSource: removeFromSource)
        return (moved, snapshot)
    }

    func remove(skos references: Set<String>, from code: String) -> (removed: Int, snapshot: LocationsWarehouse) {
        let snapshot = warehouse
        let removed = warehouse.remove(skos: references, from: code)
        return (removed, snapshot)
    }

    func update(sko reference: String, in code: String, settings: LocationsSlotSettings) {
        warehouse.update(sko: reference, in: code, settings: settings)
    }

    func setAutoRemoval(_ isOn: Bool, by name: String, on date: Date = Date()) {
        warehouse.autoRemoval = LocationsAutoRemoval(isOn: isOn, changedBy: name, changedOn: date, canEdit: warehouse.autoRemoval.canEdit)
    }

    func setRemoteAutoRemoval(_ isOn: Bool, by name: String, apiClient: APIClient) async throws {
        guard let remoteWarehouse else { return }
        let previous = warehouse.autoRemoval
        setAutoRemoval(isOn, by: name)
        do {
            let updated = try await apiClient.updateEmptyLocationsRemoval(warehouseID: remoteWarehouse.id, enabled: isOn)
            warehouse.autoRemoval = LocationsAutoRemoval(remote: updated)
            warehouse.summary.toRemoveNow = updated.toRemove
            warehouse.summary.removedLastWeek = updated.removedLast7Days
        } catch {
            warehouse.autoRemoval = previous
            throw error
        }
    }
}

struct LocationsLoadKey: Hashable, Sendable {
    var userID: Int
    var warehouseID: Int?
}

enum LocationsDataSource: Sendable {
    case sample
    case remote
}

struct LocationsListRequest: Hashable, Sendable {
    static let pageSize = 100
    static let areaPageSize = 150
    static let areaPageLimit = 10

    var tab: LocationsListTab
    var query: String
    var filter: LocationsFilter
}

extension LocationsOccupancyFilter {
    var remoteValue: String {
        switch self {
        case .empty: "empty"
        case .partiallyEmpty: "partially_empty"
        case .goodsOutGathering: "goods_out"
        case .neverRemoveEmptySkos: "keep_empty_org_stocks"
        }
    }
}

extension LocationsSort {
    var remoteValue: String? {
        switch self {
        case .code: nil
        case .stockValue: "-stock_value"
        case .weight: "-max_weight"
        case .volume: "-max_volume"
        }
    }
}

extension LocationsListTab {
    var remoteOccupancy: MobileLocationsOccupancy? {
        switch self {
        case .all: nil
        case .empty: .empty
        case .partiallyEmpty: .partiallyEmpty
        }
    }
}
