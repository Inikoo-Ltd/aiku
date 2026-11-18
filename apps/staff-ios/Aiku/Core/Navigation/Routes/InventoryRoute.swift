import Foundation

enum InventoryRoute: Hashable, Sendable {
    case dashboard
    case families
    case family(code: String)
    case skos
    case skosIn(scope: InventorySkoScope)
    case abnormalities
    case sko(reference: String)
    case skoHistory(reference: String)
    case skoID(id: Int)
    case skoHistoryID(id: Int, reference: String)
}
