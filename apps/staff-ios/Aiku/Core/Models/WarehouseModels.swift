import Foundation

struct Warehouse: Hashable, Sendable {
    let name: String
    let code: String
}

enum StockLevel: String, CaseIterable, Sendable {
    case normal
    case low
    case critical
    case outOfStock

    var label: String {
        switch self {
        case .normal: "In stock"
        case .low: "Low"
        case .critical: "Critical"
        case .outOfStock: "Out of stock"
        }
    }
}

enum MovementDirection: Sendable {
    case `in`
    case out
    case neutral
}

struct StockMovement: Identifiable, Hashable, Sendable {
    let id: String
    let title: String
    let detail: String
    let quantityChange: Int
    let direction: MovementDirection
    let actor: String
    let time: String
}

struct SKULocationStock: Identifiable, Hashable, Sendable {
    var id: String { locationCode }
    let locationCode: String
    let note: String
    let quantity: Int
    let isPicking: Bool
}

struct SKU: Identifiable, Hashable, Sendable {
    var id: String { reference }
    let reference: String
    let name: String
    let familyCode: String
    let stock: Int
    let unitValue: Decimal
    let level: StockLevel
    let locations: [SKULocationStock]
    let history: [StockMovement]

    var stockValue: Decimal { unitValue * Decimal(stock) }
}

enum LocationStatus: Sendable {
    case operational
    case auditInProcess
    case broken
}

struct LocationSKU: Identifiable, Hashable, Sendable {
    var id: String { skuReference }
    let skuReference: String
    let name: String
    let quantity: Int
}

struct WarehouseLocation: Identifiable, Hashable, Sendable {
    var id: String { code }
    let code: String
    let area: String
    let summary: String
    let stockValue: Decimal?
    let status: LocationStatus
    let allowsStocks: Bool
    let allowsFulfilment: Bool
    let weightKg: Double
    let maxWeightKg: Double
    let cbm: Double
    let maxCbm: Double
    let skus: [LocationSKU]
    let movements: [StockMovement]

    var weightUsage: Double {
        maxWeightKg > 0 ? min(weightKg / maxWeightKg, 1) : 0
    }
}

enum StockDeliveryState: String, CaseIterable, Sendable {
    case dispatched
    case received
    case checked
    case placed

    var label: String { rawValue.capitalized }
}

struct StockDeliveryItem: Identifiable, Hashable, Sendable {
    var id: String { skuReference }
    let skuReference: String
    let name: String
    let expected: Int
    var received: Int?

    var isComplete: Bool { received == expected }
    var shortfall: Int { max(expected - (received ?? 0), 0) }
}

struct StockDelivery: Identifiable, Hashable, Sendable {
    var id: String { reference }
    let reference: String
    let supplier: String
    let state: StockDeliveryState
    let timing: String
    let nextAction: String?
    let receivedAt: String?
    var items: [StockDeliveryItem]

    var checkedCount: Int { items.filter { $0.received != nil }.count }
}

enum DeliveryNoteState: String, CaseIterable, Sendable {
    case unassigned
    case inQueue
    case handling
    case packed
    case finalised
    case dispatched

    var label: String {
        switch self {
        case .unassigned: "Unassigned"
        case .inQueue: "In Queue"
        case .handling: "Handling"
        case .packed: "Packed"
        case .finalised: "Finalised"
        case .dispatched: "Dispatched"
        }
    }

    var primaryAction: String? {
        switch self {
        case .unassigned: "I will pick this"
        case .inQueue: "Start picking"
        case .handling: "Set as packed"
        case .packed: "Finalised"
        case .finalised: "Dispatch"
        case .dispatched: nil
        }
    }
}

struct PickItem: Identifiable, Hashable, Sendable {
    var id: String { skuReference }
    let skuReference: String
    let name: String
    let locationCode: String?
    let required: Int
    var picked: Int
    let locationStock: Int?
    let isOutOfStock: Bool

    var isComplete: Bool { picked >= required }
}

struct DeliveryNote: Identifiable, Hashable, Sendable {
    var id: String { reference }
    let reference: String
    let customer: String
    let shop: String
    let date: String
    let state: DeliveryNoteState
    let weightKg: Double
    let picker: String?
    var items: [PickItem]

    var itemCount: Int { items.reduce(0) { $0 + $1.required } }
    var pickedCount: Int { items.reduce(0) { $0 + min($1.picked, $1.required) } }
}

enum TaskKind: Sendable {
    case deliveryNote
    case stockDelivery
    case stockAudit
}

struct AssignedTask: Identifiable, Hashable, Sendable {
    var id: String { reference }
    let reference: String
    let kind: TaskKind
    let status: String
    let detail: String
}

struct GoodsOutSummary: Sendable {
    let unassigned: Int
    let inQueue: Int
    let handling: Int
    let blocked: Int
    let packed: Int
    let dispatchedToday: Int
    let plannedToday: Int
}

struct GoodsInSummary: Sendable {
    let dispatched: Int
    let received: Int
    let checked: Int
}

struct StaffMember: Sendable {
    let name: String

    var initials: String {
        name.split(separator: " ").compactMap(\.first).prefix(2).map(String.init).joined()
    }
}
