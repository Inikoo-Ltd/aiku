import Foundation

protocol APIResponseObserving: Sendable {
    func observe(request: URLRequest, response: HTTPURLResponse, data: Data)
}

final class OfflineNetworkObserver: APIResponseObserving, @unchecked Sendable {
    static let shared = OfflineNetworkObserver()

    private let clock: ServerClock
    private let downloads: OfflineDownloads
    private let sessions: OfflineSessionBox
    private let savedLists: SavedLists
    private let lock = NSLock()
    private var prefetch: (@Sendable (DownloadPrefetch) -> Void)?

    init(clock: ServerClock = .shared, downloads: OfflineDownloads = .shared, sessions: OfflineSessionBox = .shared, savedLists: SavedLists = .shared) {
        self.clock = clock
        self.downloads = downloads
        self.sessions = sessions
        self.savedLists = savedLists
    }

    func onPrefetch(_ handler: @escaping @Sendable (DownloadPrefetch) -> Void) {
        lock.withLock { prefetch = handler }
    }

    func observe(request: URLRequest, response: HTTPURLResponse, data: Data) {
        let dateHeader = response.value(forHTTPHeaderField: "Date")
        clock.record(dateHeader: dateHeader)
        guard let url = request.url, let route = OfflineRoutes.mobileRoute(of: url) else { return }
        guard let token = request.value(forHTTPHeaderField: "Authorization")?.replacingOccurrences(of: "Bearer ", with: ""),
              !token.isEmpty,
              let userID = sessions.userID(forToken: token) else { return }
        let seenAt = ServerClockHeader.date(dateHeader)

        if response.statusCode == 401 {
            downloads.clear()
            savedLists.clear()
        } else if request.httpMethod == HTTPMethod.get.rawValue {
            recordRead(userID: userID, token: token, route: route, response: response, data: data, seenAt: seenAt)
        } else if (200..<300).contains(response.statusCode) {
            recordWrite(userID: userID, route: route, data: data, seenAt: seenAt)
        }
    }

    private func recordRead(userID: Int, token: String, route: [String], response: HTTPURLResponse, data: Data, seenAt: Date?) {
        if let record = OfflineRoutes.detailRecord(of: route) {
            switch response.statusCode {
            case 200: downloads.replace(userID: userID, record: record, body: data, seenAt: seenAt)
            case 403, 404: downloads.forget(userID: userID, record: record)
            default: break
            }
            return
        }
        guard response.statusCode == 200 else { return }
        if route.count == 3, route[0] == "warehouses", let warehouseID = Int(route[1]), let kind = SavedListKind(rawValue: route[2]) {
            savedLists.remember(userID: userID, warehouseID: warehouseID, kind: kind, page: data)
        }
        if let warehouseID = OfflineRoutes.warehouseList(of: route, named: "my-work") {
            let notes = Self.rows(in: data)
                .filter { $0.kind == "delivery_note" && Self.offlineNoteStates.contains($0.state ?? "") }
                .map(\.id)
            schedulePrefetch(token: token, userID: userID, warehouseID: warehouseID, type: .deliveryNote, ids: Array(Self.unique(notes).prefix(Self.maxPrefetchedNotes)))
        } else if let warehouseID = OfflineRoutes.warehouseList(of: route, named: "returns") {
            let returns = Self.rows(in: data)
                .filter { $0.state == "returning" && $0.handler?.id == userID }
                .map(\.id)
            schedulePrefetch(token: token, userID: userID, warehouseID: warehouseID, type: .customerReturn, ids: Self.unique(returns))
        }
    }

    private func recordWrite(userID: Int, route: [String], data: Data, seenAt: Date?) {
        guard let record = OfflineRoutes.record(under: route),
              let answered = try? JSONDecoder().decode(AnsweredID.self, from: data),
              answered.data?.id == record.id else { return }
        downloads.replace(userID: userID, record: record, body: data, seenAt: seenAt)
    }

    private func schedulePrefetch(token: String, userID: Int, warehouseID: Int, type: OfflineRecordType, ids: [Int]) {
        guard !ids.isEmpty, let handler = lock.withLock({ prefetch }) else { return }
        handler(DownloadPrefetch(token: token, userID: userID, records: ids.map { (OfflineRecordKey(type: type, id: $0), warehouseID) }))
    }

    private struct AnsweredID: Decodable {
        struct Record: Decodable { let id: Int? }
        let data: Record?
    }

    private struct ListRow: Decodable {
        struct Handler: Decodable { let id: Int? }
        let id: Int
        let kind: String?
        let state: String?
        let handler: Handler?
    }

    private struct ListPage: Decodable {
        let data: [Lenient]
    }

    private struct Lenient: Decodable {
        let row: ListRow?

        init(from decoder: Decoder) throws {
            row = try? ListRow(from: decoder)
        }
    }

    private static func rows(in data: Data) -> [ListRow] {
        ((try? JSONDecoder().decode(ListPage.self, from: data))?.data ?? []).compactMap(\.row)
    }

    private static func unique(_ ids: [Int]) -> [Int] {
        var seen = Set<Int>()
        return ids.filter { seen.insert($0).inserted }
    }

    static let offlineNoteStates: Set<String> = ["handling", "handling_blocked", "packing"]
    static let maxPrefetchedNotes = 30
}

struct DownloadPrefetch: Sendable {
    let token: String
    let userID: Int
    let records: [(OfflineRecordKey, Int)]
}

enum ServerClockHeader {
    static func date(_ header: String?) -> Date? {
        guard let header else { return nil }
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.timeZone = TimeZone(identifier: "GMT")
        formatter.dateFormat = "EEE, dd MMM yyyy HH:mm:ss zzz"
        return formatter.date(from: header)
    }
}
