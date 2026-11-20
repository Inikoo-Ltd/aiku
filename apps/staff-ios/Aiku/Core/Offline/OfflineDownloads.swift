import Foundation

struct DownloadedRecord: Sendable {
    let body: Data
    let seenAt: Date
    let downloadedAt: Date
}

final class OfflineDownloads: @unchecked Sendable {
    static let shared = OfflineDownloads()

    private struct Stored: Codable {
        let body: Data
        let seenAt: Date
        let downloadedAt: Date
        var lastOpenedAt: Date
    }

    private let root: URL
    private let clock: @Sendable () -> Date
    private let lock = NSLock()
    private var lastCleanUp: Date?

    init(root: URL = OfflineFiles.directory("downloads"), clock: @escaping @Sendable () -> Date = { Date() }) {
        self.root = root
        self.clock = clock
    }

    func read(userID: Int, record: OfflineRecordKey) -> DownloadedRecord? {
        lock.withLock {
            cleanUpIfDue(keeping: userID)
            let url = fileURL(userID: userID, record: record)
            guard var stored = stored(at: url) else {
                try? FileManager.default.removeItem(at: url)
                return nil
            }
            stored.lastOpenedAt = clock()
            save(stored, to: url)
            return DownloadedRecord(body: stored.body, seenAt: stored.seenAt, downloadedAt: stored.downloadedAt)
        }
    }

    func downloadedAt(userID: Int, record: OfflineRecordKey) -> Date? {
        lock.withLock { stored(at: fileURL(userID: userID, record: record))?.downloadedAt }
    }

    func replace(userID: Int, record: OfflineRecordKey, body: Data, seenAt: Date?) {
        lock.withLock {
            let url = fileURL(userID: userID, record: record)
            let now = clock()
            let opened = stored(at: url)?.lastOpenedAt ?? now
            save(Stored(body: body, seenAt: seenAt ?? now, downloadedAt: now, lastOpenedAt: opened), to: url)
        }
    }

    func forget(userID: Int, record: OfflineRecordKey) {
        lock.withLock { _ = try? FileManager.default.removeItem(at: fileURL(userID: userID, record: record)) }
    }

    func keepOnly(userID: Int) {
        lock.withLock { deleteOtherUsers(than: userID) }
    }

    func clear() {
        lock.withLock {
            let folders = (try? FileManager.default.contentsOfDirectory(at: root, includingPropertiesForKeys: nil)) ?? []
            folders.forEach { try? FileManager.default.removeItem(at: $0) }
        }
    }

    private func cleanUpIfDue(keeping userID: Int) {
        let now = clock()
        if let lastCleanUp, now < lastCleanUp.addingTimeInterval(Self.cleanUpInterval) {
            return
        }
        lastCleanUp = now
        deleteOtherUsers(than: userID)
        let cutOff = now.addingTimeInterval(-Self.keepUnopenedFor)
        let folder = root.appending(path: String(userID), directoryHint: .isDirectory)
        let files = (try? FileManager.default.contentsOfDirectory(at: folder, includingPropertiesForKeys: nil)) ?? []
        for file in files where (stored(at: file)?.lastOpenedAt ?? .distantPast) < cutOff {
            try? FileManager.default.removeItem(at: file)
        }
    }

    private func deleteOtherUsers(than userID: Int) {
        let folders = (try? FileManager.default.contentsOfDirectory(at: root, includingPropertiesForKeys: nil)) ?? []
        for folder in folders where folder.lastPathComponent != String(userID) {
            try? FileManager.default.removeItem(at: folder)
        }
    }

    private func fileURL(userID: Int, record: OfflineRecordKey) -> URL {
        root.appending(path: String(userID), directoryHint: .isDirectory).appending(path: "\(record.type.rawValue)-\(record.id).json")
    }

    private func stored(at url: URL) -> Stored? {
        (try? Data(contentsOf: url)).flatMap { try? OfflineFiles.decoder.decode(Stored.self, from: $0) }
    }

    private func save(_ stored: Stored, to url: URL) {
        try? FileManager.default.createDirectory(at: url.deletingLastPathComponent(), withIntermediateDirectories: true)
        guard let data = try? OfflineFiles.encoder.encode(stored) else { return }
        OfflineFiles.write(data, to: url)
    }

    private static let keepUnopenedFor: TimeInterval = 7 * 24 * 60 * 60
    private static let cleanUpInterval: TimeInterval = 24 * 60 * 60
}
