import Foundation

enum InventorySkoState: String, CaseIterable, Hashable, Sendable {
    case active
    case discontinuing
    case discontinued
    case suspended
    case abnormality

    var title: String {
        switch self {
        case .active: "Active"
        case .discontinuing: "Discontinuing"
        case .discontinued: "Discontinued"
        case .suspended: "Suspended"
        case .abnormality: "Abnormality"
        }
    }

    var dotHex: UInt32 {
        switch self {
        case .active: 0x22C55E
        case .discontinuing: 0xF59E0B
        case .discontinued: 0xEF4444
        case .suspended: 0x94A3B8
        case .abnormality: 0xDC2626
        }
    }
}

enum InventoryStockStatus: String, CaseIterable, Hashable, Sendable {
    case excess
    case ideal
    case low
    case critical
    case outOfStock
    case error

    var title: String {
        switch self {
        case .excess: "Excess"
        case .ideal: "Ideal"
        case .low: "Low"
        case .critical: "Critical"
        case .outOfStock: "Out of stock"
        case .error: "Error"
        }
    }

    var tone: Tone {
        switch self {
        case .excess: .info
        case .ideal: .success
        case .low: .warn
        case .critical, .outOfStock, .error: .danger
        }
    }

    var dotHex: UInt32 {
        switch self {
        case .excess: 0x0EA5E9
        case .ideal: 0x22C55E
        case .low: 0xF59E0B
        case .critical: 0xF97316
        case .outOfStock: 0xEF4444
        case .error: 0x7F1D1D
        }
    }

    var chip: Chip { Chip(title, tone) }
}

enum InventoryLocationType: String, CaseIterable, Hashable, Sendable {
    case picking
    case storing

    var title: String {
        switch self {
        case .picking: "Picking"
        case .storing: "Storing"
        }
    }
}

enum InventoryChannel: String, CaseIterable, Hashable, Sendable {
    case wholesale
    case dropshipping

    var title: String {
        switch self {
        case .wholesale: "Wholesale"
        case .dropshipping: "Dropshipping"
        }
    }
}

enum InventoryArea: String, CaseIterable, Hashable, Sendable {
    case a = "A"
    case b = "B"
    case c = "C"
    case d = "D"

    var title: String {
        switch self {
        case .a: "A · Ground floor"
        case .b: "B · Mezzanine"
        case .c: "C · Bulk"
        case .d: "D · Dispatch"
        }
    }

    init?(locationCode: String) {
        guard let first = locationCode.first else { return nil }
        self.init(rawValue: String(first).uppercased())
    }
}

struct InventoryLocationLink: Hashable, Sendable {
    let linkID: Int
    let locationID: Int
    let exactQuantity: Double
    let quantityLabel: String
    var isDefaultWholesale = false
    var isDefaultDropshipping = false
    var levels: String?
}

struct InventorySkoLocation: Identifiable, Hashable, Sendable {
    let code: String
    var type: InventoryLocationType
    var channel: InventoryChannel?
    var quantity: Int
    var minimum: Int?
    var maximum: Int?
    var replenish: Int?
    var lastAudited: String?
    var link: InventoryLocationLink? = nil

    var id: String { code }

    var area: InventoryArea? { InventoryArea(locationCode: code) }

    var quantityText: String { link?.quantityLabel ?? quantity.grouped }

    var chip: Chip {
        if let link {
            let defaults = [
                link.isDefaultWholesale ? InventoryChannel.wholesale.title : nil,
                link.isDefaultDropshipping ? InventoryChannel.dropshipping.title : nil,
            ]
            return Chip(([type.title] + defaults.compactMap { $0 }).joined(separator: " · "), type == .picking ? .info : .neutral)
        }
        switch type {
        case .picking: return Chip(["Picking", channel?.title].compactMap { $0 }.joined(separator: " · "), .info)
        case .storing: return Chip("Storing", .neutral)
        }
    }

    var typeLabel: String { chip.text }

    var settingsLine: String {
        var parts: [String] = []
        if let link {
            if let levels = link.levels { parts.append(levels) }
        } else if type == .picking {
            if let minimum { parts.append("min \(minimum)") }
            if let maximum { parts.append("max \(maximum)") }
        } else if let replenish {
            parts.append("replenish \(replenish)")
        }
        parts.append(lastAudited.map { "audited \($0)" } ?? "never audited")
        return parts.joined(separator: " · ")
    }
}

struct InventorySkoLiveFacts: Hashable, Sendable {
    let id: Int
    var stateLabel: String
    var familyTitle: String?
    var stockLabel: String
    var stockIsNegative: Bool
    var reservedLabel: String
    var toBePickedLabel: String
    var canEditStocks: Bool
}

struct InventorySko: Identifiable, Hashable, Sendable {
    let reference: String
    var name: String
    var familyCode: String?
    var state: InventorySkoState
    var supplierCode: String?
    var unitsPerSko: Int
    var barcode: String?
    var unitBarcode: String?
    var pickerNote: String?
    var reserved: Int
    var toBePicked: Int
    var coverDays: Int?
    var isOnDemand: Bool
    var unitValue: Decimal
    var criticalAt: Int
    var lowAt: Int
    var excessAt: Int
    var abnormalityNote: String?
    var locations: [InventorySkoLocation]
    var packerNote: String? = nil
    var live: InventorySkoLiveFacts? = nil

    var id: String { reference }

    var stock: Int { locations.reduce(0) { $0 + $1.quantity } }

    var stockValue: Decimal { unitValue * Decimal(stock) }

    var isAbnormal: Bool { state == .abnormality }

    var hasNegativeStock: Bool { locations.contains { $0.quantity < 0 } }

    var status: InventoryStockStatus {
        let total = stock
        if hasNegativeStock || total < 0 { return .error }
        if total == 0 { return .outOfStock }
        if total <= criticalAt { return .critical }
        if total <= lowAt { return .low }
        if total >= excessAt { return .excess }
        return .ideal
    }

    var locationCountLabel: String {
        locations.count == 1 ? "1 location" : "\(locations.count) locations"
    }

    var listSubtitle: String {
        if isAbnormal {
            return [name, abnormalityNote].compactMap { $0 }.joined(separator: " · ")
        }
        return "\(name) · \(locationCountLabel)"
    }

    var listChip: Chip {
        if isAbnormal { return Chip(live?.stateLabel ?? "Abnormality", .danger) }
        if let live { return Chip(live.stateLabel, state == .active ? .success : .warn) }
        return status.chip
    }

    var canMoveStock: Bool { locations.count >= 2 && locations.contains { $0.quantity > 0 } }

    var canConsume: Bool { locations.contains { $0.quantity > 0 } }

    var coverLabel: String {
        guard let coverDays else { return "–" }
        return coverDays == 1 ? "1 day" : "\(coverDays.grouped) days"
    }

    func location(_ code: String) -> InventorySkoLocation? {
        locations.first { $0.code == code }
    }
}

enum InventoryFamilyState: String, CaseIterable, Hashable, Sendable {
    case active
    case inProcess
    case discontinuing
    case discontinued

    var remoteValue: String {
        switch self {
        case .active: "active"
        case .inProcess: "in_process"
        case .discontinuing: "discontinuing"
        case .discontinued: "discontinued"
        }
    }

    init?(remoteValue: String) {
        guard let state = Self.allCases.first(where: { $0.remoteValue == remoteValue }) else { return nil }
        self = state
    }

    var title: String {
        switch self {
        case .active: "Active"
        case .inProcess: "In process"
        case .discontinuing: "Discontinuing"
        case .discontinued: "Discontinued"
        }
    }
}

struct InventoryFamily: Identifiable, Hashable, Sendable {
    let code: String
    let name: String
    var state: InventoryFamilyState
    var skoCount: Int
    var stockValueLabel: String
    var coverWeeks: Int
    var remoteID: Int? = nil
    var coverMonths: Double? = nil

    var id: String { code }

    var title: String { name.isEmpty || name == code ? code : "\(code) · \(name)" }

    var coverLabel: String {
        guard remoteID != nil else { return "\(coverWeeks) weeks" }
        guard let coverMonths else { return "–" }
        return "\(coverMonths.formatted(.number.precision(.fractionLength(0...1)))) mo"
    }

    var subtitle: String { "\(skoCount) SKOs · stock value \(stockValueLabel) · cover \(coverLabel)" }

    func matches(search: String) -> Bool {
        let query = search.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !query.isEmpty else { return true }
        return code.localizedCaseInsensitiveContains(query) || name.localizedCaseInsensitiveContains(query)
    }
}

struct InventoryLocationOption: Identifiable, Hashable, Sendable {
    let code: String
    let detail: String
    var locationID: Int? = nil

    var id: String { code }
}

struct InventoryReasonChoice: Identifiable, Hashable, Sendable {
    let id: String
    let title: String
}

struct InventorySkoListItem: Identifiable, Hashable, Sendable {
    let skoID: Int
    let reference: String
    let subtitle: String
    let chip: Chip?
    let quantityText: String?
    var location: InventorySkoListLocation? = nil

    var id: InventorySkoListKey { InventorySkoListKey(skoID: skoID, locationOrgStockID: location?.locationOrgStockID) }
}

struct InventorySkoListKey: Hashable, Sendable {
    let skoID: Int
    let locationOrgStockID: Int?
}

struct InventorySkoListLocation: Hashable, Sendable {
    let locationOrgStockID: Int
    let code: String
    let quantityText: String?
    let isNegative: Bool
}

struct InventoryDashboardSummary: Hashable, Sendable {
    var currentSkos: Int
    var abnormalities: Int
    var replenishmentsWholesale: Int
    var replenishmentsDropshipping: Int
    var lowStockAudits: Int
    var belowMinimum: Int
    var withMinimum: Int
    var negativeStocks: Int
    var wrongWeights: Int
    var withoutProduct: Int
    var stockValueLabel: String
    var utilisationPercent: Int?
    var usedLocations: Int
    var totalLocations: Int

    var replenishmentsLabel: String { "Replenishments · dropshipping \(replenishmentsDropshipping.grouped)" }

    var belowMinimumLabel: String {
        "Below minimum stock · of \(withMinimum.grouped) \(withMinimum == 1 ? "SKO" : "SKOs") with a minimum"
    }

    var utilisationLine: String? {
        guard let utilisationPercent else { return nil }
        let noun = totalLocations == 1 ? "location" : "locations"
        return "Location utilisation \(utilisationPercent)% · \(usedLocations.grouped) of \(totalLocations.grouped) \(noun) used"
    }
}
