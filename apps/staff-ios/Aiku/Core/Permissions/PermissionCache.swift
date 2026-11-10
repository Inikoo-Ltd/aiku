import Foundation

protocol PermissionCaching: Sendable {
    func load() -> UserPermissions?
    func save(_ permissions: UserPermissions) throws
    func clear()
}

struct FilePermissionCache: PermissionCaching {
    let fileURL: URL

    static let standard = FilePermissionCache(
        fileURL: URL.applicationSupportDirectory.appending(path: "Permissions/permissions.json")
    )

    func load() -> UserPermissions? {
        guard let data = try? Data(contentsOf: fileURL) else { return nil }
        return try? JSONDecoder.permissions.decode(UserPermissions.self, from: data)
    }

    func save(_ permissions: UserPermissions) throws {
        let directory = fileURL.deletingLastPathComponent()
        try FileManager.default.createDirectory(at: directory, withIntermediateDirectories: true)
        try JSONEncoder.permissions.encode(permissions).write(to: fileURL, options: [.atomic, .completeFileProtectionUntilFirstUserAuthentication])

        var excludedFromBackup = URLResourceValues()
        excludedFromBackup.isExcludedFromBackup = true
        var fileURL = fileURL
        try fileURL.setResourceValues(excludedFromBackup)
    }

    func clear() {
        try? FileManager.default.removeItem(at: fileURL)
    }
}

private extension JSONEncoder {
    static var permissions: JSONEncoder {
        let encoder = JSONEncoder()
        encoder.dateEncodingStrategy = .iso8601
        return encoder
    }
}

private extension JSONDecoder {
    static var permissions: JSONDecoder {
        let decoder = JSONDecoder()
        decoder.dateDecodingStrategy = .iso8601
        return decoder
    }
}
