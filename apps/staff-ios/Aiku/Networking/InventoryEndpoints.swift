import Foundation

struct MobileOrgStockFamily: Decodable, Sendable, Equatable {
    let id: Int?
    let state: String
    let code: String
    let name: String?
    let numberCurrentOrgStocks: Int?
    let currencyCode: String?
    let stockValue: OptionalFlexibleDouble?
    let stockCover: OptionalFlexibleDouble?
}

struct MobileOrgStockFamiliesPage: Decodable, Sendable, Equatable {
    struct Counts: Decodable, Sendable, Equatable {
        let inProcess: Int
        let active: Int
        let discontinuing: Int
        let discontinued: Int
    }

    let data: [MobileOrgStockFamily]
    let meta: MobileLocationsPage.Meta
    let counts: Counts
}

struct MobileFamilySko: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let code: String
    let name: String?
    let state: String
    let quantityAvailable: OptionalFlexibleDouble?
    let quantityAvailableLabel: String?
    let quantityInLocations: OptionalFlexibleDouble?
    let quantityInLocationsLabel: String?
    let stockValue: OptionalFlexibleDouble?
}

struct MobileFamilyStatusCount: Decodable, Sendable, Equatable {
    let key: String
    let label: String
    let count: Int

    init(key: String, label: String, count: Int) {
        self.key = key
        self.label = label
        self.count = count
    }

    private enum CodingKeys: String, CodingKey {
        case key, label, count
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        key = (try? container.decodeIfPresent(String.self, forKey: .key)) ?? ""
        label = (try? container.decodeIfPresent(String.self, forKey: .label)) ?? ""
        count = (try? container.decodeIfPresent(FlexibleInt.self, forKey: .count))?.value ?? 0
    }
}

struct MobileFamilySkosPage: Decodable, Sendable, Equatable {
    struct Family: Decodable, Sendable, Equatable {
        let id: Int
        let code: String
        let name: String?
        let state: String
        let numberCurrentOrgStocks: Int
        let stockValue: Double
        let currencyCode: String?
        let skoStatus: [MobileFamilyStatusCount]
        let quantityStatus: [MobileFamilyStatusCount]

        private enum CodingKeys: String, CodingKey {
            case id, code, name, state, numberCurrentOrgStocks, stockValue, currencyCode, skoStatus, quantityStatus
        }

        init(from decoder: Decoder) throws {
            let container = try decoder.container(keyedBy: CodingKeys.self)
            id = try container.decode(Int.self, forKey: .id)
            code = try container.decode(String.self, forKey: .code)
            name = try container.decodeIfPresent(String.self, forKey: .name)
            state = try container.decode(String.self, forKey: .state)
            numberCurrentOrgStocks = try container.decode(Int.self, forKey: .numberCurrentOrgStocks)
            stockValue = try container.decode(Double.self, forKey: .stockValue)
            currencyCode = try container.decodeIfPresent(String.self, forKey: .currencyCode)
            skoStatus = (try? container.decodeIfPresent([MobileFamilyStatusCount].self, forKey: .skoStatus)) ?? []
            quantityStatus = (try? container.decodeIfPresent([MobileFamilyStatusCount].self, forKey: .quantityStatus)) ?? []
        }
    }

    let family: Family
    let data: [MobileFamilySko]
    let meta: MobileLocationsPage.Meta
}

extension Endpoint {
    static func mobileOrgStockFamilies(warehouseID: Int, state: String, search: String, page: Int, perPage: Int) -> Endpoint {
        var items = [
            URLQueryItem(name: "state", value: state),
            URLQueryItem(name: "page", value: "\(page)"),
            URLQueryItem(name: "per_page", value: "\(perPage)"),
        ]
        let trimmed = search.trimmingCharacters(in: .whitespacesAndNewlines)
        if !trimmed.isEmpty {
            items.append(URLQueryItem(name: "q", value: trimmed))
        }
        return Endpoint(path: "mobile/warehouses/\(warehouseID)/org-stock-families", queryItems: items)
    }

    static func mobileOrgStockFamilySkos(warehouseID: Int, familyID: Int, page: Int, perPage: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/org-stock-families/\(familyID)/skos", queryItems: [
            URLQueryItem(name: "page", value: "\(page)"),
            URLQueryItem(name: "per_page", value: "\(perPage)"),
        ])
    }
}

extension APIClient {
    func fetchOrgStockFamilies(warehouseID: Int, state: String, search: String = "", page: Int = 1, perPage: Int = 50) async throws -> MobileOrgStockFamiliesPage {
        try await send(.mobileOrgStockFamilies(warehouseID: warehouseID, state: state, search: search, page: page, perPage: perPage), as: MobileOrgStockFamiliesPage.self)
    }

    func fetchOrgStockFamilySkos(warehouseID: Int, familyID: Int, page: Int = 1, perPage: Int = 50) async throws -> MobileFamilySkosPage {
        try await send(.mobileOrgStockFamilySkos(warehouseID: warehouseID, familyID: familyID, page: page, perPage: perPage), as: MobileFamilySkosPage.self)
    }
}

struct MobileInventoryDashboard: Decodable, Sendable, Equatable {
    let currencyCode: String?
    let stockValue: Double
    let numberLocations: Int
    let numberEmptyLocations: Int
    let utilisation: Double?
    let numberCurrentOrgStocks: Int
    let numberAbnormalities: Int
    let lowStockAudits: Int
    let replenishmentsWholesale: Int
    let replenishmentsDropshipping: Int
    let belowMinimumStock: Int
    let numberWithMinimumStock: Int
    let negativeStocks: Int
    let wrongWeights: Int
    let withoutProducts: Int
}

struct MobileOrgStockFamilyReference: Decodable, Sendable, Equatable {
    let id: Int
    let code: String
    var name: String? = nil
}

struct MobileOrgStockRow: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let code: String
    var name: String? = nil
    let state: String
    var stateLabel: String? = nil
    var family: MobileOrgStockFamilyReference? = nil
    var imageUrl: String? = nil
    var quantityAvailable: OptionalFlexibleDouble? = nil
    var quantityAvailableLabel: String? = nil
    var quantityInLocations: OptionalFlexibleDouble? = nil
    var quantityInLocationsLabel: String? = nil
    var stockValue: OptionalFlexibleDouble? = nil
    var numberLocations: Int? = nil
    var lastSeenAt: String? = nil
    var locationOrgStockId: Int? = nil
    var location: MobileOrgStockDetail.LocationReference? = nil
    var locationQuantity: OptionalFlexibleDouble? = nil
    var locationQuantityLabel: String? = nil
}

struct MobileOrgStocksPage: Decodable, Sendable, Equatable {
    struct Counts: Decodable, Sendable, Equatable {
        let current: Int
        let discontinued: Int
        let abnormality: Int
        let all: Int
        var belowMinimum: Int?
        var negative: Int?
    }

    let data: [MobileOrgStockRow]
    let meta: MobileLocationsPage.Meta
    var counts: Counts? = nil
    var currencyCode: String? = nil
}

struct MobileOrgStocksQuery: Hashable, Sendable {
    var bucket = "current"
    var familyID: Int?
    var search = ""
    var sort: String?
    var page = 1
    var perPage = 50

    var queryItems: [URLQueryItem] {
        var items = [
            URLQueryItem(name: "bucket", value: bucket),
            URLQueryItem(name: "page", value: "\(page)"),
            URLQueryItem(name: "per_page", value: "\(perPage)"),
        ]
        if let familyID {
            items.append(URLQueryItem(name: "family_id", value: "\(familyID)"))
        }
        let trimmed = search.trimmingCharacters(in: .whitespacesAndNewlines)
        if !trimmed.isEmpty {
            items.append(URLQueryItem(name: "q", value: trimmed))
        }
        if let sort {
            items.append(URLQueryItem(name: "sort", value: sort))
        }
        return items
    }
}

struct MobileOrgStockDetail: Decodable, Sendable, Equatable {
    struct Cover: Decodable, Sendable, Equatable {
        var days: Int? = nil
        var outAt: String? = nil
        var dailyUsage: Double? = nil
    }

    struct LocationReference: Decodable, Sendable, Equatable {
        let id: Int
        let code: String
    }

    struct Area: Decodable, Sendable, Equatable {
        let id: Int
        var code: String? = nil
        var name: String? = nil
    }

    struct Location: Decodable, Sendable, Equatable {
        let id: Int
        let code: String
        var status: String? = nil
        var allowStocks: Bool? = nil
        var area: Area? = nil
    }

    struct Link: Decodable, Sendable, Equatable {
        let locationOrgStockId: Int
        var type: String? = nil
        var quantity: FlexibleDouble
        var quantityLabel: String? = nil
        var minStock: OptionalFlexibleDouble? = nil
        var maxStock: OptionalFlexibleDouble? = nil
        var replenishmentStock: OptionalFlexibleDouble? = nil
        var isDefaultWholesale = false
        var isDefaultDropshipping = false
        var auditedAt: String? = nil
        let location: Location
        var version: Int? = nil

        private enum CodingKeys: String, CodingKey {
            case locationOrgStockId, type, quantity, quantityLabel, minStock, maxStock, replenishmentStock, isDefaultWholesale, isDefaultDropshipping, auditedAt, location, version
        }

        init(locationOrgStockId: Int, type: String?, quantity: Double, location: Location) {
            self.locationOrgStockId = locationOrgStockId
            self.type = type
            self.quantity = FlexibleDouble(quantity)
            self.location = location
        }

        init(from decoder: Decoder) throws {
            let container = try decoder.container(keyedBy: CodingKeys.self)
            locationOrgStockId = try container.decode(Int.self, forKey: .locationOrgStockId)
            type = try container.decodeIfPresent(String.self, forKey: .type)
            quantity = try container.decode(FlexibleDouble.self, forKey: .quantity)
            quantityLabel = try container.decodeIfPresent(String.self, forKey: .quantityLabel)
            minStock = try container.decodeIfPresent(OptionalFlexibleDouble.self, forKey: .minStock)
            maxStock = try container.decodeIfPresent(OptionalFlexibleDouble.self, forKey: .maxStock)
            replenishmentStock = try container.decodeIfPresent(OptionalFlexibleDouble.self, forKey: .replenishmentStock)
            isDefaultWholesale = try container.decodeIfPresent(Bool.self, forKey: .isDefaultWholesale) ?? false
            isDefaultDropshipping = try container.decodeIfPresent(Bool.self, forKey: .isDefaultDropshipping) ?? false
            auditedAt = try container.decodeIfPresent(String.self, forKey: .auditedAt)
            location = try container.decode(Location.self, forKey: .location)
            version = try? container.decodeIfPresent(Int.self, forKey: .version)
        }
    }

    struct Abilities: Decodable, Sendable, Equatable {
        let editStocks: Bool
        let editLocations: Bool
    }

    let id: Int
    let code: String
    var name: String? = nil
    let state: String
    var stateLabel: String? = nil
    var family: MobileOrgStockFamilyReference? = nil
    var imageUrl: String? = nil
    var barcode: String? = nil
    var unitBarcode: String? = nil
    var packedIn: OptionalFlexibleDouble? = nil
    var isOnDemand: Bool? = nil
    var noteToPickers: String? = nil
    var noteToPackers: String? = nil
    var currencyCode: String? = nil
    var stockValue: OptionalFlexibleDouble? = nil
    var quantityInLocations: OptionalFlexibleDouble? = nil
    var quantityInLocationsLabel: String? = nil
    var quantityReserved: OptionalFlexibleDouble? = nil
    var quantityReservedLabel: String? = nil
    var quantityToBePicked: OptionalFlexibleDouble? = nil
    var quantityToBePickedLabel: String? = nil
    var cover: Cover? = nil
    var pickingLocation: LocationReference? = nil
    var pickingDropshippingLocation: LocationReference? = nil
    var locations: [Link] = []
    var can: Abilities? = nil
}

struct MobileOrgStockWriteResult: Decodable, Sendable, Equatable {
    struct Result: Decodable, Sendable, Equatable {
        let status: String
        var message: String? = nil
    }

    let data: MobileOrgStockDetail
    var result: Result? = nil
}

struct MobileOrgStockMovement: Decodable, Sendable, Equatable, Identifiable {
    struct User: Decodable, Sendable, Equatable {
        let id: Int
        var name: String? = nil
    }

    let id: Int
    var date: String? = nil
    let type: String
    var typeLabel: String? = nil
    var flow: String? = nil
    var quantity: OptionalFlexibleDouble? = nil
    var quantityLabel: String? = nil
    var runningQuantityOrgStock: OptionalFlexibleDouble? = nil
    var runningQuantityOrgStockLabel: String? = nil
    var location: MobileOrgStockDetail.LocationReference? = nil
    var reason: String? = nil
    var reasonLabel: String? = nil
    var note: String? = nil
    var parentType: String? = nil
    var parentReference: String? = nil
    var user: User? = nil
}

struct MobileOrgStockMovementsPage: Decodable, Sendable, Equatable {
    let data: [MobileOrgStockMovement]
    let meta: MobileLocationsPage.Meta
}

struct MobileStockMovementReasonOption: Sendable, Equatable, Hashable, Identifiable {
    let value: String
    let label: String

    var id: String { value }
}

struct MobileStockMovementReasons: Sendable, Equatable {
    let increase: [MobileStockMovementReasonOption]
    let decrease: [MobileStockMovementReasonOption]

    static let webOrder = [
        "count_gain", "count_short", "recount", "damaged", "expired", "destroyed", "defective", "lost", "theft",
        "showroom", "sample", "photo", "trade_show", "influencer", "testing", "qc", "training", "office_use",
        "internal_use", "warranty", "goodwill", "supplier_short", "supplier_over", "supplier_damage", "pick_error",
        "transfer", "bin_error", "return_to_stock", "barcode", "uom", "data_fix", "manual", "empty_location",
    ]

    static func decode(from data: Data) throws -> MobileStockMovementReasons {
        let envelope = try JSONDecoder().decode(RawEnvelope.self, from: data)
        return MobileStockMovementReasons(
            increase: ordered(envelope.data["increase"] ?? [:]),
            decrease: ordered(envelope.data["decrease"] ?? [:])
        )
    }

    static func ordered(_ labels: [String: String]) -> [MobileStockMovementReasonOption] {
        labels
            .map { MobileStockMovementReasonOption(value: $0.key, label: $0.value) }
            .sorted { lhs, rhs in
                let left = webOrder.firstIndex(of: lhs.value) ?? Int.max
                let right = webOrder.firstIndex(of: rhs.value) ?? Int.max
                return left == right ? lhs.label < rhs.label : left < right
            }
    }

    private struct RawEnvelope: Decodable {
        let data: [String: [String: String]]
    }
}

struct MobileOrgStockAuditCount: Encodable, Sendable, Equatable {
    let locationOrgStockId: Int
    let quantity: Double
    var reason: String? = nil
    var note: String? = nil
}

struct MobileOrgStockAuditBody: Encodable, Sendable {
    let counts: [MobileOrgStockAuditCount]
    var clientRequestId: String? = nil
}

struct MobileOrgStockMoveTarget: Encodable, Sendable, Equatable {
    let locationOrgStockId: Int
    let quantity: Double
}

struct MobileOrgStockMoveBody: Encodable, Sendable {
    let targets: [MobileOrgStockMoveTarget]
    var clientRequestId: String? = nil
}

struct MobileOrgStockConsumeBody: Encodable, Sendable, Equatable {
    let quantity: Double
    var note: String? = nil
}

struct MobileOrgStockAddLocationBody: Encodable, Sendable, Equatable {
    let locationId: Int
    let type: String
}

struct MobileOrgStockRemoveLocationsBody: Encodable, Sendable, Equatable {
    let locationOrgStockIds: [Int]
}

extension Endpoint {
    static func mobileInventoryDashboard(warehouseID: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/inventory-dashboard")
    }

    static func mobileOrgStocks(warehouseID: Int, query: MobileOrgStocksQuery) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/org-stocks", queryItems: query.queryItems)
    }

    static func mobileOrgStock(warehouseID: Int, id: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/org-stocks/\(id)")
    }

    static func mobileOrgStockMovements(warehouseID: Int, id: Int, flow: String?, page: Int, perPage: Int) -> Endpoint {
        var items = [URLQueryItem(name: "page", value: "\(page)"), URLQueryItem(name: "per_page", value: "\(perPage)")]
        if let flow {
            items.append(URLQueryItem(name: "flow", value: flow))
        }
        return Endpoint(path: "mobile/warehouses/\(warehouseID)/org-stocks/\(id)/stock-movements", queryItems: items)
    }

    static let mobileStockMovementReasons = Endpoint(path: "mobile/stock-movement-reasons")

    static func mobileOrgStockAudit(warehouseID: Int, id: Int, counts: [MobileOrgStockAuditCount], clientRequestID: String? = nil) -> Endpoint {
        .jsonBody("mobile/warehouses/\(warehouseID)/org-stocks/\(id)/audit", method: .post, MobileOrgStockAuditBody(counts: counts, clientRequestId: clientRequestID))
    }

    static func mobileOrgStockAddLocation(warehouseID: Int, id: Int, body: MobileOrgStockAddLocationBody) -> Endpoint {
        .jsonBody("mobile/warehouses/\(warehouseID)/org-stocks/\(id)/locations", method: .post, body)
    }

    static func mobileOrgStockRemoveLocations(warehouseID: Int, id: Int, linkIDs: [Int]) -> Endpoint {
        .jsonBody("mobile/warehouses/\(warehouseID)/org-stocks/\(id)/remove-locations", method: .post, MobileOrgStockRemoveLocationsBody(locationOrgStockIds: linkIDs))
    }

    static func mobileLocationOrgStockMove(linkID: Int, targets: [MobileOrgStockMoveTarget], clientRequestID: String? = nil) -> Endpoint {
        .jsonBody("mobile/location-org-stocks/\(linkID)/move", method: .post, MobileOrgStockMoveBody(targets: targets, clientRequestId: clientRequestID))
    }

    static func mobileLocationOrgStockConsume(linkID: Int, body: MobileOrgStockConsumeBody) -> Endpoint {
        .jsonBody("mobile/location-org-stocks/\(linkID)/consume", method: .post, body)
    }
}

extension APIClient {
    func fetchInventoryDashboard(warehouseID: Int) async throws -> MobileInventoryDashboard {
        try await send(.mobileInventoryDashboard(warehouseID: warehouseID), as: DataEnvelope<MobileInventoryDashboard>.self).value
    }

    func fetchOrgStocks(warehouseID: Int, query: MobileOrgStocksQuery) async throws -> MobileOrgStocksPage {
        try await send(.mobileOrgStocks(warehouseID: warehouseID, query: query), as: MobileOrgStocksPage.self)
    }

    func fetchOrgStock(warehouseID: Int, id: Int) async throws -> MobileOrgStockDetail {
        try await send(.mobileOrgStock(warehouseID: warehouseID, id: id), as: DataEnvelope<MobileOrgStockDetail>.self).value
    }

    func fetchOrgStockMovements(warehouseID: Int, id: Int, flow: String?, page: Int = 1, perPage: Int = 50) async throws -> MobileOrgStockMovementsPage {
        try await send(.mobileOrgStockMovements(warehouseID: warehouseID, id: id, flow: flow, page: page, perPage: perPage), as: MobileOrgStockMovementsPage.self)
    }

    func fetchStockMovementReasons() async throws -> MobileStockMovementReasons {
        try MobileStockMovementReasons.decode(from: await send(.mobileStockMovementReasons))
    }

    func auditOrgStock(warehouseID: Int, id: Int, counts: [MobileOrgStockAuditCount], clientRequestID: String? = nil) async throws -> MobileOrgStockWriteResult {
        try await send(.mobileOrgStockAudit(warehouseID: warehouseID, id: id, counts: counts, clientRequestID: clientRequestID), as: MobileOrgStockWriteResult.self)
    }

    func moveOrgStock(fromLinkID linkID: Int, targets: [MobileOrgStockMoveTarget], clientRequestID: String? = nil) async throws -> MobileOrgStockWriteResult {
        try await send(.mobileLocationOrgStockMove(linkID: linkID, targets: targets, clientRequestID: clientRequestID), as: MobileOrgStockWriteResult.self)
    }

    func consumeOrgStock(linkID: Int, body: MobileOrgStockConsumeBody) async throws -> MobileOrgStockWriteResult {
        try await send(.mobileLocationOrgStockConsume(linkID: linkID, body: body), as: MobileOrgStockWriteResult.self)
    }

    func addOrgStockLocation(warehouseID: Int, id: Int, body: MobileOrgStockAddLocationBody) async throws -> MobileOrgStockWriteResult {
        try await send(.mobileOrgStockAddLocation(warehouseID: warehouseID, id: id, body: body), as: MobileOrgStockWriteResult.self)
    }

    func removeOrgStockLocations(warehouseID: Int, id: Int, linkIDs: [Int]) async throws -> MobileOrgStockWriteResult {
        try await send(.mobileOrgStockRemoveLocations(warehouseID: warehouseID, id: id, linkIDs: linkIDs), as: MobileOrgStockWriteResult.self)
    }
}
