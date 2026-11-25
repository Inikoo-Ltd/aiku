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

    @MainActor
    var allowedText: String {
        switch self {
        case .stocks: L10n.text("stocks")
        case .fulfilment: L10n.text("fulfilment")
        case .dropshipping: L10n.text("dropshipping")
        }
    }

    @MainActor
    var deniedText: String {
        switch self {
        case .stocks: L10n.text("not stocks")
        case .fulfilment: L10n.text("not fulfilment")
        case .dropshipping: L10n.text("not dropshipping")
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
    var quantity: Double
    var type: LocationsSlotType
    var roles: Set<LocationsPickingRole> = []
    var minStock = 0
    var maxStock = 0
    var replenishment = 0
    var note = ""
    var unitWeightKg: Double
    var unitVolumeM3: Double
    var unitValue: Double
    var remoteOrgStockID: Int? = nil
    var remoteLinkID: Int? = nil
    var quantityLabel: String? = nil

    var id: String { skoReference }
    var isEmpty: Bool { quantity <= 0 }
    var quantityText: String { quantityLabel.map { FractionGlyphs.apply($0) } ?? quantity.locationsGrouped }
    var weightKg: Double { max(quantity, 0) * unitWeightKg }
    var volumeM3: Double { max(quantity, 0) * unitVolumeM3 }
    var stockValue: Double { max(quantity, 0) * unitValue }

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
    var remote: LocationsRemoteSummary?

    var id: String { code }
    var barcode: String { code.lowercased() }
    @MainActor
    var areaTitle: String { L10n.format("Area %@", areaCode) }

    private var usesRemoteSummary: Bool { slots.isEmpty && remote != nil }
    var slotCount: Int { usesRemoteSummary ? remote?.slotCount ?? 0 : slots.count }
    var emptySlotCount: Int { usesRemoteSummary ? remote?.emptySlotCount ?? 0 : slots.filter(\.isEmpty).count }
    var stockedSlotCount: Int { slotCount - emptySlotCount }
    var palletCount: Int { pallets.isEmpty ? remote?.palletCount ?? 0 : pallets.count }
    var usedWeightKg: Double { slots.map(\.weightKg).reduce(0, +) }
    var usedVolumeM3: Double { slots.map(\.volumeM3).reduce(0, +) }
    var stockValue: Double { usesRemoteSummary ? remote?.stockValue ?? 0 : slots.map(\.stockValue).reduce(0, +) }
    var totalUnits: Double { slots.map { max($0.quantity, 0) }.reduce(0, +) }

    var occupancy: LocationsOccupancy {
        if stockedSlotCount == 0, palletCount == 0 {
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

    @MainActor
    var slotSummary: String {
        if slotCount == 0 {
            if palletCount == 0 {
                return L10n.text("empty")
            }
            return L10n.format("%lld pallets", palletCount)
        }
        return L10n.format("%lld slots, %lld empty", slotCount, emptySlotCount)
    }

    @MainActor
    var shortSubtitle: String {
        "\(areaTitle) · \(slotSummary)"
    }

    @MainActor
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

    @MainActor
    var scopeSummary: String {
        let allowed = LocationsScope.allCases.filter { scopes.contains($0) }.map { $0.allowedText }
        let denied = LocationsScope.allCases.filter { !scopes.contains($0) }.map { $0.deniedText }
        let allowedText = allowed.isEmpty ? L10n.text("Allows nothing") : L10n.format("Allow %@", allowed.joined(separator: " · "))
        return ([allowedText] + denied).joined(separator: " · ")
    }

    @MainActor
    var capacitySummary: String {
        var limits: [String] = []
        if let maxWeightKg {
            limits.append("\(LocationsFormat.number(maxWeightKg)) kg")
        }
        if let maxVolumeM3 {
            limits.append("\(LocationsFormat.number(maxVolumeM3)) m³")
        }
        let limitText = limits.isEmpty ? L10n.text("No size limit") : L10n.format("Max %@", limits.joined(separator: " · "))
        return "\(limitText) · \(slotSummary)"
    }

    @MainActor
    var flagSummary: String? {
        var flags: [String] = []
        if isGoodsOutGathering {
            flags.append(L10n.text("Goods out gathering"))
        }
        if neverRemoveEmptySkos {
            flags.append(flags.isEmpty ? L10n.text("Never remove empty SKOs") : L10n.text("never remove empty SKOs"))
        }
        return flags.isEmpty ? nil : flags.joined(separator: " · ")
    }

    @MainActor
    var auditSummary: String {
        auditedOn.map { L10n.format("Audited %@", LocationsFormat.shortDate($0)) } ?? L10n.text("Not audited yet")
    }

    @MainActor
    var kicker: String {
        [L10n.text("Location"), areaTitle, L10n.text(status.title)].joined(separator: " · ")
    }
}

struct LocationsRemoteSummary: Hashable, Sendable {
    var id: Int
    var slotCount: Int
    var emptySlotCount: Int
    var palletCount: Int
    var stockValue: Double
}

struct LocationsArea: Identifiable, Hashable, Sendable {
    var remoteID: Int?
    var code: String
    var name: String
    var pickingOrder: Int?
    var locationCount: Int
    var emptyCount: Int
    var valueLabel: String

    var id: String { code }
    var codeRepeatsName: Bool {
        code.replacingOccurrences(of: "-", with: " ").caseInsensitiveCompare(name) == .orderedSame
    }
    @MainActor
    var title: String { codeRepeatsName ? name : L10n.format("Area %@ · %@", code, name) }
    var filterTitle: String { codeRepeatsName ? name : "\(code) · \(name)" }
    @MainActor
    var subtitle: String {
        let parts = [
            pickingOrder.map { L10n.format("Picking order %@", String($0)) } ?? L10n.text("No picking order"),
            L10n.format("%lld locations", locationCount),
            L10n.format("%lld empty", emptyCount),
            valueLabel,
        ]
        return parts.filter { !$0.isEmpty }.joined(separator: " · ")
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
    var changedBy: String?
    var changedOn: Date?
    var canEdit = true

    @MainActor
    var detail: String {
        switch (changedBy, changedOn) {
        case let (name?, date?): L10n.format("Changed by %@ · %@", name, LocationsFormat.shortDate(date))
        case let (name?, nil): L10n.format("Changed by %@", name)
        case let (nil, date?): L10n.format("Changed %@", LocationsFormat.shortDate(date))
        case (nil, nil): L10n.text("Not changed yet")
        }
    }
}

enum LocationsFormat {
    static let locale = Locale(identifier: "en_GB")

    static func number(_ value: Double) -> String {
        value.formatted(.number.precision(.fractionLength(0...1)).locale(locale))
    }

    static func dateTime(_ date: Date) -> String {
        date.formatted(.dateTime.day().month(.abbreviated).hour().minute().locale(locale))
    }

    static func shortDate(_ date: Date) -> String {
        let formatter = DateFormatter()
        formatter.locale = locale
        formatter.timeZone = TimeZone.current
        formatter.dateFormat = "dd MMM"
        return formatter.string(from: date)
    }
}

extension Int {
    var locationsGrouped: String { formatted(.number.locale(LocationsFormat.locale)) }
}

extension Double {
    var locationsGrouped: String { formatted(.number.precision(.fractionLength(0...3)).locale(LocationsFormat.locale)) }
}
