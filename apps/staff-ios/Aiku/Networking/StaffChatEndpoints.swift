import Foundation

enum StaffChatContextKind: String, CaseIterable, Sendable {
    case deliveryNote = "delivery_note"
    case pickingSession = "picking_session"

    var pathSegment: String {
        switch self {
        case .deliveryNote: "delivery-notes"
        case .pickingSession: "picking-sessions"
        }
    }

    var contextTitle: String {
        switch self {
        case .deliveryNote: "Delivery note"
        case .pickingSession: "Picking session"
        }
    }
}

struct StaffChatParticipant: Decodable, Equatable, Sendable {
    let id: Int
    var name: String? = nil
}

struct StaffChatConversation: Decodable, Equatable, Sendable {
    let ulid: String
    var name: String? = nil
    var contextLabel: String? = nil
    var participants: [StaffChatParticipant] = []
    var unreadCount: Int = 0

    private enum CodingKeys: String, CodingKey {
        case ulid, name, contextLabel, participants, unreadCount
    }

    init(ulid: String, name: String? = nil, contextLabel: String? = nil, participants: [StaffChatParticipant] = [], unreadCount: Int = 0) {
        self.ulid = ulid
        self.name = name
        self.contextLabel = contextLabel
        self.participants = participants
        self.unreadCount = unreadCount
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        ulid = try container.decode(String.self, forKey: .ulid)
        name = try container.decodeIfPresent(String.self, forKey: .name)
        contextLabel = try container.decodeIfPresent(String.self, forKey: .contextLabel)
        participants = try container.decodeIfPresent([StaffChatParticipant].self, forKey: .participants) ?? []
        unreadCount = try container.decodeIfPresent(Int.self, forKey: .unreadCount) ?? 0
    }
}

struct StaffChatMessage: Decodable, Equatable, Identifiable, Sendable {
    let id: Int
    var user: StaffChatParticipant? = nil
    var isMine: Bool = false
    var body: String? = nil
    var imageUrl: String? = nil
    var createdAt: String? = nil

    private enum CodingKeys: String, CodingKey {
        case id, user, isMine, body, imageUrl, createdAt
    }

    init(id: Int, user: StaffChatParticipant? = nil, isMine: Bool = false, body: String? = nil, imageUrl: String? = nil, createdAt: String? = nil) {
        self.id = id
        self.user = user
        self.isMine = isMine
        self.body = body
        self.imageUrl = imageUrl
        self.createdAt = createdAt
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(Int.self, forKey: .id)
        user = try container.decodeIfPresent(StaffChatParticipant.self, forKey: .user)
        isMine = try container.decodeIfPresent(Bool.self, forKey: .isMine) ?? false
        body = try container.decodeIfPresent(String.self, forKey: .body)
        imageUrl = try container.decodeIfPresent(String.self, forKey: .imageUrl)
        createdAt = try container.decodeIfPresent(String.self, forKey: .createdAt)
    }
}

struct StaffChatMessagePage: Decodable, Equatable, Sendable {
    let data: [StaffChatMessage]
    var hasMore: Bool = false

    private enum CodingKeys: String, CodingKey {
        case data, hasMore
    }

    init(data: [StaffChatMessage], hasMore: Bool = false) {
        self.data = data
        self.hasMore = hasMore
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        data = try container.decodeIfPresent([StaffChatMessage].self, forKey: .data) ?? []
        hasMore = try container.decodeIfPresent(Bool.self, forKey: .hasMore) ?? false
    }
}

enum StaffChatPageQuery: Equatable, Sendable {
    case latest
    case before(messageID: Int)
    case after(messageID: Int)

    var queryItems: [URLQueryItem] {
        switch self {
        case .latest: []
        case .before(let messageID): [URLQueryItem(name: "before_id", value: String(messageID))]
        case .after(let messageID): [URLQueryItem(name: "after_id", value: String(messageID))]
        }
    }
}

struct StaffChatSendBody: Encodable, Equatable, Sendable {
    let body: String
}

extension Endpoint {
    static func openStaffChat(kind: StaffChatContextKind, contextID: Int) -> Endpoint {
        Endpoint(path: "mobile/\(kind.pathSegment)/\(contextID)/chat", method: .post)
    }

    static func staffChatMessages(ulid: String, query: StaffChatPageQuery) -> Endpoint {
        Endpoint(path: "mobile/chat/\(ulid)/messages", queryItems: query.queryItems)
    }

    static func sendStaffChatMessage(ulid: String, body: String) -> Endpoint {
        .jsonBody("mobile/chat/\(ulid)/messages", method: .post, StaffChatSendBody(body: body))
    }
}

extension APIClient {
    func openStaffChat(kind: StaffChatContextKind, contextID: Int) async throws -> StaffChatConversation {
        try await send(.openStaffChat(kind: kind, contextID: contextID), as: DataEnvelope<StaffChatConversation>.self).value
    }

    func staffChatMessages(ulid: String, query: StaffChatPageQuery) async throws -> StaffChatMessagePage {
        try await send(.staffChatMessages(ulid: ulid, query: query), as: StaffChatMessagePage.self)
    }

    func sendStaffChatMessage(ulid: String, body: String) async throws -> StaffChatMessage {
        try await send(.sendStaffChatMessage(ulid: ulid, body: body), as: DataEnvelope<StaffChatMessage>.self).value
    }
}
