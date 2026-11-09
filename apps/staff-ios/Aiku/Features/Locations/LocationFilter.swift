import Foundation

enum LocationAreaFilter: Hashable {
    case all
    case area(String)
    case broken
}

struct LocationFilter {
    var area: LocationAreaFilter = .all
    var query = ""

    func apply(to locations: [WarehouseLocation]) -> [WarehouseLocation] {
        let trimmed = query.trimmingCharacters(in: .whitespacesAndNewlines)
        return locations
            .filter { location in
                switch area {
                case .all: true
                case .area(let name): location.area == name
                case .broken: location.status == .broken
                }
            }
            .filter { trimmed.isEmpty || $0.code.localizedCaseInsensitiveContains(trimmed) }
            .sorted { $0.code < $1.code }
    }
}
