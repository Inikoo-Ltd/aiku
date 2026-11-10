import Foundation

enum GoodsOutNotPickedReason: String, CaseIterable, Hashable, Sendable {
    case outOfStock
    case cancelledByCustomer
    case cancelledByWarehouse
    case notApplicable

    var title: String {
        switch self {
        case .outOfStock: "Out of stock"
        case .cancelledByCustomer: "Cancelled by customer"
        case .cancelledByWarehouse: "Cancelled by warehouse"
        case .notApplicable: "Not applicable"
        }
    }

    var chipText: String { "Not picked · \(title.lowercased())" }
    var statusText: String { "Not picked · \(title)" }
}

enum GoodsOutWaitingReason: String, CaseIterable, Hashable, Sendable {
    case warehouse
    case customerService

    var title: String {
        switch self {
        case .warehouse: "Waiting for the warehouse"
        case .customerService: "Pass to CS"
        }
    }

    var detail: String {
        switch self {
        case .warehouse: "Stock is somewhere in the warehouse, someone needs to find it"
        case .customerService: "Customer service needs to decide (substitute, refund…)"
        }
    }

    var chipText: String {
        switch self {
        case .warehouse: "Waiting · warehouse"
        case .customerService: "Waiting · CS"
        }
    }
}

enum GoodsOutLineOutcome: Hashable, Sendable {
    case notPicked(GoodsOutNotPickedReason)
    case waiting(GoodsOutWaitingReason, note: String)
}

enum GoodsOutLineStatus: Hashable, Sendable {
    case toPick
    case partlyPicked
    case picked
    case notPicked(GoodsOutNotPickedReason)
    case waiting(GoodsOutWaitingReason)

    var tone: Tone {
        switch self {
        case .toPick: .neutral
        case .partlyPicked: .info
        case .picked: .success
        case .notPicked: .danger
        case .waiting: .warn
        }
    }

    var chip: Chip? {
        switch self {
        case .toPick, .partlyPicked: nil
        case .picked: Chip("Picked", .success)
        case .notPicked(let reason): Chip(reason.chipText, .danger)
        case .waiting(let reason): Chip(reason.chipText, .warn)
        }
    }
}

struct GoodsOutLine: Identifiable, Hashable, Sendable {
    let sko: String
    let name: String
    var location: String
    var required: Int
    var picked = 0
    var pickedFrom: String?
    var locationStock: Int?
    var outcome: GoodsOutLineOutcome?
    var packedInto: [Int: Int] = [:]

    var id: String { sko }
    var remaining: Int { max(0, required - picked) }
    var isComplete: Bool { remaining == 0 }
    var isHandled: Bool { isComplete || outcome != nil }
    var pickLocation: String { pickedFrom ?? location }

    var isWaiting: Bool {
        if case .waiting = outcome { return true }
        return false
    }

    var waitingNote: String? {
        if case .waiting(_, let note) = outcome { return note }
        return nil
    }

    var status: GoodsOutLineStatus {
        if isComplete { return .picked }
        switch outcome {
        case .notPicked(let reason): return .notPicked(reason)
        case .waiting(let reason, _): return .waiting(reason)
        case nil: return picked > 0 ? .partlyPicked : .toPick
        }
    }

    var swipeStatusText: String {
        switch status {
        case .toPick: "To pick"
        case .partlyPicked, .picked: "Picked \(picked) of \(required)"
        case .notPicked(let reason): reason.statusText
        case .waiting(let reason): reason.chipText
        }
    }

    mutating func pick(_ quantity: Int, from otherLocation: String? = nil) {
        guard quantity > 0 else { return }
        picked = min(required, picked + quantity)
        if let otherLocation, otherLocation != location {
            pickedFrom = otherLocation
        }
        if isComplete {
            outcome = nil
        }
    }

    mutating func markNotPicked(_ reason: GoodsOutNotPickedReason) {
        guard !isComplete else { return }
        outcome = .notPicked(reason)
    }

    mutating func setWaiting(_ reason: GoodsOutWaitingReason, note: String) {
        guard !isComplete else { return }
        outcome = .waiting(reason, note: note.trimmingCharacters(in: .whitespacesAndNewlines))
    }

    mutating func reset() {
        picked = 0
        pickedFrom = nil
        outcome = nil
    }
}
