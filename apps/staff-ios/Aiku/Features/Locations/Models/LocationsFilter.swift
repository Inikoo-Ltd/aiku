import Foundation

enum LocationsListTab: String, CaseIterable, Hashable, Sendable {
    case all
    case empty
    case partiallyEmpty

    var title: String {
        switch self {
        case .all: "All"
        case .empty: "Empty"
        case .partiallyEmpty: "Partially empty"
        }
    }

    @MainActor
    func pillTitle(count: String) -> String {
        switch self {
        case .all: L10n.format("All %@", count)
        case .empty: L10n.format("Empty %@", count)
        case .partiallyEmpty: L10n.format("Partially empty %@", count)
        }
    }

    func matches(_ location: LocationsLocation) -> Bool {
        switch self {
        case .all: true
        case .empty: location.isEmpty
        case .partiallyEmpty: location.isPartiallyEmpty
        }
    }
}

enum LocationsOccupancyFilter: String, CaseIterable, Hashable, Sendable {
    case empty
    case partiallyEmpty
    case goodsOutGathering
    case neverRemoveEmptySkos

    var title: String {
        switch self {
        case .empty: "Empty"
        case .partiallyEmpty: "Partially empty"
        case .goodsOutGathering: "Goods out gathering"
        case .neverRemoveEmptySkos: "Never remove empty SKOs"
        }
    }

    func matches(_ location: LocationsLocation) -> Bool {
        switch self {
        case .empty: location.isEmpty
        case .partiallyEmpty: location.isPartiallyEmpty
        case .goodsOutGathering: location.isGoodsOutGathering
        case .neverRemoveEmptySkos: location.neverRemoveEmptySkos
        }
    }
}

enum LocationsSort: String, CaseIterable, Hashable, Sendable {
    case code
    case stockValue
    case weight
    case volume

    var title: String {
        switch self {
        case .code: "Code"
        case .stockValue: "Stock value"
        case .weight: "Weight"
        case .volume: "CBM"
        }
    }

    @MainActor
    var sortedByText: String {
        switch self {
        case .code: L10n.text("sorted by code")
        case .stockValue: L10n.text("sorted by stock value")
        case .weight: L10n.text("sorted by weight")
        case .volume: L10n.text("sorted by cbm")
        }
    }
}

struct LocationsFilter: Hashable, Sendable {
    var areas: Set<String> = []
    var statuses: Set<LocationsStatus> = []
    var scopes: Set<LocationsScope> = []
    var occupancies: Set<LocationsOccupancyFilter> = []
    var sort: LocationsSort = .code

    static let none = LocationsFilter()

    var activeCount: Int {
        areas.count + statuses.count + scopes.count + occupancies.count
    }

    @MainActor
    var summary: String {
        let filters = activeCount == 0 ? L10n.text("No filters") : L10n.format("%lld filters", activeCount)
        return [filters, sort.sortedByText].joined(separator: " · ")
    }

    func matches(_ location: LocationsLocation) -> Bool {
        if !areas.isEmpty, !areas.contains(location.areaCode) {
            return false
        }
        if !statuses.isEmpty, !statuses.contains(location.status) {
            return false
        }
        if !scopes.isEmpty, scopes.isDisjoint(with: location.scopes) {
            return false
        }
        if !occupancies.isEmpty, !occupancies.contains(where: { $0.matches(location) }) {
            return false
        }
        return true
    }

    func sorted(_ locations: [LocationsLocation]) -> [LocationsLocation] {
        switch sort {
        case .code:
            locations.sorted { $0.code.localizedStandardCompare($1.code) == .orderedAscending }
        case .stockValue:
            locations.sorted { ($0.stockValue, $1.code) > ($1.stockValue, $0.code) }
        case .weight:
            locations.sorted { ($0.usedWeightKg, $1.code) > ($1.usedWeightKg, $0.code) }
        case .volume:
            locations.sorted { ($0.usedVolumeM3, $1.code) > ($1.usedVolumeM3, $0.code) }
        }
    }

    static func matchesCode(_ location: LocationsLocation, query: String) -> Bool {
        let trimmed = query.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { return true }
        return location.code.lowercased().hasPrefix(trimmed.lowercased())
    }
}
