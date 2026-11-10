import Foundation

struct GoodsInRights: Hashable, Sendable {
    var canEditGoodsIn: Bool
    var isIncomingSupervisor: Bool
    var canEditProcurement: Bool
    var canEditAccounting: Bool

    static let full = GoodsInRights(canEditGoodsIn: true, isIncomingSupervisor: true, canEditProcurement: true, canEditAccounting: true)
    static let warehouseStaff = GoodsInRights(canEditGoodsIn: true, isIncomingSupervisor: false, canEditProcurement: false, canEditAccounting: false)
    static let viewOnly = GoodsInRights(canEditGoodsIn: false, isIncomingSupervisor: false, canEditProcurement: false, canEditAccounting: false)
}

enum GoodsInDeliveryAction: Hashable, Sendable {
    case markAsReceived
    case unmarkAsDispatched
    case checkItems
    case placeItems
    case unmarkAsReceived
    case cancel
    case setAsPlaced

    static func available(for delivery: GoodsInDelivery, rights: GoodsInRights) -> Set<GoodsInDeliveryAction> {
        var actions: Set<GoodsInDeliveryAction> = []
        let state = delivery.state
        let isBeforeBookingIn = state == .received || state == .checked || state == .notReceived
        if state == .arriving, rights.canEditGoodsIn {
            actions.insert(.markAsReceived)
        }
        if state == .arriving, rights.canEditProcurement {
            actions.insert(.unmarkAsDispatched)
        }
        if isBeforeBookingIn || state == .bookingIn, rights.canEditGoodsIn {
            actions.insert(.checkItems)
        }
        if state == .received || state == .checked || state == .bookingIn, rights.canEditGoodsIn, delivery.hasItemsToPlace {
            actions.insert(.placeItems)
        }
        if isBeforeBookingIn, !delivery.hasAnyPlacement, rights.isIncomingSupervisor {
            actions.insert(.unmarkAsReceived)
        }
        if isBeforeBookingIn, !delivery.hasAnyPlacement, rights.canEditProcurement {
            actions.insert(.cancel)
        }
        if state == .bookedIn, rights.canEditProcurement || rights.canEditAccounting {
            actions.insert(.setAsPlaced)
        }
        return actions
    }

    static func statusLine(for delivery: GoodsInDelivery, rights: GoodsInRights) -> String {
        switch delivery.state {
        case .arriving: "Not received yet. Marking it as received needs goods-in edit rights."
        case .received, .checked, .bookingIn: "Checking and placing items needs goods-in edit rights."
        case .notReceived: "Nothing was received. Purchasing follows up the differences."
        case .bookedIn: "Booked in. Purchasing sets it as placed when costing starts."
        case .placed: "Placed. This is its final state, nothing left to do."
        case .cancelled: "Cancelled. Nothing left to do."
        case .withdrawn: "Unmarked as dispatched. It is back with purchasing."
        }
    }
}
