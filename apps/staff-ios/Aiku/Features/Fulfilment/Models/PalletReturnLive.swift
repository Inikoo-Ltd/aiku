import Foundation
import Observation

enum PalletReturnStateFilter: String, CaseIterable, Hashable, Sendable {
    case active
    case confirmed
    case picking
    case picked
    case dispatched
    case cancel

    var title: String {
        switch self {
        case .active: "Active"
        case .confirmed: "Confirmed"
        case .picking: "Picking"
        case .picked: "Picked"
        case .dispatched: "Dispatched"
        case .cancel: "Cancelled"
        }
    }

    func count(in counts: MobilePalletReturnsPage.Counts?) -> Int? {
        guard let counts else { return nil }
        switch self {
        case .active: return counts.active
        case .confirmed: return counts.confirmed
        case .picking: return counts.picking
        case .picked: return counts.picked
        case .dispatched: return counts.dispatched
        case .cancel: return counts.cancel
        }
    }
}

enum PalletReturnTypeFilter: String, CaseIterable, Hashable, Sendable {
    case all
    case pallets
    case items

    var title: String {
        switch self {
        case .all: "All"
        case .pallets: "Pallets"
        case .items: "Stored items"
        }
    }

    var serverValue: String? { self == .all ? nil : rawValue }

    func count(in counts: MobilePalletReturnsPage.Counts?) -> Int? {
        guard let counts else { return nil }
        switch self {
        case .all: return counts.types.pallets + counts.types.items
        case .pallets: return counts.types.pallets
        case .items: return counts.types.items
        }
    }
}

enum PalletReturnSort: String, CaseIterable, Hashable, Sendable {
    case oldest = "date"
    case reference
    case customer
    case dispatched = "-dispatched_at"

    static let options: [ListSortOption<PalletReturnSort>] = [
        ListSortOption(sort: nil, label: "Newest first"),
        ListSortOption(sort: .oldest, label: "Oldest first"),
        ListSortOption(sort: .reference, label: "Reference"),
        ListSortOption(sort: .customer, label: "Customer"),
        ListSortOption(sort: .dispatched, label: "Dispatched")
    ]

    static let onlineOnly: Set<PalletReturnSort> = [.dispatched]
}

enum PalletReturnsOffline {
    private static let activeStates: Set<String> = ["confirmed", "picking", "picked"]

    static func rows(
        _ rows: [MobilePalletReturnRow],
        state: PalletReturnStateFilter,
        type: PalletReturnTypeFilter,
        search: String,
        sort: PalletReturnSort?
    ) -> [MobilePalletReturnRow] {
        let kept = rows.filter { row in
            isIn(row, state)
                && (type.serverValue == nil || row.typeGroup == type.serverValue)
                && SavedListFilter.matches(search, row.reference, row.customerReference, row.customer?.name)
        }
        switch sort {
        case nil, .dispatched?:
            return SavedListFilter.sorted(kept, descending: true, id: \.id, key: { listedAt($0) })
        case .oldest?:
            return SavedListFilter.sorted(kept, descending: false, id: \.id, key: { listedAt($0) })
        case .reference?:
            return SavedListFilter.sorted(kept, descending: false, id: \.id, key: { PalletDeliveryText.sortKey($0.reference) })
        case .customer?:
            return SavedListFilter.sorted(kept, descending: false, id: \.id, key: { PalletDeliveryText.sortKey($0.customer?.name) })
        }
    }

    private static func isIn(_ row: MobilePalletReturnRow, _ state: PalletReturnStateFilter) -> Bool {
        state == .active ? activeStates.contains(row.state) : row.state == state.rawValue
    }

    private static func listedAt(_ row: MobilePalletReturnRow) -> Date? {
        guard let date = row.date else { return nil }
        return ListDates.instant(date) ?? SavedListFilter.day(date)
    }
}

enum PalletReturnText {
    static let stages = ["confirmed", "picking", "picked", "dispatched"]
    static let stageTitles = ["Confirmed", "Picking", "Picked", "Dispatched"]
    static let unsureTitle = "Not sure it was saved"

    static func unsureMessage(_ write: MobilePalletReturnWrite) -> String {
        switch write {
        case .scan(let barcode, _): "The scan of \(barcode) didn’t get an answer. Try again; it is not counted twice."
        case .pickFromOtherPallet: "The pick from another pallet didn’t get an answer. Try again; it is not picked twice."
        default: "The pick didn’t get an answer. Try again; it is not picked twice."
        }
    }

    static func stateTone(_ state: String) -> Tone {
        switch state {
        case "picking": .info
        case "picked", "dispatched": .success
        case "cancel": .danger
        default: .neutral
        }
    }

    static func lineTone(_ state: String?, waiting: Bool) -> Tone {
        if waiting { return .warn }
        switch state {
        case "picked": return .success
        case "not_picked", "cancel": return .danger
        case "picking": return .info
        default: return .neutral
        }
    }

    static func itemLineTone(_ line: MobileReturnItemLine) -> Tone {
        if line.waitingCustomerService { return .warn }
        if line.quantityOrdered > 0, line.quantityPicked >= line.quantityOrdered { return .success }
        if line.quantityPicked > 0 || line.quantityNotPicked > 0 { return .warn }
        return .neutral
    }

    static func typeTitle(_ row: MobilePalletReturnRow) -> String {
        row.typeLabel ?? (row.typeGroup == "items" ? "Stored items" : "Pallets")
    }

    static func rowDate(_ row: MobilePalletReturnRow, in timeZone: TimeZone = .current) -> String? {
        ListDates.recordDate(row.date, in: timeZone)
    }

    static func rowChip(_ row: MobilePalletReturnRow) -> Chip {
        Chip(row.stateLabel?.nilIfEmpty ?? row.state, stateTone(row.state))
    }

    static func rowDetails(_ row: MobilePalletReturnRow) -> [String] {
        let size = row.typeGroup == "items"
            ? PalletDeliveryText.plural(row.numberItems, "item line", "item lines")
            : PalletDeliveryText.plural(row.numberPallets, "pallet", "pallets")
        let platform = row.platform?.label.map { "from \($0)" }
        let parts: [String?] = [typeTitle(row), platform, size]
        return parts.compactMap { $0?.trimmingCharacters(in: .whitespacesAndNewlines) }.filter { !$0.isEmpty }
    }

    static func kicker(_ detail: MobilePalletReturnDetail) -> String {
        PalletDeliveryText.joined(["Fulfilment return", detail.typeLabel ?? (detail.isItems ? "Stored items" : "Pallets"), detail.customer?.name])
    }

    static func locationText(_ location: MobileStoredPallet.Location?) -> String {
        location?.code ?? "no location"
    }
}

struct PalletGroup: Identifiable {
    let pallet: MobileReturnPalletRef?
    let locationCode: String?
    let lines: [(item: MobileReturnStoredItem, line: MobileReturnItemLine)]

    var id: String { "\(pallet?.id ?? 0)" }

    static func grouping(_ items: [MobileReturnStoredItem]) -> [PalletGroup] {
        var order: [Int] = []
        var byPallet: [Int: [(MobileReturnStoredItem, MobileReturnItemLine)]] = [:]
        for item in items {
            for line in item.lines {
                let key = line.pallet?.id ?? 0
                if byPallet[key] == nil { order.append(key) }
                byPallet[key, default: []].append((item, line))
            }
        }
        return order.compactMap { key in
            guard let lines = byPallet[key], let first = lines.first?.1 else { return nil }
            return PalletGroup(pallet: first.pallet, locationCode: first.location?.code, lines: lines)
        }
        .sorted {
            let left = ($0.locationCode == nil ? 1 : 0, $0.locationCode ?? "", $0.pallet?.reference ?? "")
            let right = ($1.locationCode == nil ? 1 : 0, $1.locationCode ?? "", $1.pallet?.reference ?? "")
            return left < right
        }
    }
}

@MainActor
@Observable
final class PalletReturnsFeed {
    var state: PalletReturnStateFilter = .active
    var type: PalletReturnTypeFilter = .all
    var search = ""
    var listFilter = ListFilter<PalletReturnSort>()
    private(set) var rows: [MobilePalletReturnRow] = []
    private(set) var counts: MobilePalletReturnsPage.Counts?
    private(set) var total: Int?
    private(set) var isLoadingMore = false
    private(set) var loadError: String?
    private(set) var currentPage = 0
    private(set) var lastPage = 0
    private(set) var loadedRequest: Request?
    private(set) var saved: SavedRows<MobilePalletReturnRow>?
    private let savedLists: SavedLists
    private let currentUserID: @MainActor () -> Int?

    init(savedLists: SavedLists = .shared, currentUserID: @escaping @MainActor () -> Int? = { OfflineWork.shared.userID }) {
        self.savedLists = savedLists
        self.currentUserID = currentUserID
    }

    struct Request: Hashable, Sendable {
        let warehouseID: Int
        let state: PalletReturnStateFilter
        let type: PalletReturnTypeFilter
        let search: String
        let sort: PalletReturnSort?
    }

    func request(warehouseID: Int) -> Request {
        Request(
            warehouseID: warehouseID,
            state: state,
            type: type,
            search: search.trimmingCharacters(in: .whitespacesAndNewlines),
            sort: listFilter.sort
        )
    }

    var hasMorePages: Bool { currentPage > 0 && currentPage < lastPage }
    var isShowingSavedRows: Bool { saved != nil }
    var savedAt: Date? { saved?.savedAt }
    var onlineOnlySorts: Set<PalletReturnSort> { isShowingSavedRows ? PalletReturnSort.onlineOnly : [] }

    func previewTotal(warehouseID: Int) -> Int? {
        guard loadedRequest == request(warehouseID: warehouseID), loadError == nil else { return nil }
        return isShowingSavedRows ? rows.count : total
    }

    func reload(warehouseID: Int, apiClient: APIClient) async {
        let request = request(warehouseID: warehouseID)
        do {
            let page = try await apiClient.fetchPalletReturns(warehouseID: warehouseID, query: query(request, page: 1))
            guard request == self.request(warehouseID: warehouseID) else { return }
            rows = page.data
            counts = page.counts
            total = page.meta.total
            currentPage = page.meta.currentPage
            lastPage = page.meta.lastPage
            saved = nil
            loadedRequest = request
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch APIError.offline {
            guard request == self.request(warehouseID: warehouseID) else { return }
            guard !showSavedRows(request) else { return }
            loadError = PalletDeliveryText.message(for: APIError.offline)
            loadedRequest = request
        } catch {
            loadError = PalletDeliveryText.message(for: error)
            loadedRequest = request
        }
    }

    private func showSavedRows(_ request: Request) -> Bool {
        guard let userID = currentUserID(),
              let found = savedLists.rows(MobilePalletReturnRow.self, userID: userID, warehouseID: request.warehouseID, kind: .palletReturns) else { return false }
        saved = found
        rows = PalletReturnsOffline.rows(found.rows, state: request.state, type: request.type, search: request.search, sort: request.sort)
        currentPage = 1
        lastPage = 1
        loadedRequest = request
        loadError = nil
        return true
    }

    func loadNextPage(apiClient: APIClient) async {
        guard let request = loadedRequest, hasMorePages, !isLoadingMore else { return }
        isLoadingMore = true
        defer { isLoadingMore = false }
        do {
            let page = try await apiClient.fetchPalletReturns(warehouseID: request.warehouseID, query: query(request, page: currentPage + 1))
            guard request == loadedRequest else { return }
            let known = Set(rows.map(\.id))
            rows.append(contentsOf: page.data.filter { !known.contains($0.id) })
            counts = page.counts
            total = page.meta.total
            currentPage = page.meta.currentPage
            lastPage = page.meta.lastPage
        } catch where error.isCancellation {
            return
        } catch {
            loadError = PalletDeliveryText.message(for: error)
        }
    }

    private func query(_ request: Request, page: Int) -> MobilePalletReturnsQuery {
        MobilePalletReturnsQuery(
            state: request.state.rawValue,
            type: request.type.serverValue,
            search: request.search,
            sort: request.sort?.rawValue,
            page: page
        )
    }
}

enum PalletReturnEvent: Equatable {
    case done(String, Tone)
    case lineFound(Int)
}

enum FulfilmentWriteOutcome: Equatable {
    case saved
    case refused
    case unsure
}

extension MobilePalletReturnWrite {
    var isScan: Bool {
        if case .scan = self { return true }
        return false
    }

    var isResentWhenUnsure: Bool {
        switch self {
        case .scan, .pickItem, .pickFromOtherPallet: true
        default: false
        }
    }
}

struct ReturnShipmentErrors: Equatable {
    private(set) var shipper: [String] = []
    private(set) var tracking: String?
    private(set) var parcelGeneral: [String] = []
    private(set) var parcelRows: [Int: [String]] = [:]

    init(_ fields: [String: [String]]) {
        shipper = fields["shipper_id"] ?? []
        tracking = fields["tracking"]?.first
        for key in fields.keys.sorted() where key == "parcels" || key.hasPrefix("parcels.") {
            let messages = fields[key] ?? []
            let parts = key.split(separator: ".")
            if parts.count >= 3, let index = Int(parts[1]) {
                parcelRows[index, default: []] += messages
            } else {
                parcelGeneral += messages
            }
        }
    }

    var coversTheProblem: Bool {
        !shipper.isEmpty || tracking != nil || !parcelGeneral.isEmpty || !parcelRows.isEmpty
    }
}

private struct QueuedReturnWrite {
    let id = UUID()
    let write: MobilePalletReturnWrite
    let announce: Bool
}

@MainActor
@Observable
final class PalletReturnLive {
    let warehouseID: Int
    let returnID: Int
    private let apiClient: APIClient
    private let makeRequestID: @Sendable () -> String

    private(set) var detail: MobilePalletReturnDetail?
    private(set) var loadError: String?
    private(set) var working: MobilePalletReturnWrite?
    private(set) var event: PalletReturnEvent?
    private(set) var sheetError: String?
    private(set) var fieldErrors: [String: [String]] = [:]
    private(set) var queuedScans = 0
    private(set) var scanMisses = 0
    private(set) var unsure: MobilePalletReturnWrite?
    private(set) var shippers = GoodsOutShipperList()
    private(set) var isLoadingShippers = false
    private(set) var shippersError: String?
    var selectedPalletID: Int?

    private var pending: [QueuedReturnWrite] = []
    private var outcomes: [UUID: FulfilmentWriteOutcome] = [:]
    private var awaited: Set<UUID> = []
    private var isDraining = false

    init(warehouseID: Int, returnID: Int, apiClient: APIClient, makeRequestID: @escaping @Sendable () -> String = { UUID().uuidString.lowercased() }) {
        self.warehouseID = warehouseID
        self.returnID = returnID
        self.apiClient = apiClient
        self.makeRequestID = makeRequestID
    }

    var isWorking: Bool { working != nil || isDraining }

    private func scanMissed() {
        scanMisses += 1
        ScanFeedback.shared.rejected()
    }

    func load() async {
        guard !isWorking else { return }
        do {
            detail = try await apiClient.fetchPalletReturn(warehouseID: warehouseID, id: returnID)
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            loadError = PalletDeliveryText.message(for: error)
        }
    }

    func clearEvent() { event = nil }
    func clearErrors() {
        sheetError = nil
        fieldErrors = [:]
    }

    func startPicking() async -> Bool { await act(.startPicking) }
    func finishPicking() async -> Bool { await act(.finishPicking) }
    func dispatch() async -> Bool { await act(.dispatch) }
    func printInserts() async -> Bool { await act(.printInserts) }
    func pickPallet(_ lineID: Int) async -> Bool { await act(.pickPallet(lineID: lineID, requestID: makeRequestID())) }
    func pickItem(_ lineID: Int, quantity: Int?) async -> Bool { await act(.pickItem(lineID: lineID, quantity: quantity, requestID: makeRequestID())) }
    func notPicked(_ lineID: Int, quantity: Int? = nil) async -> Bool { await act(.notPicked(lineID: lineID, quantity: quantity)) }
    func undo(_ lineID: Int) async -> Bool { await act(.undo(lineID: lineID)) }
    func pickFromOtherPallet(_ palletStoredItemID: Int, quantity: Int, fromLineID: Int?) async -> Bool {
        await act(.pickFromOtherPallet(palletStoredItemID: palletStoredItemID, quantity: quantity, fromLineID: fromLineID, requestID: makeRequestID()))
    }
    func deleteShipment(_ id: Int) async -> Bool { await act(.deleteShipment(id: id)) }
    func printLabel(_ id: Int) async -> Bool { await act(.printLabel(shipmentID: id)) }

    func createShipment(shipperID: Int, tracking: String?, parcels: [MobileReturnParcelBody]?) async -> Bool {
        if let parcels {
            guard await act(.setParcels(parcels), announce: false) else { return false }
        }
        let trimmed = tracking?.trimmingCharacters(in: .whitespacesAndNewlines)
        return await act(.addShipment(shipperID: shipperID, tracking: trimmed?.isEmpty == false ? trimmed : nil))
    }

    func scan(_ code: String) async {
        let scanned = code.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !scanned.isEmpty, let detail else { return }
        if detail.canScan {
            await enqueue(.scan(barcode: scanned, requestID: makeRequestID()), announce: true)
        } else {
            event = localMatch(detail, code: scanned)
        }
    }

    func loadShippers() async {
        guard !isLoadingShippers else { return }
        isLoadingShippers = true
        defer { isLoadingShippers = false }
        do {
            let dto = try await apiClient.fetchPalletReturnShippers(warehouseID: warehouseID, id: returnID)
            shippers = GoodsOutShipperList(
                shippers: dto.data.map {
                    GoodsOutShipperChoice(
                        id: $0.id,
                        name: $0.name ?? $0.code ?? "",
                        isAPI: $0.type == "api",
                        needsTracking: $0.needsTracking ?? false,
                        isLockedOut: $0.isLockedOut ?? false,
                        code: $0.code ?? ""
                    )
                }
            )
            shippersError = nil
        } catch where error.isCancellation {
            return
        } catch {
            shippersError = PalletDeliveryText.message(for: error)
        }
    }

    func downloadLabel(_ shipment: MobileReturnShipment) async -> Result<URL, GoodsOutProblem> {
        do {
            let data = try await apiClient.downloadPalletReturnLabel(warehouseID: warehouseID, id: returnID, shipmentID: shipment.id)
            guard data.starts(with: Data("%PDF".utf8)) else {
                return .failure(GoodsOutProblem(message: "Aiku sent something that is not a PDF."))
            }
            let folder = FileManager.default.temporaryDirectory.appending(path: "aiku-documents")
            try FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true)
            let file = folder.appending(path: "\(detail?.reference ?? "\(returnID)")-label-\(shipment.id).pdf")
            try data.write(to: file, options: .atomic)
            return .success(file)
        } catch {
            return .failure(GoodsOutProblem(message: PalletDeliveryText.message(for: error)))
        }
    }

    private func localMatch(_ detail: MobilePalletReturnDetail, code: String) -> PalletReturnEvent {
        if let line = detail.pallets.first(where: { $0.pallet?.matches(code) == true }) {
            selectedPalletID = line.pallet?.id
            return .lineFound(line.lineId)
        }
        let itemLines = detail.storedItems.flatMap(\.lines)
        if let pallet = itemLines.compactMap(\.pallet).first(where: { $0.matches(code) }) {
            selectedPalletID = pallet.id
            return .done("Pallet \(pallet.reference ?? code): now scan its items", .info)
        }
        guard let item = detail.storedItems.first(where: { $0.storedItem.matches(code) }) else {
            scanMissed()
            return .done("\(code) isn’t on this return", .info)
        }
        let line = item.lines.first { $0.pallet?.id == selectedPalletID && $0.hasActions }
            ?? item.lines.first { $0.can("pick") }
            ?? item.lines.first { $0.hasActions }
            ?? item.lines.first
        guard let line else {
            scanMissed()
            return .done("\(code) isn’t on this return", .info)
        }
        return .lineFound(line.lineId)
    }

    func retryUnsure() async {
        guard unsure != nil, !isDraining else { return }
        unsure = nil
        await drain()
    }

    @discardableResult
    private func act(_ write: MobilePalletReturnWrite, announce: Bool = true) async -> Bool {
        guard detail != nil, !isWorking else { return false }
        return await enqueue(write, announce: announce) == .saved
    }

    @discardableResult
    private func enqueue(_ write: MobilePalletReturnWrite, announce: Bool) async -> FulfilmentWriteOutcome? {
        let queued = QueuedReturnWrite(write: write, announce: announce)
        pending.append(queued)
        awaited.insert(queued.id)
        unsure = nil
        await drain()
        awaited.remove(queued.id)
        return outcomes.removeValue(forKey: queued.id)
    }

    private func drain() async {
        guard !isDraining else {
            queuedScans = waitingScans(behindHead: true)
            return
        }
        isDraining = true
        defer {
            isDraining = false
            queuedScans = waitingScans(behindHead: false)
        }
        while let next = pending.first {
            queuedScans = waitingScans(behindHead: true)
            let outcome = await run(next.write, announce: next.announce)
            if awaited.contains(next.id) {
                outcomes[next.id] = outcome
            }
            if outcome == .unsure {
                unsure = next.write
                return
            }
            pending.removeFirst()
        }
    }

    private func waitingScans(behindHead: Bool) -> Int {
        pending.dropFirst(behindHead ? 1 : 0).filter(\.write.isScan).count
    }

    private func run(_ write: MobilePalletReturnWrite, announce: Bool) async -> FulfilmentWriteOutcome {
        working = write
        clearErrors()
        do {
            let result = try await apiClient.writePalletReturn(warehouseID: warehouseID, id: returnID, write: write)
            working = nil
            detail = result.data
            loadError = nil
            if case .scan(let code, _) = write {
                event = scanEvent(code: code, result: result.result)
            } else if announce {
                let nothingChanged = result.result?.status == "nothing_changed"
                event = .done(result.result?.message ?? "Done", nothingChanged ? .info : .success)
            }
            return .saved
        } catch {
            working = nil
            let message = PalletDeliveryText.message(for: error)
            sheetError = message
            if write.isResentWhenUnsure, GoodsInText.isUnsure(error) {
                event = .done(message, .warn)
                return .unsure
            }
            if case .validation(_, _, let fields) = error as? APIError {
                fieldErrors = fields
            }
            event = .done(message, .danger)
            await reloadQuietly()
            return .refused
        }
    }

    private func scanEvent(code: String, result: MobilePalletReturnWriteResult.Result?) -> PalletReturnEvent {
        let status = result?.status ?? ""
        let message = result?.message
        if status != "picked", status != "done" {
            scanMissed()
        }
        switch status {
        case "picked", "done": return .done(message ?? "\(code) picked", .success)
        case "already_picked", "nothing_changed": return .done(message ?? "\(code) is already picked", .info)
        case "no_stock": return .done(message ?? "No stock of \(code) left to pick", .warn)
        case "wrong_state": return .done(message ?? "This return isn’t being picked", .warn)
        default:
            return .done(message ?? "\(code) isn’t on this return", .warn)
        }
    }

    private func reloadQuietly() async {
        if let fresh = try? await apiClient.fetchPalletReturn(warehouseID: warehouseID, id: returnID) {
            detail = fresh
        }
    }
}
