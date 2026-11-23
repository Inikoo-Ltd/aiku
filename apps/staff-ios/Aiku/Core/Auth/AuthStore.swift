import Foundation
import Observation

struct AuthService: Sendable {
    var login: @Sendable (MobileLoginRequest) async throws -> MobileLoginResponse
    var fetchProfile: @Sendable () async throws -> MobileProfileResponse
    var logout: @Sendable () async throws -> Void
    var changePassword: @Sendable (PasswordChangeBody) async throws -> Void = { _ in }
    var sendResetCode: @Sendable (String) async throws -> Void = { _ in }
    var resetPassword: @Sendable (PasswordResetBody) async throws -> Void = { _ in }

    static func live(_ client: APIClient) -> AuthService {
        AuthService(
            login: { try await client.login($0) },
            fetchProfile: { try await client.fetchMobileProfile() },
            logout: { try await client.logout() },
            changePassword: { try await client.changePassword($0) },
            sendResetCode: { try await client.sendPasswordResetCode(login: $0) },
            resetPassword: { try await client.resetPassword($0) }
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
    private(set) var mobileProfile: MobileProfileResponse?
    private(set) var isRefreshingProfile = false
    private(set) var lastProfileError: String?

    @ObservationIgnored private let service: AuthService
    @ObservationIgnored private let tokens: TokenStoring
    @ObservationIgnored private let deviceName: String
    @ObservationIgnored private let profileCache: ProfileCaching

    init(service: AuthService, tokens: TokenStoring, deviceName: String, profileCache: ProfileCaching = NoProfileCache()) {
        self.service = service
        self.tokens = tokens
        self.deviceName = deviceName
        self.profileCache = profileCache
        isSignedIn = tokens.load() != nil
        if isSignedIn {
            mobileProfile = profileCache.load()
        } else {
            profileCache.clear()
        }
    }

    func signIn(
        username: String,
        password: String,
        remember: Bool,
        oneTimePassword: String? = nil,
        twoFactorSetupToken: String? = nil
    ) async throws(LoginFailure) {
        let request = MobileLoginRequest(
            username: username,
            password: password,
            deviceName: deviceName,
            oneTimePassword: oneTimePassword,
            twoFactorSetupToken: twoFactorSetupToken
        )
        do {
            let response = try await service.login(request)
            tokens.save(response.token, persist: remember)
            if mobileProfile?.id != response.user.id {
                mobileProfile = nil
                profileCache.clear()
            }
            profile = response.user
            lastProfileError = nil
            isSignedIn = true
        } catch let rejection as LoginRejection {
            throw LoginFailure(rejection)
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
            let fresh = try await service.fetchProfile()
            mobileProfile = fresh
            lastProfileError = nil
            try? profileCache.save(fresh)
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
        profileCache.clear()
        profile = nil
        mobileProfile = nil
        lastProfileError = nil
        isSignedIn = false
    }

    var userID: Int? { mobileProfile?.id ?? profile?.id }

    func changePassword(current: String, password: String, confirmation: String) async -> PasswordFormError? {
        if let error = PasswordRules.changeErrors(current: current, password: password, confirmation: confirmation) {
            return error
        }
        do {
            try await service.changePassword(PasswordChangeBody(currentPassword: current, password: password, passwordConfirmation: confirmation))
            return nil
        } catch APIError.unauthorized {
            endSession()
            return PasswordFormError(message: "Your session has ended. Sign in again.")
        } catch {
            return PasswordFormError(error, offlineMessage: "Changing your password needs internet — check Wi-Fi and try again.")
        }
    }

    func sendPasswordResetCode(login: String) async -> PasswordFormError? {
        if let error = PasswordRules.loginError(login) {
            return error
        }
        do {
            try await service.sendResetCode(login.trimmingCharacters(in: .whitespacesAndNewlines))
            return nil
        } catch {
            return PasswordFormError(error, offlineMessage: "Resetting your password needs internet — check Wi-Fi and try again.")
        }
    }

    func resetPassword(login: String, code: String, password: String, confirmation: String) async -> PasswordFormError? {
        if let error = PasswordRules.resetErrors(code: code, password: password, confirmation: confirmation) {
            return error
        }
        do {
            try await service.resetPassword(PasswordResetBody(
                login: login.trimmingCharacters(in: .whitespacesAndNewlines),
                code: code.trimmingCharacters(in: .whitespacesAndNewlines),
                password: password,
                passwordConfirmation: confirmation
            ))
            return nil
        } catch {
            return PasswordFormError(error, offlineMessage: "Resetting your password needs internet — check Wi-Fi and try again.")
        }
    }
}
