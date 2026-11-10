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

    var title: String {
        switch self {
        case .todo: "To do"
        case .picking: "Picking"
        case .picked: "Picked"
        case .packing: "Packing"
        case .packed: "Packed"
        case .finalised: "Finalised"
        case .dispatched: "Dispatched"
        case .waiting: "Waiting"
        case .cancelled: "Cancelled"
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

    var chip: Chip { Chip(title, tone) }

    var isLaterState: Bool {
        switch self {
        case .packing, .packed, .finalised, .dispatched: true
        default: false
        }
    }

    var isInBacklog: Bool { self != .dispatched && self != .cancelled }

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

    var title: String {
        switch self {
        case .wholesale: "Wholesale"
        case .dropshipping: "Dropshipping"
        case .b2c: "B2C"
        case .marketplaces: "Marketplaces"
        case .partners: "Partners"
        }
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
