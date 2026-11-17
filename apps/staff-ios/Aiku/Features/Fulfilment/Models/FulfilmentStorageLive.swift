import Foundation
import Observation

enum StoredPalletFilter: String, CaseIterable, Hashable, Sendable {
    case storing
    case receiving
    case returning
    case incidents
    case returned

    var title: String {
        switch self {
        case .storing: "Storing"
        case .receiving: "Receiving"
        case .returning: "Returning"
        case .incidents: "Incidents"
        case .returned: "Returned"
        }
    }

    func count(in counts: MobileStoredPalletsPage.Counts?) -> Int? {
        guard let counts else { return nil }
        switch self {
        case .storing: return counts.storing
        case .receiving: return counts.receiving
        case .returning: return counts.returning
        case .incidents: return counts.incidents
        case .returned: return counts.returned
        }
    }
}

enum PalletIncidentFilter: String, CaseIterable, Hashable, Sendable {
    case all
    case damaged
    case lost
    case otherIncident = "other_incident"

    var title: String {
        switch self {
        case .all: "All"
        case .damaged: "Damaged"
        case .lost: "Lost"
        case .otherIncident: "Other"
        }
    }

    var serverState: String? { self == .all ? nil : rawValue }

    func count(in counts: MobileStoredPalletsPage.Counts?) -> Int? {
        guard let counts else { return nil }
        switch self {
        case .all: return counts.incidents
        case .damaged: return counts.incidentStates.damaged
        case .lost: return counts.incidentStates.lost
        case .otherIncident: return counts.incidentStates.otherIncident
        }
    }
}

enum StoredItemStateFilter: String, CaseIterable, Hashable, Sendable {
    case active
    case discontinuing
    case discontinued
    case all

    var title: String {
        switch self {
        case .active: "Active"
        case .discontinuing: "Discontinuing"
        case .discontinued: "Discontinued"
        case .all: "All"
        }
    }

    func count(in counts: MobileStoredItemsPage.Counts?) -> Int? {
        guard let counts else { return nil }
        switch self {
        case .active: return counts.active
        case .discontinuing: return counts.discontinuing
        case .discontinued: return counts.discontinued
        case .all: return counts.all
        }
    }
}

enum FulfilmentStorageText {
    static func palletTone(state: String, status: String?, incidentState: String?) -> Tone {
        if status == "incident" { return incidentTone(incidentState ?? state) }
        switch state {
        case "not_received": return .danger
        case "storing", "booked_in": return .success
        case "received", "booking_in": return .info
        default: return status == "returning" ? .info : .neutral
        }
    }

    static func incidentTone(_ state: String?) -> Tone {
        state == "other_incident" ? .warn : .danger
    }

    static func storedItemTone(_ state: String?) -> Tone {
        switch state {
        case "active": .success
        case "discontinuing": .warn
        case "discontinued": .danger
        default: .neutral
        }
    }

    static func palletStateTone(_ state: String?) -> Tone {
        switch state {
        case "storing": .success
        case "damaged", "lost", "other_incident", "not_picked": .danger
        case "picking", "picked": .info
        default: .neutral
        }
    }

    static func auditTone(type: String?, difference: Int?) -> Tone {
        switch type {
        case "no_change": return .success
        case "subtraction": return .warn
        case "addition", "set_up": return .info
        default:
            guard let difference else { return .neutral }
            if difference == 0 { return .success }
            return difference < 0 ? .warn : .info
        }
    }

    static func chip(_ pallet: MobileStoredPallet) -> String? {
        [pallet.incident?.stateLabel, pallet.stateLabel, pallet.statusLabel]
            .compactMap { $0?.trimmingCharacters(in: .whitespacesAndNewlines) }
            .first { !$0.isEmpty }
    }

    static func tone(_ pallet: MobileStoredPallet) -> Tone {
        palletTone(state: pallet.state, status: pallet.status, incidentState: pallet.incident?.state)
    }

    static func typeText(_ pallet: MobileStoredPallet) -> String? {
        if let label = pallet.typeLabel, !label.isEmpty { return label }
        return pallet.type?.capitalized
    }

    static func locationText(_ pallet: MobileStoredPallet) -> String {
        pallet.location?.code ?? "no location yet"
    }

    static func subtitle(_ pallet: MobileStoredPallet, showCustomer: Bool) -> String {
        let items = pallet.numberStoredItems ?? pallet.storedItems?.count ?? 0
        return PalletDeliveryText.joined([
            showCustomer ? pallet.customer?.name : nil,
            typeText(pallet),
            PalletDeliveryText.quoted(pallet.customerReference),
            locationText(pallet),
            items > 0 ? PalletDeliveryText.plural(items, "stored item", "stored items") : nil
        ])
    }

    static func incidentLine(_ pallet: MobileStoredPallet, includeCustomer: Bool) -> String {
        let incident = pallet.incident
        return PalletDeliveryText.joined([
            incident?.return.map { "Not picked in \($0.reference)" },
            PalletDeliveryText.quoted(incident?.message),
            incident?.reporter?.name,
            PalletDeliveryText.day(incident?.date),
            includeCustomer ? pallet.customer?.name : nil
        ])
    }

    static func auditChip(_ line: MobileStoredItemAudit.Line) -> String? {
        guard let difference = line.difference else { return line.isNewItem ? "New" : nil }
        if line.auditType == "set_up" { return line.auditTypeLabel ?? "Set up" }
        if difference == 0 { return "No change" }
        return difference < 0 ? "Subtraction −\(-difference)" : "Addition +\(difference)"
    }
}

extension MobileStoredPallet {
    func action(_ key: String) -> MobileFulfilmentAction? {
        availableActions?.first { $0.key == key }
    }

    func can(_ key: String) -> Bool {
        action(key).map { $0.disabledReason == nil } ?? false
    }

    var incidentStates: [String] {
        ["damaged", "lost"].filter { can($0) }
    }
}

extension MobileStoredItemAudit {
    func action(_ key: String) -> MobileFulfilmentAction? {
        availableActions?.first { $0.key == key }
    }

    func can(_ key: String) -> Bool {
        action(key).map { $0.disabledReason == nil } ?? false
    }

    func line(_ storedItemID: Int) -> Line? {
        lines.first { $0.storedItem.id == storedItemID }
    }

    func lineForCode(_ code: String) -> Line? {
        let needle = code.trimmingCharacters(in: .whitespacesAndNewlines)
        return lines.first { $0.storedItem.reference?.caseInsensitiveCompare(needle) == .orderedSame }
            ?? lines.first { $0.storedItem.barcode?.caseInsensitiveCompare(needle) == .orderedSame }
    }
}

struct ListLoadKey<Request: Hashable>: Hashable {
    let request: Request?
    let appearance: Int
}

struct FulfilmentDone: Equatable {
    let message: String
    let tone: Tone
}

@MainActor
@Observable
final class StoredPalletsFeed {
    var filter: StoredPalletFilter
    var incidentFilter: PalletIncidentFilter = .all
    var search = ""
    private(set) var rows: [MobileStoredPallet] = []
    private(set) var counts: MobileStoredPalletsPage.Counts?
    private(set) var isLoadingMore = false
    private(set) var loadError: String?
    private(set) var currentPage = 0
    private(set) var lastPage = 0
    private(set) var loadedRequest: Request?

    struct Request: Hashable, Sendable {
        let warehouseID: Int
        let filter: StoredPalletFilter
        let incidentFilter: PalletIncidentFilter
        let search: String
    }

    init(filter: StoredPalletFilter = .storing) {
        self.filter = filter
    }

    func request(warehouseID: Int) -> Request {
        Request(warehouseID: warehouseID, filter: filter, incidentFilter: filter == .incidents ? incidentFilter : .all, search: search.trimmingCharacters(in: .whitespacesAndNewlines))
    }

    var hasMorePages: Bool { currentPage > 0 && currentPage < lastPage }

    func reload(warehouseID: Int, apiClient: APIClient) async {
        let request = request(warehouseID: warehouseID)
        do {
            let page = try await apiClient.fetchStoredPallets(warehouseID: warehouseID, query: query(request, page: 1))
            guard request == self.request(warehouseID: warehouseID) else { return }
            rows = page.data
            counts = page.counts
            currentPage = page.meta.currentPage
            lastPage = page.meta.lastPage
            loadedRequest = request
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            loadError = PalletDeliveryText.message(for: error)
            loadedRequest = request
        }
    }

    func loadNextPage(apiClient: APIClient) async {
        guard let request = loadedRequest, hasMorePages, !isLoadingMore else { return }
        isLoadingMore = true
        defer { isLoadingMore = false }
        do {
            let page = try await apiClient.fetchStoredPallets(warehouseID: request.warehouseID, query: query(request, page: currentPage + 1))
            guard request == loadedRequest else { return }
            let known = Set(rows.map(\.id))
            rows.append(contentsOf: page.data.filter { !known.contains($0.id) })
            counts = page.counts
            currentPage = page.meta.currentPage
            lastPage = page.meta.lastPage
        } catch where error.isCancellation {
            return
        } catch {
            loadError = PalletDeliveryText.message(for: error)
        }
    }

    private func query(_ request: Request, page: Int) -> MobileStoredPalletsQuery {
        MobileStoredPalletsQuery(filter: request.filter.rawValue, incidentState: request.incidentFilter.serverState, search: request.search, page: page)
    }
}

@MainActor
@Observable
final class StoredPalletLive {
    let warehouseID: Int
    let palletID: Int
    private let apiClient: APIClient

    private(set) var pallet: MobileStoredPallet?
    private(set) var loadError: String?
    private(set) var working: String?
    private(set) var done: FulfilmentDone?
    private(set) var sheetError: String?
    private(set) var startedAuditID: Int?

    var locationQuery = ""
    private(set) var locationResults: [MobileFulfilmentLocation] = []
    private(set) var isSearchingLocations = false
    private(set) var locationError: String?
    var pickedLocation: MobileFulfilmentLocation?

    init(warehouseID: Int, palletID: Int, apiClient: APIClient) {
        self.warehouseID = warehouseID
        self.palletID = palletID
        self.apiClient = apiClient
    }

    var isWorking: Bool { working != nil }

    func load() async {
        guard !isWorking else { return }
        do {
            pallet = try await apiClient.fetchStoredPallet(warehouseID: warehouseID, id: palletID)
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            loadError = PalletDeliveryText.message(for: error)
        }
    }

    func clearDone() { done = nil }
    func clearSheetError() { sheetError = nil }
    func auditOpened() { startedAuditID = nil }

    func resetFinder() {
        locationQuery = ""
        locationResults = []
        locationError = nil
        pickedLocation = nil
        isSearchingLocations = false
        sheetError = nil
    }

    func searchLocations(_ query: String) async {
        let needle = query.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !needle.isEmpty else {
            locationResults = []
            locationError = nil
            isSearchingLocations = false
            return
        }
        try? await Task.sleep(for: .milliseconds(300))
        guard !Task.isCancelled, needle == locationQuery.trimmingCharacters(in: .whitespacesAndNewlines) else { return }
        isSearchingLocations = true
        defer { isSearchingLocations = false }
        do {
            let found = try await apiClient.fetchFulfilmentLocations(warehouseID: warehouseID, search: needle)
            guard needle == locationQuery.trimmingCharacters(in: .whitespacesAndNewlines) else { return }
            locationResults = found
            locationError = nil
        } catch where error.isCancellation {
            return
        } catch {
            locationError = PalletDeliveryText.message(for: error)
        }
    }

    func move(to location: MobileFulfilmentLocation) async -> Bool {
        await write(.move(MobilePalletBookInBody(locationId: location.id, locationCode: nil)), key: "move")
    }

    func move(scannedCode: String) async -> Bool {
        let code = scannedCode.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !code.isEmpty else { return false }
        return await write(.move(MobilePalletBookInBody(locationId: nil, locationCode: code)), key: "move")
    }

    func reportIncident(state: String, message: String) async -> Bool {
        let trimmed = message.trimmingCharacters(in: .whitespacesAndNewlines)
        return await write(.incident(MobilePalletIncidentBody(state: state, message: trimmed.isEmpty ? nil : trimmed)), key: "incident")
    }

    func startAudit() async {
        guard pallet != nil, !isWorking else { return }
        working = "audit"
        do {
            let result = try await apiClient.startPalletAudit(warehouseID: warehouseID, palletID: palletID)
            working = nil
            startedAuditID = result.data.id
        } catch {
            working = nil
            done = FulfilmentDone(message: PalletDeliveryText.message(for: error), tone: .danger)
        }
    }

    private func write(_ write: MobileStoredPalletWrite, key: String) async -> Bool {
        guard pallet != nil, !isWorking else { return false }
        working = key
        sheetError = nil
        do {
            let result = try await apiClient.writeStoredPallet(warehouseID: warehouseID, id: palletID, write: write)
            working = nil
            pallet = result.data
            let nothingChanged = result.result?.status == "nothing_changed"
            done = FulfilmentDone(message: result.result?.message ?? "Done", tone: nothingChanged ? .info : .success)
            return true
        } catch {
            working = nil
            sheetError = PalletDeliveryText.message(for: error)
            return false
        }
    }
}

@MainActor
@Observable
final class StoredItemsFeed {
    var state: StoredItemStateFilter = .active
    var search = ""
    private(set) var rows: [MobileStoredItemRow] = []
    private(set) var counts: MobileStoredItemsPage.Counts?
    private(set) var isLoadingMore = false
    private(set) var loadError: String?
    private(set) var currentPage = 0
    private(set) var lastPage = 0
    private(set) var loadedRequest: Request?

    struct Request: Hashable, Sendable {
        let warehouseID: Int
        let state: StoredItemStateFilter
        let search: String
    }

    func request(warehouseID: Int) -> Request {
        Request(warehouseID: warehouseID, state: state, search: search.trimmingCharacters(in: .whitespacesAndNewlines))
    }

    var hasMorePages: Bool { currentPage > 0 && currentPage < lastPage }

    func reload(warehouseID: Int, apiClient: APIClient) async {
        let request = request(warehouseID: warehouseID)
        do {
            let page = try await apiClient.fetchStoredItems(warehouseID: warehouseID, query: MobileStoredItemsQuery(state: request.state.rawValue, search: request.search))
            guard request == self.request(warehouseID: warehouseID) else { return }
            rows = page.data
            counts = page.counts
            currentPage = page.meta.currentPage
            lastPage = page.meta.lastPage
            loadedRequest = request
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            loadError = PalletDeliveryText.message(for: error)
            loadedRequest = request
        }
    }

    func loadNextPage(apiClient: APIClient) async {
        guard let request = loadedRequest, hasMorePages, !isLoadingMore else { return }
        isLoadingMore = true
        defer { isLoadingMore = false }
        do {
            let page = try await apiClient.fetchStoredItems(warehouseID: request.warehouseID, query: MobileStoredItemsQuery(state: request.state.rawValue, search: request.search, page: currentPage + 1))
            guard request == loadedRequest else { return }
            let known = Set(rows.map(\.id))
            rows.append(contentsOf: page.data.filter { !known.contains($0.id) })
            counts = page.counts
            currentPage = page.meta.currentPage
            lastPage = page.meta.lastPage
        } catch where error.isCancellation {
            return
        } catch {
            loadError = PalletDeliveryText.message(for: error)
        }
    }
}

@MainActor
@Observable
final class StoredItemAuditLive {
    let warehouseID: Int
    let auditID: Int
    private let apiClient: APIClient
    private let makeRequestID: @Sendable () -> String

    private(set) var audit: MobileStoredItemAudit?
    private(set) var loadError: String?
    private(set) var working: MobileStoredItemAuditWrite?
    private(set) var done: FulfilmentDone?
    private(set) var sheetError: String?
    private(set) var lastScanned: String?
    private(set) var isCompleted = false

    var choiceSearch = ""
    private(set) var choices: [MobileAuditChoice] = []
    private(set) var isLoadingChoices = false
    private(set) var choicesError: String?

    init(warehouseID: Int, auditID: Int, apiClient: APIClient, makeRequestID: @escaping @Sendable () -> String = { UUID().uuidString.lowercased() }) {
        self.warehouseID = warehouseID
        self.auditID = auditID
        self.apiClient = apiClient
        self.makeRequestID = makeRequestID
    }

    var isWorking: Bool { working != nil }

    func load() async {
        guard !isWorking else { return }
        do {
            audit = try await apiClient.fetchStoredItemAudit(warehouseID: warehouseID, id: auditID)
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            loadError = PalletDeliveryText.message(for: error)
        }
    }

    func clearDone() { done = nil }
    func clearSheetError() { sheetError = nil }

    func count(storedItemID: Int, quantity: Int) async -> Bool {
        await act(.count(MobileAuditCountBody(storedItemId: storedItemID, auditedQuantity: quantity))) { audit in
            let reference = audit.line(storedItemID)?.storedItem.reference ?? ""
            return "\(reference) counted: \(quantity)"
        }
    }

    func scan(_ code: String) async {
        let scanned = code.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !scanned.isEmpty, audit?.can("count") == true else { return }
        let succeeded = await act(.count(MobileAuditCountBody(barcode: scanned, add: 1, clientRequestId: makeRequestID()))) { audit in
            guard let line = audit.lineForCode(scanned) else { return nil }
            return "\(line.storedItem.reference ?? scanned) counted: \(line.auditedQuantity ?? 0)"
        }
        if succeeded {
            lastScanned = audit?.lineForCode(scanned)?.storedItem.reference ?? scanned
        }
    }

    func removeCount(deltaID: Int) async -> Bool {
        await act(.removeCount(deltaID: deltaID)) { _ in "Count removed" }
    }

    func complete() async {
        let reference = audit?.reference ?? ""
        if await act(.complete, message: { _ in "Audit \(reference) completed" }) {
            isCompleted = true
        }
    }

    func searchChoices(_ query: String) async {
        let needle = query.trimmingCharacters(in: .whitespacesAndNewlines)
        if !needle.isEmpty {
            try? await Task.sleep(for: .milliseconds(300))
            guard !Task.isCancelled else { return }
        }
        isLoadingChoices = true
        defer { isLoadingChoices = false }
        do {
            let found = try await apiClient.fetchStoredItemAuditChoices(warehouseID: warehouseID, id: auditID, search: needle)
            guard needle == choiceSearch.trimmingCharacters(in: .whitespacesAndNewlines) else { return }
            choices = found
            choicesError = nil
        } catch where error.isCancellation {
            return
        } catch {
            choicesError = PalletDeliveryText.message(for: error)
        }
    }

    @discardableResult
    private func act(_ write: MobileStoredItemAuditWrite, message: (MobileStoredItemAudit) -> String?) async -> Bool {
        guard audit != nil, !isWorking else { return false }
        working = write
        sheetError = nil
        do {
            let result = try await apiClient.writeStoredItemAudit(warehouseID: warehouseID, id: auditID, write: write)
            working = nil
            audit = result.data
            let nothingChanged = result.result?.status == "nothing_changed"
            done = FulfilmentDone(message: message(result.data) ?? result.result?.message ?? "Done", tone: nothingChanged ? .info : .success)
            return true
        } catch {
            working = nil
            let text = PalletDeliveryText.message(for: error)
            sheetError = text
            done = FulfilmentDone(message: text, tone: .danger)
            return false
        }
    }
}
