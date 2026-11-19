import Foundation

enum GoodsOutRoute: Hashable, Sendable {
    case backlog
    case deliveryNotes
    case deliveryNotesIn(state: GoodsOutDeliveryNoteState)
    case deliveryNote(reference: String)
    case deliveryNoteID(id: Int)
    case fastPicking(reference: String)
    case scanToPick(reference: String)
    case addShipment(reference: String)
    case askCRM(reference: String)
}
