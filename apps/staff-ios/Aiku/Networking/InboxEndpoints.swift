import Foundation

struct MobileInboxTarget: Sendable, Equatable {
    let kind: MobileMyWorkKind
    let id: Int
    var reference = ""
    var state = ""
    var warehouseID: Int?
    var typeGroup: String?
}

struct MobileInboxNotification: Decodable, Sendable, Equatable, Identifiable {
    let id: String
    var kind: String
    var event: String
    var title: String
    var body: String
    var createdAt: String?
    var readAt: String?
    var target: MobileInboxTarget?

    var isUnread: Bool { readAt == nil }

    init(
        id: String,
        kind: String = "",
        event: String = "",
        title: String = "",
        body: String = "",
        createdAt: String? = nil,
        readAt: String? = nil,
        target: MobileInboxTarget? = nil
    ) {
        self.id = id
        self.kind = kind
        self.event = event
        self.title = title
        self.body = body
        self.createdAt = createdAt
        self.readAt = readAt
        self.target = target
    }

    private enum CodingKeys: String, CodingKey {
        case id, kind, event, title, body, createdAt, readAt, target
    }

    private struct WireTarget: Decodable {
        var screen: String?
        var id: Int?
        var reference: String?
        var state: String?
        var warehouseId: Int?
        var typeGroup: String?

        private enum CodingKeys: String, CodingKey {
            case screen, id, reference, state, warehouseId, typeGroup
        }

        init(from decoder: Decoder) throws {
            let container = try decoder.container(keyedBy: CodingKeys.self)
            screen = try? container.decodeIfPresent(String.self, forKey: .screen)
            id = container.inboxInt(.id)
            reference = try? container.decodeIfPresent(String.self, forKey: .reference)
            state = try? container.decodeIfPresent(String.self, forKey: .state)
            warehouseId = container.inboxInt(.warehouseId)
            typeGroup = try? container.decodeIfPresent(String.self, forKey: .typeGroup)
        }

        var target: MobileInboxTarget? {
            guard let kind = screen.flatMap(MobileMyWorkKind.init(rawValue:)), let id else { return nil }
            return MobileInboxTarget(
                kind: kind,
                id: id,
                reference: reference ?? "",
                state: state ?? "",
                warehouseID: warehouseId.flatMap { $0 > 0 ? $0 : nil },
                typeGroup: typeGroup.inboxTrimmed
            )
        }
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        guard let id = (try? container.decodeIfPresent(String.self, forKey: .id)).inboxTrimmed else {
            throw DecodingError.dataCorruptedError(forKey: .id, in: container, debugDescription: "Inbox notification without id")
        }
        self.id = id
        kind = (try? container.decodeIfPresent(String.self, forKey: .kind)) ?? ""
        event = (try? container.decodeIfPresent(String.self, forKey: .event)) ?? ""
        title = (try? container.decodeIfPresent(String.self, forKey: .title)).inboxTrimmed ?? ""
        body = (try? container.decodeIfPresent(String.self, forKey: .body)).inboxTrimmed ?? ""
        createdAt = (try? container.decodeIfPresent(String.self, forKey: .createdAt)).inboxTrimmed
        readAt = (try? container.decodeIfPresent(String.self, forKey: .readAt)).inboxTrimmed
        target = (try? container.decodeIfPresent(WireTarget.self, forKey: .target))?.target
    }
}

struct MobileInboxPage: Decodable, Sendable, Equatable {
    var notifications: [MobileInboxNotification]
    var currentPage: Int
    var lastPage: Int
    var unread: Int?

    init(notifications: [MobileInboxNotification], currentPage: Int = 1, lastPage: Int = 1, unread: Int? = nil) {
        self.notifications = notifications
        self.currentPage = currentPage
        self.lastPage = lastPage
        self.unread = unread
    }

    private enum CodingKeys: String, CodingKey {
        case data, meta, unread
    }

    private struct Meta: Decodable {
        var currentPage: Int?
        var lastPage: Int?

        private enum CodingKeys: String, CodingKey {
            case currentPage, lastPage
        }

        init(from decoder: Decoder) throws {
            let container = try decoder.container(keyedBy: CodingKeys.self)
            currentPage = container.inboxInt(.currentPage)
            lastPage = container.inboxInt(.lastPage)
        }
    }

    private struct Skipped: Decodable {
        init(from decoder: Decoder) throws {}
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        var list = try container.nestedUnkeyedContainer(forKey: .data)
        var notifications: [MobileInboxNotification] = []
        while !list.isAtEnd {
            if let notification = try? list.decode(MobileInboxNotification.self) {
                notifications.append(notification)
            } else if (try? list.decode(Skipped.self)) == nil {
                break
            }
        }
        self.notifications = notifications
        let meta = try? container.decodeIfPresent(Meta.self, forKey: .meta)
        let loadedPage = max(meta?.currentPage ?? 1, 1)
        currentPage = loadedPage
        lastPage = max(meta?.lastPage ?? loadedPage, loadedPage)
        unread = container.inboxInt(.unread)
    }
}

struct MobileInboxUnread: Decodable, Sendable, Equatable {
    var unread: Int

    private enum CodingKeys: String, CodingKey {
        case unread
    }

    init(unread: Int) {
        self.unread = unread
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        unread = max(container.inboxInt(.unread) ?? 0, 0)
    }
}

private extension KeyedDecodingContainer {
    func inboxInt(_ key: Key) -> Int? {
        if let value = try? decodeIfPresent(Int.self, forKey: key) {
            return value
        }
        if let value = try? decodeIfPresent(Double.self, forKey: key) {
            return Int(value)
        }
        if let text = try? decodeIfPresent(String.self, forKey: key) {
            return Int(text.trimmingCharacters(in: .whitespaces))
        }
        return nil
    }
}

private extension Optional where Wrapped == String {
    var inboxTrimmed: String? {
        guard let text = self?.trimmingCharacters(in: .whitespacesAndNewlines), !text.isEmpty else { return nil }
        return text
    }
}

extension Endpoint {
    static let inboxPageSize = 30

    static func mobileInbox(page: Int) -> Endpoint {
        Endpoint(path: "mobile/inbox", queryItems: [
            URLQueryItem(name: "filter", value: "all"),
            URLQueryItem(name: "page", value: String(page)),
            URLQueryItem(name: "per_page", value: String(inboxPageSize))
        ])
    }

    static let mobileInboxUnreadCount = Endpoint(path: "mobile/inbox/unread-count")

    static let mobileInboxReadAll = Endpoint(path: "mobile/inbox/read-all", method: .post)

    static func mobileInboxRead(id: String) -> Endpoint {
        Endpoint(path: "mobile/inbox/\(id)/read", method: .post)
    }

    static func mobileInboxUnread(id: String) -> Endpoint {
        Endpoint(path: "mobile/inbox/\(id)/unread", method: .post)
    }
}

extension APIClient {
    func fetchInbox(page: Int) async throws -> MobileInboxPage {
        try await send(.mobileInbox(page: page), as: MobileInboxPage.self)
    }

    func fetchInboxUnreadCount() async throws -> Int {
        try await send(.mobileInboxUnreadCount, as: DataEnvelope<MobileInboxUnread>.self).value.unread
    }

    func markInboxRead(id: String) async throws -> MobileInboxNotification {
        try await send(.mobileInboxRead(id: id), as: DataEnvelope<MobileInboxNotification>.self).value
    }

    func markInboxUnread(id: String) async throws -> MobileInboxNotification {
        try await send(.mobileInboxUnread(id: id), as: DataEnvelope<MobileInboxNotification>.self).value
    }

    func markAllInboxRead() async throws -> Int {
        try await send(.mobileInboxReadAll, as: DataEnvelope<MobileInboxUnread>.self).value.unread
    }
}
