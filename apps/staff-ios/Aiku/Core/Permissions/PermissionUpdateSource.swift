import Foundation

protocol PermissionUpdateSource: Sendable {
    func updates(forUserID userID: Int) -> AsyncStream<Void>
}

struct NoPermissionUpdates: PermissionUpdateSource {
    func updates(forUserID userID: Int) -> AsyncStream<Void> {
        AsyncStream { $0.finish() }
    }
}
