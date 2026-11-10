import Foundation

enum GoodsInDispatchWindow: String, CaseIterable, Hashable, Sendable {
    case today
    case thisWeek
    case older

    var title: String {
        switch self {
        case .today: "Today"
        case .thisWeek: "This week"
        case .older: "Older"
        }
    }

    static func classify(_ date: Date, now: Date, calendar: Calendar = .current) -> GoodsInDispatchWindow {
        if calendar.isDate(date, inSameDayAs: now) {
            return .today
        }
        let startOfToday = calendar.startOfDay(for: now)
        let weekStart = calendar.date(byAdding: .day, value: -6, to: startOfToday) ?? startOfToday
        return date >= weekStart ? .thisWeek : .older
    }
}

enum GoodsInDeliverySort: String, CaseIterable, Hashable, Sendable {
    case dispatchedDate
    case reference
    case supplier
    case items

    var title: String {
        switch self {
        case .dispatchedDate: "Dispatched date"
        case .reference: "Reference"
        case .supplier: "Supplier"
        case .items: "Items"
        }
    }
}

struct GoodsInDeliveryFilter: Hashable, Sendable {
    static let stateOptions: [GoodsInDeliveryState] = [.arriving, .received, .checked, .bookingIn, .bookedIn, .notReceived]

    var states: Set<GoodsInDeliveryState> = []
    var sources: Set<GoodsInSource> = []
    var dispatched: Set<GoodsInDispatchWindow> = []
    var sort: GoodsInDeliverySort = .dispatchedDate

    var activeCount: Int { states.count + sources.count + dispatched.count }

    var summary: String {
        let filters = switch activeCount {
        case 0: "No filters"
        case 1: "1 filter"
        default: "\(activeCount) filters"
        }
        return "\(filters) · sorted by \(sort.title.lowercased())"
    }

    func matches(_ delivery: GoodsInDelivery, now: Date, calendar: Calendar = .current) -> Bool {
        guard delivery.state != .withdrawn else { return false }
        if !states.isEmpty {
            let state = delivery.state == .placed ? GoodsInDeliveryState.bookedIn : delivery.state
            guard states.contains(state) else { return false }
        }
        if !sources.isEmpty, !sources.contains(delivery.source) {
            return false
        }
        if !dispatched.isEmpty, !dispatched.contains(GoodsInDispatchWindow.classify(delivery.dispatchedAt, now: now, calendar: calendar)) {
            return false
        }
        return true
    }

    func apply(to deliveries: [GoodsInDelivery], now: Date, calendar: Calendar = .current) -> [GoodsInDelivery] {
        sorted(deliveries.filter { matches($0, now: now, calendar: calendar) })
    }

    func sorted(_ deliveries: [GoodsInDelivery]) -> [GoodsInDelivery] {
        deliveries.sorted { lhs, rhs in
            switch sort {
            case .dispatchedDate:
                if lhs.dispatchedAt != rhs.dispatchedAt { return lhs.dispatchedAt > rhs.dispatchedAt }
            case .reference:
                break
            case .supplier:
                if lhs.sourceName != rhs.sourceName { return lhs.sourceName < rhs.sourceName }
            case .items:
                if lhs.items.count != rhs.items.count { return lhs.items.count > rhs.items.count }
            }
            return lhs.reference < rhs.reference
        }
    }
}

enum GoodsInListScope: Hashable, Sendable {
    case state(GoodsInDeliveryState)
    case all

    static let options: [GoodsInListScope] = [.state(.arriving), .state(.received), .state(.checked), .state(.bookingIn), .state(.bookedIn), .all]

    var title: String {
        switch self {
        case .state(let state): state.title
        case .all: "All"
        }
    }

    var showsCount: Bool {
        switch self {
        case .state(let state): state.isActiveWork
        case .all: false
        }
    }

    func contains(_ delivery: GoodsInDelivery) -> Bool {
        switch self {
        case .all:
            return delivery.state != .withdrawn
        case .state(let state):
            switch state {
            case .bookedIn: return delivery.state == .bookedIn || delivery.state == .placed
            case .checked: return delivery.state == .checked || delivery.state == .notReceived
            default: return delivery.state == state
            }
        }
    }
}

struct GoodsInBacklogCounts: Hashable, Sendable {
    var arriving = 0
    var received = 0
    var checked = 0
    var bookingIn = 0
    var bookedInLastWeek = 0

    static func count(_ deliveries: [GoodsInDelivery], now: Date) -> GoodsInBacklogCounts {
        let weekAgo = now.addingTimeInterval(-7 * 24 * 3600)
        var counts = GoodsInBacklogCounts()
        for delivery in deliveries {
            switch delivery.state {
            case .arriving: counts.arriving += 1
            case .received: counts.received += 1
            case .checked, .notReceived: counts.checked += 1
            case .bookingIn: counts.bookingIn += 1
            case .bookedIn, .placed:
                if (delivery.bookedInAt ?? now) >= weekAgo {
                    counts.bookedInLastWeek += 1
                }
            case .cancelled, .withdrawn: break
            }
        }
        return counts
    }
}
