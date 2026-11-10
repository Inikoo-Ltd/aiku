import Foundation

enum DispatchRoute: Hashable, Sendable {
    case pickingSessions
    case pickingSession(reference: String)
    case shippers
    case trolleys
    case pickedBays
    case pickingSessionPick(reference: String)
    case pickingSessionPack(reference: String)
}
