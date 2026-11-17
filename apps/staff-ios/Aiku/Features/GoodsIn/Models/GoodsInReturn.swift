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

struct GoodsInReturnPut: Identifiable, Hashable, Sendable {
    let id: Int
    let sko: String
    let kind: String
    let label: String
    let quantity: Double
    let location: String?
    var packedIn = 1

    var title: String {
        [GoodsInQuantity.text(quantity, packedIn: packedIn), label, location].compactMap { $0 }.joined(separator: " · ")
    }

    var undoMessage: String {
        if let location, kind == "sow" {
            return "\(GoodsInQuantity.text(quantity, packedIn: packedIn)) is taken back out of \(location) and goes back to remaining."
        }
        return "The quantity goes back to remaining."
    }
}

struct GoodsInReturnItem: Identifiable, Hashable, Sendable {
    let sko: String
    let name: String
    let quantity: Double
    let homeLocation: String
    var outcome: GoodsInReturnOutcome?
    var remoteID: Int? = nil
    var homeLinkID: Int? = nil
    var packedIn = 1
    var puts: [GoodsInReturnPut] = []

    var id: String { sko }

    var returnedQuantity: Double {
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
        case .none: "\(name) · \(GoodsInQuantity.text(quantity, packedIn: packedIn)) to process"
        case .toLocation(let location): "\(GoodsInQuantity.text(quantity, packedIn: packedIn)) back in \(location)"
        case .damaged: "\(name) · \(GoodsInQuantity.text(quantity, packedIn: packedIn)) set as damaged"
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
    var remoteID: Int? = nil
    var remoteState: GoodsInReturnState? = nil
    var remoteItemCount: Int? = nil
    var remoteHandledCount: Int? = nil
    var remoteActions: Set<String> = []
    var isDetailLoaded = true

    var id: String { reference }

    var processedCount: Int { items.filter { $0.outcome != nil }.count }
    var isFullyProcessed: Bool { !items.isEmpty && processedCount == items.count }
    var nextPendingItem: GoodsInReturnItem? { items.first { $0.outcome == nil } }

    var state: GoodsInReturnState {
        if let remoteState { return remoteState }
        if isDone { return .done }
        if isSetAsReturned { return .returned }
        return processedCount > 0 ? .returning : .received
    }

    var listSubtitle: String {
        let total = isDetailLoaded ? items.count : (remoteItemCount ?? items.count)
        let handled = isDetailLoaded ? processedCount : (remoteHandledCount ?? processedCount)
        let count = total == 1 ? "1 item" : "\(total) items"
        if state == .returning {
            return "\(customer) · \(handled) of \(total) items processed"
        }
        return "\(customer) · \(count)"
    }

    mutating func process(_ sko: String, outcome: GoodsInReturnOutcome) {
        guard state == .received || state == .returning, let index = items.firstIndex(where: { $0.sko == sko }), items[index].outcome == nil else { return }
        items[index].outcome = outcome
    }

    mutating func undo(_ put: GoodsInReturnPut) {
        guard state == .returning, let index = items.firstIndex(where: { $0.sko == put.sko }) else { return }
        items[index].puts.removeAll { $0.id == put.id }
        items[index].outcome = nil
    }

    mutating func setAsReturned() {
        guard isFullyProcessed, state == .returning else { return }
        isSetAsReturned = true
    }
}
