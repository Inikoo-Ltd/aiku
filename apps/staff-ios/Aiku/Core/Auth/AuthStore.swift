import Foundation
import Observation

struct AuthService: Sendable {
    var login: @Sendable (MobileLoginRequest) async throws -> MobileLoginResponse
    var fetchProfile: @Sendable () async throws -> ProfileResponse
    var logout: @Sendable () async throws -> Void

    static func live(_ client: APIClient) -> AuthService {
        AuthService(
            login: { try await client.login($0) },
            fetchProfile: { try await client.fetchProfile() },
            logout: { try await client.logout() }
        )
    }
}

enum DeviceName {
    static func make(model: String, systemName: String, systemVersion: String) -> String {
        String("\(model) · \(systemName) \(systemVersion)".prefix(100))
    }
}

@MainActor
@Observable
final class AuthStore {
    private(set) var isSignedIn: Bool
    private(set) var profile: ProfileResponse?
    private(set) var isRefreshingProfile = false
    private(set) var lastProfileError: String?

    @ObservationIgnored private let service: AuthService
    @ObservationIgnored private let tokens: TokenStoring
    @ObservationIgnored private let deviceName: String

    init(service: AuthService, tokens: TokenStoring, deviceName: String) {
        self.service = service
        self.tokens = tokens
        self.deviceName = deviceName
        isSignedIn = tokens.load() != nil
    }

    func signIn(username: String, password: String, remember: Bool) async throws(LoginFailure) {
        let request = MobileLoginRequest(username: username, password: password, deviceName: deviceName)
        do {
            let response = try await service.login(request)
            tokens.save(response.token, persist: remember)
            profile = response.user
            lastProfileError = nil
            isSignedIn = true
        } catch let error as APIError {
            throw LoginFailure(error)
        } catch {
            throw .server(message: error.localizedDescription)
        }
    }

    func refreshProfile() async {
        guard isSignedIn, !isRefreshingProfile else { return }
        isRefreshingProfile = true
        defer { isRefreshingProfile = false }
        do {
            profile = try await service.fetchProfile()
            lastProfileError = nil
        } catch APIError.unauthorized {
            endSession()
        } catch where error.isCancellation {
            return
        } catch {
            lastProfileError = error.localizedDescription
        }
    }

    func signOut() async {
        if isSignedIn {
            try? await service.logout()
        }
        endSession()
    }

    func endSession() {
        tokens.clear()
        profile = nil
        lastProfileError = nil
        isSignedIn = false
    }
}
