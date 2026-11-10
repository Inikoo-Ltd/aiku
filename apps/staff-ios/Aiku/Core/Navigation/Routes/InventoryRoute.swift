import Foundation

enum InventoryRoute: Hashable, Sendable {
    case dashboard
    case families
    case family(code: String)
    case skos
    case abnormalities
    case sko(reference: String)
    case stockHistory
    case skoHistory(reference: String)
}
