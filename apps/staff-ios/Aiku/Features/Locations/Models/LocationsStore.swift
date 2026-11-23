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
    @ObservationIgnored var selectedWarehouseID: @MainActor () -> Int? = { WarehouseStore.shared.selectedID }
    @ObservationIgnored var selectedWarehouse: @MainActor () -> MobileWarehouse? = { WarehouseStore.shared.selected }
    private(set) var abilities: [String: MobileLocation.Abilities] = [:]
    @ObservationIgnored private var loadedKey: LocationsLoadKey?
    @ObservationIgnored private var remoteUserID: Int?
    @ObservationIgnored private var remoteLoad: (key: LocationsLoadKey, task: Task<Void, Never>)?
    @ObservationIgnored private var offlineBases: [String: MobileLocation] = [:]
    private(set) var copyDates: [String: Date] = [:]

    init(warehouse: LocationsWarehouse = LocationsSampleData.warehouse) {
        self.warehouse = warehouse
        OfflineWork.shared.onQueueChanged { [weak self] in
            self?.reapplyOfflineWork()
        }
        OfflineWork.shared.onRecordsChanged { [weak self] records in
            self?.reloadChanged(records)
        }
    }

    var listedLocations: [LocationsLocation] {
        guard source == .remote else {
            return warehouse.list(tab: listTab, query: listQuery, filter: listFilter)
        }
        return listCodes.compactMap(warehouse.location(code:))
    }

    func pillTitle(for tab: LocationsListTab) -> String {
        guard tab == listTab, let count = loadedCount(for: tab) else { return tab.title }
        return "\(tab.title) \(count.locationsGrouped)"
    }

    private func loadedCount(for tab: LocationsListTab) -> Int? {
        guard source == .remote else { return count(for: tab) }
        var request = listRequestKey
        request.tab = tab
        return listTotalRequest == request ? listTotal : nil
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

    func beginRemoteLoad() {
        guard source == .sample else { return }
        warehouse = LocationsWarehouse(
            areas: [],
            locations: [],
            summary: LocationsWarehouseSummary(areaCount: 0, locationCount: 0, emptyCount: 0, utilisationPercent: 0, toRemoveNow: 0, removedLastWeek: 0),
            autoRemoval: LocationsAutoRemoval(isOn: false, changedBy: nil, changedOn: nil, canEdit: false)
        )
        source = .remote
        listCodes = []
        isLoadingList = true
    }

    func loadRemote(for userID: Int, warehouse chosen: MobileWarehouse?, apiClient: APIClient) async {
        let key = LocationsLoadKey(userID: userID, warehouseID: chosen?.id)
        guard loadedKey != key else { return }
        if let running = remoteLoad, running.key == key {
            await running.task.value
            return
        }
        remoteLoad?.task.cancel()
        let task = Task { await performRemoteLoad(key: key, userID: userID, chosen: chosen, apiClient: apiClient) }
        remoteLoad = (key, task)
        await task.value
        if remoteLoad?.key == key {
            remoteLoad = nil
        }
    }

    private func performRemoteLoad(key: LocationsLoadKey, userID: Int, chosen: MobileWarehouse?, apiClient: APIClient) async {
        remoteUserID = userID
        loadError = nil
        guard let chosen else {
            loadedKey = key
            loadError = "Your account has no warehouse in Aiku."
            isLoadingList = false
            return
        }
        guard chosen.can.viewLocations else {
            loadedKey = key
            loadError = "You don’t have access to locations in \(chosen.name)."
            isLoadingList = false
            return
        }
        do {
            async let overview = apiClient.fetchLocationsOverview(warehouseID: chosen.id)
            async let areas = apiClient.fetchWarehouseAreas(warehouseID: chosen.id)
            let (loadedOverview, loadedAreas) = try await (overview, areas)
            guard !Task.isCancelled else { return }
            remoteWarehouse = chosen
            remoteOverview = loadedOverview
            warehouse = LocationsWarehouse(
                areas: loadedAreas.map(LocationsArea.init(remote:)),
                locations: [],
                summary: LocationsWarehouseSummary(remote: loadedOverview),
                autoRemoval: loadedOverview.emptyLocationsRemoval.map(LocationsAutoRemoval.init(remote:)) ?? warehouse.autoRemoval
            )
            source = .remote
            loadedKey = key
            await reloadList(apiClient: apiClient)
        } catch where error.isCancellation {
            return
        } catch {
            loadError = error.localizedDescription
            isLoadingList = false
        }
    }

    func refreshList(apiClient: APIClient) async {
        if await reloadedForChangedWarehouse(apiClient: apiClient) { return }
        await reloadList(apiClient: apiClient)
    }

    private func reloadedForChangedWarehouse(apiClient: APIClient) async -> Bool {
        guard source == .remote, let remoteUserID else { return false }
        let chosen = selectedWarehouse()
        guard chosen?.id != remoteWarehouse?.id else { return false }
        loadedKey = nil
        await loadRemote(for: remoteUserID, warehouse: chosen, apiClient: apiClient)
        return true
    }

    func refreshWarehouse(apiClient: APIClient) async {
        if await reloadedForChangedWarehouse(apiClient: apiClient) { return }
        guard source == .remote, let refreshed = remoteWarehouse else { return }
        do {
            async let overview = apiClient.fetchLocationsOverview(warehouseID: refreshed.id)
            async let areas = apiClient.fetchWarehouseAreas(warehouseID: refreshed.id)
            let (loadedOverview, loadedAreas) = try await (overview, areas)
            guard refreshed.id == remoteWarehouse?.id else { return }
            remoteOverview = loadedOverview
            warehouse.areas = loadedAreas.map(LocationsArea.init(remote:))
            warehouse.summary = LocationsWarehouseSummary(remote: loadedOverview)
            if let removal = loadedOverview.emptyLocationsRemoval {
                warehouse.autoRemoval = LocationsAutoRemoval(remote: removal)
            }
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
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

    func location(code: String) -> LocationsLocation? {
        if let known = warehouse.location(code: code) {
            return known
        }
        return source == .sample ? warehouse.locations.first : nil
    }

    func loadLocation(code: String, apiClient: APIClient) async {
        guard source == .remote else { return }
        let known: LocationsLocation?
        if let loaded = warehouse.location(code: code) {
            known = loaded
        } else {
            known = await locationMatching(scannedCode: code, apiClient: apiClient)
        }
        guard let id = known?.remote?.id else { return }
        do {
            applyDetail(try await apiClient.fetchLocation(id: id))
        } catch where error.isCancellation {
            return
        } catch {
            if error as? APIError == .offline, let copy = offlineQueue(locationID: id)?.copy(MobileLocation.self) {
                applyDetail(copy.value, copyAt: copy.downloadedAt)
                return
            }
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
        guard source == .remote, let warehouseID = remoteWarehouse?.id ?? selectedWarehouseID() else { return nil }
        do {
            let page = try await apiClient.fetchLocations(warehouseID: warehouseID, query: MobileLocationsQuery(search: wanted, perPage: 10))
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
        abilities = [:]
        offlineBases = [:]
        copyDates = [:]
        currentPage = 0
        lastPage = 0
        loadError = nil
        remoteLoad?.task.cancel()
        remoteLoad = nil
        loadedKey = nil
        remoteUserID = nil
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

    var isRemote: Bool {
        source == .remote
    }

    func canEditStocks(in code: String) -> Bool {
        source == .sample || abilities[code]?.editStocks == true
    }

    func canEditLocations(in code: String) -> Bool {
        source == .sample || abilities[code]?.editLocations == true
    }

    func offlineQueue(locationID: Int) -> OfflineRecordQueue? {
        guard let warehouseID = remoteWarehouse?.id ?? selectedWarehouseID() else { return nil }
        return OfflineRecordQueue(warehouseID: warehouseID, record: .location(locationID))
    }

    func offlineQueue(code: String) -> OfflineRecordQueue? {
        warehouse.location(code: code)?.remote.flatMap { offlineQueue(locationID: $0.id) }
    }

    func offlineNames(code: String) -> StockOfflineText.Names {
        let slots = warehouse.location(code: code)?.slots ?? []
        let known = warehouse.locations
        return StockOfflineText.Names(
            sko: { linkID in slots.first { $0.remoteLinkID == linkID }?.skoReference },
            location: { locationID in known.first { $0.remote?.id == locationID }?.code }
        )
    }

    @discardableResult
    func auditRemotely(location code: String, counts: [LocationsAuditCount], apiClient: APIClient) async throws -> LocationsWriteOutcome {
        guard let location = warehouse.location(code: code), let id = location.remote?.id else { throw LocationsActionError.notLoaded }
        let changes = counts.compactMap { count -> MobileLocationAuditCount? in
            guard let slot = location.slot(count.skoReference), let linkID = slot.remoteLinkID else { return nil }
            let difference = count.counted - max(slot.quantity, 0)
            return MobileLocationAuditCount(
                locationOrgStockId: linkID,
                quantity: max(count.counted, 0),
                reason: difference == 0 ? LocationsAuditReason.unchangedRemoteValue : (count.reason?.remoteValue ?? LocationsAuditReason.unchangedRemoteValue),
                note: count.note.isEmpty ? nil : count.note
            )
        }
        guard !changes.isEmpty, let queue = offlineQueue(locationID: id) else { throw LocationsActionError.notLoaded }
        let differences = counts.filter { count in
            location.slot(count.skoReference).map { count.counted != max($0.quantity, 0) } ?? false
        }.count
        let requestID = queue.newClientRequestID()
        let payload = offlineBases[code].flatMap { StockOfflinePayloads.auditLocation(locationID: id, counts: changes, versions: $0.versions, clientRequestID: requestID) }
        let queued = payload.map { [OfflineQueuedStockWrite(operation: .auditLocation, payload: $0)] } ?? []
        if try await queueFirst(queued, on: queue) { return .queued }
        do {
            applyDetail(try await apiClient.auditLocation(id: id, counts: changes, clientRequestID: requestID))
        } catch APIError.offline {
            guard queueAfterLostConnection(queued, on: queue) else { throw APIError.offline }
            return .queued
        }
        return .saved(differences)
    }

    @discardableResult
    func moveRemotely(from sourceCode: String, to targetCode: String, quantities: [String: Int], removeFromSource: Bool, movesEverything: Bool, apiClient: APIClient) async throws -> LocationsWriteOutcome {
        guard let source = warehouse.location(code: sourceCode), let sourceID = source.remote?.id, let queue = offlineQueue(locationID: sourceID) else {
            throw LocationsActionError.notLoaded
        }
        guard let target = await locationMatching(scannedCode: targetCode, apiClient: apiClient), let targetID = target.remote?.id else {
            throw LocationsActionError.targetNotFound(targetCode)
        }
        let moved = quantities.values.reduce(0, +)
        let requestID = queue.newClientRequestID()
        let moves = offlineMoves(from: source, quantities: quantities, movesEverything: movesEverything, queue: queue)
        let queued = moves.enumerated().map { index, move in
            OfflineQueuedStockWrite(
                operation: .moveStock,
                payload: StockOfflinePayloads.moveToLocation(
                    sourceLinkID: move.linkID,
                    targetLocationID: targetID,
                    quantity: move.quantity,
                    clientRequestID: index == 0 ? requestID : queue.newClientRequestID()
                )
            )
        }
        if try await queueFirst(queued, on: queue) { return .queued }
        do {
            if movesEverything {
                applyDetail(try await apiClient.moveAllFromLocation(id: sourceID, targetID: targetID, removeAfterMove: removeFromSource, clientRequestID: requestID))
            } else {
                let orgStocks = quantities.compactMap { reference, quantity -> MobileMovedOrgStock? in
                    guard quantity > 0, let orgStockID = source.slot(reference)?.remoteOrgStockID else { return nil }
                    return MobileMovedOrgStock(orgStockId: orgStockID, quantity: quantity, removeAfterMove: removeFromSource)
                }
                guard !orgStocks.isEmpty else { return .saved(0) }
                applyDetail(try await apiClient.moveSomeFromLocation(id: sourceID, targetID: targetID, orgStocks: orgStocks, clientRequestID: requestID))
            }
        } catch APIError.offline {
            guard queueAfterLostConnection(queued, on: queue) else { throw APIError.offline }
            return .queued
        }
        detailLoadedCodes.remove(target.code)
        return .saved(moved)
    }

    @discardableResult
    func removeRemotely(skos references: Set<String>, from code: String, apiClient: APIClient) async throws -> Int {
        guard let location = warehouse.location(code: code), let id = location.remote?.id else { throw LocationsActionError.notLoaded }
        let orgStockIDs = references.compactMap { location.slot($0)?.remoteOrgStockID }
        guard !orgStockIDs.isEmpty else { return 0 }
        try await waitForOfflineWork(locationID: id)
        let result = try await apiClient.removeOrgStocks(fromLocation: id, orgStockIDs: orgStockIDs)
        applyDetail(result.data)
        return result.removed
    }

    func updateRemotely(sko reference: String, in code: String, settings: LocationsSlotSettings, apiClient: APIClient) async throws {
        guard let location = warehouse.location(code: code), let slot = location.slot(reference), let linkID = slot.remoteLinkID else { throw LocationsActionError.notLoaded }
        if let id = location.remote?.id {
            try await waitForOfflineWork(locationID: id)
        }
        applyDetail(try await apiClient.updateLocationOrgStock(linkID: linkID, update: LocationsRemoteUpdate.body(from: slot, to: settings)))
    }

    func searchMoveTargets(_ query: String, apiClient: APIClient) async {
        guard source == .remote, let remoteWarehouse else { return }
        let page = try? await apiClient.fetchLocations(
            warehouseID: remoteWarehouse.id,
            query: MobileLocationsQuery(search: query, perPage: 20)
        )
        guard let page else { return }
        merge(page.data.map(LocationsLocation.init(remote:)), replacingAll: false)
    }

    private func offlineMoves(from source: LocationsLocation, quantities: [String: Int], movesEverything: Bool, queue: OfflineRecordQueue) -> [StockOfflineMove] {
        if movesEverything {
            guard let base = offlineBases[source.code] else { return [] }
            let shown = StockOfflineOverlay.apply(queue.unsent, to: base)
            return (shown.orgStocks ?? [])
                .filter { $0.quantity.value > 0 }
                .map { StockOfflineMove(linkID: $0.locationOrgStockId, quantity: $0.quantity.value) }
        }
        return quantities.keys.sorted().compactMap { reference -> StockOfflineMove? in
            guard let quantity = quantities[reference], quantity > 0, let linkID = source.slot(reference)?.remoteLinkID else { return nil }
            return StockOfflineMove(linkID: linkID, quantity: Double(quantity))
        }
    }

    private func queueAfterLostConnection(_ writes: [OfflineQueuedStockWrite], on queue: OfflineRecordQueue) -> Bool {
        guard writes.count == 1, let write = writes.first else { return false }
        return queue.enqueue(write.operation, payload: write.payload) != nil
    }

    private func queueFirst(_ writes: [OfflineQueuedStockWrite], on queue: OfflineRecordQueue) async throws -> Bool {
        guard !writes.isEmpty else {
            if let reason = await queue.blockingReason() {
                throw LocationsActionError.waitingForOfflineWork(reason)
            }
            return false
        }
        guard queue.shouldQueue else { return false }
        for write in writes {
            guard queue.enqueue(write.operation, payload: write.payload) != nil else { return false }
        }
        return true
    }

    private func waitForOfflineWork(locationID: Int) async throws {
        if let reason = await offlineQueue(locationID: locationID)?.blockingReason() {
            throw LocationsActionError.waitingForOfflineWork(reason)
        }
    }

    private func applyDetail(_ detail: MobileLocation, copyAt: Date? = nil) {
        offlineBases[detail.code] = detail
        copyDates[detail.code] = copyAt
        let shown = offlineQueue(locationID: detail.id).map { StockOfflineOverlay.apply($0.unsent, to: detail) } ?? detail
        let location = LocationsLocation(remote: shown)
        if let index = warehouse.locations.firstIndex(where: { $0.code == location.code }) {
            warehouse.locations[index] = location
        } else {
            warehouse.locations.append(location)
        }
        detailLoadedCodes.insert(location.code)
        if let can = detail.can {
            abilities[location.code] = can
        }
    }

    private func reapplyOfflineWork() {
        for (code, base) in offlineBases {
            guard let index = warehouse.locations.firstIndex(where: { $0.code == code }),
                  let queue = offlineQueue(locationID: base.id) else { continue }
            let location = LocationsLocation(remote: StockOfflineOverlay.apply(queue.unsent, to: base))
            if warehouse.locations[index] != location {
                warehouse.locations[index] = location
            }
        }
    }

    private func reloadChanged(_ records: [OfflineRecordKey]) {
        let changed = Set(records.filter { $0.type == .location }.map(\.id))
        let codes = offlineBases.values.filter { changed.contains($0.id) }.map(\.code)
        for code in codes {
            Task { await loadLocation(code: code, apiClient: .shared) }
        }
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

enum LocationsActionError: LocalizedError, Equatable {
    case notLoaded
    case targetNotFound(String)
    case waitingForOfflineWork(String)

    var errorDescription: String? {
        switch self {
        case .notLoaded: "This location is still loading. Try again in a moment."
        case let .targetNotFound(code): "No location \(code) in this warehouse."
        case let .waitingForOfflineWork(reason): reason
        }
    }
}

enum LocationsWriteOutcome: Equatable, Sendable {
    case saved(Int)
    case queued
}

struct OfflineQueuedStockWrite: Sendable {
    let operation: OfflineOperation
    let payload: OfflinePayload
}

enum LocationsRemoteUpdate {
    static func body(from slot: LocationsSlot, to settings: LocationsSlotSettings) -> MobileLocationOrgStockUpdate {
        var update = MobileLocationOrgStockUpdate(type: settings.type.rawValue, notes: settings.note)
        if settings.type == .picking {
            update.minStock = settings.minStock
            update.maxStock = settings.maxStock
            if settings.roles.contains(.wholesale), !slot.roles.contains(.wholesale) {
                update.setAsPriorityWholesale = true
            }
            if settings.roles.contains(.dropshipping), !slot.roles.contains(.dropshipping) {
                update.setAsPriorityDropshipping = true
            }
        } else {
            update.replenishmentStock = settings.replenishment
        }
        return update
    }
}
