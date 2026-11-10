import Foundation

struct MobileLoginRequest: Encodable, Sendable {
    let username: String
    let password: String
    let deviceName: String
    var oneTimePassword: String?
}

struct MobileLoginResponse: Decodable, Sendable {
    let token: String
    let expiresAt: String?
    let user: ProfileResponse
}

struct ProfileResponse: Decodable, Equatable, Sendable {
    struct Employee: Decodable, Equatable, Sendable {
        let contactName: String?
        let organisationSlug: String?
        let workerNumber: String?
        let jobTitle: String?
        let email: String?
    }

    struct JobPosition: Decodable, Equatable, Sendable {
        let name: String
        let code: String?
    }

    let id: Int
    let username: String
    let contactName: String?
    let email: String?
    let employee: [Employee]?
    var jobPositions: [JobPosition]? = nil

    var displayedJobPositions: [String] {
        let positions = jobPositions ?? []
        let codes = Set(positions.compactMap(\.code))
        return positions
            .filter { position in
                guard let code = position.code else { return true }
                return !codes.contains(code + "-m")
            }
            .map(\.name)
    }
}

struct DataEnvelope<Wrapped: Decodable & Sendable>: Decodable, Sendable {
    let value: Wrapped

    private enum CodingKeys: String, CodingKey {
        case data
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        if container.contains(.data) {
            value = try container.decode(Wrapped.self, forKey: .data)
        } else {
            value = try Wrapped(from: decoder)
        }
    }
}

struct APIValidationBody: Decodable, Sendable {
    let message: String?
    let code: String?
    let errors: [String: [String]]?

    var firstError: String? {
        errors?.values.first?.first ?? message
    }
}

extension Endpoint {
    static func mobileLogin(_ request: MobileLoginRequest) -> Endpoint {
        let encoder = JSONEncoder()
        encoder.keyEncodingStrategy = .convertToSnakeCase
        return Endpoint(path: "mobile/login", method: .post, body: try? encoder.encode(request))
    }

    static let mobileLogout = Endpoint(path: "mobile/logout", method: .post)
}

extension APIClient {
    func login(_ request: MobileLoginRequest) async throws -> MobileLoginResponse {
        try await send(.mobileLogin(request), as: MobileLoginResponse.self)
    }

    func fetchProfile() async throws -> ProfileResponse {
        try await send(.profile, as: DataEnvelope<ProfileResponse>.self).value
    }

    func logout() async throws {
        _ = try await send(.mobileLogout)
    }
}
