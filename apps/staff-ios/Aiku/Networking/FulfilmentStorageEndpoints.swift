import Foundation

struct MobileStoredPalletIncident: Decodable, Sendable, Equatable {
    struct Reporter: Decodable, Sendable, Equatable {
        let id: Int?
        let name: String?
    }

    struct ReturnRef: Decodable, Sendable, Equatable {
        let id: Int
        let reference: String
    }

    var state: String? = nil
    var stateLabel: String? = nil
    var message: String? = nil
    var reporter: Reporter? = nil
    var date: String? = nil
    var `return`: ReturnRef? = nil
}

struct MobileStoredPallet: Decodable, Sendable, Equatable, Identifiable {
    struct Location: Decodable, Sendable, Equatable {
        let id: Int
        let code: String
    }

    struct Ref: Decodable, Sendable, Equatable {
        let id: Int
        let reference: String
    }

    struct StoredItem: Decodable, Sendable, Equatable, Identifiable {
        let id: Int
        var reference: String? = nil
        var name: String? = nil
        var barcode: String? = nil
        var quantity: Int = 0
        var state: String? = nil
        var stateLabel: String? = nil
    }

    let id: Int
    let reference: String
    var customerReference: String? = nil
    var type: String? = nil
    var typeLabel: String? = nil
    let state: String
    var stateLabel: String? = nil
    var status: String? = nil
    var statusLabel: String? = nil
    var customer: MobileFulfilmentCustomer? = nil
    var location: Location? = nil
    var notes: String? = nil
    var numberStoredItems: Int? = nil
    var incident: MobileStoredPalletIncident? = nil
    var delivery: Ref? = nil
    var `return`: Ref? = nil
    var receivedAt: String? = nil
    var bookedInAt: String? = nil
    var storingAt: String? = nil
    var itemsStorage: Bool? = nil
    var storedItems: [StoredItem]? = nil
    var openAuditId: Int? = nil
    var availableActions: [MobileFulfilmentAction]? = nil
}

struct MobileStoredPalletsPage: Decodable, Sendable, Equatable {
    struct Counts: Decodable, Sendable, Equatable {
        struct IncidentStates: Decodable, Sendable, Equatable {
            var damaged = 0
            var lost = 0
            var otherIncident = 0
        }

        var storing = 0
        var receiving = 0
        var returning = 0
        var incidents = 0
        var returned = 0
        var incidentStates: IncidentStates = IncidentStates()
    }

    let data: [MobileStoredPallet]
    let meta: MobileLocationsPage.Meta
    var counts: Counts = Counts()
}

struct MobileStoredPalletWriteResult: Decodable, Sendable, Equatable {
    let data: MobileStoredPallet
    var result: MobileGoodsInResult? = nil
}

struct MobilePalletIncidentBody: Encodable, Sendable, Equatable {
    let state: String
    var message: String?
}

struct MobileStoredItemRow: Decodable, Sendable, Equatable, Identifiable {
    struct PalletLine: Decodable, Sendable, Equatable {
        struct Pallet: Decodable, Sendable, Equatable {
            let id: Int
            let reference: String
            var customerReference: String? = nil
            var state: String? = nil
            var stateLabel: String? = nil
        }

        let pallet: Pallet
        var location: MobileStoredPallet.Location? = nil
        var quantity: Int = 0
    }

    let id: Int
    let reference: String
    var name: String? = nil
    var barcode: String? = nil
    var state: String? = nil
    var stateLabel: String? = nil
    var customer: MobileFulfilmentCustomer? = nil
    var quantity: Int = 0
    var numberPallets: Int = 0
    var pallets: [PalletLine]? = nil
}

struct MobileStoredItemsPage: Decodable, Sendable, Equatable {
    struct Counts: Decodable, Sendable, Equatable {
        var active = 0
        var discontinuing = 0
        var discontinued = 0
        var all = 0
    }

    let data: [MobileStoredItemRow]
    let meta: MobileLocationsPage.Meta
    var counts: Counts = Counts()
}

struct MobileStoredItemAudit: Decodable, Sendable, Equatable {
    struct Pallet: Decodable, Sendable, Equatable {
        let id: Int
        let reference: String
        var customerReference: String? = nil
        var location: MobileStoredPallet.Location? = nil
    }

    struct Item: Decodable, Sendable, Equatable {
        let id: Int
        var reference: String? = nil
        var name: String? = nil
        var barcode: String? = nil
    }

    struct Line: Decodable, Sendable, Equatable {
        let storedItem: Item
        var quantity: Int = 0
        var auditedQuantity: Int? = nil
        var deltaId: Int? = nil
        var auditType: String? = nil
        var auditTypeLabel: String? = nil
        var isNewItem: Bool = false

        var isCounted: Bool { auditedQuantity != nil }
        var difference: Int? { auditedQuantity.map { $0 - quantity } }
    }

    struct Counts: Decodable, Sendable, Equatable {
        var lines = 0
        var counted = 0
        var differences = 0
    }

    let id: Int
    var reference: String? = nil
    let state: String
    var stateLabel: String? = nil
    var pallet: Pallet? = nil
    var customer: MobileFulfilmentCustomer? = nil
    var lines: [Line] = []
    var counts: Counts = Counts()
    var availableActions: [MobileFulfilmentAction]? = nil
}

struct MobileStoredItemAuditWriteResult: Decodable, Sendable, Equatable {
    let data: MobileStoredItemAudit
    var result: MobileGoodsInResult? = nil
}

struct MobileAuditCountBody: Encodable, Sendable, Equatable {
    var storedItemId: Int?
    var barcode: String?
    var auditedQuantity: Int?
    var add: Int?
    var clientRequestId: String?
}

struct MobileAuditChoice: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let reference: String
    var name: String? = nil
    var barcode: String? = nil
}

struct MobileAuditChoices: Decodable, Sendable, Equatable {
    let data: [MobileAuditChoice]
}

struct MobileStoredPalletsQuery: Equatable, Sendable {
    var filter = "storing"
    var incidentState: String?
    var search = ""
    var page = 1
    var perPage = 50

    var queryItems: [URLQueryItem] {
        var items = [
            URLQueryItem(name: "filter", value: filter),
            URLQueryItem(name: "page", value: "\(page)"),
            URLQueryItem(name: "per_page", value: "\(perPage)")
        ]
        if let incidentState { items.append(URLQueryItem(name: "incident_state", value: incidentState)) }
        let trimmed = search.trimmingCharacters(in: .whitespacesAndNewlines)
        if !trimmed.isEmpty { items.append(URLQueryItem(name: "q", value: trimmed)) }
        return items
    }
}

struct MobileStoredItemsQuery: Equatable, Sendable {
    var state = "active"
    var search = ""
    var page = 1
    var perPage = 50

    var queryItems: [URLQueryItem] {
        var items = [
            URLQueryItem(name: "state", value: state),
            URLQueryItem(name: "page", value: "\(page)"),
            URLQueryItem(name: "per_page", value: "\(perPage)")
        ]
        let trimmed = search.trimmingCharacters(in: .whitespacesAndNewlines)
        if !trimmed.isEmpty { items.append(URLQueryItem(name: "q", value: trimmed)) }
        return items
    }
}

enum MobileStoredPalletWrite: Equatable, Sendable {
    case move(MobilePalletBookInBody)
    case incident(MobilePalletIncidentBody)
}

enum MobileStoredItemAuditWrite: Equatable, Sendable {
    case count(MobileAuditCountBody)
    case removeCount(deltaID: Int)
    case complete
}

extension Endpoint {
    static func mobileStoredPallets(warehouseID: Int, query: MobileStoredPalletsQuery) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/pallets", queryItems: query.queryItems)
    }

    static func mobileStoredPallet(warehouseID: Int, id: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/pallets/\(id)")
    }

    static func mobileStoredPalletWrite(warehouseID: Int, id: Int, write: MobileStoredPalletWrite) -> Endpoint {
        let base = "mobile/warehouses/\(warehouseID)/pallets/\(id)"
        switch write {
        case .move(let body): return .jsonBody("\(base)/move", method: .post, body)
        case .incident(let body): return .jsonBody("\(base)/incident", method: .post, body)
        }
    }

    static func mobileStartPalletAudit(warehouseID: Int, palletID: Int) -> Endpoint {
        .jsonBody("mobile/warehouses/\(warehouseID)/pallets/\(palletID)/audit", method: .post, MobileEmptyBody())
    }

    static func mobileStoredItems(warehouseID: Int, query: MobileStoredItemsQuery) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/stored-items", queryItems: query.queryItems)
    }

    static func mobileStoredItem(warehouseID: Int, id: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/stored-items/\(id)")
    }

    static func mobileStoredItemAudit(warehouseID: Int, id: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/stored-item-audits/\(id)")
    }

    static func mobileStoredItemAuditChoices(warehouseID: Int, id: Int, search: String) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/stored-item-audits/\(id)/stored-items", queryItems: [URLQueryItem(name: "q", value: search)])
    }

    static func mobileStoredItemAuditWrite(warehouseID: Int, id: Int, write: MobileStoredItemAuditWrite) -> Endpoint {
        let base = "mobile/warehouses/\(warehouseID)/stored-item-audits/\(id)"
        switch write {
        case .count(let body): return .jsonBody("\(base)/count", method: .post, body)
        case .removeCount(let deltaID): return Endpoint(path: "\(base)/deltas/\(deltaID)", method: .delete)
        case .complete: return .jsonBody("\(base)/complete", method: .post, MobileEmptyBody())
        }
    }
}

extension APIClient {
    func fetchStoredPallets(warehouseID: Int, query: MobileStoredPalletsQuery) async throws -> MobileStoredPalletsPage {
        try await send(.mobileStoredPallets(warehouseID: warehouseID, query: query), as: MobileStoredPalletsPage.self)
    }

    func fetchStoredPallet(warehouseID: Int, id: Int) async throws -> MobileStoredPallet {
        try await send(.mobileStoredPallet(warehouseID: warehouseID, id: id), as: DataEnvelope<MobileStoredPallet>.self).value
    }

    func writeStoredPallet(warehouseID: Int, id: Int, write: MobileStoredPalletWrite) async throws -> MobileStoredPalletWriteResult {
        try await send(.mobileStoredPalletWrite(warehouseID: warehouseID, id: id, write: write), as: MobileStoredPalletWriteResult.self)
    }

    func startPalletAudit(warehouseID: Int, palletID: Int) async throws -> MobileStoredItemAuditWriteResult {
        try await send(.mobileStartPalletAudit(warehouseID: warehouseID, palletID: palletID), as: MobileStoredItemAuditWriteResult.self)
    }

    func fetchStoredItems(warehouseID: Int, query: MobileStoredItemsQuery) async throws -> MobileStoredItemsPage {
        try await send(.mobileStoredItems(warehouseID: warehouseID, query: query), as: MobileStoredItemsPage.self)
    }

    func fetchStoredItem(warehouseID: Int, id: Int) async throws -> MobileStoredItemRow {
        try await send(.mobileStoredItem(warehouseID: warehouseID, id: id), as: DataEnvelope<MobileStoredItemRow>.self).value
    }

    func fetchStoredItemAudit(warehouseID: Int, id: Int) async throws -> MobileStoredItemAudit {
        try await send(.mobileStoredItemAudit(warehouseID: warehouseID, id: id), as: DataEnvelope<MobileStoredItemAudit>.self).value
    }

    func fetchStoredItemAuditChoices(warehouseID: Int, id: Int, search: String) async throws -> [MobileAuditChoice] {
        try await send(.mobileStoredItemAuditChoices(warehouseID: warehouseID, id: id, search: search), as: MobileAuditChoices.self).data
    }

    func writeStoredItemAudit(warehouseID: Int, id: Int, write: MobileStoredItemAuditWrite) async throws -> MobileStoredItemAuditWriteResult {
        try await send(.mobileStoredItemAuditWrite(warehouseID: warehouseID, id: id, write: write), as: MobileStoredItemAuditWriteResult.self)
    }
}
