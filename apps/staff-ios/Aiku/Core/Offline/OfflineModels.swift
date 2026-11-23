import Foundation

enum OfflineOperation: String, Codable, CaseIterable, Sendable {
    case pick
    case notPicked = "not_picked"
    case setWaitingWarehouse = "set_waiting_warehouse"
    case setWaitingCRM = "set_waiting_crm"
    case pack
    case moveStock = "move_stock"
    case placeStockDeliveryItem = "place_stock_delivery_item"
    case returnPutBack = "return_put_back"
    case returnDamaged = "return_damaged"
    case returnNotReturned = "return_not_returned"
    case auditLocation = "audit_location"
    case auditOrgStock = "audit_org_stock"

    static let goodsOut: Set<OfflineOperation> = [.pick, .notPicked, .setWaitingWarehouse, .setWaitingCRM, .pack]
    static let returnPuts: Set<OfflineOperation> = [.returnPutBack, .returnDamaged, .returnNotReturned]
    static let stock: Set<OfflineOperation> = [.moveStock, .auditLocation, .auditOrgStock]

    var label: String {
        switch self {
        case .pick: "Pick"
        case .notPicked: "Not picked"
        case .setWaitingWarehouse: "Waiting for the warehouse"
        case .setWaitingCRM: "Passed to customer services"
        case .pack: "Pack"
        case .moveStock: "Move"
        case .placeStockDeliveryItem: "Place"
        case .returnPutBack: "Back to stock"
        case .returnDamaged: "Damaged"
        case .returnNotReturned: "Not returned"
        case .auditLocation: "Location count"
        case .auditOrgStock: "SKO count"
        }
    }

    var icon: AppIcon {
        switch self {
        case .pick, .notPicked, .setWaitingWarehouse, .setWaitingCRM: .deliveryNote
        case .pack: .packed
        case .moveStock: .transfer
        case .placeStockDeliveryItem: .stockDelivery
        case .returnPutBack, .returnDamaged, .returnNotReturned: .goodsIn
        case .auditLocation, .auditOrgStock: .audit
        }
    }
}

enum OfflineRecordType: String, Codable, Sendable {
    case deliveryNote = "delivery_note"
    case customerReturn = "return"
    case stockDelivery = "stock_delivery"
    case location
    case orgStock = "org_stock"

    func title(_ reference: String) -> String {
        switch self {
        case .deliveryNote: "Delivery note \(reference)"
        case .customerReturn: "Return \(reference)"
        case .stockDelivery: "Stock delivery \(reference)"
        case .location: "Location \(reference)"
        case .orgStock: "SKO \(reference)"
        }
    }
}

struct OfflineRecordKey: Hashable, Codable, Sendable {
    let type: OfflineRecordType
    let id: Int

    static func deliveryNote(_ id: Int) -> OfflineRecordKey { OfflineRecordKey(type: .deliveryNote, id: id) }
    static func stockDelivery(_ id: Int) -> OfflineRecordKey { OfflineRecordKey(type: .stockDelivery, id: id) }
    static func customerReturn(_ id: Int) -> OfflineRecordKey { OfflineRecordKey(type: .customerReturn, id: id) }
    static func location(_ id: Int) -> OfflineRecordKey { OfflineRecordKey(type: .location, id: id) }
    static func orgStock(_ id: Int) -> OfflineRecordKey { OfflineRecordKey(type: .orgStock, id: id) }
}

enum OfflinePayloadKey {
    static let clientRequestID = "client_request_id"
    static let quantity = "quantity"
    static let locationOrgStockID = "location_org_stock_id"
    static let locationID = "location_id"
}

enum QueuedActionState: String, Codable, Sendable {
    case waiting
    case sending
    case applied
    case needsYou = "needs_you"
    case void
}

enum SyncReason: Sendable {
    case queued
    case reconnected
    case foreground
    case clockOut
    case manual
    case periodic
}

enum OfflineJSON: Codable, Hashable, Sendable {
    case null
    case bool(Bool)
    case number(Double)
    case string(String)
    case array([OfflineJSON])
    case object([String: OfflineJSON])

    init(from decoder: Decoder) throws {
        let container = try decoder.singleValueContainer()
        if container.decodeNil() {
            self = .null
        } else if let value = try? container.decode(Bool.self) {
            self = .bool(value)
        } else if let value = try? container.decode(Double.self) {
            self = .number(value)
        } else if let value = try? container.decode(String.self) {
            self = .string(value)
        } else if let value = try? container.decode([OfflineJSON].self) {
            self = .array(value)
        } else {
            self = .object(try container.decode([String: OfflineJSON].self))
        }
    }

    func encode(to encoder: Encoder) throws {
        var container = encoder.singleValueContainer()
        switch self {
        case .null: try container.encodeNil()
        case .bool(let value): try container.encode(value)
        case .number(let value): try container.encode(value)
        case .string(let value): try container.encode(value)
        case .array(let value): try container.encode(value)
        case .object(let value): try container.encode(value)
        }
    }

    static func int(_ value: Int) -> OfflineJSON { .number(Double(value)) }

    static func optional(_ value: String?) -> OfflineJSON {
        guard let trimmed = value?.trimmingCharacters(in: .whitespacesAndNewlines), !trimmed.isEmpty else { return .null }
        return .string(trimmed)
    }

    var numberValue: Double? {
        if case .number(let value) = self { return value }
        if case .string(let value) = self { return Double(value) }
        return nil
    }

    var intValue: Int? { numberValue.map { Int($0) } }

    var stringValue: String? {
        if case .string(let value) = self { return value }
        return nil
    }

    var arrayValue: [OfflineJSON] {
        if case .array(let value) = self { return value }
        return []
    }

    var objectValue: [String: OfflineJSON]? {
        if case .object(let value) = self { return value }
        return nil
    }
}

typealias OfflinePayload = [String: OfflineJSON]

struct OfflineResult: Codable, Equatable, Sendable {
    var status: String
    var operationID: Int?
    var reason: String?
    var instruction: String
    var message: String?
    var requestedQuantity: Double?
    var appliedQuantity: Double?
    var countedSince: Bool
    var taskReference: String?
    var conflictingUserName: String?
}

struct QueueEntry: Codable, Equatable, Identifiable, Sendable {
    var localID: Int64
    let userID: Int
    let warehouseID: Int
    var operation: OfflineOperation?
    var record: OfflineRecordKey?
    var payload: OfflinePayload?
    let clientRequestID: String
    let performedAt: Date
    let timeSourceIsServer: Bool
    let seenAt: Date?
    var sequence: Int64?
    var state: QueuedActionState
    var result: OfflineResult?
    var syncedAt: Date?
    let createdAt: Date

    var id: Int64 { localID }

    var isPendingSend: Bool {
        state == .waiting || (state == .void && sequence != nil && syncedAt == nil)
    }

    var isUnsent: Bool { state == .waiting || state == .sending }

    var sendsAsVoid: Bool { state == .void || operation == nil || payload == nil }

    var canUndo: Bool { state == .waiting && operation != nil }

    var quantity: Double { payload?["quantity"]?.numberValue ?? 0 }

    func payloadInt(_ key: String) -> Int? { payload?[key]?.intValue }
}
