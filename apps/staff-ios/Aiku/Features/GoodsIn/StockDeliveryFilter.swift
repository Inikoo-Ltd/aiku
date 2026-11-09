import Foundation

enum StockDeliveryStateFilter: Hashable {
    case open
    case state(StockDeliveryState)
}

struct StockDeliveryFilter {
    var state: StockDeliveryStateFilter = .open
    var query = ""

    func apply(to deliveries: [StockDelivery]) -> [StockDelivery] {
        let trimmed = query.trimmingCharacters(in: .whitespacesAndNewlines)
        return deliveries
            .filter { delivery in
                switch state {
                case .open: delivery.state != .placed
                case .state(let wanted): delivery.state == wanted
                }
            }
            .filter { trimmed.isEmpty || $0.reference.localizedCaseInsensitiveContains(trimmed) }
            .sorted { lhs, rhs in
                lhs.state.priority == rhs.state.priority ? lhs.reference < rhs.reference : lhs.state.priority < rhs.state.priority
            }
    }
}

extension StockDeliveryState {
    var priority: Int {
        switch self {
        case .received: 0
        case .checked: 1
        case .dispatched: 2
        case .placed: 3
        }
    }
}
