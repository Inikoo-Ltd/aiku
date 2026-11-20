import Foundation

final class OfflineSessionBox: @unchecked Sendable {
    static let shared = OfflineSessionBox()

    struct Current: Equatable, Sendable {
        let userID: Int
        let token: String
    }

    private let lock = NSLock()
    private var stored: Current?

    var current: Current? { lock.withLock { stored } }

    func set(_ current: Current?) {
        lock.withLock { stored = current }
    }

    func userID(forToken token: String) -> Int? {
        lock.withLock { stored?.token == token ? stored?.userID : nil }
    }
}
