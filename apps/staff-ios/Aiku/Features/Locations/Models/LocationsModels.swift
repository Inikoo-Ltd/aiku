import Foundation

enum LocationsScope: String, CaseIterable, Hashable, Sendable {
    case stocks
    case fulfilment
    case dropshipping

    var title: String {
        switch self {
        case .stocks: "Stocks"
        case .fulfilment: "Fulfilment"
        case .dropshipping: "Dropshipping"
        }
    }
}

enum LocationsStatus: String, CaseIterable, Hashable, Sendable {
    case operational
    case broken

    var title: String {
        switch self {
        case .operational: "Operational"
        case .broken: "Broken"
        }
    }

    var tone: Tone {
        switch self {
        case .operational: .success
        case .broken: .danger
        }
    }
}

enum LocationsSlotType: String, CaseIterable, Hashable, Sendable {
    case picking
    case storing

    var title: String {
        switch self {
        case .picking: "Picking"
        case .storing: "Storing"
        }
    }

    var chip: Chip {
        switch self {
        case .picking: Chip("Picking", .info)
        case .storing: Chip("Storing", .neutral)
        }
    }
}

enum LocationsPickingRole: String, CaseIterable, Hashable, Sendable {
    case wholesale
    case dropshipping

    var title: String {
        switch self {
        case .wholesale: "Wholesale"
        case .dropshipping: "Dropshipping"
        }
    }

    var activeDetail: String {
        switch self {
        case .wholesale: "Pickers of wholesale orders come here"
        case .dropshipping: "Pickers of dropshipping orders come here"
        }
    }
}

enum LocationsOccupancy: Hashable, Sendable {
    case empty
    case partiallyEmpty
    case stocked
}

struct LocationsSlot: Identifiable, Hashable, Sendable {
    var skoReference: String
    var name: String
    var quantity: Int
    var type: LocationsSlotType
    var roles: Set<LocationsPickingRole> = []
    var minStock = 0
    var maxStock = 0
    var replenishment = 0
    var note = ""
    var unitWeightKg: Double
    var unitVolumeM3: Double
    var unitValue: Double

    var id: String { skoReference }
    var isEmpty: Bool { quantity <= 0 }
    var weightKg: Double { Double(max(quantity, 0)) * unitWeightKg }
    var volumeM3: Double { Double(max(quantity, 0)) * unitVolumeM3 }
    var stockValue: Double { Double(max(quantity, 0)) * unitValue }

    var settings: LocationsSlotSettings {
        LocationsSlotSettings(type: type, roles: roles, minStock: minStock, maxStock: maxStock, replenishment: replenishment, note: note)
    }
}

struct LocationsSlotSettings: Hashable, Sendable {
    var type: LocationsSlotType
    var roles: Set<LocationsPickingRole>
    var minStock: Int
    var maxStock: Int
    var replenishment: Int
    var note: String
}

struct LocationsPallet: Identifiable, Hashable, Sendable {
    var reference: String
    var customer: String
    var state: String

    var id: String { reference }
}

struct LocationsLocation: Identifiable, Hashable, Sendable {
    var code: String
    var areaCode: String
    var status: LocationsStatus = .operational
    var scopes: Set<LocationsScope>
    var isGoodsOutGathering = false
    var neverRemoveEmptySkos = false
    var maxWeightKg: Double?
    var maxVolumeM3: Double?
    var slots: [LocationsSlot] = []
    var pallets: [LocationsPallet] = []
    var auditedOn: Date?

    var id: String { code }
    var barcode: String { code.lowercased() }
    var areaTitle: String { "Area \(areaCode)" }

    var emptySlotCount: Int { slots.filter(\.isEmpty).count }
    var stockedSlotCount: Int { slots.count - emptySlotCount }
    var usedWeightKg: Double { slots.map(\.weightKg).reduce(0, +) }
    var usedVolumeM3: Double { slots.map(\.volumeM3).reduce(0, +) }
    var stockValue: Double { slots.map(\.stockValue).reduce(0, +) }
    var totalUnits: Int { slots.map { max($0.quantity, 0) }.reduce(0, +) }

    var occupancy: LocationsOccupancy {
        if stockedSlotCount == 0, pallets.isEmpty {
            return .empty
        }
        return emptySlotCount > 0 ? .partiallyEmpty : .stocked
    }

    var isEmpty: Bool { occupancy == .empty }
    var isPartiallyEmpty: Bool { occupancy == .partiallyEmpty }

    var weightUtilisation: Double? {
        guard let maxWeightKg, maxWeightKg > 0 else { return nil }
        return usedWeightKg / maxWeightKg
    }

    var volumeUtilisation: Double? {
        guard let maxVolumeM3, maxVolumeM3 > 0 else { return nil }
        return usedVolumeM3 / maxVolumeM3
    }

    func remainingWeightKg() -> Double? {
        maxWeightKg.map { $0 - usedWeightKg }
    }

    func exceedsWeight(adding kilograms: Double) -> Bool {
        guard let maxWeightKg else { return false }
        return usedWeightKg + kilograms > maxWeightKg + 0.0001
    }

    func exceedsVolume(adding cubicMetres: Double) -> Bool {
        guard let maxVolumeM3 else { return false }
        return usedVolumeM3 + cubicMetres > maxVolumeM3 + 0.0001
    }

    func slot(_ reference: String) -> LocationsSlot? {
        slots.first { $0.skoReference.caseInsensitiveCompare(reference) == .orderedSame }
    }

    var chip: Chip {
        if status == .broken {
            return Chip("Broken", .danger)
        }
        if scopes.contains(.stocks) {
            return Chip("Stocks", .info)
        }
        if scopes.contains(.fulfilment) {
            return Chip("Fulfilment", .neutral)
        }
        return Chip("Dropshipping", .neutral)
    }

    var slotSummary: String {
        if slots.isEmpty {
            if pallets.isEmpty {
                return "empty"
            }
            return pallets.count == 1 ? "1 pallet" : "\(pallets.count) pallets"
        }
        let noun = slots.count == 1 ? "slot" : "slots"
        return "\(slots.count) \(noun), \(emptySlotCount) empty"
    }

    var shortSubtitle: String {
        "\(areaTitle) · \(slotSummary)"
    }

    var listSubtitle: String {
        var parts = [areaTitle, slotSummary]
        if !slots.isEmpty {
            if let maxWeightKg {
                parts.append("\(LocationsFormat.number(usedWeightKg)) / \(LocationsFormat.number(maxWeightKg)) kg")
            }
            if let maxVolumeM3 {
                parts.append("\(LocationsFormat.number(usedVolumeM3)) / \(LocationsFormat.number(maxVolumeM3)) m³")
            }
        }
        return parts.joined(separator: " · ")
    }

    var scopeSummary: String {
        let allowed = LocationsScope.allCases.filter { scopes.contains($0) }.map { $0.title.lowercased() }
        let denied = LocationsScope.allCases.filter { !scopes.contains($0) }.map { "not \($0.title.lowercased())" }
        let allowedText = allowed.isEmpty ? "Allows nothing" : "Allow " + allowed.joined(separator: " · ")
        return ([allowedText] + denied).joined(separator: " · ")
    }

    var capacitySummary: String {
        var limits: [String] = []
        if let maxWeightKg {
            limits.append("\(LocationsFormat.number(maxWeightKg)) kg")
        }
        if let maxVolumeM3 {
            limits.append("\(LocationsFormat.number(maxVolumeM3)) m³")
        }
        let limitText = limits.isEmpty ? "No size limit" : "Max " + limits.joined(separator: " · ")
        return "\(limitText) · \(slotSummary)"
    }

    var flagSummary: String? {
        var flags: [String] = []
        if isGoodsOutGathering {
            flags.append("Goods out gathering")
        }
        if neverRemoveEmptySkos {
            flags.append(flags.isEmpty ? "Never remove empty SKOs" : "never remove empty SKOs")
        }
        return flags.isEmpty ? nil : flags.joined(separator: " · ")
    }

    var auditSummary: String {
        auditedOn.map { "Audited \(LocationsFormat.shortDate($0))" } ?? "Not audited yet"
    }

    var kicker: String {
        "Location · \(areaTitle) · \(status.title)"
    }
}

struct LocationsArea: Identifiable, Hashable, Sendable {
    var code: String
    var name: String
    var pickingOrder: Int
    var locationCount: Int
    var emptyCount: Int
    var valueLabel: String

    var id: String { code }
    var title: String { "Area \(code) · \(name)" }
    var filterTitle: String { "\(code) · \(name)" }
    var subtitle: String {
        "Picking order \(pickingOrder) · \(locationCount.locationsGrouped) locations · \(emptyCount.locationsGrouped) empty · \(valueLabel)"
    }
}

struct LocationsWarehouseSummary: Hashable, Sendable {
    var areaCount: Int
    var locationCount: Int
    var emptyCount: Int
    var utilisationPercent: Int
    var toRemoveNow: Int
    var removedLastWeek: Int
}

struct LocationsAutoRemoval: Hashable, Sendable {
    var isOn: Bool
    var changedBy: String
    var changedOn: Date

    var detail: String {
        "Changed by \(changedBy) · \(LocationsFormat.shortDate(changedOn))"
    }
}

enum LocationsFormat {
    static let locale = Locale(identifier: "en_GB")

    static func number(_ value: Double) -> String {
        value.formatted(.number.precision(.fractionLength(0...1)).locale(locale))
    }

    static func shortDate(_ date: Date) -> String {
        let formatter = DateFormatter()
        formatter.locale = locale
        formatter.timeZone = TimeZone.current
        formatter.dateFormat = "dd MMM"
        return formatter.string(from: date)
    }

    static func units(_ count: Int) -> String {
        count == 1 ? "1 unit" : "\(count.locationsGrouped) units"
    }

    static func skos(_ count: Int) -> String {
        count == 1 ? "1 SKO" : "\(count) SKOs"
    }
}

extension Int {
    var locationsGrouped: String { formatted(.number.locale(LocationsFormat.locale)) }
}
