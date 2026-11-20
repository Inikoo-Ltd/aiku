import Foundation
import Observation

struct InventoryService: Sendable {
    var dashboard: @Sendable (Int) async throws -> MobileInventoryDashboard = { _ in throw APIError.unexpectedBody }
    var orgStocks: @Sendable (Int, MobileOrgStocksQuery) async throws -> MobileOrgStocksPage = { _, _ in throw APIError.unexpectedBody }
    var orgStock: @Sendable (Int, Int) async throws -> MobileOrgStockDetail = { _, _ in throw APIError.unexpectedBody }
    var movements: @Sendable (Int, Int, String?, Int) async throws -> MobileOrgStockMovementsPage = { _, _, _, _ in throw APIError.unexpectedBody }
    var reasons: @Sendable () async throws -> MobileStockMovementReasons = { throw APIError.unexpectedBody }
    var audit: @Sendable (Int, Int, [MobileOrgStockAuditCount]) async throws -> MobileOrgStockWriteResult = { _, _, _ in throw APIError.unexpectedBody }
    var move: @Sendable (Int, [MobileOrgStockMoveTarget]) async throws -> MobileOrgStockWriteResult = { _, _ in throw APIError.unexpectedBody }
    var consume: @Sendable (Int, MobileOrgStockConsumeBody) async throws -> MobileOrgStockWriteResult = { _, _ in throw APIError.unexpectedBody }
    var addLocation: @Sendable (Int, Int, MobileOrgStockAddLocationBody) async throws -> MobileOrgStockWriteResult = { _, _, _ in throw APIError.unexpectedBody }
    var removeLocations: @Sendable (Int, Int, [Int]) async throws -> MobileOrgStockWriteResult = { _, _, _ in throw APIError.unexpectedBody }
    var findLocations: @Sendable (Int, String) async throws -> [MobileLocation] = { _, _ in throw APIError.unexpectedBody }
    var families: @Sendable (Int, Int) async throws -> MobileOrgStockFamiliesPage = { _, _ in throw APIError.unexpectedBody }
    var scan: @Sendable (Int, String) async throws -> MobileScanResult = { _, _ in throw APIError.unexpectedBody }

    static let movementsPageSize = 50
    static let familyOptionsPageSize = 150

    static func live(_ client: APIClient) -> InventoryService {
        InventoryService(
            dashboard: { try await client.fetchInventoryDashboard(warehouseID: $0) },
            orgStocks: { try await client.fetchOrgStocks(warehouseID: $0, query: $1) },
            orgStock: { try await client.fetchOrgStock(warehouseID: $0, id: $1) },
            movements: { try await client.fetchOrgStockMovements(warehouseID: $0, id: $1, flow: $2, page: $3, perPage: movementsPageSize) },
            reasons: { try await client.fetchStockMovementReasons() },
            audit: { try await client.auditOrgStock(warehouseID: $0, id: $1, counts: $2) },
            move: { try await client.moveOrgStock(fromLinkID: $0, targets: $1) },
            consume: { try await client.consumeOrgStock(linkID: $0, body: $1) },
            addLocation: { try await client.addOrgStockLocation(warehouseID: $0, id: $1, body: $2) },
            removeLocations: { try await client.removeOrgStockLocations(warehouseID: $0, id: $1, linkIDs: $2) },
            findLocations: { warehouseID, search in
                try await client.fetchLocations(warehouseID: warehouseID, query: MobileLocationsQuery(search: search, perPage: 20)).data
            },
            families: { try await client.fetchOrgStockFamilies(warehouseID: $0, state: InventoryFamilyState.active.remoteValue, page: $1, perPage: familyOptionsPageSize) },
            scan: { try await client.scan(warehouseID: $0, code: $1) }
        )
    }
}

enum InventoryLive {
    static func message(for error: Error) -> String {
        if let failure = error as? InventoryStockError {
            return failure.message
        }
        if let apiError = error as? APIError {
            switch apiError {
            case .offline: return "You need an internet connection to do this. Check the connection and try again."
            case .unauthorized: return "Your session has ended. Sign in again."
            case .http(403): return "You need stock edit rights to change the stock."
            default: return apiError.localizedDescription
            }
        }
        if error is DecodingError {
            return "Aiku sent something the app could not read."
        }
        return error.localizedDescription
    }

    static func quantityText(_ value: Double?, label: String?) -> String {
        if let label = label?.trimmingCharacters(in: .whitespacesAndNewlines), !label.isEmpty {
            return label
        }
        guard let value else { return "0" }
        return value.formatted(.number.precision(.fractionLength(0...3)).locale(Locale(identifier: "en_GB")))
    }

    static func wholeQuantity(_ value: Double) -> Int {
        Int(value.rounded(.towardZero))
    }

    static func date(_ iso: String?) -> Date? {
        guard let iso, !iso.isEmpty else { return nil }
        let formatter = ISO8601DateFormatter()
        if let date = formatter.date(from: iso) { return date }
        formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        return formatter.date(from: iso)
    }

    static func dayText(_ iso: String?, timeZone: TimeZone = .current) -> String? {
        date(iso).map { $0.formatted(Date.FormatStyle(timeZone: timeZone).day(.twoDigits).month(.abbreviated).locale(Locale(identifier: "en_GB"))) }
    }

    static func momentText(_ iso: String?, timeZone: TimeZone = .current) -> String? {
        date(iso).map {
            $0.formatted(Date.FormatStyle(timeZone: timeZone).day(.defaultDigits).month(.abbreviated).hour(.twoDigits(amPM: .omitted)).minute(.twoDigits).locale(Locale(identifier: "en_GB")))
        }
    }

    static func dashboard(_ remote: MobileInventoryDashboard) -> InventoryDashboardSummary {
        InventoryDashboardSummary(
            currentSkos: remote.numberCurrentOrgStocks,
            abnormalities: remote.numberAbnormalities,
            replenishmentsWholesale: remote.replenishmentsWholesale,
            replenishmentsDropshipping: remote.replenishmentsDropshipping,
            lowStockAudits: remote.lowStockAudits,
            belowMinimum: remote.belowMinimumStock,
            withMinimum: remote.numberWithMinimumStock,
            negativeStocks: remote.negativeStocks,
            wrongWeights: remote.wrongWeights,
            withoutProduct: remote.withoutProducts,
            stockValueLabel: InventoryMoney.label(remote.stockValue, currencyCode: remote.currencyCode),
            utilisationPercent: remote.utilisation.map { Int(($0 * 100).rounded()) },
            usedLocations: max(0, remote.numberLocations - remote.numberEmptyLocations),
            totalLocations: remote.numberLocations
        )
    }

    static func listItem(_ row: MobileOrgStockRow, scope: InventorySkoScope) -> InventorySkoListItem {
        let isAbnormalities = scope == .abnormalities
        let name = row.name?.trimmingCharacters(in: .whitespacesAndNewlines)
        let location = isAbnormalities ? nil : listLocation(row)
        let detail: String?
        if isAbnormalities {
            detail = dayText(row.lastSeenAt).map { "Last seen \($0)" }
        } else if location != nil {
            detail = nil
        } else {
            let count = row.numberLocations ?? 0
            detail = count == 1 ? "1 location" : "\(count.grouped) locations"
        }
        let stateLabel = row.stateLabel?.isEmpty == false ? row.stateLabel : nil
        return InventorySkoListItem(
            skoID: row.id,
            reference: row.code,
            subtitle: [name?.isEmpty == false ? name : nil, detail].compactMap { $0 }.joined(separator: " · "),
            chip: row.state == InventorySkoState.active.rawValue ? nil : stateLabel.map { Chip($0, isAbnormalities || row.state == InventorySkoState.abnormality.rawValue ? .danger : .neutral) },
            quantityText: isAbnormalities ? nil : quantityText(row.quantityAvailable?.value, label: row.quantityAvailableLabel),
            location: location
        )
    }

    static func listLocation(_ row: MobileOrgStockRow) -> InventorySkoListLocation? {
        guard let place = row.location, let linkID = row.locationOrgStockId else { return nil }
        let quantity = row.locationQuantity?.value
        return InventorySkoListLocation(
            locationOrgStockID: linkID,
            code: place.code,
            quantityText: quantity.map { $0 < 0 ? "−\(plainQuantityText(abs($0)))" : plainQuantityText($0) },
            isNegative: (quantity ?? 0) < 0
        )
    }

    static func plainQuantityText(_ value: Double) -> String {
        value.formatted(.number.grouping(.never).precision(.fractionLength(0...2)).locale(Locale(identifier: "en_GB")))
    }

    static func levels(_ link: MobileOrgStockDetail.Link) -> String? {
        let minimum = link.minStock?.value.map { quantityText($0, label: nil) }
        let maximum = link.maxStock?.value.map { quantityText($0, label: nil) }
        let replenish = link.replenishmentStock?.value.map { quantityText($0, label: nil) }
        switch (minimum, maximum) {
        case let (min?, max?): return "min \(min) · max \(max)"
        case let (min?, nil): return "min \(min)"
        case let (nil, max?): return "max \(max)"
        default: return replenish.map { "replenish \($0)" }
        }
    }

    static func location(_ link: MobileOrgStockDetail.Link) -> InventorySkoLocation {
        let type = InventoryLocationType(rawValue: link.type ?? "") ?? .storing
        let channel: InventoryChannel? = link.isDefaultWholesale ? .wholesale : link.isDefaultDropshipping ? .dropshipping : nil
        return InventorySkoLocation(
            code: link.location.code,
            type: type,
            channel: type == .picking ? channel : nil,
            quantity: wholeQuantity(link.quantity.value),
            minimum: link.minStock?.value.map(wholeQuantity),
            maximum: link.maxStock?.value.map(wholeQuantity),
            replenish: link.replenishmentStock?.value.map(wholeQuantity),
            lastAudited: dayText(link.auditedAt),
            link: InventoryLocationLink(
                linkID: link.locationOrgStockId,
                locationID: link.location.id,
                exactQuantity: link.quantity.value,
                quantityLabel: quantityText(link.quantity.value, label: link.quantityLabel),
                isDefaultWholesale: link.isDefaultWholesale,
                isDefaultDropshipping: link.isDefaultDropshipping,
                levels: levels(link)
            )
        )
    }

    static func sko(_ remote: MobileOrgStockDetail, canEditStocks fallback: Bool) -> InventorySko {
        let state = InventorySkoState(rawValue: remote.state) ?? .active
        let familyTitle = remote.family.map { family in
            [family.code, family.name].compactMap { $0?.trimmingCharacters(in: .whitespacesAndNewlines) }.filter { !$0.isEmpty }.joined(separator: " · ")
        }
        let stock = remote.quantityInLocations?.value ?? 0
        return InventorySko(
            reference: remote.code,
            name: remote.name ?? remote.code,
            familyCode: remote.family?.code,
            state: state,
            supplierCode: nil,
            unitsPerSko: remote.packedIn?.value.map(wholeQuantity) ?? 0,
            barcode: nonEmpty(remote.barcode),
            unitBarcode: nonEmpty(remote.unitBarcode),
            pickerNote: nonEmpty(remote.noteToPickers),
            reserved: wholeQuantity(remote.quantityReserved?.value ?? 0),
            toBePicked: wholeQuantity(remote.quantityToBePicked?.value ?? 0),
            coverDays: remote.cover?.days,
            isOnDemand: remote.isOnDemand ?? false,
            unitValue: 0,
            criticalAt: 0,
            lowAt: 0,
            excessAt: 0,
            abnormalityNote: nil,
            locations: remote.locations.map(location),
            packerNote: nonEmpty(remote.noteToPackers),
            live: InventorySkoLiveFacts(
                id: remote.id,
                stateLabel: nonEmpty(remote.stateLabel) ?? state.title,
                familyTitle: familyTitle?.isEmpty == false ? familyTitle : nil,
                stockLabel: quantityText(stock, label: remote.quantityInLocationsLabel),
                stockIsNegative: stock < 0,
                reservedLabel: quantityText(remote.quantityReserved?.value, label: remote.quantityReservedLabel),
                toBePickedLabel: quantityText(remote.quantityToBePicked?.value, label: remote.quantityToBePickedLabel),
                canEditStocks: remote.can?.editStocks ?? fallback
            )
        )
    }

    static func movement(_ remote: MobileOrgStockMovement, skoCode: String) -> InventoryMovementDisplay {
        let quantity = remote.quantity?.value ?? 0
        let tone: Tone = switch remote.flow {
        case "in": .success
        case "audit": quantity < 0 ? .warn : .success
        default: .neutral
        }
        let type = nonEmpty(remote.typeLabel) ?? remote.type
        let running = remote.runningQuantityOrgStock?.value.map { "running \(quantityText($0, label: nil))" }
        let parts: [String?] = [
            nonEmpty(remote.reasonLabel),
            nonEmpty(remote.parentReference),
            nonEmpty(remote.note),
            nonEmpty(remote.user?.name),
            momentText(remote.date),
            running,
        ]
        return InventoryMovementDisplay(
            id: "\(remote.id)",
            title: remote.location?.code ?? skoCode,
            subtitle: parts.compactMap { $0 }.joined(separator: " · "),
            chip: Chip("\(type) \(signedQuantity(quantity, label: remote.quantityLabel))", tone)
        )
    }

    static func signedQuantity(_ value: Double, label: String?) -> String {
        let magnitude = quantityText(abs(value), label: label?.replacingOccurrences(of: "-", with: ""))
        return value > 0 ? "+\(magnitude)" : value < 0 ? "−\(magnitude)" : "0"
    }

    static func auditCounts(for sko: InventorySko, counted: [String: Int], reasons: [String: String]) -> [MobileOrgStockAuditCount] {
        sko.locations.compactMap { location in
            guard let link = location.link else { return nil }
            let count = counted[location.code] ?? max(0, location.quantity)
            if count == location.quantity {
                return MobileOrgStockAuditCount(locationOrgStockId: link.linkID, quantity: link.exactQuantity)
            }
            return MobileOrgStockAuditCount(locationOrgStockId: link.linkID, quantity: Double(count), reason: reasons[location.code])
        }
    }

    static func reasonChoices(_ reasons: MobileStockMovementReasons, change: Int) -> [InventoryReasonChoice] {
        let options = change < 0 ? reasons.decrease : change > 0 ? reasons.increase : []
        return options.map { InventoryReasonChoice(id: $0.value, title: $0.label) }
    }

    static func locationOption(_ location: MobileLocation) -> InventoryLocationOption {
        let area = location.area.flatMap { area in
            [area.name, area.code].compactMap { $0?.trimmingCharacters(in: .whitespacesAndNewlines) }.first { !$0.isEmpty }
        }
        let slots = location.numberOrgStockSlots
        let status: String? = location.status == "operational" ? nil : location.status.replacingOccurrences(of: "_", with: " ").capitalized
        let contents: String = slots == 0 ? "empty" : slots == 1 ? "1 SKO" : "\(slots.grouped) SKOs"
        let parts: [String?] = [area.map { "Area \($0)" }, contents, status]
        return InventoryLocationOption(code: location.code, detail: parts.compactMap { $0 }.joined(separator: " · "), locationID: location.id)
    }

    static func nonEmpty(_ text: String?) -> String? {
        guard let trimmed = text?.trimmingCharacters(in: .whitespacesAndNewlines), !trimmed.isEmpty else { return nil }
        return trimmed
    }

    static func resolveID(reference: String, warehouseID: Int, service: InventoryService) async throws -> Int? {
        let query = MobileOrgStocksQuery(bucket: InventorySkoScope.all.bucket, search: reference, perPage: 20)
        let page = try await service.orgStocks(warehouseID, query)
        return page.data.first { $0.code.caseInsensitiveCompare(reference.trimmingCharacters(in: .whitespacesAndNewlines)) == .orderedSame }?.id
    }
}

@MainActor
@Observable
final class InventoryDashboardFeed {
    static let shared = InventoryDashboardFeed()

    private(set) var summary: InventoryDashboardSummary?
    private(set) var isLoading = false
    private(set) var loadError: String?
    private(set) var loadedWarehouseID: Int?
    @ObservationIgnored private let service: InventoryService

    init(service: InventoryService = .live(.shared)) {
        self.service = service
    }

    func load(warehouseID: Int) async {
        if loadedWarehouseID != warehouseID {
            summary = nil
        }
        isLoading = true
        defer { isLoading = false }
        do {
            summary = InventoryLive.dashboard(try await service.dashboard(warehouseID))
            loadedWarehouseID = warehouseID
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            loadError = InventoryLive.message(for: error)
        }
    }
}

struct InventorySkoListRequest: Hashable, Sendable {
    var warehouseID: Int
    var scope: InventorySkoScope
    var search: String
    var filter: InventoryLiveSkoFilter

    func query(page: Int, perPage: Int) -> MobileOrgStocksQuery {
        filter.query(scope: scope, search: scope == .abnormalities ? "" : search, page: page, perPage: perPage)
    }
}

struct InventoryFamilyOption: Identifiable, Hashable, Sendable {
    let id: Int
    let code: String
    let name: String

    var title: String { name.isEmpty || name == code ? code : "\(code) · \(name)" }
}

@MainActor
@Observable
final class InventorySkoListFeed {
    static let pageSize = 50
    static let maxFamilyPages = 10

    var filter = InventoryLiveSkoFilter()
    private(set) var items: [InventorySkoListItem] = []
    private(set) var counts: MobileOrgStocksPage.Counts?
    private(set) var total: Int?
    private(set) var currentPage = 0
    private(set) var lastPage = 0
    private(set) var isLoading = false
    private(set) var isLoadingMore = false
    private(set) var loadError: String?
    private(set) var loadedRequest: InventorySkoListRequest?
    private(set) var familyOptions: [InventoryFamilyOption]?
    @ObservationIgnored private let service: InventoryService

    init(service: InventoryService = .live(.shared)) {
        self.service = service
    }

    var hasMorePages: Bool {
        currentPage > 0 && currentPage < lastPage
    }

    func reload(_ request: InventorySkoListRequest) async {
        isLoading = true
        defer { isLoading = false }
        do {
            let page = try await service.orgStocks(request.warehouseID, request.query(page: 1, perPage: Self.pageSize))
            items = page.data.map { InventoryLive.listItem($0, scope: request.scope) }
            apply(page)
            loadedRequest = request
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            loadError = InventoryLive.message(for: error)
        }
    }

    func loadNextPage() async {
        guard hasMorePages, !isLoading, !isLoadingMore, let request = loadedRequest else { return }
        isLoadingMore = true
        defer { isLoadingMore = false }
        do {
            let page = try await service.orgStocks(request.warehouseID, request.query(page: currentPage + 1, perPage: Self.pageSize))
            guard loadedRequest == request else { return }
            let known = Set(items.map(\.id))
            items += page.data.map { InventoryLive.listItem($0, scope: request.scope) }.filter { !known.contains($0.id) }
            apply(page)
        } catch where error.isCancellation {
            return
        } catch {
            loadError = InventoryLive.message(for: error)
        }
    }

    func total(for request: InventorySkoListRequest) async -> Int? {
        if request == loadedRequest, let total {
            return total
        }
        return try? await service.orgStocks(request.warehouseID, request.query(page: 1, perPage: 1)).meta.total
    }

    func loadFamilyOptions(warehouseID: Int) async {
        guard familyOptions == nil else { return }
        var options: [InventoryFamilyOption] = []
        var page = 1
        var lastPage = 1
        while page <= lastPage, page <= Self.maxFamilyPages {
            guard let result = try? await service.families(warehouseID, page) else { return }
            options += result.data.compactMap { family in
                family.id.map { InventoryFamilyOption(id: $0, code: family.code, name: family.name ?? family.code) }
            }
            lastPage = result.meta.lastPage
            page += 1
        }
        familyOptions = options
    }

    func skoID(scanned code: String, warehouseID: Int) async -> Int? {
        let trimmed = code.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty, let result = try? await service.scan(warehouseID, trimmed) else { return nil }
        return result.scanType == .sko ? result.sko?.id : nil
    }

    private func apply(_ page: MobileOrgStocksPage) {
        total = page.meta.total
        currentPage = page.meta.currentPage
        lastPage = page.meta.lastPage
        if let pageCounts = page.counts {
            counts = pageCounts
        }
    }
}

@MainActor
@Observable
final class InventorySkoFeed {
    enum Target: Hashable, Sendable {
        case id(Int)
        case reference(String)
    }

    let warehouseID: Int
    let target: Target
    let canEditStocksFallback: Bool
    private(set) var orgStockID: Int?
    private(set) var sko: InventorySko?
    private(set) var isLoading = false
    private(set) var isNotFound = false
    private(set) var loadError: String?
    private(set) var reasons: MobileStockMovementReasons?
    private(set) var reasonsError: String?
    @ObservationIgnored private let service: InventoryService

    init(warehouseID: Int, target: Target, canEditStocks: Bool, service: InventoryService = .live(.shared)) {
        self.warehouseID = warehouseID
        self.target = target
        self.canEditStocksFallback = canEditStocks
        self.service = service
        if case .id(let id) = target {
            orgStockID = id
        }
    }

    var title: String {
        if let sko { return sko.reference }
        if case .reference(let reference) = target { return reference }
        return "SKO"
    }

    func load() async {
        isLoading = true
        defer { isLoading = false }
        do {
            guard let id = try await resolvedID() else {
                isNotFound = true
                return
            }
            apply(try await service.orgStock(warehouseID, id))
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch APIError.http(statusCode: 404) {
            isNotFound = true
        } catch {
            loadError = InventoryLive.message(for: error)
        }
    }

    func loadReasons() async {
        guard reasons == nil else { return }
        do {
            reasons = try await service.reasons()
            reasonsError = nil
        } catch where error.isCancellation {
            return
        } catch {
            reasonsError = InventoryLive.message(for: error)
        }
    }

    func reasonChoices(change: Int) -> [InventoryReasonChoice] {
        reasons.map { InventoryLive.reasonChoices($0, change: change) } ?? []
    }

    func audit(counted: [String: Int], reasons chosen: [String: String]) async throws -> String {
        let (sko, id) = try current()
        let counts = InventoryLive.auditCounts(for: sko, counted: counted, reasons: chosen)
        guard !counts.isEmpty else { throw InventoryStockError.nothingSelected }
        return try finish(await service.audit(warehouseID, id, counts), fallback: "Audit saved")
    }

    func move(from source: String, to destination: String, quantity: Int) async throws -> String {
        let (sko, _) = try current()
        guard source != destination else { throw InventoryStockError.sameLocation }
        guard quantity > 0 else { throw InventoryStockError.quantityMustBePositive }
        let from = try link(source, in: sko)
        let to = try link(destination, in: sko)
        let target = MobileOrgStockMoveTarget(locationOrgStockId: to.linkID, quantity: Double(quantity))
        return try finish(await service.move(from.linkID, [target]), fallback: "Stock moved")
    }

    func consume(location code: String, quantity: Int, note: String) async throws -> String {
        let (sko, _) = try current()
        guard quantity > 0 else { throw InventoryStockError.quantityMustBePositive }
        let link = try link(code, in: sko)
        let body = MobileOrgStockConsumeBody(quantity: Double(quantity), note: InventoryLive.nonEmpty(note))
        return try finish(await service.consume(link.linkID, body), fallback: "Consumption recorded")
    }

    func addLocation(_ option: InventoryLocationOption, type: InventoryLocationType) async throws -> String {
        let (sko, id) = try current()
        guard let locationID = option.locationID else { throw InventoryStockError.nothingSelected }
        guard !sko.locations.contains(where: { $0.link?.locationID == locationID }) else {
            throw InventoryStockError.locationAlreadyAssigned(option.code)
        }
        let body = MobileOrgStockAddLocationBody(locationId: locationID, type: type.rawValue)
        return try finish(await service.addLocation(warehouseID, id, body), fallback: "Location added")
    }

    func removeLocations(_ codes: Set<String>) async throws -> String {
        let (sko, id) = try current()
        guard !codes.isEmpty else { throw InventoryStockError.nothingSelected }
        let linkIDs = try codes.sorted().map { try link($0, in: sko).linkID }
        let removed = codes.count == 1 ? "1 location removed" : "\(codes.count) locations removed"
        return try finish(await service.removeLocations(warehouseID, id, linkIDs), fallback: removed)
    }

    func locationOptions(search: String) async throws -> [InventoryLocationOption] {
        let query = search.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !query.isEmpty else { return [] }
        let taken = Set(sko?.locations.compactMap { $0.link?.locationID } ?? [])
        return try await service.findLocations(warehouseID, query)
            .filter { $0.allowStocks && !taken.contains($0.id) }
            .map(InventoryLive.locationOption)
    }

    private func resolvedID() async throws -> Int? {
        if let orgStockID { return orgStockID }
        guard case .reference(let reference) = target else { return nil }
        let id = try await InventoryLive.resolveID(reference: reference, warehouseID: warehouseID, service: service)
        orgStockID = id
        return id
    }

    private func current() throws -> (InventorySko, Int) {
        guard let sko, let orgStockID else { throw InventoryStockError.nothingSelected }
        return (sko, orgStockID)
    }

    private func link(_ code: String, in sko: InventorySko) throws -> InventoryLocationLink {
        guard let link = sko.location(code)?.link else { throw InventoryStockError.unknownLocation(code) }
        return link
    }

    private func apply(_ detail: MobileOrgStockDetail) {
        orgStockID = detail.id
        sko = InventoryLive.sko(detail, canEditStocks: canEditStocksFallback)
        isNotFound = false
    }

    private func finish(_ result: MobileOrgStockWriteResult, fallback: String) -> String {
        apply(result.data)
        return InventoryLive.nonEmpty(result.result?.message) ?? fallback
    }
}

@MainActor
@Observable
final class InventoryMovementsFeed {
    let warehouseID: Int
    let target: InventorySkoFeed.Target
    private(set) var orgStockID: Int?
    private(set) var skoCode: String
    private(set) var items: [InventoryMovementDisplay] = []
    private(set) var total: Int?
    private(set) var currentPage = 0
    private(set) var lastPage = 0
    private(set) var isLoading = false
    private(set) var isLoadingMore = false
    private(set) var isNotFound = false
    private(set) var loadError: String?
    private(set) var loadedFilter: InventoryHistoryFilter?
    @ObservationIgnored private let service: InventoryService

    init(warehouseID: Int, target: InventorySkoFeed.Target, skoCode: String, service: InventoryService = .live(.shared)) {
        self.warehouseID = warehouseID
        self.target = target
        self.skoCode = skoCode
        self.service = service
        if case .id(let id) = target {
            orgStockID = id
        }
    }

    var hasMorePages: Bool {
        currentPage > 0 && currentPage < lastPage
    }

    func reload(_ filter: InventoryHistoryFilter) async {
        isLoading = true
        defer { isLoading = false }
        do {
            guard let id = try await resolvedID() else {
                isNotFound = true
                return
            }
            let page = try await service.movements(warehouseID, id, filter.flow, 1)
            items = page.data.map { InventoryLive.movement($0, skoCode: skoCode) }
            apply(page.meta)
            loadedFilter = filter
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch APIError.http(statusCode: 404) {
            isNotFound = true
        } catch {
            loadError = InventoryLive.message(for: error)
        }
    }

    func loadNextPage() async {
        guard hasMorePages, !isLoading, !isLoadingMore, let filter = loadedFilter, let id = orgStockID else { return }
        isLoadingMore = true
        defer { isLoadingMore = false }
        do {
            let page = try await service.movements(warehouseID, id, filter.flow, currentPage + 1)
            guard loadedFilter == filter else { return }
            let known = Set(items.map(\.id))
            items += page.data.map { InventoryLive.movement($0, skoCode: skoCode) }.filter { !known.contains($0.id) }
            apply(page.meta)
        } catch where error.isCancellation {
            return
        } catch {
            loadError = InventoryLive.message(for: error)
        }
    }

    private func resolvedID() async throws -> Int? {
        if let orgStockID { return orgStockID }
        guard case .reference(let reference) = target else { return nil }
        let id = try await InventoryLive.resolveID(reference: reference, warehouseID: warehouseID, service: service)
        orgStockID = id
        return id
    }

    private func apply(_ meta: MobileLocationsPage.Meta) {
        total = meta.total
        currentPage = meta.currentPage
        lastPage = meta.lastPage
    }
}
