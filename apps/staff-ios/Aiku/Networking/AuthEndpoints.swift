import Foundation

struct MobileLoginRequest: Encodable, Sendable {
    let username: String
    let password: String
    let deviceName: String
    var oneTimePassword: String?
    var twoFactorSetupToken: String?
}

extension MobileLoginRequest: CustomStringConvertible, CustomDebugStringConvertible, CustomReflectable {
    var description: String { "MobileLoginRequest(username: \(username), deviceName: \(deviceName))" }
    var debugDescription: String { description }
    var customMirror: Mirror { Mirror(self, children: ["username": username, "deviceName": deviceName]) }
}

struct TwoFactorSetup: Decodable, Equatable, Sendable {
    let setupToken: String
    let secret: String
    let otpauthUrl: String
    var issuer: String?
    var account: String?
    var expiresIn: Int?

    private enum CodingKeys: String, CodingKey {
        case setupToken = "setup_token"
        case secret
        case otpauthUrl = "otpauth_url"
        case issuer
        case account
        case expiresIn = "expires_in"
    }

    var authenticatorURL: URL? {
        guard let url = URL(string: otpauthUrl), url.scheme?.lowercased() == "otpauth" else { return nil }
        return url
    }

    var isUsable: Bool {
        !setupToken.isEmpty && !secret.isEmpty && authenticatorURL != nil
    }

    var qrCodeMessage: Data { Data(otpauthUrl.utf8) }

    var groupedSecret: String {
        let characters = Array(secret.filter { !$0.isWhitespace })
        return stride(from: 0, to: characters.count, by: 4)
            .map { start in String(characters[start..<min(start + 4, characters.count)]) }
            .joined(separator: " ")
    }
}

extension TwoFactorSetup: CustomStringConvertible, CustomDebugStringConvertible, CustomReflectable {
    var description: String { "TwoFactorSetup(issuer: \(issuer ?? "-"), account: \(account ?? "-"))" }
    var debugDescription: String { description }
    var customMirror: Mirror { Mirror(self, children: ["issuer": issuer as Any, "account": account as Any]) }
}

struct LoginRejection: LocalizedError, Equatable, Sendable {
    static let codeRequired = "otp_required"
    static let setupRequired = "two_factor_setup_required"
    static let codeField = "one_time_password"
    static let usernameField = "username"

    let message: String
    var code: String?
    var fieldErrors: [String: String]
    var twoFactorSetup: TwoFactorSetup?

    init(message: String, code: String? = nil, fieldErrors: [String: String] = [:], twoFactorSetup: TwoFactorSetup? = nil) {
        self.message = message
        self.code = code
        self.fieldErrors = fieldErrors
        self.twoFactorSetup = twoFactorSetup
    }

    init?(data: Data) {
        guard let body = try? JSONDecoder().decode(LoginRejectionBody.self, from: data) else { return nil }
        let fieldErrors = (body.errors ?? [:]).compactMapValues { messages in
            messages.first { !$0.isEmpty }
        }
        let serverMessage = body.message.flatMap { $0.isEmpty ? nil : $0 }
        self.init(
            message: serverMessage ?? fieldErrors.values.first ?? "Check your details and try again.",
            code: body.code,
            fieldErrors: fieldErrors,
            twoFactorSetup: body.twoFactorSetup
        )
    }

    var errorDescription: String? { message }
}

private struct LoginRejectionBody: Decodable {
    let message: String?
    let code: String?
    let errors: [String: [String]]?
    let twoFactorSetup: TwoFactorSetup?

    private enum CodingKeys: String, CodingKey {
        case message
        case code
        case errors
        case twoFactorSetup = "two_factor_setup"
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        message = try? container.decodeIfPresent(String.self, forKey: .message)
        code = try? container.decodeIfPresent(String.self, forKey: .code)
        errors = try? container.decodeIfPresent([String: [String]].self, forKey: .errors)
        twoFactorSetup = try? container.decodeIfPresent(TwoFactorSetup.self, forKey: .twoFactorSetup)
    }
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
        let (data, response) = try await perform(.mobileLogin(request))
        if response.statusCode == 422, let rejection = LoginRejection(data: data) {
            throw rejection
        }
        try check(response, data: data)
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        return try decoder.decode(MobileLoginResponse.self, from: data)
    }

    func fetchProfile() async throws -> ProfileResponse {
        try await send(.profile, as: DataEnvelope<ProfileResponse>.self).value
    }

    func logout() async throws {
        _ = try await send(.mobileLogout)
    }
}
