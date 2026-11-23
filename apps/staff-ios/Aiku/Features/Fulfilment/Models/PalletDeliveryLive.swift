import Foundation
import Observation

enum PalletDeliveryFilter: String, CaseIterable, Hashable, Sendable {
    case active
    case confirmed
    case received
    case bookingIn = "booking_in"
    case bookedIn = "booked_in"
    case notReceived = "not_received"

    var title: String {
        switch self {
        case .active: "Active"
        case .confirmed: "Confirmed"
        case .received: "Received"
        case .bookingIn: "Booking in"
        case .bookedIn: "Booked in"
        case .notReceived: "Not received"
        }
    }

    func count(in counts: MobilePalletDeliveriesPage.Counts?) -> Int? {
        guard let counts else { return nil }
        switch self {
        case .active: return counts.active
        case .confirmed: return counts.confirmed
        case .received: return counts.received
        case .bookingIn: return counts.bookingIn
        case .bookedIn: return counts.bookedIn
        case .notReceived: return counts.notReceived
        }
    }
}

enum PalletDeliveryText {
    static func deliveryTone(_ state: String) -> Tone {
        switch state {
        case "received": .info
        case "booking_in": .info
        case "booked_in": .success
        case "not_received": .danger
        default: .neutral
        }
    }

    static func palletTone(_ pallet: MobileDeliveryPallet) -> Tone {
        if pallet.status == "incident" { return pallet.incident?.state == "other_incident" ? .warn : .danger }
        switch pallet.state {
        case "not_received": return .danger
        case "storing", "booked_in": return .success
        case "received", "booking_in": return .info
        default: return pallet.status == "returning" ? .info : .neutral
        }
    }

    static func palletChip(_ pallet: MobileDeliveryPallet) -> String? {
        [pallet.incident?.stateLabel, pallet.stateLabel, pallet.statusLabel]
            .compactMap { $0?.trimmingCharacters(in: .whitespacesAndNewlines) }
            .first { !$0.isEmpty }
    }

    static func quoted(_ reference: String?) -> String? {
        guard let reference = reference?.trimmingCharacters(in: .whitespacesAndNewlines), !reference.isEmpty else { return nil }
        return "“\(reference)”"
    }

    static func typeText(_ pallet: MobileDeliveryPallet) -> String? {
        if let label = pallet.typeLabel, !label.isEmpty { return label }
        return pallet.type?.capitalized
    }

    static func locationText(_ pallet: MobileDeliveryPallet) -> String {
        pallet.location?.code ?? "no location yet"
    }

    static func plural(_ count: Int, _ one: String, _ many: String) -> String {
        "\(count.grouped) \(count == 1 ? one : many)"
    }

    static func palletSubtitle(_ pallet: MobileDeliveryPallet) -> String {
        let items = pallet.numberStoredItems ?? pallet.storedItems?.count ?? 0
        return joined([
            typeText(pallet),
            quoted(pallet.customerReference),
            locationText(pallet),
            items > 0 ? plural(items, "stored item", "stored items") : nil
        ])
    }

    static func countsText(_ counts: MobilePalletDeliveryCounts) -> String? {
        let parts = [
            counts.pallets > 0 ? plural(counts.pallets, "pallet", "pallets") : nil,
            counts.boxes > 0 ? plural(counts.boxes, "box", "boxes") : nil,
            counts.oversizes > 0 ? plural(counts.oversizes, "oversize", "oversize") : nil
        ].compactMap { $0 }
        return parts.isEmpty ? nil : parts.joined(separator: ", ")
    }

    static func bookInProgress(booked: Int, of total: Int) -> String {
        "\(booked.grouped) of \(total.grouped) booked in"
    }

    static func day(_ iso: String?) -> String? {
        guard let iso, !iso.isEmpty else { return nil }
        if let date = InventoryLive.date(iso) { return GoodsInDates.dayMonth(date) }
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.dateFormat = "yyyy-MM-dd"
        return formatter.date(from: iso).map(GoodsInDates.dayMonth)
    }

    static func rowSubtitle(_ row: MobilePalletDeliveryRow) -> String {
        let progress: String? = {
            if row.state == "booking_in" {
                return bookInProgress(booked: row.counts.bookedIn, of: max(row.counts.total - row.counts.notReceived, 0))
            }
            return countsText(row.counts)
        }()
        let moment: String? = {
            switch row.state {
            case "confirmed": return day(row.estimatedDeliveryDate).map { "Expected \($0)" }
            case "booked_in": return day(row.bookedInAt).map { "Booked in \($0)" }
            default: return day(row.receivedAt).map { "Received \($0)" }
            }
        }()
        return joined([row.customer?.name, quoted(row.customerReference), progress, moment])
    }

    static func joined(_ parts: [String?]) -> String {
        parts.compactMap { $0?.trimmingCharacters(in: .whitespacesAndNewlines) }.filter { !$0.isEmpty }.joined(separator: " · ")
    }

    static func message(for error: Error) -> String {
        if let apiError = error as? APIError {
            switch apiError {
            case .offline: return "You need an internet connection to do this. Check the connection and try again."
            case .unauthorized: return "Your session has ended. Sign in again."
            case .http(403): return "You don’t have the right to do this in this warehouse."
            case .http(404): return GoodsInText.notFound
            default: return apiError.localizedDescription
            }
        }
        if error is DecodingError {
            return "Aiku sent something the app could not read."
        }
        return error.localizedDescription
    }
}

extension MobilePalletDeliveryWrite {
    var actionKey: String {
        switch self {
        case .receive: "receive"
        case .startBooking: "start_booking"
        case .finishBooking: "finish_booking"
        case .bookIn: "book_in"
        case .notReceived: "not_received"
        case .undoNotReceived: "undo_not_received"
        }
    }
}

extension MobileDeliveryPallet {
    func action(_ key: String) -> MobileFulfilmentAction? {
        availableActions?.first { $0.key == key }
    }

    func can(_ key: String) -> Bool {
        guard let action = action(key) else { return false }
        return action.disabledReason == nil
    }

    var isWorkable: Bool { can("book_in") || can("not_received") }

    func isLabelled(_ code: String) -> Bool {
        let scanned = code.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !scanned.isEmpty else { return false }
        return reference.caseInsensitiveCompare(scanned) == .orderedSame
            || customerReference?.caseInsensitiveCompare(scanned) == .orderedSame
    }
}

extension MobilePalletDeliveryDetail {
    func action(_ key: String) -> MobileFulfilmentAction? {
        availableActions?.first { $0.key == key }
    }

    var palletList: [MobileDeliveryPallet] { pallets ?? [] }

    func pallet(_ id: Int) -> MobileDeliveryPallet? {
        palletList.first { $0.id == id }
    }

    func palletLabelled(_ code: String) -> MobileDeliveryPallet? {
        let scanned = code.trimmingCharacters(in: .whitespacesAndNewlines)
        if let exact = palletList.first(where: { $0.reference.caseInsensitiveCompare(scanned) == .orderedSame }) { return exact }
        let matches = palletList.filter { $0.isLabelled(scanned) }
        return matches.count == 1 ? matches[0] : nil
    }

    func at(_ state: String) -> String? {
        timeline?.first { $0.state == state }?.at
    }

    var bookInTotal: Int { palletList.filter { $0.state != "not_received" }.count }
    var bookedInCount: Int { palletList.filter { $0.state == "booked_in" }.count }

    var nextToBookIn: MobileDeliveryPallet? {
        palletList.first { $0.can("book_in") && $0.location == nil }
    }

    var canScanForBookIn: Bool {
        state == "booking_in" && palletList.contains { $0.isWorkable }
    }
}

@MainActor
@Observable
final class PalletDeliveriesFeed {
    static let shared = PalletDeliveriesFeed()

    var filter: PalletDeliveryFilter = .active
    var search = ""
    private(set) var rows: [MobilePalletDeliveryRow] = []
    private(set) var counts: MobilePalletDeliveriesPage.Counts?
    private(set) var isLoading = false
    private(set) var isLoadingMore = false
    private(set) var loadError: String?
    private(set) var currentPage = 0
    private(set) var lastPage = 0
    private(set) var loadedRequest: Request?

    struct Request: Hashable, Sendable {
        let warehouseID: Int
        let filter: PalletDeliveryFilter
        let search: String
    }

    func request(warehouseID: Int) -> Request {
        Request(warehouseID: warehouseID, filter: filter, search: search.trimmingCharacters(in: .whitespacesAndNewlines))
    }

    var hasMorePages: Bool { currentPage > 0 && currentPage < lastPage }

    func reset() {
        rows = []
        counts = nil
        loadError = nil
        currentPage = 0
        lastPage = 0
        loadedRequest = nil
        filter = .active
        search = ""
    }

    func reload(warehouseID: Int, apiClient: APIClient) async {
        let request = request(warehouseID: warehouseID)
        isLoading = true
        defer { isLoading = false }
        do {
            let page = try await apiClient.fetchPalletDeliveries(warehouseID: warehouseID, query: query(request, page: 1))
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
            let page = try await apiClient.fetchPalletDeliveries(warehouseID: request.warehouseID, query: query(request, page: currentPage + 1))
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

    private func query(_ request: Request, page: Int) -> MobilePalletDeliveriesQuery {
        MobilePalletDeliveriesQuery(state: request.filter.rawValue, search: request.search, page: page)
    }
}

struct PalletDeliveryDone: Equatable {
    let message: String
    let tone: Tone
}

@MainActor
@Observable
final class PalletDeliveryLive {
    let warehouseID: Int
    let deliveryID: Int
    private let apiClient: APIClient

    private(set) var delivery: MobilePalletDeliveryDetail?
    private(set) var loadError: String?
    private(set) var working: MobilePalletDeliveryWrite?
    private(set) var done: PalletDeliveryDone?
    private(set) var fieldError: String?
    var openPalletID: Int?

    var locationQuery = ""
    private(set) var locationResults: [MobileFulfilmentLocation] = []
    private(set) var isSearchingLocations = false
    private(set) var locationError: String?
    var pickedLocation: MobileFulfilmentLocation?

    init(warehouseID: Int, deliveryID: Int, apiClient: APIClient) {
        self.warehouseID = warehouseID
        self.deliveryID = deliveryID
        self.apiClient = apiClient
    }

    var isWorking: Bool { working != nil }
    var openPallet: MobileDeliveryPallet? { openPalletID.flatMap { delivery?.pallet($0) } }

    func isWorking(on actionKey: String) -> Bool {
        working?.actionKey == actionKey
    }

    func load() async {
        guard !isWorking else { return }
        do {
            delivery = try await apiClient.fetchPalletDelivery(warehouseID: warehouseID, id: deliveryID)
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            loadError = PalletDeliveryText.message(for: error)
        }
    }

    func clearDone() {
        done = nil
    }

    func openPallet(_ id: Int) {
        resetFinder()
        fieldError = nil
        openPalletID = id
    }

    func closePallet() {
        resetFinder()
        fieldError = nil
        openPalletID = nil
    }

    func receive() async { await act(.receive) }
    func startBooking() async { await act(.startBooking) }
    func finishBooking() async { await act(.finishBooking) }
    func notReceived(_ palletID: Int) async { await act(.notReceived(palletID: palletID)) }
    func undoNotReceived(_ palletID: Int) async { await act(.undoNotReceived(palletID: palletID)) }

    func bookIn(_ palletID: Int, location: MobileFulfilmentLocation) async {
        await act(.bookIn(palletID: palletID, body: MobilePalletBookInBody(locationId: location.id, locationCode: nil)))
    }

    func bookIn(_ palletID: Int, scannedCode: String) async {
        await act(.bookIn(palletID: palletID, body: MobilePalletBookInBody(locationId: nil, locationCode: scannedCode)))
    }

    enum ScanOutcome: Equatable {
        case openedPallet
        case bookingIn
        case notOnDelivery(String)
        case ignored
    }

    func scanForBookIn(_ code: String) async -> ScanOutcome {
        let scanned = code.trimmingCharacters(in: .whitespacesAndNewlines)
        guard let delivery, !scanned.isEmpty, !isWorking else { return .ignored }
        if let labelled = delivery.palletLabelled(scanned) {
            openPallet(labelled.id)
            return .openedPallet
        }
        if let open = openPallet, open.can("book_in") {
            await bookIn(open.id, scannedCode: scanned)
            return .bookingIn
        }
        return .notOnDelivery(scanned)
    }

    func searchLocations(_ query: String) async {
        locationQuery = query
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
            if let picked = pickedLocation, let refreshed = found.first(where: { $0.id == picked.id }) {
                pickedLocation = refreshed
            }
            locationError = nil
        } catch where error.isCancellation {
            return
        } catch {
            locationError = PalletDeliveryText.message(for: error)
        }
    }

    private func resetFinder() {
        locationQuery = ""
        locationResults = []
        locationError = nil
        pickedLocation = nil
        isSearchingLocations = false
    }

    private func act(_ write: MobilePalletDeliveryWrite) async {
        guard delivery != nil, !isWorking else { return }
        working = write
        fieldError = nil
        do {
            let result = try await apiClient.writePalletDelivery(warehouseID: warehouseID, deliveryID: deliveryID, write: write)
            working = nil
            delivery = result.data
            loadError = nil
            if write.palletID != nil {
                closePallet()
            }
            let nothingChanged = result.result?.status == "nothing_changed"
            done = PalletDeliveryDone(message: result.result?.message ?? "Done", tone: nothingChanged ? .info : .success)
        } catch {
            working = nil
            if case .bookIn = write, case .validation(_, _, let fields) = error as? APIError {
                fieldError = (fields["location_id"] ?? fields["location_code"])?.first
            }
            done = PalletDeliveryDone(message: PalletDeliveryText.message(for: error), tone: .danger)
        }
    }
}
