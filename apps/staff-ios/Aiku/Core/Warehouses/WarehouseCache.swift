import Foundation

protocol WarehouseCaching: Sendable {
    func load(for userID: Int) -> [MobileWarehouse]?
    func save(_ warehouses: [MobileWarehouse], for userID: Int) throws
    func clear()
}

struct FileWarehouseCache: WarehouseCaching {
    private struct Snapshot: Codable {
        let userID: Int
        let warehouses: [MobileWarehouse]
    }

    let fileURL: URL

    static let standard = FileWarehouseCache(
        fileURL: URL.applicationSupportDirectory.appending(path: "Warehouses/warehouses.json")
    )

    func load(for userID: Int) -> [MobileWarehouse]? {
        guard let data = try? Data(contentsOf: fileURL),
              let snapshot = try? JSONDecoder().decode(Snapshot.self, from: data),
              snapshot.userID == userID else { return nil }
        return snapshot.warehouses
    }

    func save(_ warehouses: [MobileWarehouse], for userID: Int) throws {
        let directory = fileURL.deletingLastPathComponent()
        try FileManager.default.createDirectory(at: directory, withIntermediateDirectories: true)
        try JSONEncoder().encode(Snapshot(userID: userID, warehouses: warehouses))
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
