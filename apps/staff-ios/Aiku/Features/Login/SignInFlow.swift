import Foundation
import Observation

enum SignInStep: Equatable, Sendable {
    case credentials
    case oneTimeCode
    case twoFactorSetup(TwoFactorSetup)

    var setup: TwoFactorSetup? {
        if case .twoFactorSetup(let setup) = self { return setup }
        return nil
    }

    var asksForCode: Bool { self != .credentials }
}

enum OneTimeCode {
    static let length = 6

    static func sanitised(_ input: String) -> String {
        String(input.filter { $0.isASCII && $0.isNumber }.prefix(length))
    }
}

enum TwoFactorCopy {
    static let codeTitle = "Two-step verification"
    static let setupTitle = "Set up two-factor authentication"
    static let setupDescription = "Your account needs two-factor authentication. Add Aiku to an authenticator app, then enter the 6-digit code it shows."
    static let codeMissing = "Enter the 6-digit code."
    static let setupRenewed = "That setup expired, so here is a new key. Add it to your authenticator app, then enter the code it shows."

    static func codeDescription(username: String) -> String {
        "Enter the 6-digit code from your authenticator app to finish signing in as \(username)."
    }
}

@MainActor
@Observable
final class SignInFlow {
    var form = LoginForm()
    private(set) var step = SignInStep.credentials
    private(set) var state = LoginState.idle
    private(set) var code = ""
    private(set) var codeError: String?
    private(set) var showsFieldErrors = false

    var isSigningIn: Bool { state.isSigningIn }

    func signIn(with authStore: AuthStore, remember: Bool) async {
        guard step == .credentials, !isSigningIn else { return }
        showsFieldErrors = true
        guard form.canSubmit else { return }
        await submit(with: authStore, remember: remember, oneTimePassword: nil)
    }

    @discardableResult
    func updateCode(_ input: String) -> Bool {
        let sanitised = OneTimeCode.sanitised(input)
        let previous = code
        code = sanitised
        guard sanitised != previous else { return false }
        codeError = nil
        return sanitised.count == OneTimeCode.length
    }

    func verifyCode(with authStore: AuthStore, remember: Bool) async {
        guard step.asksForCode, !isSigningIn else { return }
        guard code.count == OneTimeCode.length else {
            codeError = TwoFactorCopy.codeMissing
            return
        }
        await submit(with: authStore, remember: remember, oneTimePassword: code)
    }

    func backToCredentials() {
        guard !isSigningIn else { return }
        clearSecrets()
        showsFieldErrors = false
        state = .idle
    }

    func clearSecrets() {
        form.password = ""
        code = ""
        codeError = nil
        step = .credentials
    }

    func show(_ failure: LoginFailure) {
        state = .failed(failure)
    }

    private func submit(with authStore: AuthStore, remember: Bool, oneTimePassword: String?) async {
        let startingStep = step
        state = .signingIn
        codeError = nil
        do throws(LoginFailure) {
            try await authStore.signIn(
                username: form.trimmedUsername,
                password: form.password,
                remember: remember,
                oneTimePassword: oneTimePassword,
                twoFactorSetupToken: startingStep.setup?.setupToken
            )
            clearSecrets()
            showsFieldErrors = false
            state = .idle
        } catch {
            receive(error, during: startingStep)
        }
    }

    private func receive(_ failure: LoginFailure, during startingStep: SignInStep) {
        state = .idle
        switch failure {
        case .otpRequired:
            code = ""
            if startingStep == .oneTimeCode {
                codeError = TwoFactorCopy.codeMissing
            } else {
                step = .oneTimeCode
            }
        case .twoFactorSetupRequired(let setup):
            code = ""
            codeError = startingStep.setup == nil ? nil : TwoFactorCopy.setupRenewed
            step = .twoFactorSetup(setup)
        case .codeRejected(let message) where startingStep.asksForCode:
            code = ""
            codeError = message
        default:
            if startingStep == .credentials {
                form.password = ""
                showsFieldErrors = false
            }
            state = .failed(failure)
        }
    }
}
