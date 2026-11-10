import Foundation

struct GoodsOutRights: Hashable, Sendable {
    var isDispatchingSupervisor = true
    var canEditStock = true
    var canScanToPick = true
    var canEditOrders = true

    var canCancelNotes: Bool { isDispatchingSupervisor || canEditOrders }
}

struct GoodsOutTrolley: Identifiable, Hashable, Sendable {
    let code: String
    var heldBy: String?

    var id: String { code }
}

struct GoodsOutPickedBay: Identifiable, Hashable, Sendable {
    let name: String
    var otherNotes = 0

    var id: String { name }
}

struct GoodsOutPickerProfile: Identifiable, Hashable, Sendable {
    let name: String
    var session: String?
    var sessionOrders = 0
    var sessionTrolleys = 0

    var id: String { name }
}

struct GoodsOutStockLocation: Identifiable, Hashable, Sendable {
    let code: String
    let kind: String
    let stock: Int

    var id: String { code }
    var isEmpty: Bool { stock <= 0 }
    var stockText: String { isEmpty ? "Empty" : "\(stock) stocks" }
}

struct GoodsOutChoice: Identifiable, Hashable, Sendable {
    let id: String
    let title: String
    let subtitle: String
    var isAvailable = true
}

enum GoodsOutResources {
    static func trolleyChoices(_ trolleys: [GoodsOutTrolley], notes: [GoodsOutDeliveryNote], for reference: String) -> [GoodsOutChoice] {
        trolleys.map { trolley in
            if let held = notes.first(where: { $0.trolley == trolley.code && $0.reference != reference && ($0.state == .picking || $0.state == .todo) }) {
                return GoodsOutChoice(id: trolley.code, title: trolley.code, subtitle: "Delivery note \(held.reference)", isAvailable: false)
            }
            if let heldBy = trolley.heldBy {
                return GoodsOutChoice(id: trolley.code, title: trolley.code, subtitle: heldBy, isAvailable: false)
            }
            return GoodsOutChoice(id: trolley.code, title: trolley.code, subtitle: "Free")
        }
    }

    static func bayChoices(_ bays: [GoodsOutPickedBay], notes: [GoodsOutDeliveryNote], for reference: String) -> [GoodsOutChoice] {
        bays.map { bay in
            let count = bay.otherNotes + notes.filter { $0.pickedBay == bay.name && $0.reference != reference && ($0.state == .picked || $0.state == .waiting) }.count
            let subtitle = count == 0 ? "Free" : "\(count) delivery \(count == 1 ? "note" : "notes")"
            return GoodsOutChoice(id: bay.name, title: bay.name, subtitle: subtitle)
        }
    }

    static func pickerChoices(_ pickers: [GoodsOutPickerProfile], notes: [GoodsOutDeliveryNote], currentUser: String) -> [GoodsOutChoice] {
        pickers.map { picker in
            let title = picker.name == currentUser ? "\(picker.name) (you)" : picker.name
            if let session = picker.session {
                return GoodsOutChoice(id: picker.name, title: title, subtitle: "Picking session \(session)")
            }
            let count = notes.filter { $0.state == .picking && $0.picker == picker.name }.count
            let subtitle = count == 0 ? "No current work" : "Picking \(count) \(count == 1 ? "order" : "orders")"
            return GoodsOutChoice(id: picker.name, title: title, subtitle: subtitle)
        }
    }

    static func pickerActivity(_ picker: GoodsOutPickerProfile, notes: [GoodsOutDeliveryNote]) -> String? {
        if let session = picker.session {
            return "Picking session \(session) · \(picker.sessionOrders) orders · \(picker.sessionTrolleys) trolleys"
        }
        let picking = notes.filter { $0.state == .picking && $0.picker == picker.name }
        guard !picking.isEmpty else { return nil }
        let trolleys = Set(picking.compactMap(\.trolley)).count
        return "\(picking.count) \(picking.count == 1 ? "order" : "orders") · \(trolleys) \(trolleys == 1 ? "trolley" : "trolleys")"
    }
}
