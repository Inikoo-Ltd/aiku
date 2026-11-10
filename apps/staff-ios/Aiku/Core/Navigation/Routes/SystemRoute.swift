import Foundation

enum SystemRoute: Hashable, Sendable {
    case syncQueue
    case conflicts
    case conflict(reference: String)
    case statesGallery
    case onlineOnlyAction
    case offline
    case sessionExpired
    case noAccess
    case notFound
    case serverError
    case cameraOff
    case emptySearch
    case emptyTasks
    case loadingDashboard
    case actionFailed
}
