import Foundation

struct RealtimeServer: Decodable, Equatable, Sendable {
    static let protocolVersion = 7
    static let secureScheme = "https"

    let key: String
    var cluster: String? = nil
    let host: String
    let port: Int
    var scheme: String = RealtimeServer.secureScheme

    private enum CodingKeys: String, CodingKey {
        case key, cluster, host, port, scheme
    }

    init(key: String, cluster: String? = nil, host: String, port: Int, scheme: String = RealtimeServer.secureScheme) {
        self.key = key
        self.cluster = cluster
        self.host = host
        self.port = port
        self.scheme = scheme
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        key = try container.decode(String.self, forKey: .key)
        cluster = try container.decodeIfPresent(String.self, forKey: .cluster)
        host = try container.decode(String.self, forKey: .host)
        port = try container.decode(Int.self, forKey: .port)
        scheme = try container.decodeIfPresent(String.self, forKey: .scheme) ?? Self.secureScheme
    }

    func socketURL(client: String, version: String) -> URL? {
        let isSecure = scheme == Self.secureScheme
        var components = URLComponents()
        components.scheme = isSecure ? "wss" : "ws"
        components.host = host
        components.port = port == (isSecure ? 443 : 80) ? nil : port
        components.path = "/app/\(key)"
        components.queryItems = [
            URLQueryItem(name: "protocol", value: String(Self.protocolVersion)),
            URLQueryItem(name: "client", value: client),
            URLQueryItem(name: "version", value: version),
            URLQueryItem(name: "flash", value: "false"),
        ]
        return components.url
    }
}

struct RealtimeServerEnvelope: Decodable, Sendable {
    let data: RealtimeServer?
}

struct RealtimeChannelAuth: Decodable, Sendable {
    let auth: String?
}

private struct RealtimeChannelAuthBody: Encodable, Sendable {
    let socketId: String
    let channelName: String
}

extension Endpoint {
    static let realtimeServer = Endpoint(path: "mobile/realtime")

    static func authorizeRealtimeChannel(socketID: String, channel: String) -> Endpoint {
        jsonBody("mobile/broadcasting/auth", method: .post, RealtimeChannelAuthBody(socketId: socketID, channelName: channel))
    }
}

extension APIClient {
    func fetchRealtimeServer() async throws -> RealtimeServer? {
        try await send(.realtimeServer, as: RealtimeServerEnvelope.self).data
    }

    func authorizeRealtimeChannel(socketID: String, channel: String) async throws -> String? {
        let auth = try await send(.authorizeRealtimeChannel(socketID: socketID, channel: channel), as: RealtimeChannelAuth.self).auth
        guard let auth, !auth.isEmpty else { return nil }
        return auth
    }
}
