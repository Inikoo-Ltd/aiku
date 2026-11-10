import Foundation

enum GoodsInReturnState: String, CaseIterable, Hashable, Sendable {
    case received
    case returning
    case returned
    case done

    var title: String {
        switch self {
        case .received: "Received"
        case .returning: "Returning to locations"
        case .returned: "Returned"
        case .done: "Done"
        }
    }

    var stepTitle: String {
        switch self {
        case .received: "Received"
        case .returning: "Returning"
        case .returned: "Returned"
        case .done: "Done"
        }
    }

    var chip: Chip {
        switch self {
        case .received, .returning: Chip(title, .info)
        case .returned, .done: Chip(title, .success)
        }
    }
}

enum GoodsInReturnOutcome: Hashable, Sendable {
    case toLocation(String)
    case damaged
    case notReturned
}

struct GoodsInReturnItem: Identifiable, Hashable, Sendable {
    let sko: String
    let name: String
    let quantity: Int
    let homeLocation: String
    var outcome: GoodsInReturnOutcome?

    var id: String { sko }

    var returnedQuantity: Int {
        if case .toLocation = outcome { quantity } else { 0 }
    }

    var chip: Chip? {
        switch outcome {
        case .none: nil
        case .toLocation: Chip("Returned", .success)
        case .damaged: Chip("Damaged", .warn)
        case .notReturned: Chip("Not returned", .danger)
        }
    }

    var tone: Tone {
        switch outcome {
        case .none: .neutral
        case .toLocation: .success
        case .damaged: .warn
        case .notReturned: .danger
        }
    }

    var subtitle: String {
        switch outcome {
        case .none: "\(name) · \(quantity.grouped) to process"
        case .toLocation(let location): "\(quantity.grouped) back in \(location)"
        case .damaged: "\(name) · \(quantity.grouped) set as damaged"
        case .notReturned: "\(name) · set as not returned"
        }
    }
}

struct GoodsInReturn: Identifiable, Hashable, Sendable {
    let reference: String
    let customer: String
    var items: [GoodsInReturnItem]
    var isSetAsReturned = false
    var isDone = false

    var id: String { reference }

    var processedCount: Int { items.filter { $0.outcome != nil }.count }
    var isFullyProcessed: Bool { !items.isEmpty && processedCount == items.count }
    var nextPendingItem: GoodsInReturnItem? { items.first { $0.outcome == nil } }

    var state: GoodsInReturnState {
        if isDone { return .done }
        if isSetAsReturned { return .returned }
        return processedCount > 0 ? .returning : .received
    }

    var listSubtitle: String {
        let count = items.count == 1 ? "1 item" : "\(items.count) items"
        if state == .returning {
            return "\(customer) · \(processedCount) of \(items.count) items processed"
        }
        return "\(customer) · \(count)"
    }

    mutating func process(_ sko: String, outcome: GoodsInReturnOutcome) {
        guard state == .received || state == .returning, let index = items.firstIndex(where: { $0.sko == sko }), items[index].outcome == nil else { return }
        items[index].outcome = outcome
    }

    mutating func setAsReturned() {
        guard isFullyProcessed, state == .returning else { return }
        isSetAsReturned = true
    }
}
