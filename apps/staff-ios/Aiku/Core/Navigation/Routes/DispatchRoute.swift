import Foundation

enum DispatchRoute: Hashable, Sendable {
    case pickingSessions
    case pickingSession(reference: String)
    case shippers
    case trolleys
    case pickedBays
    case pickingSessionPick(reference: String)
    case pickingSessionPack(reference: String)
    case pickingSessionID(id: Int)
    case pickingSessionPickID(id: Int)
    case pickingSessionPackID(id: Int)
}
