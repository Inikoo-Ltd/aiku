import Foundation
import Observation

struct GoodsInService: Sendable {
    var hub: @Sendable (_ warehouseID: Int) async throws -> [MobileGoodsInHubEntry] = { _ in throw APIError.unexpectedBody }
    var deliveries: @Sendable (_ warehouseID: Int, _ query: MobileStockDeliveriesQuery) async throws -> MobileStockDeliveriesPage = { _, _ in throw APIError.unexpectedBody }
    var delivery: @Sendable (_ warehouseID: Int, _ id: Int) async throws -> MobileStockDelivery = { _, _ in throw APIError.unexpectedBody }
    var writeDelivery: @Sendable (_ warehouseID: Int, _ id: Int, _ write: GoodsInDeliveryWrite) async throws -> MobileStockDeliveryWriteResult = { _, _, _ in throw APIError.unexpectedBody }
    var returns: @Sendable (_ warehouseID: Int, _ query: MobileReturnsQuery) async throws -> MobileReturnsPage = { _, _ in throw APIError.unexpectedBody }
    var goodsReturn: @Sendable (_ warehouseID: Int, _ id: Int) async throws -> MobileReturn = { _, _ in throw APIError.unexpectedBody }
    var writeReturn: @Sendable (_ warehouseID: Int, _ id: Int, _ write: GoodsInReturnWrite) async throws -> MobileReturnWriteResult = { _, _, _ in throw APIError.unexpectedBody }
    var scan: @Sendable (_ warehouseID: Int, _ code: String) async throws -> MobileScanResult = { _, _ in throw APIError.unexpectedBody }
    var locations: @Sendable (_ warehouseID: Int, _ search: String) async throws -> [MobileLocation] = { _, _ in [] }

    static func live(_ client: APIClient) -> GoodsInService {
        GoodsInService(
            hub: { try await client.fetchGoodsInHub(warehouseID: $0) },
            deliveries: { try await client.fetchStockDeliveries(warehouseID: $0, query: $1) },
            delivery: { try await client.fetchStockDelivery(warehouseID: $0, id: $1) },
            writeDelivery: { try await client.writeStockDelivery(warehouseID: $0, id: $1, write: $2) },
            returns: { try await client.fetchReturns(warehouseID: $0, query: $1) },
            goodsReturn: { try await client.fetchReturn(warehouseID: $0, id: $1) },
            writeReturn: { try await client.writeReturn(warehouseID: $0, id: $1, write: $2) },
            scan: { try await client.scan(warehouseID: $0, code: $1) },
            locations: { try await client.fetchLocations(warehouseID: $0, query: MobileLocationsQuery(search: $1, perPage: 20)).data }
        )
    }
}

enum GoodsInText {
    static let separator = " · "
    static let offline = "There is no connection. Check Wi-Fi and try again."
    static let server = "aiku is having trouble. Try again in a moment."
    static let forbidden = "You don’t have the right to see this in this warehouse."
    static let notFound = "It doesn’t exist, or it belongs to another warehouse."

    static func problem(_ error: Error) -> String {
        if let form = error as? FormValidationError { return form.message }
        if let apiError = error as? APIError {
            switch apiError {
            case .offline: return offline
            case .unauthorized: return "Your session has ended. Sign in again."
            case .rejected(let message), .validation(let message, _), .tooManyAttempts(let message): return message
            case .http(403): return forbidden
            case .http(404): return notFound
            default: return server
            }
        }
        return server
    }

    static func scanProblem(code: String, error: Error) -> String {
        switch error as? APIError {
        case .offline: "Couldn’t look up \(code) · no connection"
        case .http(403): "You can’t look up \(code) in this warehouse"
        default: "Couldn’t look up \(code) · aiku is having trouble"
        }
    }

    static func isUnsure(_ error: Error) -> Bool {
        guard let apiError = error as? APIError else { return false }
        switch apiError {
        case .offline, .invalidResponse: return true
        case .http(let code): return code >= 500
        default: return false
        }
    }

    static func quantity(_ value: Double, label: String? = nil) -> String {
        if let label = label?.trimmingCharacters(in: .whitespacesAndNewlines), !label.isEmpty { return label }
        return number(value)
    }

    static func number(_ value: Double) -> String {
        value.formatted(.number.grouping(.never).precision(.fractionLength(0...2)).locale(Locale(identifier: "en_GB")))
    }

    static func date(_ value: String?, timeZone: TimeZone = .current) -> String? {
        guard let value, !value.trimmingCharacters(in: .whitespaces).isEmpty else { return nil }
        if let date = InventoryLive.date(value) {
            return date.formatted(Date.FormatStyle(timeZone: timeZone).day(.defaultDigits).month(.abbreviated).locale(Locale(identifier: "en_GB")))
        }
        let day = String(value.prefix(10))
        let parser = DateFormatter()
        parser.locale = Locale(identifier: "en_US_POSIX")
        parser.timeZone = TimeZone(identifier: "UTC")
        parser.dateFormat = "yyyy-MM-dd"
        guard let date = parser.date(from: day) else { return nil }
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_GB")
        formatter.timeZone = TimeZone(identifier: "UTC")
        formatter.dateFormat = "d MMM yyyy"
        return formatter.string(from: date)
    }

    static func moment(_ value: String?, timeZone: TimeZone = .current) -> String? {
        guard let date = InventoryLive.date(value) else { return date(value, timeZone: timeZone) }
        return date.formatted(Date.FormatStyle(timeZone: timeZone).day(.defaultDigits).month(.abbreviated).hour(.twoDigits(amPM: .omitted)).minute(.twoDigits).locale(Locale(identifier: "en_GB")))
    }

    static func plural(_ count: Int, _ one: String, _ other: String) -> String {
        count == 1 ? one : other
    }

    static func deliveryTone(_ state: String) -> Tone {
        switch state {
        case GoodsInDeliveryStateKey.received, GoodsInDeliveryStateKey.checked: .info
        case GoodsInDeliveryStateKey.bookingIn: .lime
        case GoodsInDeliveryStateKey.bookedIn, GoodsInDeliveryStateKey.placed: .success
        case GoodsInDeliveryStateKey.notReceived, GoodsInDeliveryStateKey.cancelled: .danger
        default: .neutral
        }
    }

    static func returnTone(_ state: String) -> Tone {
        switch state {
        case GoodsInReturnStateKey.returning: .info
        case GoodsInReturnStateKey.returned, GoodsInReturnStateKey.done: .success
        case GoodsInReturnStateKey.cancelled: .danger
        default: .neutral
        }
    }

    static func newRequestID() -> String {
        UUID().uuidString.lowercased()
    }
}

enum GoodsInReturnStateKey {
    static let received = "received"
    static let returning = "returning"
    static let returned = "returned"
    static let done = "done"
    static let cancelled = "cancelled"
    static let cancellation = "cancellation"
}

enum GoodsInReturnPutType {
    static let sow = "sow"
    static let notSow = "not-sow"
    static let damaged = "damaged"
}

enum GoodsInEvent: Equatable, Sendable {
    case done(message: String?, isNothingChanged: Bool, wasRaised: Bool)
    case refused(message: String)
}

struct GoodsInPagedList<Row: Sendable & Equatable>: Equatable, Sendable {
    var rows: [Row] = []
    var page = 0
    var lastPage = 0
    var total: Int?
    var loadingPage: Int?
    var failedPage: Int?
    var problem: String?
    var isRefreshing = false

    var hasLoaded: Bool { page > 0 }
    var canLoadMore: Bool { hasLoaded && page < lastPage && loadingPage == nil && failedPage == nil }
    var isEmpty: Bool { hasLoaded && rows.isEmpty && problem == nil }

    func loading(_ page: Int) -> GoodsInPagedList {
        var copy = self
        copy.loadingPage = page
        copy.failedPage = nil
        if page == 1 { copy.problem = nil }
        return copy
    }

    func refreshing() -> GoodsInPagedList {
        var copy = self
        copy.isRefreshing = true
        return copy
    }

    func loaded(_ newRows: [Row], meta: MobileLocationsPage.Meta, id: (Row) -> Int) -> GoodsInPagedList {
        var copy = self
        if meta.currentPage <= 1 {
            copy.rows = newRows
        } else {
            let known = Set(rows.map(id))
            copy.rows += newRows.filter { !known.contains(id($0)) }
        }
        copy.page = meta.currentPage
        copy.lastPage = meta.lastPage
        copy.total = meta.total
        copy.loadingPage = nil
        copy.failedPage = nil
        copy.problem = nil
        copy.isRefreshing = false
        return copy
    }

    func failed(_ page: Int, problem: String) -> GoodsInPagedList {
        var copy = self
        copy.loadingPage = nil
        copy.failedPage = page
        copy.problem = problem
        copy.isRefreshing = false
        return copy
    }
}

enum GoodsInDeliveryView: String, CaseIterable, Hashable, Sendable {
    case items
    case check
    case place
    case done
    case checklist
}

extension MobileStockDelivery {
    var views: [GoodsInDeliveryView] {
        var views: [GoodsInDeliveryView] = []
        if GoodsInDeliveryStateKey.beforeReceiving.contains(state) {
            views.append(.items)
        } else if GoodsInDeliveryStateKey.inGoodsIn.contains(state) {
            views.append(.check)
            if GoodsInDeliveryStateKey.placing.contains(state) { views.append(.place) }
            views.append(.done)
        } else {
            views.append(.done)
        }
        if checklist != nil { views.append(.checklist) }
        return views
    }

    var defaultView: GoodsInDeliveryView {
        if GoodsInDeliveryStateKey.beforeReceiving.contains(state) { return .items }
        if GoodsInDeliveryStateKey.inGoodsIn.contains(state), items.contains(where: \.needsCheck) { return .check }
        if GoodsInDeliveryStateKey.placing.contains(state), items.contains(where: \.canBePlaced) { return .place }
        return .done
    }

    func items(for view: GoodsInDeliveryView) -> [MobileStockDeliveryItem] {
        switch view {
        case .items: items
        case .check: items.filter { !$0.isDone }
        case .place: items.filter { $0.state != GoodsInDeliveryStateKey.cancelled && $0.hasBeenChecked }
        case .done: items.filter(\.isDone)
        case .checklist: []
        }
    }

    var canCheck: Bool { availableActions.can(GoodsInActionKey.check) }
    var canPlace: Bool { availableActions.can(GoodsInActionKey.place) }
    var canRecordChecks: Bool { availableActions.can(GoodsInActionKey.checklist) }

    func scansLines(in view: GoodsInDeliveryView?) -> Bool {
        switch view {
        case .check: canCheck
        case .place: canPlace
        default: false
        }
    }

    var nextToPlace: MobileStockDeliveryItem? {
        items(for: .place).first(where: \.canBePlaced)
    }

    func scanCandidates(in view: GoodsInDeliveryView?) -> [MobileStockDeliveryItem] {
        view == .place ? items.filter(\.canBePlaced) : items(for: .check)
    }

    var isBeforeReceiving: Bool { GoodsInDeliveryStateKey.beforeReceiving.contains(state) }
}

extension Array where Element == MobileStockDeliveryItem {
    func matching(code: String) -> [MobileStockDeliveryItem] {
        let needle = code.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !needle.isEmpty else { return [] }
        return filter { item in
            guard let sko = item.sko else { return false }
            return sko.barcode?.trimmingCharacters(in: .whitespaces) == needle || sko.code.caseInsensitiveCompare(needle) == .orderedSame
        }
    }
}

extension MobileStockDeliveryItem {
    func wasRaised(above sent: Double) -> Bool {
        (skoChecked ?? 0) > sent + 0.00005
    }
}

enum GoodsInLineScanOutcome: Equatable, Sendable {
    case line(itemID: Int)
    case choose(code: String, itemIDs: [Int])
    case notOnDelivery(code: String)
    case failed(code: String, message: String)
}

@MainActor
@Observable
final class GoodsInDeliveryFeed {
    let warehouseID: Int
    let deliveryID: Int
    private(set) var delivery: MobileStockDelivery?
    private(set) var isLoading = false
    private(set) var loadError: String?
    var chosenView: GoodsInDeliveryView?
    private(set) var working: GoodsInDeliveryWrite?
    var event: GoodsInEvent?
    private(set) var lastWrite: GoodsInDeliveryWrite?
    private(set) var writeErrors: [String: String] = [:]
    private(set) var unsurePlacement: GoodsInDeliveryWrite?
    private(set) var lookingUpCode: String?
    var scanOutcome: GoodsInLineScanOutcome?
    @ObservationIgnored private let service: GoodsInService
    @ObservationIgnored private var loadTask: Task<Void, Never>?

    init(warehouseID: Int, deliveryID: Int, service: GoodsInService) {
        self.warehouseID = warehouseID
        self.deliveryID = deliveryID
        self.service = service
    }

    var isWorking: Bool { working != nil }

    var view: GoodsInDeliveryView? {
        guard let delivery else { return nil }
        if let chosenView, delivery.views.contains(chosenView) { return chosenView }
        return delivery.defaultView
    }

    func load() async {
        guard !isWorking else { return }
        loadTask?.cancel()
        let task = Task { await performLoad() }
        loadTask = task
        await task.value
    }

    private func performLoad() async {
        isLoading = true
        loadError = nil
        do {
            let fetched = try await service.delivery(warehouseID, deliveryID)
            guard !Task.isCancelled else { return }
            delivery = fetched
            isLoading = false
        } catch where error.isCancellation {
            return
        } catch {
            isLoading = false
            loadError = GoodsInText.problem(error)
        }
    }

    func receive() async { await act(.receive) }
    func unreceive() async { await act(.unreceive) }
    func checkAll() async { await act(.checkAll) }

    func check(itemID: Int, skoQuantity: Double) async {
        await act(.check(itemID: itemID, skoQuantity: max(0, skoQuantity)))
    }

    func notReceived(itemID: Int) async {
        await act(.check(itemID: itemID, skoQuantity: 0))
    }

    func place(itemID: Int, quantity: Double, target: GoodsInPlaceTarget, setAsPickingLocation: Bool) async {
        await act(.place(itemID: itemID, quantity: quantity, target: target, setAsPickingLocation: setAsPickingLocation, clientRequestID: GoodsInText.newRequestID()))
    }

    func placeAll(itemID: Int, target: GoodsInPlaceTarget, setAsPickingLocation: Bool) async {
        await act(.placeAll(itemID: itemID, target: target, setAsPickingLocation: setAsPickingLocation, clientRequestID: GoodsInText.newRequestID()))
    }

    func retryPlacement() async {
        guard let unsurePlacement else { return }
        await act(unsurePlacement)
    }

    func recordCheck(key: String, result: GoodsInChecklistResult, body: String?, incidentType: String?, incidentQuantity: Double?) async {
        let trimmed = body?.trimmingCharacters(in: .whitespacesAndNewlines)
        await act(.recordCheck(
            key: key,
            result: result,
            body: trimmed?.isEmpty == false ? trimmed : nil,
            incidentType: result == .failed ? incidentType : nil,
            incidentQuantity: result == .failed ? incidentQuantity : nil
        ))
    }

    func clearWriteErrors() {
        writeErrors = [:]
    }

    func scanLine(_ code: String) async {
        let scanned = code.trimmingCharacters(in: .whitespacesAndNewlines)
        guard let delivery, !scanned.isEmpty, lookingUpCode == nil else { return }
        let candidates = delivery.scanCandidates(in: view)
        let local = candidates.matching(code: scanned)
        if !local.isEmpty {
            scanOutcome = Self.outcome(scanned, local.map(\.id))
            return
        }
        lookingUpCode = scanned
        scanOutcome = nil
        defer { lookingUpCode = nil }
        do {
            let result = try await service.scan(warehouseID, scanned)
            let skoID = result.scanType == .sko ? result.sko?.id : nil
            scanOutcome = Self.outcome(scanned, candidates.filter { skoID != nil && $0.sko?.id == skoID }.map(\.id))
        } catch APIError.http(404) {
            scanOutcome = .notOnDelivery(code: scanned)
        } catch {
            scanOutcome = .failed(code: scanned, message: GoodsInText.scanProblem(code: scanned, error: error))
        }
    }

    private static func outcome(_ code: String, _ itemIDs: [Int]) -> GoodsInLineScanOutcome {
        switch itemIDs.count {
        case 0: .notOnDelivery(code: code)
        case 1: .line(itemID: itemIDs[0])
        default: .choose(code: code, itemIDs: itemIDs)
        }
    }

    private func act(_ write: GoodsInDeliveryWrite) async {
        guard delivery != nil, !isWorking else { return }
        loadTask?.cancel()
        isLoading = false
        working = write
        event = nil
        writeErrors = [:]
        unsurePlacement = nil
        defer { working = nil }
        do {
            let result = try await service.writeDelivery(warehouseID, deliveryID, write)
            delivery = result.data
            loadError = nil
            lastWrite = write
            var wasRaised = false
            if case let .check(itemID, sent) = write {
                wasRaised = result.data.item(itemID)?.wasRaised(above: sent) == true
            }
            event = .done(
                message: Self.nonEmpty(result.result?.message),
                isNothingChanged: result.result?.status == "nothing_changed",
                wasRaised: wasRaised
            )
        } catch let form as FormValidationError {
            writeErrors = form.fields
            lastWrite = write
            event = .refused(message: form.message)
        } catch where error.isCancellation {
            return
        } catch {
            lastWrite = write
            if write.isPlacement, GoodsInText.isUnsure(error) {
                unsurePlacement = write
            }
            event = .refused(message: GoodsInText.problem(error))
        }
    }

    static func nonEmpty(_ text: String?) -> String? {
        guard let text = text?.trimmingCharacters(in: .whitespacesAndNewlines), !text.isEmpty else { return nil }
        return text
    }
}

@MainActor
@Observable
final class GoodsInReturnFeed {
    let warehouseID: Int
    let returnID: Int
    private(set) var goodsReturn: MobileReturn?
    private(set) var isLoading = false
    private(set) var loadError: String?
    private(set) var working: GoodsInReturnWrite?
    var event: GoodsInEvent?
    private(set) var lastWrite: GoodsInReturnWrite?
    private(set) var writeErrors: [String: String] = [:]
    @ObservationIgnored private let service: GoodsInService
    @ObservationIgnored private var loadTask: Task<Void, Never>?

    init(warehouseID: Int, returnID: Int, service: GoodsInService) {
        self.warehouseID = warehouseID
        self.returnID = returnID
        self.service = service
    }

    var isWorking: Bool { working != nil }

    func load() async {
        guard !isWorking else { return }
        loadTask?.cancel()
        let task = Task { await performLoad() }
        loadTask = task
        await task.value
    }

    private func performLoad() async {
        isLoading = true
        loadError = nil
        do {
            let fetched = try await service.goodsReturn(warehouseID, returnID)
            guard !Task.isCancelled else { return }
            goodsReturn = fetched
            isLoading = false
        } catch where error.isCancellation {
            return
        } catch {
            isLoading = false
            loadError = GoodsInText.problem(error)
        }
    }

    func start() async { await act(.start) }

    func backToStock(itemID: Int, quantity: Double?, locationOrgStockID: Int) async {
        await act(.returned(itemID: itemID, quantity: quantity, locationOrgStockID: locationOrgStockID, clientRequestID: GoodsInText.newRequestID()))
    }

    func damaged(itemID: Int, quantity: Double?) async {
        await act(.damaged(itemID: itemID, quantity: quantity, clientRequestID: GoodsInText.newRequestID()))
    }

    func notReturned(itemID: Int, quantity: Double?) async {
        await act(.notReturned(itemID: itemID, quantity: quantity, clientRequestID: GoodsInText.newRequestID()))
    }

    func undo(putID: Int) async { await act(.undoPut(putID: putID)) }
    func setAsReturned() async { await act(.setAsReturned) }

    func clearWriteErrors() {
        writeErrors = [:]
    }

    private func act(_ write: GoodsInReturnWrite) async {
        guard goodsReturn != nil, !isWorking else { return }
        loadTask?.cancel()
        isLoading = false
        working = write
        event = nil
        writeErrors = [:]
        defer { working = nil }
        do {
            let result = try await service.writeReturn(warehouseID, returnID, write)
            goodsReturn = result.data
            loadError = nil
            lastWrite = write
            event = .done(
                message: GoodsInDeliveryFeed.nonEmpty(result.result?.message),
                isNothingChanged: result.result?.status == "nothing_changed",
                wasRaised: false
            )
        } catch let form as FormValidationError {
            writeErrors = form.fields
            lastWrite = write
            event = .refused(message: form.message)
        } catch where error.isCancellation {
            return
        } catch {
            lastWrite = write
            event = .refused(message: GoodsInText.problem(error))
        }
    }
}

struct GoodsInDeliveryFilter: Hashable, Sendable {
    var state: GoodsInDeliveryStateFilter = .active
    var source: GoodsInDeliverySource?
    var search = ""
    var dispatched: GoodsInDispatchedBucket?
    var sort: GoodsInDeliverySort?

    var activeFilterCount: Int { [source != nil, dispatched != nil].filter { $0 }.count }
    var isFiltered: Bool { activeFilterCount > 0 || sort != nil }

    func cleared() -> GoodsInDeliveryFilter {
        GoodsInDeliveryFilter(state: state, search: search)
    }

    func query(page: Int, perPage: Int = 50) -> MobileStockDeliveriesQuery {
        MobileStockDeliveriesQuery(state: state, source: source, search: search, dispatched: dispatched, sort: sort, page: page, perPage: perPage)
    }
}

enum GoodsInListScanOutcome: Equatable, Sendable {
    case open(id: Int, reference: String)
    case noMatch(code: String)
    case failed(code: String, message: String)
}

@MainActor
@Observable
final class GoodsInDeliveriesFeed {
    static let searchDelay: Duration = .milliseconds(300)

    let warehouseID: Int
    private(set) var filter: GoodsInDeliveryFilter
    private(set) var list = GoodsInPagedList<MobileStockDeliveryRow>()
    private(set) var counts: MobileGoodsInCounts?
    private(set) var preview: (filter: GoodsInDeliveryFilter, total: Int)?
    private(set) var lookingUpCode: String?
    var scanOutcome: GoodsInListScanOutcome?
    @ObservationIgnored private let service: GoodsInService
    @ObservationIgnored private let perPage: Int
    @ObservationIgnored private var listTask: Task<Void, Never>?
    @ObservationIgnored private var searchTask: Task<Void, Never>?
    @ObservationIgnored private var previewTask: Task<Void, Never>?

    init(warehouseID: Int, filter: GoodsInDeliveryFilter = GoodsInDeliveryFilter(), service: GoodsInService, perPage: Int = 50) {
        self.warehouseID = warehouseID
        self.filter = filter
        self.service = service
        self.perPage = perPage
    }

    func start() async {
        guard list.loadingPage == nil, !list.hasLoaded || list.page <= 1 else { return }
        await loadPage(1)
    }

    func select(_ state: GoodsInDeliveryStateFilter) async {
        guard state != filter.state else { return }
        var changed = filter
        changed.state = state
        await apply(changed)
    }

    func search(_ text: String) {
        filter.search = text
        searchTask?.cancel()
        searchTask = Task {
            try? await Task.sleep(for: Self.searchDelay)
            guard !Task.isCancelled else { return }
            list = GoodsInPagedList()
            await loadPage(1)
        }
    }

    func apply(_ newFilter: GoodsInDeliveryFilter) async {
        searchTask?.cancel()
        filter = newFilter
        list = GoodsInPagedList()
        await loadPage(1)
    }

    func clearFilters() async {
        await apply(filter.cleared())
    }

    func loadMore() async {
        guard list.canLoadMore else { return }
        await loadPage(list.page + 1)
    }

    func retry() async {
        await loadPage(list.failedPage ?? 1)
    }

    func refresh() async {
        list = list.refreshing()
        await loadPage(1)
    }

    func total(for draft: GoodsInDeliveryFilter) -> Int? {
        if draft == filter, list.hasLoaded, list.loadingPage == nil { return list.total }
        if let preview, preview.filter == draft { return preview.total }
        return nil
    }

    func previewTotal(_ draft: GoodsInDeliveryFilter) {
        previewTask?.cancel()
        guard total(for: draft) == nil else { return }
        previewTask = Task {
            try? await Task.sleep(for: Self.searchDelay)
            guard !Task.isCancelled else { return }
            if let page = try? await service.deliveries(warehouseID, draft.query(page: 1, perPage: 1)) {
                preview = (draft, page.meta.total)
            }
        }
    }

    func lookUpScan(_ code: String) async {
        let scanned = code.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !scanned.isEmpty, lookingUpCode == nil else { return }
        lookingUpCode = scanned
        scanOutcome = nil
        defer { lookingUpCode = nil }
        do {
            let result = try await service.scan(warehouseID, scanned)
            if result.scanType == .stockDelivery, let found = result.stockDelivery {
                scanOutcome = .open(id: found.id, reference: found.reference)
            } else {
                scanOutcome = .noMatch(code: scanned)
            }
        } catch APIError.http(404) {
            scanOutcome = .noMatch(code: scanned)
        } catch {
            scanOutcome = .failed(code: scanned, message: GoodsInText.scanProblem(code: scanned, error: error))
        }
    }

    private func loadPage(_ page: Int) async {
        let requested = filter
        listTask?.cancel()
        list = list.loading(page)
        let task = Task {
            do {
                let result = try await service.deliveries(warehouseID, requested.query(page: page, perPage: perPage))
                guard !Task.isCancelled, requested == filter else { return }
                list = list.loaded(result.data, meta: result.meta, id: \.id)
                counts = result.counts ?? counts
            } catch where error.isCancellation {
                return
            } catch {
                guard requested == filter else { return }
                list = list.failed(page, problem: GoodsInText.problem(error))
            }
        }
        listTask = task
        await task.value
    }
}

@MainActor
@Observable
final class GoodsInReturnsFeed {
    let warehouseID: Int
    private(set) var state: GoodsInReturnStateFilter
    private(set) var search = ""
    private(set) var list = GoodsInPagedList<MobileReturnRow>()
    private(set) var counts: MobileGoodsInCounts?
    @ObservationIgnored private let service: GoodsInService
    @ObservationIgnored private let perPage: Int
    @ObservationIgnored private var listTask: Task<Void, Never>?
    @ObservationIgnored private var searchTask: Task<Void, Never>?

    init(warehouseID: Int, state: GoodsInReturnStateFilter = .all, service: GoodsInService, perPage: Int = 50) {
        self.warehouseID = warehouseID
        self.state = state
        self.service = service
        self.perPage = perPage
    }

    func start() async {
        guard list.loadingPage == nil, !list.hasLoaded || list.page <= 1 else { return }
        await loadPage(1)
    }

    func select(_ newState: GoodsInReturnStateFilter) async {
        guard newState != state else { return }
        searchTask?.cancel()
        state = newState
        list = GoodsInPagedList()
        await loadPage(1)
    }

    func search(_ text: String) {
        search = text
        searchTask?.cancel()
        searchTask = Task {
            try? await Task.sleep(for: GoodsInDeliveriesFeed.searchDelay)
            guard !Task.isCancelled else { return }
            list = GoodsInPagedList()
            await loadPage(1)
        }
    }

    func loadMore() async {
        guard list.canLoadMore else { return }
        await loadPage(list.page + 1)
    }

    func retry() async {
        await loadPage(list.failedPage ?? 1)
    }

    func refresh() async {
        list = list.refreshing()
        await loadPage(1)
    }

    private func loadPage(_ page: Int) async {
        let query = MobileReturnsQuery(state: state, search: search, page: page, perPage: perPage)
        listTask?.cancel()
        list = list.loading(page)
        let task = Task {
            do {
                let result = try await service.returns(warehouseID, query)
                guard !Task.isCancelled, query.state == state, query.search == search else { return }
                list = list.loaded(result.data, meta: result.meta, id: \.id)
                counts = result.counts ?? counts
            } catch where error.isCancellation {
                return
            } catch {
                list = list.failed(page, problem: GoodsInText.problem(error))
            }
        }
        listTask = task
        await task.value
    }
}

enum GoodsInHubFigure: String, CaseIterable, Hashable, Sendable {
    case arriving
    case received
    case checked
    case bookingIn
    case bookedInWeek
    case placedWeek

    var deliveryState: GoodsInDeliveryStateFilter {
        switch self {
        case .arriving: .dispatched
        case .received: .received
        case .checked: .checked
        case .bookingIn: .bookingIn
        case .bookedInWeek: .bookedIn
        case .placedWeek: .placed
        }
    }

    var returnState: GoodsInReturnStateFilter? {
        switch self {
        case .received: .received
        case .bookingIn: .returning
        default: nil
        }
    }
}

extension MobileGoodsInHubEntry {
    var counts: [(figure: GoodsInHubFigure, value: Int)] {
        [
            arriving.map { (GoodsInHubFigure.arriving, $0) },
            received.map { (GoodsInHubFigure.received, $0) },
            checked.map { (GoodsInHubFigure.checked, $0) },
            bookingIn.map { (GoodsInHubFigure.bookingIn, $0) },
            bookedIn7D.map { (GoodsInHubFigure.bookedInWeek, $0) },
            placed7D.map { (GoodsInHubFigure.placedWeek, $0) },
        ].compactMap { $0 }
    }

    func isVisible(_ can: MobileWarehouse.Abilities) -> Bool {
        switch key {
        case GoodsInHubKey.fulfilment: can.viewFulfilment
        case GoodsInHubKey.returns: can.viewReturns
        default: can.viewGoodsIn
        }
    }

    var source: GoodsInDeliverySource? {
        switch key {
        case GoodsInHubKey.stockDeliveries: .suppliers
        case GoodsInHubKey.agents: .agents
        case GoodsInHubKey.partners: .partners
        default: nil
        }
    }
}

@MainActor
@Observable
final class GoodsInBacklogFeed {
    let warehouseID: Int
    private(set) var rows: [MobileGoodsInHubEntry]?
    private(set) var isLoading = false
    private(set) var loadError: String?
    @ObservationIgnored private let service: GoodsInService

    init(warehouseID: Int, service: GoodsInService) {
        self.warehouseID = warehouseID
        self.service = service
    }

    func load() async {
        isLoading = true
        loadError = nil
        defer { isLoading = false }
        do {
            rows = try await service.hub(warehouseID)
        } catch where error.isCancellation {
            return
        } catch {
            loadError = GoodsInText.problem(error)
        }
    }
}

struct GoodsInPickedLocation: Hashable, Sendable {
    let id: Int
    let code: String
    var area: String?
}

@MainActor
@Observable
final class GoodsInLocationFinder {
    let warehouseID: Int
    private(set) var query = ""
    private(set) var results: [MobileLocation] = []
    private(set) var isSearching = false
    private(set) var problem: String?
    private(set) var picked: GoodsInPickedLocation?
    private(set) var lookingUp: String?
    private(set) var notALocation: String?
    @ObservationIgnored private let service: GoodsInService
    @ObservationIgnored private var searchTask: Task<Void, Never>?

    init(warehouseID: Int, service: GoodsInService) {
        self.warehouseID = warehouseID
        self.service = service
    }

    func reset() {
        searchTask?.cancel()
        query = ""
        results = []
        isSearching = false
        problem = nil
        picked = nil
        lookingUp = nil
        notALocation = nil
    }

    func search(_ text: String) {
        query = text
        notALocation = nil
        searchTask?.cancel()
        let needle = text.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !needle.isEmpty else {
            results = []
            isSearching = false
            problem = nil
            return
        }
        searchTask = Task {
            try? await Task.sleep(for: GoodsInDeliveriesFeed.searchDelay)
            guard !Task.isCancelled else { return }
            await runSearch(needle)
        }
    }

    func pick(_ location: MobileLocation) {
        picked = GoodsInPickedLocation(id: location.id, code: location.code, area: Self.areaText(location.area))
    }

    func scan(_ code: String) async {
        let scanned = code.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !scanned.isEmpty, lookingUp == nil else { return }
        lookingUp = scanned
        notALocation = nil
        searchTask?.cancel()
        do {
            let result = try await service.scan(warehouseID, scanned)
            lookingUp = nil
            guard result.scanType == .location, let location = result.location else {
                notALocation = scanned
                return
            }
            query = location.code
            picked = GoodsInPickedLocation(id: location.id, code: location.code)
            await runSearch(location.code)
        } catch APIError.http(404) {
            lookingUp = nil
            notALocation = scanned
        } catch {
            lookingUp = nil
            problem = GoodsInText.problem(error)
        }
    }

    private func runSearch(_ needle: String) async {
        isSearching = true
        problem = nil
        do {
            let found = try await service.locations(warehouseID, needle)
            guard !Task.isCancelled else { return }
            results = found
            isSearching = false
            if let picked, let refreshed = found.first(where: { $0.id == picked.id }) {
                self.picked = GoodsInPickedLocation(id: refreshed.id, code: refreshed.code, area: Self.areaText(refreshed.area))
            }
        } catch where error.isCancellation {
            return
        } catch {
            isSearching = false
            problem = GoodsInText.problem(error)
        }
    }

    static func areaText(_ area: MobileLocation.Area?) -> String? {
        guard let area else { return nil }
        let name = area.name?.trimmingCharacters(in: .whitespacesAndNewlines) ?? ""
        return name.isEmpty ? area.code : name
    }
}

enum GoodsInStage: Int, CaseIterable, Sendable {
    case dispatched
    case received
    case checked
    case bookingIn
    case bookedIn

    var title: String {
        switch self {
        case .dispatched: "Dispatched"
        case .received: "Received"
        case .checked: "Checked"
        case .bookingIn: "Booking in"
        case .bookedIn: "Booked in"
        }
    }
}

extension MobileStockDelivery {
    var stage: GoodsInStage? {
        if GoodsInDeliveryStateKey.beforeReceiving.contains(state) { return .dispatched }
        switch state {
        case GoodsInDeliveryStateKey.received: return .received
        case GoodsInDeliveryStateKey.checked: return .checked
        case GoodsInDeliveryStateKey.bookingIn: return .bookingIn
        case GoodsInDeliveryStateKey.bookedIn, GoodsInDeliveryStateKey.placed: return .bookedIn
        default: return nil
        }
    }

    var differences: [MobileStockDeliveryItem] {
        items.filter { item in item.skoChecked.map { $0 != item.skoExpected } ?? false }
    }
}

extension MobileStockDeliveryChecklist {
    func check(_ key: String) -> Check? {
        sections.lazy.flatMap(\.checks).first { $0.key == key }
    }
}

struct GoodsInDifference: Equatable, Sendable {
    let chip: Chip
    let description: String

    static func of(checked: Double, expected: Double) -> GoodsInDifference {
        let gap = checked - expected
        if checked == 0, expected > 0 {
            return GoodsInDifference(chip: Chip("Not received −\(GoodsInText.number(expected))", .danger), description: "\(GoodsInText.number(expected)) under the expected quantity")
        }
        if gap < 0 {
            return GoodsInDifference(chip: Chip("Under −\(GoodsInText.number(-gap))", .warn), description: "\(GoodsInText.number(-gap)) under the expected quantity")
        }
        if gap > 0 {
            return GoodsInDifference(chip: Chip("Over +\(GoodsInText.number(gap))", .orange), description: "\(GoodsInText.number(gap)) over the expected quantity")
        }
        return GoodsInDifference(chip: Chip("Matches", .success), description: "Matches the expected quantity")
    }
}

extension MobileStockDeliveryItem {
    var expectedLine: String {
        "\(name) · expected \(GoodsInText.quantity(skoExpected, label: skoExpectedLabel))"
    }

    var checkLook: (chip: Chip?, subtitle: String) {
        guard let checked = skoChecked else { return (nil, expectedLine) }
        if isNotReceived { return (Chip("Not received", .danger), "\(name) · checked as 0") }
        if checked < skoExpected { return (Chip("Checked · under", .warn), expectedLine) }
        if checked > skoExpected { return (Chip("Checked · over", .orange), expectedLine) }
        return (Chip("Checked", .success), expectedLine)
    }

    var pickingLocationCode: String? {
        (locations.first(where: \.isDefaultWholesale) ?? locations.first(where: \.isPicking))?.location.code
    }

    var preferredLocation: MobileGoodsInItemLocation? {
        locations.first(where: \.isDefaultWholesale) ?? locations.first(where: \.isPicking) ?? locations.first
    }
}

@MainActor
final class GoodsInFeeds {
    static let shared = GoodsInFeeds()
    private var deliveries: [String: GoodsInDeliveryFeed] = [:]

    func delivery(warehouseID: Int, id: Int, service: GoodsInService) -> GoodsInDeliveryFeed {
        let key = "\(warehouseID)-\(id)"
        if let feed = deliveries[key] { return feed }
        let feed = GoodsInDeliveryFeed(warehouseID: warehouseID, deliveryID: id, service: service)
        deliveries[key] = feed
        return feed
    }

    func reset() {
        deliveries = [:]
    }
}

enum GoodsInDates {
    static func dayMonth(_ date: Date) -> String {
        date.formatted(.dateTime.day(.twoDigits).month(.abbreviated).locale(Locale(identifier: "en_GB")))
    }
}

enum GoodsInQuantity {
    static func decimal(_ value: Double) -> String {
        ((value * 10_000).rounded() / 10_000).formatted(.number.precision(.fractionLength(0...4)).locale(Locale(identifier: "en_GB")))
    }
}
