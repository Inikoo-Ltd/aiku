import Foundation

enum GoodsOutRoute: Hashable, Sendable {
    case backlog
    case deliveryNotes
    case deliveryNote(reference: String)
    case fastPicking(reference: String)
    case packingQueue
    case scanToPick(reference: String)
    case addShipment(reference: String)
    case manualShipment(reference: String)
    case askCRM(reference: String)
    case tariffCodes(reference: String)
    case customsFile(reference: String)
    case dispatchedEmails(reference: String)
}
