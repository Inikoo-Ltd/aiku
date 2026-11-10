import Foundation
import Testing
@testable import Aiku

private actor CallLog {
    var requests: [MobileLoginRequest] = []
    var logouts = 0

    func record(_ request: MobileLoginRequest) { requests.append(request) }
    func recordLogout() { logouts += 1 }
}

private let sampleProfile = ProfileResponse(
    id: 7,
    username: "alex.morgan",
    contactName: "Alex Morgan",
    email: "alex@example.com",
    employee: [.init(contactName: "Alex Morgan", organisationSlug: "aw", workerNumber: "12", jobTitle: "Picker", email: nil)]
)

private func makeService(log: CallLog, login: Result<MobileLoginResponse, APIError>, profile: Result<ProfileResponse, APIError> = .success(sampleProfile)) -> AuthService {
    AuthService(
        login: { request in
            await log.record(request)
            return try login.get()
        },
        fetchProfile: { try profile.get() },
        logout: { await log.recordLogout() }
    )
}

@MainActor
struct AuthStoreTests {
    @Test func signInStoresTheTokenAndProfile() async throws {
        let log = CallLog()
        let tokens = InMemoryTokenStore()
        let store = AuthStore(
            service: makeService(log: log, login: .success(MobileLoginResponse(token: "1|abc", expiresAt: nil, user: sampleProfile))),
            tokens: tokens,
            deviceName: "iPhone · iOS 26.0"
        )

        try await store.signIn(username: "alex.morgan", password: "secret", remember: true)

        #expect(store.isSignedIn)
        #expect(store.profile == sampleProfile)
        #expect(tokens.load() == "1|abc")
        #expect(tokens.persistedToken == "1|abc")
        let request = try #require(await log.requests.first)
        #expect(request.deviceName == "iPhone · iOS 26.0")
        #expect(request.username == "alex.morgan")
    }

    @Test func signInWithoutRememberMeKeepsTheTokenOnlyInMemory() async throws {
        let tokens = InMemoryTokenStore()
        let store = AuthStore(
            service: makeService(log: CallLog(), login: .success(MobileLoginResponse(token: "1|abc", expiresAt: nil, user: sampleProfile))),
            tokens: tokens,
            deviceName: "iPhone"
        )

        try await store.signIn(username: "alex.morgan", password: "secret", remember: false)

        #expect(tokens.load() == "1|abc")
        #expect(tokens.persistedToken == nil)
    }

    @Test func wrongPasswordIsReportedAsInvalidCredentials() async {
        let store = AuthStore(
            service: makeService(log: CallLog(), login: .failure(.validation(message: "These credentials do not match our records.", code: nil))),
            tokens: InMemoryTokenStore(),
            deviceName: "iPhone"
        )

        await #expect(throws: LoginFailure.invalidCredentials(message: "These credentials do not match our records.")) {
            try await store.signIn(username: "alex", password: "wrong", remember: true)
        }
        #expect(store.isSignedIn == false)
    }

    @Test func apiErrorsMapToLoginFailures() {
        #expect(LoginFailure(.validation(message: "Enter OTP", code: "otp_required")) == .otpRequired(message: "Enter OTP"))
        #expect(LoginFailure(.tooManyAttempts(message: "Wait")) == .tooManyAttempts(message: "Wait"))
        #expect(LoginFailure(.offline) == .offline)
        #expect(LoginFailure(.http(statusCode: 500)).title == "Couldn’t sign in")
    }

    @Test func aSavedTokenMeansSignedInOnLaunch() {
        let store = AuthStore(service: makeService(log: CallLog(), login: .failure(.offline)), tokens: InMemoryTokenStore(token: "1|saved"), deviceName: "iPhone")

        #expect(store.isSignedIn)
    }

    @Test func refreshingWithAnExpiredTokenSignsOut() async {
        let tokens = InMemoryTokenStore(token: "1|old")
        let store = AuthStore(service: makeService(log: CallLog(), login: .failure(.offline), profile: .failure(.unauthorized)), tokens: tokens, deviceName: "iPhone")

        await store.refreshProfile()

        #expect(store.isSignedIn == false)
        #expect(tokens.load() == nil)
    }

    @Test func refreshFailureKeepsTheSessionAndShowsTheError() async {
        let store = AuthStore(service: makeService(log: CallLog(), login: .failure(.offline), profile: .failure(.offline)), tokens: InMemoryTokenStore(token: "1|t"), deviceName: "iPhone")

        await store.refreshProfile()

        #expect(store.isSignedIn)
        #expect(store.lastProfileError == "No connection")
    }

    @Test func aCancelledRefreshIsNotAnError() async {
        let store = AuthStore(
            service: AuthService(login: { _ in throw APIError.offline }, fetchProfile: { throw URLError(.cancelled) }, logout: {}),
            tokens: InMemoryTokenStore(token: "1|t"),
            deviceName: "iPhone"
        )

        await store.refreshProfile()

        #expect(store.isSignedIn)
        #expect(store.lastProfileError == nil)
    }

    @Test func signOutCallsTheApiAndClearsTheToken() async {
        let log = CallLog()
        let tokens = InMemoryTokenStore(token: "1|t")
        let store = AuthStore(service: makeService(log: log, login: .failure(.offline)), tokens: tokens, deviceName: "iPhone")

        await store.signOut()

        #expect(await log.logouts == 1)
        #expect(store.isSignedIn == false)
        #expect(tokens.load() == nil)
    }

    @Test func sessionTakesNameRoleAndEmailFromTheProfile() {
        let session = Session()

        session.apply(sampleProfile)

        #expect(session.profile.name == "Alex Morgan")
        #expect(session.profile.role == "Picker")
        #expect(session.profile.email == "alex@example.com")
        #expect(session.profile.initials == "AM")
    }

    @Test func leadPositionsHideTheirBasePosition() {
        let session = Session()
        let response = ProfileResponse(
            id: 214, username: "artha", contactName: "Artha", email: nil,
            employee: [.init(contactName: "Artha", organisationSlug: nil, workerNumber: nil, jobTitle: "Developer", email: nil)],
            jobPositions: [.init(name: "Engineer", code: "gp-hd"), .init(name: "Lead engineer", code: "gp-hd-m"), .init(name: "QA", code: "gp-qa")]
        )

        session.apply(response)

        #expect(session.profile.jobPositions == ["Lead engineer", "QA"])
        #expect(session.profile.role == "Lead engineer")
    }

    @Test func jobTitleIsUsedWhenThereAreNoPositions() {
        let session = Session()

        session.apply(ProfileResponse(id: 1, username: "artha", contactName: "Artha", email: nil, employee: [.init(contactName: nil, organisationSlug: nil, workerNumber: nil, jobTitle: "Developer", email: nil)]))

        #expect(session.profile.role == "Developer")
        #expect(session.profile.jobPositions.isEmpty)
    }

    @Test func sessionFallsBackToTheUsernameWithoutAContactName() {
        let session = Session()

        session.apply(ProfileResponse(id: 1, username: "sam", contactName: nil, email: nil, employee: []))

        #expect(session.profile.name == "sam")
        #expect(session.profile.role == "Staff")
    }

    @Test func deviceNameIsCappedForTheApi() {
        #expect(DeviceName.make(model: "iPhone", systemName: "iOS", systemVersion: "26.0") == "iPhone · iOS 26.0")
        #expect(DeviceName.make(model: String(repeating: "x", count: 120), systemName: "iOS", systemVersion: "1").count == 100)
    }
}

struct AuthAPITests {
    private struct StubTransport: HTTPTransport {
        let status: Int
        let body: String

        func data(for request: URLRequest) async throws -> (Data, URLResponse) {
            (Data(body.utf8), HTTPURLResponse(url: request.url!, statusCode: status, httpVersion: nil, headerFields: nil)!)
        }
    }

    private struct OfflineTransport: HTTPTransport {
        func data(for request: URLRequest) async throws -> (Data, URLResponse) {
            throw URLError(.notConnectedToInternet)
        }
    }

    private func client(_ transport: HTTPTransport) -> APIClient {
        APIClient(configuration: AppConfiguration(apiBaseURL: URL(string: "http://192.168.0.10:8000/app/api")!, mobileAppKey: "key"), transport: transport, tokenProvider: { "1|tok" })
    }

    @Test func loginPostsSnakeCaseBodyToTheMobileRoute() throws {
        let request = client(OfflineTransport()).makeRequest(for: .mobileLogin(MobileLoginRequest(username: "alex", password: "pw", deviceName: "iPhone")))

        #expect(request.url?.absoluteString == "http://192.168.0.10:8000/app/api/mobile/login")
        #expect(request.httpMethod == "POST")
        let body = try JSONSerialization.jsonObject(with: try #require(request.httpBody)) as? [String: String]
        #expect(body?["device_name"] == "iPhone")
        #expect(body?["username"] == "alex")
    }

    @Test func validationErrorsCarryTheFirstMessage() async {
        let body = #"{"message":"These credentials do not match our records.","errors":{"username":["These credentials do not match our records."]}}"#

        await #expect(throws: APIError.validation(message: "These credentials do not match our records.", code: nil)) {
            try await client(StubTransport(status: 422, body: body)).login(MobileLoginRequest(username: "a", password: "b", deviceName: "c"))
        }
    }

    @Test func otpRequiredKeepsItsCode() async {
        let body = #"{"message":"Enter OTP from your Authenticator App","code":"otp_required","errors":{"one_time_password":["Enter OTP from your Authenticator App"]}}"#

        await #expect(throws: APIError.validation(message: "Enter OTP from your Authenticator App", code: "otp_required")) {
            try await client(StubTransport(status: 422, body: body)).login(MobileLoginRequest(username: "a", password: "b", deviceName: "c"))
        }
    }

    @Test func networkFailureIsOffline() async {
        await #expect(throws: APIError.offline) {
            try await client(OfflineTransport()).fetchProfile()
        }
    }

    @Test func profileDecodesTheWrappedApiResource() async throws {
        let body = #"{"data":{"id":3,"username":"artha","contact_name":"Artha","email":null,"employee":[{"contact_name":"Artha","job_title":"Lead Engineer"}],"guest":[]}}"#

        let profile = try await client(StubTransport(status: 200, body: body)).fetchProfile()

        #expect(profile.employee?.first?.jobTitle == "Lead Engineer")
    }

    @Test func profileDecodesTheApiResource() async throws {
        let body = #"{"id":3,"username":"alex","avatar":{"original":"x"},"contact_name":"Alex Morgan","email":"a@b.c","status":true,"app_theme":null,"employee":[{"id":9,"contact_name":"Alex Morgan","organisation_slug":"aw","worker_number":"12","job_title":"Picker","job_positions":[]}],"guest":[]}"#

        let profile = try await client(StubTransport(status: 200, body: body)).fetchProfile()

        #expect(profile.contactName == "Alex Morgan")
        #expect(profile.employee?.first?.jobTitle == "Picker")
    }
}
