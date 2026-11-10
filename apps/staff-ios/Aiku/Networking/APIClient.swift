import Foundation
import SwiftUI

enum HTTPMethod: String, Sendable {
    case get = "GET"
    case post = "POST"
    case patch = "PATCH"
    case delete = "DELETE"
}

struct Endpoint: Sendable {
    let path: String
    var method: HTTPMethod = .get
    var queryItems: [URLQueryItem] = []
    var body: Data?

    static let ping = Endpoint(path: "ping")
    static let profile = Endpoint(path: "profile")
    static let mobilePermissions = Endpoint(path: "mobile/permissions")
}

enum APIError: LocalizedError, Equatable {
    case invalidResponse
    case unauthorized
    case http(statusCode: Int)
    case validation(message: String, code: String?)
    case tooManyAttempts(message: String)
    case offline
    case unexpectedBody

    var errorDescription: String? {
        switch self {
        case .invalidResponse: "Invalid server response"
        case .unauthorized: "Not signed in"
        case .http(let statusCode): "Server error (\(statusCode))"
        case .validation(let message, _): message
        case .tooManyAttempts(let message): message
        case .offline: "No connection"
        case .unexpectedBody: "Unexpected server response"
        }
    }
}

protocol HTTPTransport: Sendable {
    func data(for request: URLRequest) async throws -> (Data, URLResponse)
}

extension URLSession: HTTPTransport {}

final class APIClient: Sendable {
    static let shared = APIClient(configuration: .current, tokenProvider: { KeychainTokenStore.shared.load() })

    private let configuration: AppConfiguration
    private let transport: HTTPTransport
    private let tokenProvider: @Sendable () -> String?

    init(
        configuration: AppConfiguration,
        transport: HTTPTransport = URLSession.shared,
        tokenProvider: @escaping @Sendable () -> String? = { nil }
    ) {
        self.configuration = configuration
        self.transport = transport
        self.tokenProvider = tokenProvider
    }

    func makeRequest(for endpoint: Endpoint) -> URLRequest {
        var components = URLComponents(
            url: configuration.apiBaseURL.appending(path: endpoint.path),
            resolvingAgainstBaseURL: false
        )!
        if !endpoint.queryItems.isEmpty {
            components.queryItems = endpoint.queryItems
        }

        var request = URLRequest(url: components.url!)
        request.httpMethod = endpoint.method.rawValue
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        if let mobileAppKey = configuration.mobileAppKey {
            request.setValue(mobileAppKey, forHTTPHeaderField: "X-Aiku-App-Key")
        }
        if let body = endpoint.body {
            request.httpBody = body
            request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        }
        if let token = tokenProvider() {
            request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }
        return request
    }

    func send(_ endpoint: Endpoint) async throws -> Data {
        let data: Data
        let response: URLResponse
        do {
            (data, response) = try await transport.data(for: makeRequest(for: endpoint))
        } catch let error as URLError {
            throw APIError.offline.unlessCancelled(error)
        }
        guard let httpResponse = response as? HTTPURLResponse else {
            throw APIError.invalidResponse
        }
        switch httpResponse.statusCode {
        case 200..<300:
            return data
        case 401:
            throw APIError.unauthorized
        case 422:
            let body = try? JSONDecoder().decode(APIValidationBody.self, from: data)
            throw APIError.validation(message: body?.firstError ?? "Check your details and try again.", code: body?.code)
        case 429:
            let body = try? JSONDecoder().decode(APIValidationBody.self, from: data)
            throw APIError.tooManyAttempts(message: body?.firstError ?? "Too many attempts. Try again in a minute.")
        default:
            throw APIError.http(statusCode: httpResponse.statusCode)
        }
    }

    func send<Response: Decodable>(_ endpoint: Endpoint, as type: Response.Type) async throws -> Response {
        let data = try await send(endpoint)
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        return try decoder.decode(Response.self, from: data)
    }

    func fetchPermissions() async throws -> UserPermissions {
        let response = try await send(.mobilePermissions, as: MobilePermissionsResponse.self)
        return UserPermissions(userID: response.userId, permissions: Set(response.permissions), roles: response.roles, fetchedAt: .now)
    }

    func ping() async throws {
        let data = try await send(.ping)
        guard String(decoding: data, as: UTF8.self).contains("pong") else {
            throw APIError.unexpectedBody
        }
    }
}

extension APIError {
    func unlessCancelled(_ error: URLError) -> Error {
        error.code == .cancelled ? error : self
    }
}

extension EnvironmentValues {
    @Entry var apiClient = APIClient.shared
}

extension Error {
    var isCancellation: Bool {
        if self is CancellationError { return true }
        if let urlError = self as? URLError, urlError.code == .cancelled { return true }
        return false
    }
}
