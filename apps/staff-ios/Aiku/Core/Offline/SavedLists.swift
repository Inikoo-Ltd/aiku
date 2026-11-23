import Foundation

enum SavedListKind: String, CaseIterable, Sendable {
    case deliveryNotes = "delivery-notes"
    case pickingSessions = "picking-sessions"
    case palletReturns = "pallet-returns"
    case stockDeliveries = "stock-deliveries"
    case palletDeliveries = "pallet-deliveries"
    case returns = "returns"

    var nestedRowsKey: String? {
        self == .deliveryNotes ? "delivery_notes" : nil
    }
}

struct SavedRows<Row: Sendable>: Sendable {
    let rows: [Row]
    let savedAt: Date
}

final class SavedLists: @unchecked Sendable {
    static let shared = SavedLists()
    static let maxRows = 500

    private struct Stored: Codable {
        var rows: [Data]
        var savedAt: Date
        var lastOpenedAt: Date
    }

    private let root: URL
    private let clock: @Sendable () -> Date
    private let lock = NSLock()

    init(root: URL = OfflineFiles.directory("lists"), clock: @escaping @Sendable () -> Date = { Date() }) {
        self.root = root
        self.clock = clock
    }

    func remember(userID: Int, warehouseID: Int, kind: SavedListKind, page body: Data) {
        guard let object = try? JSONSerialization.jsonObject(with: body) as? [String: Any],
              let rows = Self.rows(in: object["data"], nestedUnder: kind.nestedRowsKey) else { return }
        let seen = rows.compactMap { row -> (Int, Data)? in
            guard let id = Self.id(of: row), let data = try? JSONSerialization.data(withJSONObject: row) else { return nil }
            return (id, data)
        }
        guard !seen.isEmpty else { return }
        lock.withLock {
            let url = fileURL(userID: userID, warehouseID: warehouseID, kind: kind)
            let earlier = stored(at: url)?.rows ?? []
            var ids = Set<Int>()
            var merged: [Data] = []
            for (id, data) in seen where ids.insert(id).inserted {
                merged.append(data)
            }
            for data in earlier {
                guard merged.count < Self.maxRows else { break }
                guard let id = Self.id(of: data), ids.insert(id).inserted else { continue }
                merged.append(data)
            }
            let now = clock()
            save(Stored(rows: Array(merged.prefix(Self.maxRows)), savedAt: now, lastOpenedAt: now), to: url)
        }
    }

    func rows<Row: Decodable & Sendable>(_ type: Row.Type, userID: Int, warehouseID: Int, kind: SavedListKind) -> SavedRows<Row>? {
        lock.withLock {
            cleanUp(keeping: userID)
            let url = fileURL(userID: userID, warehouseID: warehouseID, kind: kind)
            guard var stored = stored(at: url) else { return nil }
            stored.lastOpenedAt = clock()
            save(stored, to: url)
            let decoder = JSONDecoder()
            decoder.keyDecodingStrategy = .convertFromSnakeCase
            let rows = stored.rows.compactMap { try? decoder.decode(Row.self, from: $0) }
            return SavedRows(rows: rows, savedAt: stored.savedAt)
        }
    }

    func clear() {
        lock.withLock {
            let folders = (try? FileManager.default.contentsOfDirectory(at: root, includingPropertiesForKeys: nil)) ?? []
            folders.forEach { try? FileManager.default.removeItem(at: $0) }
        }
    }

    private func cleanUp(keeping userID: Int) {
        let folders = (try? FileManager.default.contentsOfDirectory(at: root, includingPropertiesForKeys: nil)) ?? []
        for folder in folders where folder.lastPathComponent != String(userID) {
            try? FileManager.default.removeItem(at: folder)
        }
        let cutOff = clock().addingTimeInterval(-Self.keepUnopenedFor)
        let mine = root.appending(path: String(userID), directoryHint: .isDirectory)
        let files = (try? FileManager.default.contentsOfDirectory(at: mine, includingPropertiesForKeys: nil)) ?? []
        for file in files where (stored(at: file)?.lastOpenedAt ?? .distantPast) < cutOff {
            try? FileManager.default.removeItem(at: file)
        }
    }

    private func fileURL(userID: Int, warehouseID: Int, kind: SavedListKind) -> URL {
        root.appending(path: String(userID), directoryHint: .isDirectory).appending(path: "\(warehouseID)-\(kind.rawValue).json")
    }

    private func stored(at url: URL) -> Stored? {
        (try? Data(contentsOf: url)).flatMap { try? OfflineFiles.decoder.decode(Stored.self, from: $0) }
    }

    private func save(_ stored: Stored, to url: URL) {
        try? FileManager.default.createDirectory(at: url.deletingLastPathComponent(), withIntermediateDirectories: true)
        guard let data = try? OfflineFiles.encoder.encode(stored) else { return }
        OfflineFiles.write(data, to: url)
    }

    private static func rows(in data: Any?, nestedUnder key: String?) -> [[String: Any]]? {
        if let rows = data as? [[String: Any]] { return rows }
        guard let key, let wrapper = data as? [String: Any] else { return nil }
        return wrapper[key] as? [[String: Any]]
    }

    private static func id(of row: [String: Any]) -> Int? {
        if let id = row["id"] as? Int { return id }
        if let id = row["id"] as? Double { return Int(id) }
        if let id = row["id"] as? String { return Int(id) }
        return nil
    }

    private static func id(of data: Data) -> Int? {
        (try? JSONSerialization.jsonObject(with: data) as? [String: Any]).flatMap { id(of: $0) }
    }

    private static let keepUnopenedFor: TimeInterval = 7 * 24 * 60 * 60
}

enum SavedListFilter {
    static func matches(_ search: String, _ fields: String?...) -> Bool {
        let wanted = search.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !wanted.isEmpty else { return true }
        return fields.contains { $0?.localizedCaseInsensitiveContains(wanted) == true }
    }

    static func day(_ value: String?, in timeZone: TimeZone = .current) -> Date? {
        guard let text = value?.trimmingCharacters(in: .whitespacesAndNewlines), !text.isEmpty else { return nil }
        if let instant = ListDates.instant(text) {
            return Calendar.gregorian(in: timeZone).startOfDay(for: instant)
        }
        return ListDates.day(String(text.prefix(10)), in: timeZone)
    }

    static func within(_ value: String?, range: ListDateRange, in timeZone: TimeZone = .current) -> Bool {
        guard range.isSet else { return true }
        guard let day = day(value, in: timeZone) else { return false }
        let calendar = Calendar.gregorian(in: timeZone)
        if let from = range.from, day < calendar.startOfDay(for: from) { return false }
        if let to = range.to, day > calendar.startOfDay(for: to) { return false }
        return true
    }

    static func sorted<Row, Key: Comparable>(_ rows: [Row], descending: Bool, id: (Row) -> Int, key: (Row) -> Key?) -> [Row] {
        rows.sorted { lhs, rhs in
            switch (key(lhs), key(rhs)) {
            case let (left?, right?) where left != right:
                return descending ? left > right : left < right
            case (.some, nil):
                return true
            case (nil, .some):
                return false
            default:
                return descending ? id(lhs) > id(rhs) : id(lhs) < id(rhs)
            }
        }
    }
}
