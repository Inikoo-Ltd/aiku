import Foundation

struct MobileProfileResponse: Decodable, Equatable, Sendable {
    struct JobPosition: Decodable, Equatable, Sendable {
        let code: String?
        let name: String
    }

    struct Organisation: Decodable, Equatable, Sendable {
        let id: Int
        let code: String?
        let name: String
    }

    struct Language: Decodable, Equatable, Sendable {
        let code: String
        let name: String?
    }

    let id: Int
    let username: String
    let name: String?
    let position: String?
    let jobTitle: String?
    let jobPositions: [JobPosition]
    let organisation: Organisation?
    var language: Language? = nil

    var displayedJobPositions: [String] {
        let codes = Set(jobPositions.compactMap(\.code))
        return jobPositions
            .filter { position in
                guard let code = position.code else { return true }
                return !codes.contains(code + "-m")
            }
            .map(\.name)
    }
}

struct FormValidationError: Error, Equatable, Sendable {
    let message: String
    let fields: [String: String]

    func field(_ name: String) -> String? {
        fields[name]
    }
}

struct PasswordChangeBody: Encodable, Sendable {
    let currentPassword: String
    let password: String
    let passwordConfirmation: String
}

struct ProfileLanguageBody: Encodable, Sendable {
    let language: String
}

struct PasswordResetCodeBody: Encodable, Sendable {
    let login: String
}

struct PasswordResetBody: Encodable, Sendable {
    let login: String
    let code: String
    let password: String
    let passwordConfirmation: String
}

extension Endpoint {
    static let mobileProfile = Endpoint(path: "mobile/profile")
}

extension APIClient {
    func fetchMobileProfile() async throws -> MobileProfileResponse {
        try await send(.mobileProfile, as: DataEnvelope<MobileProfileResponse>.self).value
    }

    func updateProfileLanguage(_ code: String) async throws -> MobileProfileResponse {
        let data = try await sendForm(.jsonBody("mobile/profile/language", method: .patch, ProfileLanguageBody(language: code)))
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        return try decoder.decode(DataEnvelope<MobileProfileResponse>.self, from: data).value
    }

    func changePassword(_ body: PasswordChangeBody) async throws {
        _ = try await sendForm(.jsonBody("mobile/password", method: .post, body))
    }

    func sendPasswordResetCode(login: String) async throws {
        _ = try await sendForm(.jsonBody("mobile/password/reset-code", method: .post, PasswordResetCodeBody(login: login)))
    }

    func resetPassword(_ body: PasswordResetBody) async throws {
        _ = try await sendForm(.jsonBody("mobile/password/reset", method: .post, body))
    }

    func sendForm(_ endpoint: Endpoint) async throws -> Data {
        let (data, response) = try await perform(endpoint)
        if response.statusCode == 422 {
            throw FormValidationError.decode(from: data)
        }
        try check(response, data: data)
        return data
    }
}

extension FormValidationError {
    static func decode(from data: Data) -> FormValidationError {
        let body = try? JSONDecoder().decode(APIValidationBody.self, from: data)
        var fields: [String: String] = [:]
        for (key, messages) in body?.errors ?? [:] {
            if let first = messages.first {
                fields[key] = first
            }
        }
        return FormValidationError(message: body?.firstError ?? "Check your details and try again.", fields: fields)
    }
}
