import Foundation

struct LivePermissionUpdates: PermissionUpdateSource {
    let live: LiveUpdates

    func updates(forUserID userID: Int) -> AsyncStream<Void> {
        let live = live
        return AsyncStream { continuation in
            let listening = Task { @MainActor in
                for await _ in live.settledEvents(on: LiveChannelName.personal(userID: userID), named: LiveEventName.permissions) {
                    continuation.yield()
                }
                continuation.finish()
            }
            continuation.onTermination = { _ in listening.cancel() }
        }
    }
}
