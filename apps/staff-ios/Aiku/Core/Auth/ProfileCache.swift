import Foundation

protocol ProfileCaching: Sendable {
    func load() -> MobileProfileResponse?
    func save(_ profile: MobileProfileResponse) throws
    func clear()
}

struct NoProfileCache: ProfileCaching {
    func load() -> MobileProfileResponse? { nil }
    func save(_ profile: MobileProfileResponse) throws {}
    func clear() {}
}

struct FileProfileCache: ProfileCaching {
    let fileURL: URL

    static let standard = FileProfileCache(
        fileURL: URL.applicationSupportDirectory.appending(path: "Profile/profile.json")
    )

    func load() -> MobileProfileResponse? {
        guard let data = try? Data(contentsOf: fileURL) else { return nil }
        return try? JSONDecoder().decode(MobileProfileResponse.self, from: data)
    }

    func save(_ profile: MobileProfileResponse) throws {
        try FileManager.default.createDirectory(at: fileURL.deletingLastPathComponent(), withIntermediateDirectories: true)
        try JSONEncoder().encode(profile)
            .write(to: fileURL, options: [.atomic, .completeFileProtectionUntilFirstUserAuthentication])

        var excludedFromBackup = URLResourceValues()
        excludedFromBackup.isExcludedFromBackup = true
        var fileURL = fileURL
        try fileURL.setResourceValues(excludedFromBackup)
    }

    func clear() {
        try? FileManager.default.removeItem(at: fileURL)
    }
}
