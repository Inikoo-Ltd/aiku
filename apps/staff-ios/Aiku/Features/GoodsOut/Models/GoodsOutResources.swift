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
    var apiID: Int?
    var heldByNoteReference: String?

    var id: String { code }
}

struct GoodsOutPickedBay: Identifiable, Hashable, Sendable {
    let name: String
    var otherNotes = 0
    var apiID: Int?
    var noteReferences: [String] = []

    var id: String { name }
}

struct GoodsOutPickerProfile: Identifiable, Hashable, Sendable {
    let name: String
    var session: String?
    var sessionOrders = 0
    var sessionTrolleys = 0
    var userID: Int?

    var id: String { name }
}

struct GoodsOutStockLocation: Identifiable, Hashable, Sendable {
    let code: String
    let kind: String
    let stock: Int
    var locationOrgStockID: Int?
    var stockLabel: String?

    var id: String { code }
    var isEmpty: Bool { stock <= 0 }
    @MainActor
    var stockText: String {
        if isEmpty { return L10n.text("Empty") }
        return stockLabel.map { L10n.format("%@ in stock", $0) } ?? L10n.format("%lld stocks", stock)
    }
}

struct GoodsOutChoice: Identifiable, Hashable, Sendable {
    let id: String
    let title: String
    let subtitle: String
    var isAvailable = true
}

@MainActor
enum GoodsOutResources {
    static func trolleyChoices(_ trolleys: [GoodsOutTrolley], notes: [GoodsOutDeliveryNote], for reference: String) -> [GoodsOutChoice] {
        trolleys.map { trolley in
            if let held = notes.first(where: { $0.trolley == trolley.code && $0.reference != reference && ($0.state == .picking || $0.state == .todo) }) {
                return GoodsOutChoice(id: trolley.code, title: trolley.code, subtitle: L10n.format("Delivery note %@", held.reference), isAvailable: false)
            }
            if let heldBy = trolley.heldBy {
                return GoodsOutChoice(id: trolley.code, title: trolley.code, subtitle: heldBy, isAvailable: false)
            }
            return GoodsOutChoice(id: trolley.code, title: trolley.code, subtitle: L10n.text("Free"))
        }
    }

    static func bayChoices(_ bays: [GoodsOutPickedBay], notes: [GoodsOutDeliveryNote], for reference: String) -> [GoodsOutChoice] {
        bays.map { bay in
            let count = bay.otherNotes + notes.filter { $0.pickedBay == bay.name && $0.reference != reference && ($0.state == .picked || $0.state == .waiting) }.count
            let subtitle = count == 0 ? L10n.text("Free") : L10n.format("%lld delivery notes", count)
            return GoodsOutChoice(id: bay.name, title: bay.name, subtitle: subtitle)
        }
    }

    static func pickerChoices(_ pickers: [GoodsOutPickerProfile], notes: [GoodsOutDeliveryNote], currentUser: String) -> [GoodsOutChoice] {
        pickers.map { picker in
            let title = picker.name == currentUser ? L10n.format("%@ (you)", picker.name) : picker.name
            if let session = picker.session {
                return GoodsOutChoice(id: picker.name, title: title, subtitle: L10n.format("Picking session %@", session))
            }
            let count = notes.filter { $0.state == .picking && $0.picker == picker.name }.count
            let subtitle = count == 0 ? L10n.text("No current work") : L10n.format("Picking %lld orders", count)
            return GoodsOutChoice(id: picker.name, title: title, subtitle: subtitle)
        }
    }

    static func pickerActivity(_ picker: GoodsOutPickerProfile, notes: [GoodsOutDeliveryNote]) -> String? {
        if let session = picker.session {
            return L10n.format("Picking session %@ · %lld orders · %lld trolleys", session, picker.sessionOrders, picker.sessionTrolleys)
        }
        let picking = notes.filter { $0.state == .picking && $0.picker == picker.name }
        guard !picking.isEmpty else { return nil }
        let trolleys = Set(picking.compactMap(\.trolley)).count
        return L10n.format("%lld orders · %lld trolleys", picking.count, trolleys)
    }

    static func remoteTrolleyChoices(_ trolleys: [GoodsOutTrolley], for reference: String) -> [GoodsOutChoice] {
        trolleys.map { trolley in
            if let held = trolley.heldByNoteReference, held != reference {
                return GoodsOutChoice(id: trolley.code, title: trolley.code, subtitle: L10n.format("Delivery note %@", held), isAvailable: false)
            }
            if trolley.heldByNoteReference == reference {
                return GoodsOutChoice(id: trolley.code, title: trolley.code, subtitle: L10n.text("On this delivery note"))
            }
            if let heldBy = trolley.heldBy {
                return GoodsOutChoice(id: trolley.code, title: trolley.code, subtitle: heldBy, isAvailable: false)
            }
            return GoodsOutChoice(id: trolley.code, title: trolley.code, subtitle: L10n.text("Free"))
        }
    }

    static func remoteBayChoices(_ bays: [GoodsOutPickedBay], for reference: String) -> [GoodsOutChoice] {
        bays.map { bay in
            let count = max(0, bay.otherNotes - (bay.noteReferences.contains(reference) ? 1 : 0))
            let subtitle = count == 0 ? L10n.text("Free") : L10n.format("%lld delivery notes", count)
            return GoodsOutChoice(id: bay.name, title: bay.name, subtitle: subtitle)
        }
    }

    static func remotePickerChoices(_ pickers: [GoodsOutPickerProfile], activity: [GoodsOutPersonActivity], currentUser: String) -> [GoodsOutChoice] {
        pickers.compactMap { picker in
            guard let userID = picker.userID else { return nil }
            let title = picker.name == currentUser ? L10n.format("%@ (you)", picker.name) : picker.name
            let subtitle = activity.first { $0.id == userID }?.subtitle ?? L10n.text("No current work")
            return GoodsOutChoice(id: String(userID), title: title, subtitle: subtitle)
        }
    }
}
