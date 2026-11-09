import Foundation
import Testing
@testable import Aiku

struct StubTransport: HTTPTransport {
    let statusCode: Int
    let body: String

    func data(for request: URLRequest) async throws -> (Data, URLResponse) {
        let response = HTTPURLResponse(url: request.url!, statusCode: statusCode, httpVersion: nil, headerFields: nil)!
        return (Data(body.utf8), response)
    }
}

struct APIClientTests {
    private let configuration = AppConfiguration(apiBaseURL: URL(string: "https://example.test/app/api")!)

    @Test func buildsRequestAgainstBaseURL() {
        let client = APIClient(configuration: configuration, tokenProvider: { "secret" })
        let request = client.makeRequest(for: Endpoint(path: "shops", queryItems: [URLQueryItem(name: "page", value: "2")]))

        #expect(request.url?.absoluteString == "https://example.test/app/api/shops?page=2")
        #expect(request.httpMethod == "GET")
        #expect(request.value(forHTTPHeaderField: "Accept") == "application/json")
        #expect(request.value(forHTTPHeaderField: "Authorization") == "Bearer secret")
    }

    @Test func omitsAuthorizationWithoutToken() {
        let request = APIClient(configuration: configuration).makeRequest(for: .profile)

        #expect(request.value(forHTTPHeaderField: "Authorization") == nil)
    }

    @Test func pingSucceedsOnPong() async throws {
        let client = APIClient(configuration: configuration, transport: StubTransport(statusCode: 200, body: "pong"))

        try await client.ping()
    }

    @Test func mapsUnauthorizedStatus() async {
        let client = APIClient(configuration: configuration, transport: StubTransport(statusCode: 401, body: ""))

        await #expect(throws: APIError.unauthorized) {
            try await client.send(.profile)
        }
    }

    @Test func decodesSnakeCaseJSON() async throws {
        struct Profile: Decodable { let userName: String }
        let client = APIClient(configuration: configuration, transport: StubTransport(statusCode: 200, body: #"{"user_name":"artha"}"#))

        let profile = try await client.send(.profile, as: Profile.self)

        #expect(profile.userName == "artha")
    }

    @Test func readsBaseURLFromInfoPlist() {
        #expect(AppConfiguration.current.apiBaseURL.path() == "/app/api")
    }
}
