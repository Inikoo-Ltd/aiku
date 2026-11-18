import Foundation
import Testing
import UIKit
@testable import Aiku

private let setupSecret = "JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP"
private let setupURL = "otpauth://totp/aiku:alex.morgan?secret=JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP&issuer=aiku&algorithm=SHA1&digits=6&period=30"

private let sampleSetup = TwoFactorSetup(
    setupToken: "eyJpdiI6InNldHVwIn0=",
    secret: setupSecret,
    otpauthUrl: setupURL,
    issuer: "aiku",
    account: "alex.morgan",
    expiresIn: 600
)

private let renewedSetup = TwoFactorSetup(
    setupToken: "eyJpdiI6InJlbmV3ZWQifQ==",
    secret: "KRSXG5CTMVRXEZLUKRSXG5CTMVRXEZLU",
    otpauthUrl: "otpauth://totp/aiku:alex.morgan?secret=KRSXG5CTMVRXEZLUKRSXG5CTMVRXEZLU&issuer=aiku",
    issuer: "aiku",
    account: "alex.morgan",
    expiresIn: 600
)

private let otpRequiredBody = #"{"message":"Enter OTP from your Authenticator App","code":"otp_required","errors":{"one_time_password":["Enter OTP from your Authenticator App"]}}"#
private let setupRequiredBody = #"{"message":"Set up two-factor authentication to continue.","code":"two_factor_setup_required","errors":{"one_time_password":["Set up two-factor authentication to continue."]},"two_factor_setup":{"setup_token":"eyJpdiI6InNldHVwIn0=","secret":"JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP","otpauth_url":"otpauth://totp/aiku:alex.morgan?secret=JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP&issuer=aiku&algorithm=SHA1&digits=6&period=30","issuer":"aiku","account":"alex.morgan","expires_in":600}}"#
private let invalidCodeBody = #"{"message":"Invalid OTP is given. Please check your Authenticator App","errors":{"one_time_password":["Invalid OTP is given. Please check your Authenticator App"]}}"#
private let codeLockoutBody = #"{"message":"Too many wrong codes. Please try again in 15 minutes.","errors":{"one_time_password":["Too many wrong codes. Please try again in 15 minutes."]}}"#
private let loginLockoutBody = #"{"message":"Too many login attempts. Please try again in 42 seconds.","errors":{"username":["Too many login attempts. Please try again in 42 seconds."]}}"#

private let signedInProfile = ProfileResponse(id: 7, username: "alex.morgan", contactName: "Alex Morgan", email: nil, employee: [])

private struct TwoFactorTransport: HTTPTransport {
    let status: Int
    let body: String

    func data(for request: URLRequest) async throws -> (Data, URLResponse) {
        (Data(body.utf8), HTTPURLResponse(url: request.url!, statusCode: status, httpVersion: nil, headerFields: nil)!)
    }
}

private func loginClient(status: Int, body: String) -> APIClient {
    APIClient(
        configuration: AppConfiguration(apiBaseURL: URL(string: "http://192.168.0.10:8000/app/api")!, mobileAppKey: "key"),
        transport: TwoFactorTransport(status: status, body: body)
    )
}

private func failure(status: Int, body: String) async -> LoginFailure? {
    do {
        _ = try await loginClient(status: status, body: body).login(MobileLoginRequest(username: "alex.morgan", password: "pw", deviceName: "iPhone"))
        return nil
    } catch let rejection as LoginRejection {
        return LoginFailure(rejection)
    } catch let error as APIError {
        return LoginFailure(error)
    } catch {
        return nil
    }
}

private actor ScriptedLogin {
    private var replies: [Result<MobileLoginResponse, any Error>]
    private(set) var requests: [MobileLoginRequest] = []

    init(_ replies: [Result<MobileLoginResponse, any Error>]) {
        self.replies = replies
    }

    func reply(to request: MobileLoginRequest) throws -> MobileLoginResponse {
        requests.append(request)
        guard !replies.isEmpty else { throw APIError.offline }
        return try replies.removeFirst().get()
    }
}

private let signedIn: Result<MobileLoginResponse, any Error> = .success(MobileLoginResponse(token: "1|abc", expiresAt: nil, user: signedInProfile))
private let codeRequired: Result<MobileLoginResponse, any Error> = .failure(LoginRejection(
    message: "Enter OTP from your Authenticator App",
    code: LoginRejection.codeRequired,
    fieldErrors: [LoginRejection.codeField: "Enter OTP from your Authenticator App"]
))
private let invalidCode: Result<MobileLoginResponse, any Error> = .failure(LoginRejection(
    message: "Invalid OTP is given. Please check your Authenticator App",
    fieldErrors: [LoginRejection.codeField: "Invalid OTP is given. Please check your Authenticator App"]
))

private func setupRequired(_ setup: TwoFactorSetup) -> Result<MobileLoginResponse, any Error> {
    .failure(LoginRejection(
        message: "Set up two-factor authentication to continue.",
        code: LoginRejection.setupRequired,
        fieldErrors: [LoginRejection.codeField: "Set up two-factor authentication to continue."],
        twoFactorSetup: setup
    ))
}

@MainActor
private func makeFlow(_ script: ScriptedLogin, tokens: InMemoryTokenStore = InMemoryTokenStore()) -> (SignInFlow, AuthStore) {
    let service = AuthService(
        login: { try await script.reply(to: $0) },
        fetchProfile: { throw APIError.offline },
        logout: {}
    )
    let flow = SignInFlow()
    flow.form = LoginForm(username: " alex.morgan ", password: "secret")
    return (flow, AuthStore(service: service, tokens: tokens, deviceName: "iPhone"))
}

struct TwoFactorRequestTests {
    private func encodedBody(of request: MobileLoginRequest) throws -> [String: String] {
        let urlRequest = loginClient(status: 200, body: "").makeRequest(for: .mobileLogin(request))
        let data = try #require(urlRequest.httpBody)
        let json = try JSONSerialization.jsonObject(with: data)
        return try #require(json as? [String: String])
    }

    @Test("The code and setup token go to mobile/login in the fields the API reads")
    func codeAndSetupTokenAreEncoded() throws {
        let body = try encodedBody(of: MobileLoginRequest(
            username: "alex.morgan",
            password: "pw",
            deviceName: "iPhone",
            oneTimePassword: "123456",
            twoFactorSetupToken: "setup-token"
        ))

        #expect(body["one_time_password"] == "123456")
        #expect(body["two_factor_setup_token"] == "setup-token")
        #expect(body["username"] == "alex.morgan")
        #expect(body["device_name"] == "iPhone")
    }

    @Test("A first sign in sends neither the code nor a setup token")
    func firstSignInLeavesTheTwoFactorFieldsOut() throws {
        let body = try encodedBody(of: MobileLoginRequest(username: "alex.morgan", password: "pw", deviceName: "iPhone"))

        #expect(body["one_time_password"] == nil)
        #expect(body["two_factor_setup_token"] == nil)
    }

    @Test("Printing a login request or a setup never shows the password, code or secret")
    func secretsStayOutOfDescriptions() {
        let request = MobileLoginRequest(username: "alex", password: "hunter2", deviceName: "iPhone", oneTimePassword: "123456", twoFactorSetupToken: "setup-token")
        let printed = [String(describing: request), String(reflecting: request), String(describing: sampleSetup), String(reflecting: LoginFailure.twoFactorSetupRequired(sampleSetup))]

        for text in printed {
            #expect(!text.contains("hunter2"))
            #expect(!text.contains("123456"))
            #expect(!text.contains("setup-token"))
            #expect(!text.contains(setupSecret))
            #expect(!text.contains(sampleSetup.setupToken))
        }
    }
}

struct TwoFactorResponseTests {
    @Test("otp_required asks for the authenticator code")
    func otpRequiredMapsToTheCodeStep() async {
        #expect(await failure(status: 422, body: otpRequiredBody) == .otpRequired(message: "Enter OTP from your Authenticator App"))
    }

    @Test("two_factor_setup_required carries the setup block from the server")
    func setupRequiredCarriesTheSetup() async {
        #expect(await failure(status: 422, body: setupRequiredBody) == .twoFactorSetupRequired(sampleSetup))
    }

    @Test("A setup answer without a usable block is shown as a sign-in problem")
    func setupWithoutABlockIsAServerProblem() async {
        let body = #"{"message":"Set up two-factor authentication to continue.","code":"two_factor_setup_required","two_factor_setup":{"setup_token":"t","secret":"S","otpauth_url":"https://example.com"}}"#

        #expect(await failure(status: 422, body: body) == .server(message: "Set up two-factor authentication to continue."))
    }

    @Test("A wrong code and the code lockout keep the server's words")
    func codeErrorsAreShownAsSent() async {
        #expect(await failure(status: 422, body: invalidCodeBody) == .codeRejected(message: "Invalid OTP is given. Please check your Authenticator App"))
        #expect(await failure(status: 422, body: codeLockoutBody) == .codeRejected(message: "Too many wrong codes. Please try again in 15 minutes."))
    }

    @Test("Sign-in lockouts show the server's message")
    func lockoutsAreShownAsSent() async throws {
        let loginLockout = try #require(await failure(status: 422, body: loginLockoutBody))
        let throttled = try #require(await failure(status: 429, body: #"{"message":"Too Many Attempts."}"#))

        #expect(loginLockout.message == "Too many login attempts. Please try again in 42 seconds.")
        #expect(throttled == .tooManyAttempts(message: "Too Many Attempts."))
        #expect(throttled.message == "Too Many Attempts.")
    }

    @Test("A successful login still decodes the token")
    func successStillDecodes() async throws {
        let body = #"{"token":"1|abc","expires_at":null,"user":{"id":7,"username":"alex.morgan","contact_name":"Alex Morgan","email":null,"employee":[]}}"#

        let response = try await loginClient(status: 200, body: body).login(MobileLoginRequest(username: "alex.morgan", password: "pw", deviceName: "iPhone"))

        #expect(response.token == "1|abc")
        #expect(response.user.username == "alex.morgan")
    }
}

struct TwoFactorSetupTests {
    @Test("The authenticator link and the QR code both come from the server's otpauth URL")
    func authenticatorLinkAndQRCodeUseTheServerURL() throws {
        let url = try #require(sampleSetup.authenticatorURL)

        #expect(url.absoluteString == setupURL)
        #expect(url.scheme == "otpauth")
        #expect(sampleSetup.qrCodeMessage == Data(setupURL.utf8))
        #expect(sampleSetup.isUsable)
    }

    @Test("The QR code is generated on the device")
    func qrCodeIsGenerated() throws {
        let image = try #require(TwoFactorQRCode.image(for: sampleSetup))

        #expect(image.size.width > 0)
        #expect(image.size.width == image.size.height)
    }

    @Test("A link that is not otpauth is not opened")
    func onlyOtpauthLinksAreUsable() {
        let web = TwoFactorSetup(setupToken: "t", secret: "S", otpauthUrl: "https://example.com")
        let empty = TwoFactorSetup(setupToken: "", secret: setupSecret, otpauthUrl: setupURL)

        #expect(web.authenticatorURL == nil)
        #expect(web.isUsable == false)
        #expect(empty.isUsable == false)
    }

    @Test("The setup key is shown in groups of four")
    func setupKeyIsGrouped() {
        #expect(sampleSetup.groupedSecret == "JBSW Y3DP EHPK 3PXP JBSW Y3DP EHPK 3PXP")
        #expect(TwoFactorSetup(setupToken: "t", secret: "ABCDEF", otpauthUrl: setupURL).groupedSecret == "ABCD EF")
    }
}

struct OneTimeCodeTests {
    @Test("Typed or pasted codes keep only the first six ASCII digits", arguments: [
        ("123456", "123456"),
        ("123 456", "123456"),
        ("12-34-56", "123456"),
        ("1234567", "123456"),
        (" 0012a", "0012"),
        ("abc", ""),
        ("١٢٣٤٥٦", "")
    ])
    func codeIsSanitised(input: String, expected: String) {
        #expect(OneTimeCode.sanitised(input) == expected)
    }
}

@MainActor
struct SignInFlowTests {
    @Test("otp_required moves to the code step and keeps the password for the second request")
    func otpRequiredOpensTheCodeStep() async {
        let script = ScriptedLogin([codeRequired])
        let (flow, store) = makeFlow(script)

        await flow.signIn(with: store, remember: true)

        #expect(flow.step == .oneTimeCode)
        #expect(flow.state == .idle)
        #expect(flow.form.password == "secret")
        #expect(store.isSignedIn == false)
    }

    @Test("Six digits submit on their own and finish sign in with the code")
    func sixDigitsSubmitTheCode() async throws {
        let script = ScriptedLogin([codeRequired, signedIn])
        let tokens = InMemoryTokenStore()
        let (flow, store) = makeFlow(script, tokens: tokens)
        await flow.signIn(with: store, remember: true)

        #expect(flow.updateCode("12345") == false)
        #expect(flow.updateCode("123456"))
        #expect(flow.updateCode("123456") == false)
        await flow.verifyCode(with: store, remember: true)

        let requests = await script.requests
        let second = try #require(requests.last)
        #expect(requests.count == 2)
        #expect(requests.first?.oneTimePassword == nil)
        #expect(second.oneTimePassword == "123456")
        #expect(second.twoFactorSetupToken == nil)
        #expect(second.username == "alex.morgan")
        #expect(store.isSignedIn)
        #expect(tokens.load() == "1|abc")
    }

    @Test("A short code is not sent")
    func shortCodeIsNotSent() async {
        let script = ScriptedLogin([codeRequired])
        let (flow, store) = makeFlow(script)
        await flow.signIn(with: store, remember: true)

        flow.updateCode("123")
        await flow.verifyCode(with: store, remember: true)

        #expect(flow.codeError == "Enter the 6-digit code.")
        #expect(await script.requests.count == 1)
    }

    @Test("A wrong code shows the server's error and clears the code")
    func wrongCodeShowsTheServerError() async {
        let script = ScriptedLogin([codeRequired, invalidCode])
        let (flow, store) = makeFlow(script)
        await flow.signIn(with: store, remember: true)

        flow.updateCode("000000")
        await flow.verifyCode(with: store, remember: true)

        #expect(flow.step == .oneTimeCode)
        #expect(flow.code.isEmpty)
        #expect(flow.codeError == "Invalid OTP is given. Please check your Authenticator App")
        #expect(flow.state.failure == nil)
    }

    @Test("Typing again clears the code error")
    func typingClearsTheCodeError() async {
        let script = ScriptedLogin([codeRequired, invalidCode])
        let (flow, store) = makeFlow(script)
        await flow.signIn(with: store, remember: true)
        flow.updateCode("000000")
        await flow.verifyCode(with: store, remember: true)

        flow.updateCode("1")

        #expect(flow.codeError == nil)
    }

    @Test("Too many requests on the code step is shown as sent")
    func throttlingOnTheCodeStepIsShown() async {
        let script = ScriptedLogin([codeRequired, .failure(APIError.tooManyAttempts(message: "Too Many Attempts."))])
        let (flow, store) = makeFlow(script)
        await flow.signIn(with: store, remember: true)

        flow.updateCode("123456")
        await flow.verifyCode(with: store, remember: true)

        #expect(flow.step == .oneTimeCode)
        #expect(flow.state.failure == .tooManyAttempts(message: "Too Many Attempts."))
        #expect(flow.form.password == "secret")
    }

    @Test("Setup required opens the setup step, then the code goes with the setup token")
    func setupSendsTheTokenWithTheCode() async throws {
        let script = ScriptedLogin([setupRequired(sampleSetup), signedIn])
        let (flow, store) = makeFlow(script)

        await flow.signIn(with: store, remember: false)
        #expect(flow.step == .twoFactorSetup(sampleSetup))
        #expect(flow.codeError == nil)

        flow.updateCode("654321")
        await flow.verifyCode(with: store, remember: false)

        let second = try #require(await script.requests.last)
        #expect(second.oneTimePassword == "654321")
        #expect(second.twoFactorSetupToken == sampleSetup.setupToken)
        #expect(store.isSignedIn)
    }

    @Test("An expired setup is replaced by the new one with a notice")
    func expiredSetupIsRenewed() async {
        let script = ScriptedLogin([setupRequired(sampleSetup), setupRequired(renewedSetup)])
        let (flow, store) = makeFlow(script)
        await flow.signIn(with: store, remember: true)

        flow.updateCode("654321")
        await flow.verifyCode(with: store, remember: true)

        #expect(flow.step == .twoFactorSetup(renewedSetup))
        #expect(flow.code.isEmpty)
        #expect(flow.codeError == "That setup expired, so here is a new key. Add it to your authenticator app, then enter the code it shows.")
    }

    @Test("A wrong code during setup keeps the same setup and shows the server's error")
    func wrongSetupCodeKeepsTheSetup() async {
        let script = ScriptedLogin([setupRequired(sampleSetup), invalidCode])
        let (flow, store) = makeFlow(script)
        await flow.signIn(with: store, remember: true)

        flow.updateCode("000000")
        await flow.verifyCode(with: store, remember: true)

        #expect(flow.step == .twoFactorSetup(sampleSetup))
        #expect(flow.codeError == "Invalid OTP is given. Please check your Authenticator App")
    }

    @Test("Signing in clears the password, code and setup")
    func signingInClearsTheSecrets() async {
        let script = ScriptedLogin([setupRequired(sampleSetup), signedIn])
        let (flow, store) = makeFlow(script)
        await flow.signIn(with: store, remember: true)
        flow.updateCode("654321")

        await flow.verifyCode(with: store, remember: true)

        #expect(flow.form.password.isEmpty)
        #expect(flow.code.isEmpty)
        #expect(flow.step == .credentials)
        #expect(flow.step.setup == nil)
        #expect(flow.form.username == " alex.morgan ")
    }

    @Test("Back to sign in clears the password, code and setup but keeps the username")
    func backClearsTheSecrets() async {
        let script = ScriptedLogin([setupRequired(sampleSetup)])
        let (flow, store) = makeFlow(script)
        await flow.signIn(with: store, remember: true)
        flow.updateCode("65")

        flow.backToCredentials()

        #expect(flow.step == .credentials)
        #expect(flow.form.password.isEmpty)
        #expect(flow.code.isEmpty)
        #expect(flow.codeError == nil)
        #expect(flow.state == .idle)
        #expect(flow.form.username == " alex.morgan ")
    }

    @Test("A wrong password on the first step clears the password and shows the server's message")
    func wrongPasswordStaysOnCredentials() async {
        let rejection = LoginRejection(
            message: "These credentials do not match our records.",
            fieldErrors: [LoginRejection.usernameField: "These credentials do not match our records."]
        )
        let script = ScriptedLogin([.failure(rejection)])
        let (flow, store) = makeFlow(script)

        await flow.signIn(with: store, remember: true)

        #expect(flow.step == .credentials)
        #expect(flow.form.password.isEmpty)
        #expect(flow.state.failure == .invalidCredentials(message: "These credentials do not match our records."))
        #expect(flow.showsFieldErrors == false)
    }
}
