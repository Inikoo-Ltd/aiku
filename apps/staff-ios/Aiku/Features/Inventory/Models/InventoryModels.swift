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

    @MainActor
    var chip: Chip {
        if let link {
            let defaults = [
                link.isDefaultWholesale ? InventoryChannel.wholesale.title : nil,
                link.isDefaultDropshipping ? InventoryChannel.dropshipping.title : nil,
            ]
            let labels = ([type.title] + defaults.compactMap { $0 }).map { L10n.text($0) }
            return Chip(labels.joined(separator: " · "), type == .picking ? .info : .neutral)
        }
        switch type {
        case .picking: return Chip(["Picking", channel?.title].compactMap { $0 }.map { L10n.text($0) }.joined(separator: " · "), .info)
        case .storing: return Chip(L10n.text("Storing"), .neutral)
        }
    }

    @MainActor
    var typeLabel: String { chip.text }

    var typeTone: Tone { type == .picking ? .info : .neutral }

    var exactQuantity: Double { link?.exactQuantity ?? Double(quantity) }

    var isNegative: Bool { exactQuantity < 0 }

    var defaultChannels: [InventoryChannel] {
        if let link {
            var channels: [InventoryChannel] = []
            if link.isDefaultWholesale { channels.append(.wholesale) }
            if link.isDefaultDropshipping { channels.append(.dropshipping) }
            return channels
        }
        guard type == .picking, let channel else { return [] }
        return [channel]
    }

    var levelsText: String? {
        if let link { return link.levels }
        return Self.levels(minimum: minimum?.grouped, maximum: maximum?.grouped, replenish: replenish?.grouped)
    }

    func auditedText(translate: (String) -> String = { $0 }) -> String {
        guard let lastAudited else { return translate("Never audited") }
        return String(format: translate("Last audited %@"), lastAudited)
    }

    func defaultsText(translate: (String) -> String = { $0 }) -> String? {
        let channels = defaultChannels
        guard !channels.isEmpty else { return nil }
        let titles = channels.map { translate($0.title) }.joined(separator: " · ")
        return String(format: translate("Default for %@"), titles)
    }

    func footerLines(translate: (String) -> String = { $0 }) -> [String] {
        [defaultsText(translate: translate), levelsText].compactMap { $0 }
    }

    func unitsInTotalText(packedIn: Double?, translate: (String) -> String = { $0 }) -> String? {
        guard let units = InventorySkoUnits.total(exactQuantity, packedIn: packedIn) else { return nil }
        return InventorySkoUnits.text(units, translate: translate)
    }

    static func levels(minimum: String?, maximum: String?, replenish: String?) -> String? {
        switch (minimum, maximum) {
        case let (min?, max?): return L10n.formatInSavedLanguage("min %@ · max %@", min, max)
        case let (min?, nil): return L10n.formatInSavedLanguage("min %@", min)
        case let (nil, max?): return L10n.formatInSavedLanguage("max %@", max)
        default: return replenish.map { L10n.formatInSavedLanguage("replenish %@", $0) }
        }
    }
}

enum InventorySkoUnits {
    static func total(_ quantity: Double, packedIn: Double?) -> Double? {
        guard let packedIn, packedIn > 1, quantity >= 0 else { return nil }
        let units = quantity * packedIn
        let nearest = units.rounded()
        if abs(units - nearest) < 0.05 { return nearest }
        var exact = Decimal(string: "\(units)", locale: Locale(identifier: "en_US_POSIX")) ?? Decimal(units)
        var rounded = Decimal()
        NSDecimalRound(&rounded, &exact, 1, .plain)
        return NSDecimalNumber(decimal: rounded).doubleValue
    }

    static func text(_ units: Double, translate: (String) -> String = { $0 }) -> String {
        if units == 1 { return translate("1 unit in total") }
        let count = units.formatted(.number.precision(.fractionLength(0...1)).locale(Locale(identifier: "en_GB")))
        return String(format: translate("%@ units in total"), count)
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
    var stockQuantity: Double = 0
    var packedIn: Double? = nil
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

    @MainActor
    var locationCountLabel: String {
        L10n.format("%lld locations", locations.count)
    }

    @MainActor
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

    var stockQuantity: Double { live?.stockQuantity ?? Double(stock) }

    var packedIn: Double? {
        if let live { return live.packedIn }
        return unitsPerSko > 0 ? Double(unitsPerSko) : nil
    }

    func unitsInTotalText(translate: (String) -> String = { $0 }) -> String? {
        guard let units = InventorySkoUnits.total(stockQuantity, packedIn: packedIn) else { return nil }
        return InventorySkoUnits.text(units, translate: translate)
    }

    @MainActor
    var coverLabel: String {
        guard let coverDays else { return "–" }
        return L10n.format("%lld days", coverDays)
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

    var emptyTitle: String {
        switch self {
        case .active: "No active families"
        case .inProcess: "No in process families"
        case .discontinuing: "No discontinuing families"
        case .discontinued: "No discontinued families"
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

    @MainActor
    var coverLabel: String {
        guard remoteID != nil else { return L10n.format("%lld weeks", coverWeeks) }
        guard let coverMonths else { return "–" }
        return L10n.format("%@ mo", coverMonths.formatted(.number.precision(.fractionLength(0...1))))
    }

    @MainActor
    var subtitle: String {
        [L10n.format("%lld SKOs", skoCount), L10n.format("stock value %@", stockValueLabel), L10n.format("cover %@", coverLabel)].joined(separator: " · ")
    }

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

    @MainActor
    var replenishmentsLabel: String { L10n.format("Replenishments · dropshipping %@", replenishmentsDropshipping.grouped) }

    @MainActor
    var belowMinimumLabel: String {
        L10n.format("Below minimum stock · of %lld SKOs with a minimum", withMinimum)
    }

    @MainActor
    var utilisationLine: String? {
        guard let utilisationPercent else { return nil }
        return L10n.format("Location utilisation %@ · %@ of %lld locations used", "\(utilisationPercent)%", usedLocations.grouped, totalLocations)
    }
}
