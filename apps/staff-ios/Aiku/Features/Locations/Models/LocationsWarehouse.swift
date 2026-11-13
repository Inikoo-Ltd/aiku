import Foundation

struct LocationsAuditCount: Hashable, Sendable {
    var skoReference: String
    var counted: Int
    var reason: LocationsAuditReason? = nil
    var note = ""
}

enum LocationsAuditReason: String, CaseIterable, Hashable, Sendable {
    case countShortage
    case damagedStock
    case countGain

    var title: String {
        switch self {
        case .countShortage: "Count shortage"
        case .damagedStock: "Damaged stock"
        case .countGain: "Count gain (stock found)"
        }
    }

    var remoteValue: String {
        switch self {
        case .countShortage: "count_short"
        case .damagedStock: "damaged"
        case .countGain: "count_gain"
        }
    }

    static let unchangedRemoteValue = "recount"

    static func options(forChange change: Int) -> [LocationsAuditReason] {
        if change < 0 {
            return [.countShortage, .damagedStock]
        }
        if change > 0 {
            return [.countGain]
        }
        return []
    }
}

struct LocationsWarehouse: Hashable, Sendable {
    var areas: [LocationsArea]
    var locations: [LocationsLocation]
    var summary: LocationsWarehouseSummary
    var autoRemoval: LocationsAutoRemoval

    func area(code: String) -> LocationsArea? {
        areas.first { $0.code.caseInsensitiveCompare(code) == .orderedSame }
    }

    func areaOrFallback(code: String) -> LocationsArea? {
        area(code: code) ?? areas.first
    }

    func location(code: String) -> LocationsLocation? {
        locations.first { $0.code.caseInsensitiveCompare(code) == .orderedSame }
    }

    func locationOrFallback(code: String) -> LocationsLocation? {
        location(code: code) ?? locations.first
    }

    func locations(inArea code: String) -> [LocationsLocation] {
        LocationsFilter.none.sorted(locations.filter { $0.areaCode.caseInsensitiveCompare(code) == .orderedSame })
    }

    func areas(matching query: String) -> [LocationsArea] {
        let trimmed = query.trimmingCharacters(in: .whitespacesAndNewlines).lowercased()
        guard !trimmed.isEmpty else { return areas }
        return areas.filter { $0.code.lowercased().hasPrefix(trimmed) || $0.title.lowercased().contains(trimmed) }
    }

    func baseList(query: String, filter: LocationsFilter) -> [LocationsLocation] {
        locations.filter { LocationsFilter.matchesCode($0, query: query) && filter.matches($0) }
    }

    func list(tab: LocationsListTab, query: String, filter: LocationsFilter) -> [LocationsLocation] {
        filter.sorted(baseList(query: query, filter: filter).filter(tab.matches))
    }

    func count(tab: LocationsListTab, query: String, filter: LocationsFilter) -> Int {
        baseList(query: query, filter: filter).filter(tab.matches).count
    }

    func moveTargets(from source: String, query: String) -> [LocationsLocation] {
        let candidates = locations.filter {
            $0.code.caseInsensitiveCompare(source) != .orderedSame
                && $0.status == .operational
                && $0.scopes.contains(.stocks)
                && LocationsFilter.matchesCode($0, query: query)
        }
        return candidates.sorted { lhs, rhs in
            if lhs.isEmpty != rhs.isEmpty {
                return lhs.isEmpty
            }
            return lhs.code.localizedStandardCompare(rhs.code) == .orderedAscending
        }
    }

    func otherLocation(holding role: LocationsPickingRole, sko: String, excluding location: String) -> String? {
        locations.first { candidate in
            candidate.code.caseInsensitiveCompare(location) != .orderedSame
                && candidate.slot(sko)?.roles.contains(role) == true
        }?.code
    }

    func movedWeight(from source: String, quantities: [String: Int]) -> Double {
        guard let location = location(code: source) else { return 0 }
        return quantities.reduce(0) { total, entry in
            guard let slot = location.slot(entry.key) else { return total }
            return total + Double(min(max(entry.value, 0), max(slot.quantity, 0))) * slot.unitWeightKg
        }
    }

    func movedVolume(from source: String, quantities: [String: Int]) -> Double {
        guard let location = location(code: source) else { return 0 }
        return quantities.reduce(0) { total, entry in
            guard let slot = location.slot(entry.key) else { return total }
            return total + Double(min(max(entry.value, 0), max(slot.quantity, 0))) * slot.unitVolumeM3
        }
    }

    func allQuantities(of code: String) -> [String: Int] {
        guard let location = location(code: code) else { return [:] }
        return Dictionary(uniqueKeysWithValues: location.slots.map { ($0.skoReference, max($0.quantity, 0)) })
    }

    @discardableResult
    mutating func audit(location code: String, counts: [LocationsAuditCount], on date: Date) -> Int {
        guard let index = index(of: code) else { return 0 }
        var differences = 0
        for count in counts {
            guard let slotIndex = locations[index].slots.firstIndex(where: { $0.skoReference == count.skoReference }) else { continue }
            let counted = max(count.counted, 0)
            if locations[index].slots[slotIndex].quantity != counted {
                differences += 1
                locations[index].slots[slotIndex].quantity = counted
            }
        }
        locations[index].auditedOn = date
        return differences
    }

    @discardableResult
    mutating func move(from source: String, to target: String, quantities: [String: Int], removeFromSource: Bool) -> Int {
        guard let sourceIndex = index(of: source), let targetIndex = index(of: target), sourceIndex != targetIndex else { return 0 }
        var moved = 0
        for (reference, requested) in quantities.sorted(by: { $0.key < $1.key }) {
            guard let slotIndex = locations[sourceIndex].slots.firstIndex(where: { $0.skoReference == reference }) else { continue }
            let available = max(locations[sourceIndex].slots[slotIndex].quantity, 0)
            let quantity = min(max(requested, 0), available)
            let sourceSlot = locations[sourceIndex].slots[slotIndex]
            if quantity > 0 {
                locations[sourceIndex].slots[slotIndex].quantity -= quantity
                if let targetSlot = locations[targetIndex].slots.firstIndex(where: { $0.skoReference == reference }) {
                    locations[targetIndex].slots[targetSlot].quantity += quantity
                } else {
                    var newSlot = sourceSlot
                    newSlot.quantity = quantity
                    newSlot.type = .storing
                    newSlot.roles = []
                    newSlot.minStock = 0
                    newSlot.maxStock = 0
                    newSlot.note = ""
                    locations[targetIndex].slots.append(newSlot)
                }
                moved += quantity
            }
            if removeFromSource, locations[sourceIndex].slots[slotIndex].quantity == 0 {
                locations[sourceIndex].slots.remove(at: slotIndex)
            }
        }
        return moved
    }

    @discardableResult
    mutating func remove(skos references: Set<String>, from code: String) -> Int {
        guard let index = index(of: code) else { return 0 }
        let before = locations[index].slots.count
        locations[index].slots.removeAll { references.contains($0.skoReference) }
        return before - locations[index].slots.count
    }

    mutating func update(sko reference: String, in code: String, settings: LocationsSlotSettings) {
        guard let index = index(of: code),
              let slotIndex = locations[index].slots.firstIndex(where: { $0.skoReference == reference }) else { return }
        let roles = settings.type == .picking ? settings.roles : []
        locations[index].slots[slotIndex].type = settings.type
        locations[index].slots[slotIndex].roles = roles
        locations[index].slots[slotIndex].minStock = max(settings.minStock, 0)
        locations[index].slots[slotIndex].maxStock = max(settings.maxStock, settings.minStock, 0)
        locations[index].slots[slotIndex].replenishment = max(settings.replenishment, 0)
        locations[index].slots[slotIndex].note = settings.note.trimmingCharacters(in: .whitespacesAndNewlines)
        guard settings.type == .picking else { return }
        for otherIndex in locations.indices where otherIndex != index {
            guard let otherSlot = locations[otherIndex].slots.firstIndex(where: { $0.skoReference == reference }) else { continue }
            locations[otherIndex].slots[otherSlot].roles.subtract(roles)
            if locations[otherIndex].slots[otherSlot].type == .picking, locations[otherIndex].slots[otherSlot].roles.isEmpty {
                locations[otherIndex].slots[otherSlot].type = .storing
            }
        }
    }

    private func index(of code: String) -> Int? {
        locations.firstIndex { $0.code.caseInsensitiveCompare(code) == .orderedSame }
    }
}
