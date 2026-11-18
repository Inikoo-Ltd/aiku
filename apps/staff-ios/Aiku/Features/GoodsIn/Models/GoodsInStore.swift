import Foundation
import Observation

@MainActor
@Observable
final class GoodsInStore {
    static let shared = GoodsInStore()

    var deliveries: [GoodsInDelivery]
    var returns: [GoodsInReturn]
    var filter = GoodsInDeliveryFilter()
    var listScope: GoodsInListScope = .state(.received)
    private(set) var isRemote = false
    private(set) var warehouseID: Int?
    private(set) var hub: [MobileGoodsInHubEntry] = []
    private(set) var serverCounts: MobileStockDeliveriesPage.Counts?
    private(set) var returnCounts: MobileReturnsPage.Counts?
    private(set) var listReferences: [String] = []
    private(set) var listTotal: Int?
    private(set) var listRequest: GoodsInListRequest?
    private(set) var currentPage = 0
    private(set) var lastPage = 0
    private(set) var isLoadingList = false
    private(set) var isLoadingMore = false
    private(set) var returnReferences: [String] = []
    private(set) var isLoadingReturns = false
    private(set) var loadError: String?
    private(set) var actionError: String?
    @ObservationIgnored private var remoteClient: APIClient?

    init(deliveries: [GoodsInDelivery] = GoodsInSampleData.deliveries(), returns: [GoodsInReturn] = GoodsInSampleData.returns()) {
        self.deliveries = deliveries
        self.returns = returns
    }

    var visibleDeliveries: [GoodsInDelivery] {
        deliveries.filter { $0.state != .withdrawn }
    }

    func delivery(_ reference: String) -> GoodsInDelivery {
        deliveries.first { $0.reference == reference } ?? deliveries.first ?? GoodsInDelivery.placeholder(reference)
    }

    func goodsReturn(_ reference: String) -> GoodsInReturn {
        returns.first { $0.reference == reference } ?? returns.first ?? GoodsInReturn(reference: reference, customer: "", items: [])
    }

    func count(in scope: GoodsInListScope) -> Int {
        guard isRemote else { return visibleDeliveries.filter(scope.contains).count }
        guard let serverCounts else { return 0 }
        switch scope {
        case .all: return serverCounts.active
        case .state(let state):
            switch state {
            case .arriving: return serverCounts.dispatched
            case .received: return serverCounts.received
            case .checked: return serverCounts.checked + serverCounts.notReceived
            case .bookingIn: return serverCounts.bookingIn
            case .bookedIn: return serverCounts.bookedIn
            case .placed: return serverCounts.placed
            case .notReceived: return serverCounts.notReceived
            case .cancelled: return serverCounts.cancelled
            case .withdrawn: return 0
            }
        }
    }

    func listedDeliveries(now: Date = .now) -> [GoodsInDelivery] {
        guard isRemote else { return filter.apply(to: visibleDeliveries.filter(listScope.contains), now: now) }
        return listReferences.compactMap { reference in deliveries.first { $0.reference == reference } }
    }

    func otherActiveDeliveries(now: Date = .now) -> [GoodsInDelivery] {
        guard !isRemote, case .state = listScope, filter.activeCount == 0 else { return [] }
        let others = visibleDeliveries.filter { $0.state.isActiveWork && !listScope.contains($0) }
        return filter.sorted(others)
    }

    func filteredCount(_ draft: GoodsInDeliveryFilter, now: Date = .now) -> Int {
        guard isRemote else { return draft.apply(to: visibleDeliveries, now: now).count }
        return listTotal ?? 0
    }

    func applyFilter(_ draft: GoodsInDeliveryFilter) {
        filter = draft
        if draft.activeCount > 0 {
            listScope = .all
        }
    }

    func backlogCounts(for sources: Set<GoodsInSource>, now: Date = .now) -> GoodsInBacklogCounts {
        guard isRemote else { return GoodsInBacklogCounts.count(deliveries.filter { sources.contains($0.source) }, now: now) }
        let key = sources.contains(.agent) ? "agents" : sources.contains(.partner) ? "partners" : "stock_deliveries"
        return GoodsInBacklogCounts(hubEntry: hub.first { $0.key == key })
    }

    var fulfilmentBacklog: GoodsInBacklogCounts {
        guard isRemote else { return GoodsInBacklogCounts(arriving: 2, received: 0, checked: 0, bookingIn: 1, bookedInLastWeek: 4) }
        return GoodsInBacklogCounts(hubEntry: hub.first { $0.key == "fulfilment" })
    }

    var returnsBacklog: GoodsInBacklogCounts {
        if isRemote { return GoodsInBacklogCounts(hubEntry: hub.first { $0.key == "returns" }) }
        var counts = GoodsInBacklogCounts()
        for item in returns {
            switch item.state {
            case .received, .returning: counts.received += 1
            case .returned, .done: counts.bookedInLastWeek += 1
            }
        }
        return counts
    }

    func nextDeliveryNeedingWork(after reference: String) -> GoodsInDelivery? {
        let order: [GoodsInDeliveryState] = [.bookingIn, .checked, .received, .arriving]
        for state in order {
            if let match = deliveries.first(where: { $0.reference != reference && $0.state == state }) {
                return match
            }
        }
        return nil
    }

    private func update(_ reference: String, _ change: (inout GoodsInDelivery) -> Void) {
        guard let index = deliveries.firstIndex(where: { $0.reference == reference }) else { return }
        change(&deliveries[index])
    }

    private func updateReturn(_ reference: String, _ change: (inout GoodsInReturn) -> Void) {
        guard let index = returns.firstIndex(where: { $0.reference == reference }) else { return }
        change(&returns[index])
    }

    func markAsReceived(_ reference: String, now: Date = .now) {
        update(reference) { $0.markAsReceived(label: "today \(GoodsInDates.time(now))") }
        sendDelivery(reference, path: "receive", body: MobileEmptyBody())
    }

    func unmarkAsReceived(_ reference: String) {
        update(reference) { $0.unmarkAsReceived() }
        sendDelivery(reference, path: "unreceive", body: MobileEmptyBody())
    }

    func unmarkAsDispatched(_ reference: String) {
        guard !refuseWithoutEndpoint("Unmarking as dispatched") else { return }
        update(reference) { $0.unmarkAsDispatched() }
    }

    func cancel(_ reference: String) {
        guard !refuseWithoutEndpoint("Cancelling a stock delivery") else { return }
        update(reference) { $0.cancel() }
    }

    func setAsPlaced(_ reference: String) {
        guard !refuseWithoutEndpoint("Setting a delivery as placed") else { return }
        update(reference) { $0.setAsPlaced() }
    }

    func startBookingIn(_ reference: String) {
        update(reference) { $0.startBookingIn() }
    }

    func setChecked(_ reference: String, sko: String, quantity: Double) {
        update(reference) { $0.setChecked(sko, quantity: quantity) }
        sendCheck(reference, sko: sko)
    }

    func checkAll(_ reference: String) {
        update(reference) { delivery in
            for item in delivery.items where item.checked == nil {
                delivery.setChecked(item.sko, quantity: item.delivered)
            }
        }
        sendDelivery(reference, path: "check-all", body: MobileEmptyBody())
    }

    func registerScan(_ reference: String, code: String) -> GoodsInScanResult {
        var result = GoodsInScanResult.notOnDelivery(code: code)
        update(reference) { result = $0.registerScan(code) }
        if case let .counted(sko, _, _) = result {
            sendCheck(reference, sko: sko)
        }
        return result
    }

    @discardableResult
    func place(_ reference: String, sko: String, quantity: Double, in location: String, setAsPicking: Bool = false, now: Date = .now) -> Double {
        var placed = 0.0
        let leftBefore = deliveries.first { $0.reference == reference }?.item(sko)?.leftToPlace ?? 0
        update(reference) { placed = $0.place(sko, quantity: quantity, in: location, setAsPicking: setAsPicking, now: now) }
        if placed > GoodsInQuantity.tolerance {
            sendPlace(reference, sko: sko, quantity: GoodsInQuantity.isEqual(placed, leftBefore) ? nil : placed, location: location, setAsPicking: setAsPicking)
        }
        return placed
    }

    func recordCheck(_ reference: String, checkID: String, passed: Bool, note: String, incidentType: String? = nil, incidentQuantity: Double? = nil, by name: String, now: Date = .now) {
        update(reference) { $0.recordCheck(checkID, passed: passed, note: note, by: name, at: GoodsInDates.time(now)) }
        let trimmed = note.trimmingCharacters(in: .whitespacesAndNewlines)
        let body = MobileChecklistBody(
            key: checkID,
            result: passed ? "passed" : "failed",
            body: trimmed.isEmpty ? nil : trimmed,
            incidentType: passed ? nil : incidentType,
            incidentQuantity: passed ? nil : incidentQuantity
        )
        sendDelivery(reference, path: "checklist", body: body)
    }

    func processReturnItem(_ reference: String, sko: String, outcome: GoodsInReturnOutcome) {
        let item = returns.first { $0.reference == reference }?.items.first { $0.sko == sko }
        updateReturn(reference) { $0.process(sko, outcome: outcome) }
        guard let item, let itemID = item.remoteID else { return }
        switch outcome {
        case .toLocation(let code):
            let linkID = code.caseInsensitiveCompare(item.homeLocation) == .orderedSame ? item.homeLinkID : nil
            sendReturnItem(reference, path: "items/\(itemID)/returned", body: MobileReturnPutBody(locationOrgStockId: linkID))
        case .damaged:
            sendReturnItem(reference, path: "items/\(itemID)/damaged", body: MobileReturnPutBody())
        case .notReturned:
            sendReturnItem(reference, path: "items/\(itemID)/not-returned", body: MobileReturnPutBody())
        }
    }

    func undoPut(_ reference: String, put: GoodsInReturnPut) {
        updateReturn(reference) { $0.undo(put) }
        guard isRemote, let client = remoteClient, let warehouseID, let id = returns.first(where: { $0.reference == reference })?.remoteID else { return }
        Task {
            do {
                storeReturn(try await client.deleteReturnPut(warehouseID: warehouseID, id: id, putID: put.id).data)
                actionError = nil
            } catch {
                actionError = InventoryLive.message(for: error)
                await loadReturn(reference, apiClient: client)
            }
        }
    }

    func setReturnAsReturned(_ reference: String) {
        updateReturn(reference) { $0.setAsReturned() }
        sendReturn(reference, path: "set-as-returned", body: MobileEmptyBody(), startFirst: false)
    }

    func clearActionError() {
        actionError = nil
    }

    private func refuseWithoutEndpoint(_ action: String) -> Bool {
        guard isRemote else { return false }
        actionError = "\(action) is done on the web for now."
        return true
    }

    private func sendCheck(_ reference: String, sko: String) {
        guard let item = deliveries.first(where: { $0.reference == reference })?.item(sko), let itemID = item.remoteID, let checked = item.checked else { return }
        sendDelivery(reference, path: "items/\(itemID)/check", body: MobileCheckItemBody(skoQuantity: checked))
    }

    private func sendPlace(_ reference: String, sko: String, quantity: Double?, location: String, setAsPicking: Bool) {
        guard isRemote, let client = remoteClient, let warehouseID,
              let delivery = deliveries.first(where: { $0.reference == reference }), let id = delivery.remoteID,
              let item = delivery.item(sko), let itemID = item.remoteID else { return }
        let code = location.trimmingCharacters(in: .whitespacesAndNewlines).uppercased()
        let linkID = item.locationLinks.first { $0.key.caseInsensitiveCompare(code) == .orderedSame }?.value
        Task {
            do {
                var body = MobilePlaceItemBody(quantity: quantity, locationOrgStockId: linkID, locationId: nil, setAsPickingLocation: setAsPicking, clientRequestId: UUID().uuidString.lowercased())
                if linkID == nil {
                    let match = try await client.fetchLocations(warehouseID: warehouseID, query: MobileLocationsQuery(search: code, perPage: 10)).data.first { $0.code.caseInsensitiveCompare(code) == .orderedSame }
                    guard let match else {
                        actionError = "No location \(code) in this warehouse."
                        await loadDelivery(reference, apiClient: client)
                        return
                    }
                    body.locationId = match.id
                }
                let path = quantity == nil ? "items/\(itemID)/place-all" : "items/\(itemID)/place"
                let result = try await client.stockDeliveryAction(warehouseID: warehouseID, id: id, path: path, body: body)
                store(result.data)
                actionError = nil
            } catch {
                actionError = InventoryLive.message(for: error)
                await loadDelivery(reference, apiClient: client)
            }
        }
    }

    private func sendDelivery(_ reference: String, path: String, body: some Encodable & Sendable) {
        guard isRemote, let client = remoteClient, let warehouseID, let id = deliveries.first(where: { $0.reference == reference })?.remoteID else { return }
        Task {
            do {
                let result = try await client.stockDeliveryAction(warehouseID: warehouseID, id: id, path: path, body: body)
                store(result.data)
                actionError = nil
            } catch {
                actionError = InventoryLive.message(for: error)
                await loadDelivery(reference, apiClient: client)
            }
        }
    }

    private func sendReturnItem(_ reference: String, path: String, body: MobileReturnPutBody) {
        sendReturn(reference, path: path, body: body, startFirst: true)
    }

    private func sendReturn(_ reference: String, path: String, body: some Encodable & Sendable, startFirst: Bool) {
        guard isRemote, let client = remoteClient, let warehouseID, let goodsReturn = returns.first(where: { $0.reference == reference }), let id = goodsReturn.remoteID else { return }
        let needsStart = startFirst && goodsReturn.remoteActions.contains("start")
        Task {
            do {
                if needsStart {
                    storeReturn(try await client.returnAction(warehouseID: warehouseID, id: id, path: "start").data)
                }
                let result = try await client.returnAction(warehouseID: warehouseID, id: id, path: path, body: body)
                storeReturn(result.data)
                actionError = nil
            } catch {
                actionError = InventoryLive.message(for: error)
                await loadReturn(reference, apiClient: client)
            }
        }
    }

    func prepareRemote(warehouseID: Int, apiClient: APIClient) {
        remoteClient = apiClient
        guard !isRemote || self.warehouseID != warehouseID else { return }
        isRemote = true
        self.warehouseID = warehouseID
        deliveries = []
        returns = []
        hub = []
        serverCounts = nil
        returnCounts = nil
        listReferences = []
        returnReferences = []
        listTotal = nil
        listRequest = nil
        currentPage = 0
        lastPage = 0
        loadError = nil
    }

    func resetToSample() {
        isRemote = false
        warehouseID = nil
        deliveries = GoodsInSampleData.deliveries()
        returns = GoodsInSampleData.returns()
        hub = []
        serverCounts = nil
        returnCounts = nil
        listReferences = []
        returnReferences = []
        listTotal = nil
        listRequest = nil
        currentPage = 0
        lastPage = 0
        loadError = nil
        actionError = nil
    }

    var currentListRequest: GoodsInListRequest {
        GoodsInListRequest(scope: listScope, filter: filter)
    }

    var hasMorePages: Bool {
        isRemote && currentPage > 0 && currentPage < lastPage
    }

    func loadHub(apiClient: APIClient) async {
        guard isRemote, let warehouseID else { return }
        do {
            hub = try await apiClient.fetchGoodsInHub(warehouseID: warehouseID)
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            loadError = InventoryLive.message(for: error)
        }
    }

    func reloadList(apiClient: APIClient) async {
        guard isRemote, let warehouseID else { return }
        let request = currentListRequest
        isLoadingList = true
        defer { isLoadingList = false }
        do {
            let page = try await apiClient.fetchStockDeliveries(warehouseID: warehouseID, query: request.query(page: 1))
            guard request == currentListRequest else { return }
            listReferences = merge(page.data)
            apply(page, request: request)
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            loadError = InventoryLive.message(for: error)
        }
    }

    func loadNextPage(apiClient: APIClient) async {
        guard hasMorePages, !isLoadingList, !isLoadingMore, let warehouseID, let request = listRequest else { return }
        isLoadingMore = true
        defer { isLoadingMore = false }
        do {
            let page = try await apiClient.fetchStockDeliveries(warehouseID: warehouseID, query: request.query(page: currentPage + 1))
            guard request == listRequest else { return }
            let known = Set(listReferences)
            listReferences += merge(page.data).filter { !known.contains($0) }
            apply(page, request: request)
        } catch where error.isCancellation {
            return
        } catch {
            loadError = InventoryLive.message(for: error)
        }
    }

    func loadDelivery(_ reference: String, apiClient: APIClient) async {
        guard let id = deliveries.first(where: { $0.reference == reference })?.remoteID else { return }
        await loadDelivery(id: id, apiClient: apiClient)
    }

    func loadDelivery(id: Int, apiClient: APIClient) async {
        guard isRemote, let warehouseID else { return }
        do {
            store(try await apiClient.fetchStockDelivery(warehouseID: warehouseID, id: id))
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            loadError = InventoryLive.message(for: error)
        }
    }

    func reloadReturns(apiClient: APIClient) async {
        guard isRemote, let warehouseID else { return }
        isLoadingReturns = true
        defer { isLoadingReturns = false }
        do {
            let page = try await apiClient.fetchReturns(warehouseID: warehouseID, state: "all", perPage: 150)
            var references: [String] = []
            for row in page.data {
                let fetched = GoodsInReturn(row: row)
                if let index = returns.firstIndex(where: { $0.reference == fetched.reference }) {
                    if !returns[index].isDetailLoaded { returns[index] = fetched }
                } else {
                    returns.append(fetched)
                }
                references.append(fetched.reference)
            }
            returnReferences = references
            returnCounts = page.counts
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            loadError = InventoryLive.message(for: error)
        }
    }

    func isWaitingForDelivery(_ reference: String) -> Bool {
        isRemote && deliveries.first(where: { $0.reference == reference })?.isDetailLoaded != true
    }

    func isWaitingForReturn(_ reference: String) -> Bool {
        isRemote && returns.first(where: { $0.reference == reference })?.isDetailLoaded != true
    }

    var listedReturns: [GoodsInReturn] {
        guard isRemote else { return returns }
        return returnReferences.compactMap { reference in returns.first { $0.reference == reference } }
    }

    func loadReturn(_ reference: String, apiClient: APIClient) async {
        guard isRemote, let warehouseID, let id = returns.first(where: { $0.reference == reference })?.remoteID else { return }
        do {
            storeReturn(try await apiClient.fetchReturn(warehouseID: warehouseID, id: id))
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            loadError = InventoryLive.message(for: error)
        }
    }

    func store(_ detail: MobileStockDeliveryDetail) {
        let fetched = GoodsInDelivery(detail: detail)
        if let index = deliveries.firstIndex(where: { $0.reference == fetched.reference }) {
            deliveries[index] = fetched
        } else {
            deliveries.append(fetched)
        }
    }

    func storeReturn(_ detail: MobileReturnDetail) {
        let fetched = GoodsInReturn(detail: detail)
        if let index = returns.firstIndex(where: { $0.reference == fetched.reference }) {
            returns[index] = fetched
        } else {
            returns.append(fetched)
        }
    }

    private func merge(_ rows: [MobileStockDeliveryRow]) -> [String] {
        rows.map { row in
            let fetched = GoodsInDelivery(row: row)
            if let index = deliveries.firstIndex(where: { $0.reference == fetched.reference }) {
                if deliveries[index].isDetailLoaded, deliveries[index].remoteState == fetched.remoteState {
                    deliveries[index].remoteItemCount = fetched.remoteItemCount
                } else {
                    deliveries[index] = fetched
                }
            } else {
                deliveries.append(fetched)
            }
            return fetched.reference
        }
    }

    private func apply(_ page: MobileStockDeliveriesPage, request: GoodsInListRequest) {
        listRequest = request
        listTotal = page.meta.total
        currentPage = page.meta.currentPage
        lastPage = page.meta.lastPage
        serverCounts = page.counts
    }
}

struct GoodsInListRequest: Hashable, Sendable {
    var scope: GoodsInListScope
    var filter: GoodsInDeliveryFilter

    func query(page: Int, perPage: Int = 50) -> MobileStockDeliveriesQuery {
        var query = MobileStockDeliveriesQuery(page: page, perPage: perPage)
        switch scope {
        case .state(let state):
            query.state = GoodsInRemote.remoteState(state)
        case .all:
            query.state = filter.states.count == 1 ? filter.states.first.flatMap(GoodsInRemote.remoteState) : nil
        }
        if filter.sources.count == 1, let source = filter.sources.first {
            query.source = GoodsInRemote.remoteSource(source)
        } else if filter.sources == [.supplier, .production] {
            query.source = "suppliers"
        }
        if filter.dispatched.count == 1, let window = filter.dispatched.first {
            query.dispatched = GoodsInRemote.remoteDispatched(window)
        }
        query.sort = GoodsInRemote.remoteSort(filter.sort)
        return query
    }
}

extension GoodsInBacklogCounts {
    init(hubEntry: MobileGoodsInHubEntry?) {
        self.init()
        arriving = hubEntry?.arriving ?? 0
        received = hubEntry?.received ?? 0
        checked = hubEntry?.checked ?? 0
        bookingIn = hubEntry?.bookingIn ?? 0
        bookedInLastWeek = hubEntry?.bookedIn7D ?? 0
    }
}

extension GoodsInDelivery {
    static func placeholder(_ reference: String) -> GoodsInDelivery {
        var delivery = GoodsInDelivery(reference: reference, source: .supplier, sourceName: "", phase: .dispatched, dispatchedAt: .distantPast, items: [])
        delivery.isDetailLoaded = false
        return delivery
    }
}
