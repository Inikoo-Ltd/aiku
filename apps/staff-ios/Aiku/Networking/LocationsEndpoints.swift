import Foundation

struct MobileWarehouse: Decodable, Sendable, Equatable {
    struct Organisation: Decodable, Sendable, Equatable {
        let id: Int
        let name: String
    }

    struct Abilities: Decodable, Sendable, Equatable {
        let viewLocations: Bool
        let editLocations: Bool
    }

    let id: Int
    let code: String
    let name: String
    let organisation: Organisation
    let can: Abilities
}

struct MobileLocationsOverview: Decodable, Sendable, Equatable {
    let numberAreas: Int
    let numberLocations: Int
    let numberLocationsOperational: Int
    let numberLocationsBroken: Int
    let numberLocationsEmpty: Int
    let numberLocationsPartiallyEmpty: Int
    let stockValue: Double
    let emptyLocationsRemoval: MobileEmptyLocationsRemoval?
}

struct MobileEmptyLocationsRemoval: Decodable, Sendable, Equatable {
    struct Change: Decodable, Sendable, Equatable {
        let by: String?
        let at: String?
    }

    let enabled: Bool
    let canEdit: Bool
    let lastChange: Change?
    let toRemove: Int
    let removedLast7Days: Int
    let maxPerRun: Int
}

struct MobileEmptyLocationSlot: Decodable, Sendable, Equatable {
    let id: Int
    let date: String?
    let code: String
    let name: String?
    let locationCode: String
}

struct MobileEmptyLocationSlotsPage: Decodable, Sendable, Equatable {
    let data: [MobileEmptyLocationSlot]
    let meta: MobileLocationsPage.Meta
}

enum MobileEmptyLocationSlotsKind: Hashable, Sendable {
    case toRemove
    case removed
}

struct MobileEmptyLocationsRemovalBody: Encodable, Sendable {
    let enabled: Bool
}

struct MobileWarehouseArea: Decodable, Sendable, Equatable {
    let id: Int
    let code: String
    let name: String
    let pickingPosition: Double?
    let numberLocations: Int
    let numberEmptyLocations: Int
    let stockValue: Double
}

struct MobileLocation: Decodable, Sendable, Equatable {
    struct Area: Decodable, Sendable, Equatable {
        let id: Int
        let code: String?
        let name: String?
    }

    struct OrgStock: Decodable, Sendable, Equatable {
        let id: Int
        let code: String
        let name: String?
        let locationOrgStockId: Int
        let quantity: FlexibleDouble
        let type: String
        let value: FlexibleDouble?
        let notes: String?
        let defaultWholesalePickingLocation: Bool
        let defaultDropshippingPickingLocation: Bool
        let minStock: FlexibleInt?
        let maxStock: FlexibleInt?
        let replenishmentStock: FlexibleInt?
    }

    let id: Int
    let code: String
    let status: String
    let area: Area?
    let allowStocks: Bool
    let allowFulfilment: Bool
    let allowDropshipping: Bool
    let isGoodsOut: Bool
    let keepEmptyOrgStocks: Bool
    let maxWeight: Double?
    let maxVolume: Double?
    let numberOrgStockSlots: Int
    let numberEmptyStockSlots: Int
    let numberPallets: Int
    let stockValue: Double
    let auditedAt: String?
    let orgStocks: [OrgStock]?
    let can: Abilities?

    struct Abilities: Decodable, Sendable, Equatable {
        let editStocks: Bool
        let editLocations: Bool
    }
}

struct MobileLocationAuditCount: Encodable, Sendable, Equatable {
    let locationOrgStockId: Int
    let quantity: Int
    let reason: String
    let note: String?
}

struct MobileLocationAuditBody: Encodable, Sendable {
    let counts: [MobileLocationAuditCount]
}

struct MobileMoveAllBody: Encodable, Sendable {
    let locationId: Int
    let removeAfterMove: Bool
    let reason: String
}

struct MobileMovedOrgStock: Encodable, Sendable, Equatable {
    let orgStockId: Int
    let quantity: Int
    let removeAfterMove: Bool
}

struct MobileMoveSomeBody: Encodable, Sendable {
    let locationId: Int
    let orgStocks: [MobileMovedOrgStock]
    let reason: String
}

struct MobileRemoveOrgStocksBody: Encodable, Sendable {
    let orgStockIds: [Int]
}

struct MobileRemovedOrgStocks: Decodable, Sendable {
    let removed: Int
    let data: MobileLocation
}

struct MobileLocationOrgStockUpdate: Encodable, Sendable, Equatable {
    var type: String
    var minStock: Int?
    var maxStock: Int?
    var replenishmentStock: Int?
    var notes: String
    var setAsPriorityWholesale: Bool?
    var setAsPriorityDropshipping: Bool?
}

enum MobileStockMovementReason {
    static let transfer = "transfer"
}

struct FlexibleDouble: Decodable, Sendable, Equatable {
    let value: Double

    init(_ value: Double) {
        self.value = value
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.singleValueContainer()
        if let number = try? container.decode(Double.self) {
            value = number
        } else if let text = try? container.decode(String.self), let number = Double(text) {
            value = number
        } else {
            throw DecodingError.dataCorruptedError(in: container, debugDescription: "Not a number")
        }
    }
}

struct FlexibleInt: Decodable, Sendable, Equatable {
    let value: Int

    init(_ value: Int) {
        self.value = value
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.singleValueContainer()
        if let number = try? container.decode(Int.self) {
            value = number
        } else if let number = try? container.decode(Double.self) {
            value = Int(number)
        } else if let text = try? container.decode(String.self), let number = Double(text) {
            value = Int(number)
        } else {
            throw DecodingError.dataCorruptedError(in: container, debugDescription: "Not a number")
        }
    }
}

struct MobileLocationsPage: Decodable, Sendable, Equatable {
    struct Meta: Decodable, Sendable, Equatable {
        let currentPage: Int
        let lastPage: Int
        let perPage: Int
        let total: Int
    }

    let data: [MobileLocation]
    let meta: Meta
}

enum MobileLocationsOccupancy: String, Sendable {
    case empty
    case partiallyEmpty = "partially_empty"
}

struct MobileLocationsQuery: Equatable, Sendable {
    var search = ""
    var areaID: Int?
    var areaIDs: [Int] = []
    var occupancy: MobileLocationsOccupancy?
    var occupancies: [String] = []
    var statuses: [String] = []
    var scopes: [String] = []
    var sort: String?
    var page = 1
    var perPage = 50

    var queryItems: [URLQueryItem] {
        var items = [URLQueryItem(name: "page", value: "\(page)"), URLQueryItem(name: "per_page", value: "\(perPage)")]
        let trimmed = search.trimmingCharacters(in: .whitespacesAndNewlines)
        if !trimmed.isEmpty {
            items.append(URLQueryItem(name: "q", value: trimmed))
        }
        if let areaID {
            items.append(URLQueryItem(name: "area_id", value: "\(areaID)"))
        }
        if let occupancy {
            items.append(URLQueryItem(name: "occupancy", value: occupancy.rawValue))
        }
        items += areaIDs.sorted().map { URLQueryItem(name: "area_ids[]", value: "\($0)") }
        items += occupancies.sorted().map { URLQueryItem(name: "occupancies[]", value: $0) }
        items += statuses.sorted().map { URLQueryItem(name: "statuses[]", value: $0) }
        items += scopes.sorted().map { URLQueryItem(name: "scopes[]", value: $0) }
        if let sort {
            items.append(URLQueryItem(name: "sort", value: sort))
        }
        return items
    }
}

extension Endpoint {
    static let mobileWarehouses = Endpoint(path: "mobile/warehouses")

    static func mobileLocationsOverview(warehouseID: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/locations-overview")
    }

    static func mobileWarehouseAreas(warehouseID: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/areas")
    }

    static func mobileLocations(warehouseID: Int, query: MobileLocationsQuery) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/locations", queryItems: query.queryItems)
    }

    static func mobileLocation(id: Int) -> Endpoint {
        Endpoint(path: "mobile/locations/\(id)")
    }

    static func mobileEmptyLocationSlots(warehouseID: Int, kind: MobileEmptyLocationSlotsKind, search: String, page: Int, perPage: Int) -> Endpoint {
        var items = [URLQueryItem(name: "page", value: "\(page)"), URLQueryItem(name: "per_page", value: "\(perPage)")]
        let trimmed = search.trimmingCharacters(in: .whitespacesAndNewlines)
        if !trimmed.isEmpty {
            items.append(URLQueryItem(name: "q", value: trimmed))
        }
        switch kind {
        case .toRemove:
            return Endpoint(path: "mobile/warehouses/\(warehouseID)/empty-location-org-stocks", queryItems: items)
        case .removed:
            return Endpoint(path: "mobile/warehouses/\(warehouseID)/removed-empty-location-org-stocks", queryItems: items)
        }
    }

    static func mobileLocationAudit(id: Int, counts: [MobileLocationAuditCount]) -> Endpoint {
        .jsonBody("mobile/locations/\(id)/audit", method: .post, MobileLocationAuditBody(counts: counts))
    }

    static func mobileLocationMoveAll(id: Int, targetID: Int, removeAfterMove: Bool) -> Endpoint {
        .jsonBody("mobile/locations/\(id)/move-all", method: .post, MobileMoveAllBody(locationId: targetID, removeAfterMove: removeAfterMove, reason: MobileStockMovementReason.transfer))
    }

    static func mobileLocationMoveSome(id: Int, targetID: Int, orgStocks: [MobileMovedOrgStock]) -> Endpoint {
        .jsonBody("mobile/locations/\(id)/move-some", method: .post, MobileMoveSomeBody(locationId: targetID, orgStocks: orgStocks, reason: MobileStockMovementReason.transfer))
    }

    static func mobileLocationRemoveOrgStocks(id: Int, orgStockIDs: [Int]) -> Endpoint {
        .jsonBody("mobile/locations/\(id)/remove-org-stocks", method: .post, MobileRemoveOrgStocksBody(orgStockIds: orgStockIDs))
    }

    static func mobileLocationOrgStockUpdate(linkID: Int, update: MobileLocationOrgStockUpdate) -> Endpoint {
        .jsonBody("mobile/location-org-stocks/\(linkID)", method: .patch, update)
    }

    static func mobileEmptyLocationsRemoval(warehouseID: Int, enabled: Bool) -> Endpoint {
        .jsonBody("mobile/warehouses/\(warehouseID)/empty-locations-removal", method: .patch, MobileEmptyLocationsRemovalBody(enabled: enabled))
    }
}

extension APIClient {
    func fetchWarehouses() async throws -> [MobileWarehouse] {
        try await send(.mobileWarehouses, as: DataEnvelope<[MobileWarehouse]>.self).value
    }

    func fetchLocationsOverview(warehouseID: Int) async throws -> MobileLocationsOverview {
        try await send(.mobileLocationsOverview(warehouseID: warehouseID), as: DataEnvelope<MobileLocationsOverview>.self).value
    }

    func fetchWarehouseAreas(warehouseID: Int) async throws -> [MobileWarehouseArea] {
        try await send(.mobileWarehouseAreas(warehouseID: warehouseID), as: DataEnvelope<[MobileWarehouseArea]>.self).value
    }

    func fetchLocations(warehouseID: Int, query: MobileLocationsQuery) async throws -> MobileLocationsPage {
        try await send(.mobileLocations(warehouseID: warehouseID, query: query), as: MobileLocationsPage.self)
    }

    func fetchLocation(id: Int) async throws -> MobileLocation {
        try await send(.mobileLocation(id: id), as: DataEnvelope<MobileLocation>.self).value
    }

    func fetchEmptyLocationSlots(warehouseID: Int, kind: MobileEmptyLocationSlotsKind, search: String = "", page: Int = 1, perPage: Int = 50) async throws -> MobileEmptyLocationSlotsPage {
        try await send(.mobileEmptyLocationSlots(warehouseID: warehouseID, kind: kind, search: search, page: page, perPage: perPage), as: MobileEmptyLocationSlotsPage.self)
    }

    func auditLocation(id: Int, counts: [MobileLocationAuditCount]) async throws -> MobileLocation {
        try await send(.mobileLocationAudit(id: id, counts: counts), as: DataEnvelope<MobileLocation>.self).value
    }

    func moveAllFromLocation(id: Int, targetID: Int, removeAfterMove: Bool) async throws -> MobileLocation {
        try await send(.mobileLocationMoveAll(id: id, targetID: targetID, removeAfterMove: removeAfterMove), as: DataEnvelope<MobileLocation>.self).value
    }

    func moveSomeFromLocation(id: Int, targetID: Int, orgStocks: [MobileMovedOrgStock]) async throws -> MobileLocation {
        try await send(.mobileLocationMoveSome(id: id, targetID: targetID, orgStocks: orgStocks), as: DataEnvelope<MobileLocation>.self).value
    }

    func removeOrgStocks(fromLocation id: Int, orgStockIDs: [Int]) async throws -> MobileRemovedOrgStocks {
        try await send(.mobileLocationRemoveOrgStocks(id: id, orgStockIDs: orgStockIDs), as: MobileRemovedOrgStocks.self)
    }

    func updateLocationOrgStock(linkID: Int, update: MobileLocationOrgStockUpdate) async throws -> MobileLocation {
        try await send(.mobileLocationOrgStockUpdate(linkID: linkID, update: update), as: DataEnvelope<MobileLocation>.self).value
    }

    func updateEmptyLocationsRemoval(warehouseID: Int, enabled: Bool) async throws -> MobileEmptyLocationsRemoval {
        try await send(.mobileEmptyLocationsRemoval(warehouseID: warehouseID, enabled: enabled), as: DataEnvelope<MobileEmptyLocationsRemoval>.self).value
    }
}
