import Foundation

enum InventorySkoScope: String, CaseIterable, Hashable, Sendable {
    case current
    case discontinued
    case abnormalities
    case all

    var title: String {
        switch self {
        case .current: "Current"
        case .discontinued: "Discontinued"
        case .abnormalities: "Abnormalities"
        case .all: "All"
        }
    }

    func includes(_ sko: InventorySko) -> Bool {
        switch self {
        case .current: sko.state != .discontinued && sko.state != .abnormality
        case .discontinued: sko.state == .discontinued
        case .abnormalities: sko.isAbnormal
        case .all: true
        }
    }
}

enum InventorySkoSort: String, CaseIterable, Hashable, Sendable {
    case reference
    case name
    case stockLowFirst
    case stockValue
    case family

    var title: String {
        switch self {
        case .reference: "Reference"
        case .name: "Name"
        case .stockLowFirst: "Stock (low first)"
        case .stockValue: "Stock value"
        case .family: "Family"
        }
    }
}

struct InventorySkoFilter: Hashable, Sendable {
    var states: Set<InventorySkoState> = []
    var statuses: Set<InventoryStockStatus> = []
    var familyCodes: Set<String> = []
    var areas: Set<InventoryArea> = []
    var locationTypes: Set<InventoryLocationType> = []
    var sort: InventorySkoSort = .reference

    var activeCount: Int {
        states.count + statuses.count + familyCodes.count + areas.count + locationTypes.count
    }

    var summary: String {
        let count = activeCount
        let filters = count == 0 ? "No filters" : count == 1 ? "1 filter" : "\(count) filters"
        return "\(filters) · sorted by \(sort.title.lowercased())"
    }

    func matches(_ sko: InventorySko) -> Bool {
        if !states.isEmpty, !states.contains(sko.state) { return false }
        if !statuses.isEmpty, !statuses.contains(sko.status) { return false }
        if !familyCodes.isEmpty {
            guard let family = sko.familyCode, familyCodes.contains(family) else { return false }
        }
        if !areas.isEmpty, !sko.locations.contains(where: { $0.area.map(areas.contains) ?? false }) { return false }
        if !locationTypes.isEmpty, !sko.locations.contains(where: { locationTypes.contains($0.type) }) { return false }
        return true
    }

    static func toggled<Element: Hashable>(_ set: Set<Element>, _ element: Element) -> Set<Element> {
        var copy = set
        if copy.contains(element) {
            copy.remove(element)
        } else {
            copy.insert(element)
        }
        return copy
    }
}

enum InventorySkoQuery {
    static func matchesSearch(_ sko: InventorySko, _ search: String) -> Bool {
        let query = search.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !query.isEmpty else { return true }
        return sko.reference.localizedCaseInsensitiveContains(query)
            || sko.name.localizedCaseInsensitiveContains(query)
            || (sko.supplierCode?.localizedCaseInsensitiveContains(query) ?? false)
    }

    static func results(
        _ skos: [InventorySko],
        scope: InventorySkoScope = .all,
        filter: InventorySkoFilter = InventorySkoFilter(),
        search: String = "",
        familyNames: [String: String] = [:]
    ) -> [InventorySko] {
        let matching = skos.filter { scope.includes($0) && filter.matches($0) && matchesSearch($0, search) }
        return sorted(matching, by: filter.sort, familyNames: familyNames)
    }

    static func sorted(_ skos: [InventorySko], by sort: InventorySkoSort, familyNames: [String: String] = [:]) -> [InventorySko] {
        switch sort {
        case .reference:
            return skos.sorted { $0.reference.localizedStandardCompare($1.reference) == .orderedAscending }
        case .name:
            return skos.sorted { $0.name.localizedCaseInsensitiveCompare($1.name) == .orderedAscending }
        case .stockLowFirst:
            return skos.sorted { ($0.stock, $0.reference) < ($1.stock, $1.reference) }
        case .stockValue:
            return skos.sorted { $0.stockValue == $1.stockValue ? $0.reference < $1.reference : $0.stockValue > $1.stockValue }
        case .family:
            return skos.sorted {
                let lhs = $0.familyCode.flatMap { familyNames[$0] } ?? "~"
                let rhs = $1.familyCode.flatMap { familyNames[$0] } ?? "~"
                return lhs == rhs ? $0.reference < $1.reference : lhs < rhs
            }
        }
    }
}
