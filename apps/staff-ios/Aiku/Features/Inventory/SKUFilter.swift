import Foundation

enum SKULevelFilter: Hashable, CaseIterable {
    case all
    case low
    case critical
    case outOfStock

    var level: StockLevel? {
        switch self {
        case .all: nil
        case .low: .low
        case .critical: .critical
        case .outOfStock: .outOfStock
        }
    }
}

struct SKUFilter {
    var level: SKULevelFilter = .all
    var query = ""

    func apply(to skus: [SKU]) -> [SKU] {
        let trimmed = query.trimmingCharacters(in: .whitespacesAndNewlines)
        return skus
            .filter { level.level == nil || $0.level == level.level }
            .filter { trimmed.isEmpty || $0.reference.localizedCaseInsensitiveContains(trimmed) || $0.name.localizedCaseInsensitiveContains(trimmed) }
            .sorted { $0.reference < $1.reference }
    }
}
