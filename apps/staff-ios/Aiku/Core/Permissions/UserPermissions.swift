import Foundation

struct UserPermissions: Codable, Equatable, Sendable {
    let userID: Int
    let permissions: Set<String>
    let roles: [String]
    let fetchedAt: Date

    func allows(_ permission: String) -> Bool {
        permissions.contains(permission)
    }

    func allowsAny(_ candidates: [String]) -> Bool {
        candidates.contains(where: permissions.contains)
    }

    func allowsAny(withPrefix prefix: String) -> Bool {
        permissions.contains { $0.hasPrefix(prefix) }
    }
}

struct MobilePermissionsResponse: Decodable, Sendable {
    let userId: Int
    let permissions: [String]
    let roles: [String]
}
