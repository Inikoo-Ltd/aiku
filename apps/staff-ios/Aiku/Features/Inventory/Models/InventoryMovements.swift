import Foundation

enum InventoryMovementKind: String, CaseIterable, Hashable, Sendable {
    case purchase
    case picked
    case locationTransfer
    case found
    case consumption
    case writeOff
    case adjustment
    case audit
    case associate
    case disassociate
    case returns

    var title: String {
        switch self {
        case .purchase: "Purchase"
        case .picked: "Picked"
        case .locationTransfer: "Location transfer"
        case .found: "Found"
        case .consumption: "Consumption"
        case .writeOff: "Write off"
        case .adjustment: "Adjustment"
        case .audit: "Audit"
        case .associate: "Associate"
        case .disassociate: "Disassociate"
        case .returns: "Returns"
        }
    }
}

enum InventoryHistoryFilter: String, CaseIterable, Hashable, Sendable {
    case all
    case stockIn
    case stockOut
    case audit

    var title: String {
        switch self {
        case .all: "All"
        case .stockIn: "In"
        case .stockOut: "Out"
        case .audit: "Audit"
        }
    }

    var flow: String? {
        switch self {
        case .all: nil
        case .stockIn: "in"
        case .stockOut: "out"
        case .audit: "audit"
        }
    }

    func includes(_ movement: InventoryStockMovement) -> Bool {
        switch self {
        case .all: true
        case .audit: movement.kind == .audit
        case .stockIn: movement.kind != .audit && movement.kind != .locationTransfer && movement.quantity > 0
        case .stockOut: movement.kind != .audit && movement.kind != .locationTransfer && movement.quantity < 0
        }
    }
}

struct InventoryStockMovement: Identifiable, Hashable, Sendable {
    let id: UUID
    let skoReference: String
    let kind: InventoryMovementKind
    let quantity: Int
    let summary: String
    let todaySummary: String?

    init(
        id: UUID = UUID(),
        skoReference: String,
        kind: InventoryMovementKind,
        quantity: Int,
        summary: String,
        todaySummary: String? = nil
    ) {
        self.id = id
        self.skoReference = skoReference
        self.kind = kind
        self.quantity = quantity
        self.summary = summary
        self.todaySummary = todaySummary
    }

    @MainActor
    var chip: Chip {
        let title = L10n.text(kind.title)
        switch kind {
        case .locationTransfer:
            return Chip("\(title) \(quantity)", .info)
        case .associate, .disassociate:
            return Chip(kind.title, .info)
        case .purchase, .found, .returns:
            return Chip("\(title) \(Self.signed(quantity))", .success)
        case .audit, .adjustment:
            return Chip("\(title) \(Self.signed(quantity))", quantity < 0 ? .warn : .success)
        case .writeOff:
            return Chip("\(title) \(Self.signed(quantity))", .danger)
        case .picked, .consumption:
            return Chip("\(title) \(Self.signed(quantity))", .neutral)
        }
    }

    static func signed(_ value: Int) -> String {
        value > 0 ? "+\(value)" : value < 0 ? "−\(-value)" : "0"
    }

    @MainActor
    func display(usesTodaySummary: Bool = false) -> InventoryMovementDisplay {
        InventoryMovementDisplay(
            id: id.uuidString,
            title: skoReference,
            subtitle: usesTodaySummary ? (todaySummary ?? summary) : summary,
            chip: chip
        )
    }
}

struct InventoryMovementDisplay: Identifiable, Hashable, Sendable {
    let id: String
    let title: String
    let subtitle: String
    let chip: Chip
}

enum InventoryAuditReason: String, CaseIterable, Hashable, Sendable {
    case countShortage
    case damagedStock
    case lostMissing
    case expired
    case pickingError
    case countGain
    case recountCorrection
    case returnedToSaleable
    case supplierOver

    var title: String {
        switch self {
        case .countShortage: "Count shortage"
        case .damagedStock: "Damaged stock"
        case .lostMissing: "Lost / missing"
        case .expired: "Expired"
        case .pickingError: "Picking error"
        case .countGain: "Count gain (stock found)"
        case .recountCorrection: "Recount correction"
        case .returnedToSaleable: "Returned to saleable stock"
        case .supplierOver: "Supplier over"
        }
    }

    var isIncrease: Bool {
        Self.increaseReasons.contains(self)
    }

    static let decreaseReasons: [InventoryAuditReason] = [.countShortage, .damagedStock, .lostMissing, .expired, .pickingError]
    static let increaseReasons: [InventoryAuditReason] = [.countGain, .recountCorrection, .returnedToSaleable, .supplierOver]

    static func reasons(forChange change: Int) -> [InventoryAuditReason] {
        change < 0 ? decreaseReasons : increaseReasons
    }
}

enum InventoryMoveReason: String, CaseIterable, Hashable, Sendable {
    case warehouseTransfer
    case wrongBinLocation

    var title: String {
        switch self {
        case .warehouseTransfer: "Warehouse transfer"
        case .wrongBinLocation: "Wrong bin location"
        }
    }
}
