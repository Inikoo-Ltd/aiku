import Foundation

enum ScreenCacheKey {
    static let clocking = "clocking"
    static let workBadgesPrefix = "work_badges."

    static func workBadges(warehouseID: Int) -> String {
        workBadgesPrefix + String(warehouseID)
    }
}

enum ScreenCacheDrop: Equatable {
    case nothing
    case clocking
    case warehouseWork

    static let unaffectedRoutes: Set<String> = ["profile", "password", "chat", "login", "logout", "app-version", "permissions"]

    static func after(writeTo route: [String]) -> ScreenCacheDrop {
        guard let first = route.first else { return .nothing }
        if first == "clocking" { return .clocking }
        return unaffectedRoutes.contains(first) ? .nothing : .warehouseWork
    }
}

final class ScreenCache: @unchecked Sendable {
    static let shared = ScreenCache(fileURL: URL.applicationSupportDirectory.appending(path: "ScreenCache/screens.json"))
    static let keptUnopenedFor: TimeInterval = 7 * 86_400

    private struct Entry: Codable {
        var data: Data
        var savedAt: Date
        var openedAt: Date
    }

    private struct Snapshot: Codable {
        var userID: Int
        var entries: [String: Entry]
    }

    private let fileURL: URL
    private let now: @Sendable () -> Date
    private let lock = NSLock()
    private var snapshot: Snapshot?

    init(fileURL: URL, now: @escaping @Sendable () -> Date = { .now }) {
        self.fileURL = fileURL
        self.now = now
    }

    func signedIn(userID: Int) {
        lock.withLock {
            let stored = snapshot ?? storedSnapshot()
            let cutoff = now().addingTimeInterval(-Self.keptUnopenedFor)
            let kept = stored?.userID == userID ? (stored?.entries ?? [:]).filter { $0.value.openedAt >= cutoff } : [:]
            snapshot = Snapshot(userID: userID, entries: kept)
            persist()
        }
    }

    func read<Value: Decodable>(_ type: Value.Type, key: String) -> (value: Value, savedAt: Date)? {
        lock.withLock {
            guard var current = snapshot, var entry = current.entries[key] else { return nil }
            guard let value = try? JSONDecoder().decode(type, from: entry.data) else {
                current.entries[key] = nil
                snapshot = current
                persist()
                return nil
            }
            entry.openedAt = now()
            current.entries[key] = entry
            snapshot = current
            persist()
            return (value, entry.savedAt)
        }
    }

    func save<Value: Encodable>(_ value: Value, key: String) {
        lock.withLock {
            guard var current = snapshot, let data = try? JSONEncoder().encode(value) else { return }
            let savedAt = now()
            current.entries[key] = Entry(data: data, savedAt: savedAt, openedAt: savedAt)
            snapshot = current
            persist()
        }
    }

    func remove(_ key: String) {
        removeAll { $0 == key }
    }

    func keepWorkBadges(forWarehouses warehouseIDs: Set<Int>) {
        let kept = Set(warehouseIDs.map { ScreenCacheKey.workBadges(warehouseID: $0) })
        removeAll { $0.hasPrefix(ScreenCacheKey.workBadgesPrefix) && !kept.contains($0) }
    }

    func forget(afterWriteTo route: [String]) {
        switch ScreenCacheDrop.after(writeTo: route) {
        case .nothing:
            return
        case .clocking:
            remove(ScreenCacheKey.clocking)
        case .warehouseWork:
            removeAll { $0.hasPrefix(ScreenCacheKey.workBadgesPrefix) }
        }
    }

    func clear() {
        lock.withLock {
            snapshot = nil
            try? FileManager.default.removeItem(at: fileURL)
        }
    }

    private func removeAll(where shouldRemove: (String) -> Bool) {
        lock.withLock {
            guard var current = snapshot else { return }
            let removed = current.entries.keys.filter(shouldRemove)
            guard !removed.isEmpty else { return }
            removed.forEach { current.entries[$0] = nil }
            snapshot = current
            persist()
        }
    }

    private func storedSnapshot() -> Snapshot? {
        guard let data = try? Data(contentsOf: fileURL) else { return nil }
        return try? JSONDecoder().decode(Snapshot.self, from: data)
    }

    private func persist() {
        guard let snapshot, let data = try? JSONEncoder().encode(snapshot) else { return }
        try? FileManager.default.createDirectory(at: fileURL.deletingLastPathComponent(), withIntermediateDirectories: true)
        try? data.write(to: fileURL, options: [.atomic, .completeFileProtectionUntilFirstUserAuthentication])
        var excludedFromBackup = URLResourceValues()
        excludedFromBackup.isExcludedFromBackup = true
        var fileURL = fileURL
        try? fileURL.setResourceValues(excludedFromBackup)
    }
}

enum LastUpdatedText {
    static func label(for updatedAt: Date, now: Date = .now, timeZone: TimeZone = .current, locale: Locale = .current) -> String {
        var calendar = Calendar(identifier: .gregorian)
        calendar.timeZone = timeZone
        let formatter = DateFormatter()
        formatter.locale = locale
        formatter.timeZone = timeZone
        formatter.dateFormat = calendar.isDate(updatedAt, inSameDayAs: now) ? "HH:mm" : "d MMM HH:mm"
        return "Last updated \(formatter.string(from: updatedAt))"
    }
}
