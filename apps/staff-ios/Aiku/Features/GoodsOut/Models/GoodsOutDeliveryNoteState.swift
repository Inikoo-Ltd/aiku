import Foundation

enum GoodsOutDeliveryNoteState: String, CaseIterable, Hashable, Sendable {
    case todo
    case picking
    case picked
    case packing
    case packed
    case finalised
    case dispatched
    case waiting
    case cancelled

    @MainActor
    var title: String {
        switch self {
        case .todo: L10n.text("To do")
        case .picking: L10n.text("Picking")
        case .picked: L10n.text("Picked")
        case .packing: L10n.text("Packing")
        case .packed: L10n.text("Packed")
        case .finalised: L10n.text("Finalised")
        case .dispatched: L10n.text("Dispatched")
        case .waiting: L10n.text("Waiting")
        case .cancelled: L10n.text("Cancelled")
        }
    }

    var tone: Tone {
        switch self {
        case .todo: .neutral
        case .picking, .picked, .packing, .packed: .info
        case .finalised, .dispatched: .success
        case .waiting: .warn
        case .cancelled: .danger
        }
    }

    @MainActor
    var chip: Chip { Chip(title, tone) }

    var isLaterState: Bool {
        switch self {
        case .packing, .packed, .finalised, .dispatched: true
        default: false
        }
    }

    var isInBacklog: Bool { self != .dispatched && self != .cancelled }

    var apiBucket: String? {
        switch self {
        case .todo: "unassigned"
        case .picking: "handling"
        case .waiting: "handling_blocked"
        case .picked: "picked"
        case .packing: "packing"
        case .packed: "packed"
        case .finalised: "finalised"
        case .dispatched: "dispatched"
        case .cancelled: nil
        }
    }

    init?(apiState: String) {
        switch apiState {
        case "unassigned", "queued": self = .todo
        case "handling": self = .picking
        case "handling_blocked": self = .waiting
        case "picked": self = .picked
        case "packing": self = .packing
        case "packed": self = .packed
        case "finalised": self = .finalised
        case "dispatched": self = .dispatched
        case "cancelled": self = .cancelled
        default: return nil
        }
    }

    static let flow: [GoodsOutDeliveryNoteState] = [.todo, .picking, .picked, .packing, .packed, .finalised, .dispatched]
    static let listTabs: [GoodsOutDeliveryNoteState] = [.todo, .picking, .waiting, .picked, .packing, .packed, .finalised, .dispatched]
    static let backlogColumns: [GoodsOutDeliveryNoteState] = [.todo, .picking, .waiting, .picked, .packing, .packed, .finalised]
}

enum GoodsOutStepStatus: Hashable, Sendable {
    case done
    case current
    case waiting
    case upcoming
}

struct GoodsOutStep: Hashable, Sendable, Identifiable {
    let title: String
    let status: GoodsOutStepStatus

    var id: String { title }
}

@MainActor
enum GoodsOutStepper {
    static func steps(for state: GoodsOutDeliveryNoteState) -> [GoodsOutStep] {
        let flow = GoodsOutDeliveryNoteState.flow
        switch state {
        case .cancelled:
            return []
        case .waiting:
            return [
                GoodsOutStep(title: GoodsOutDeliveryNoteState.todo.title, status: .done),
                GoodsOutStep(title: GoodsOutDeliveryNoteState.picking.title, status: .done),
                GoodsOutStep(title: GoodsOutDeliveryNoteState.waiting.title, status: .waiting)
            ] + flow.dropFirst(3).map { GoodsOutStep(title: $0.title, status: .upcoming) }
        default:
            let currentIndex = flow.firstIndex(of: state) ?? 0
            return flow.enumerated().map { index, step in
                let status: GoodsOutStepStatus = index < currentIndex ? .done : (index == currentIndex ? .current : .upcoming)
                return GoodsOutStep(title: step.title, status: status)
            }
        }
    }
}

enum GoodsOutChannel: String, CaseIterable, Hashable, Sendable {
    case wholesale
    case dropshipping
    case b2c
    case marketplaces
    case partners
    case fulfilment

    @MainActor
    var title: String {
        switch self {
        case .wholesale: L10n.text("Wholesale")
        case .dropshipping: L10n.text("Dropshipping")
        case .b2c: L10n.text("B2C")
        case .marketplaces: L10n.text("Marketplaces")
        case .partners: L10n.text("Partners")
        case .fulfilment: L10n.text("Fulfilment")
        }
    }

    var apiKey: String {
        switch self {
        case .wholesale: "b2b"
        case .dropshipping: "dropshipping"
        case .b2c: "b2c"
        case .marketplaces: "external"
        case .partners: "partners"
        case .fulfilment: "fulfilment"
        }
    }

    init?(apiKey: String?) {
        guard let match = Self.allCases.first(where: { $0.apiKey == apiKey }) else { return nil }
        self = match
    }

    var skipsPacking: Bool { self == .dropshipping }
    var hasCustomerShippingLabel: Bool { self == .dropshipping }
}

enum GoodsOutShipper: String, CaseIterable, Hashable, Sendable {
    case dpd
    case apc
    case collection

    var title: String {
        switch self {
        case .dpd: "DPD (GB)"
        case .apc: "APC (GB)"
        case .collection: "Collection"
        }
    }
}

enum GoodsOutNoteAge: Hashable, Sendable {
    case today
    case yesterday
    case thisWeek
    case older
}
