import Foundation

enum LocationsRoute: Hashable, Sendable {
    case warehouse
    case areas
    case area(code: String)
    case locations
    case location(code: String)
    case skoInLocation(sko: String, location: String)
    case emptySlots(LocationsEmptySlotsKind)
}
