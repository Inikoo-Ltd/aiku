import Foundation
import Testing
@testable import Aiku

private actor AccountCalls {
    var changes: [PasswordChangeBody] = []
    var codeRequests: [String] = []
    var resets: [PasswordResetBody] = []

    func record(_ body: PasswordChangeBody) { changes.append(body) }
    func recordCode(_ login: String) { codeRequests.append(login) }
    func record(_ body: PasswordResetBody) { resets.append(body) }
}

private func accountService(calls: AccountCalls, failure: Error? = nil) -> AuthService {
    AuthService(
        login: { _ in throw APIError.offline },
        fetchProfile: { throw APIError.offline },
        logout: {},
        changePassword: { body in
            await calls.record(body)
            if let failure { throw failure }
        },
        sendResetCode: { login in
            await calls.recordCode(login)
            if let failure { throw failure }
        },
        resetPassword: { body in
            await calls.record(body)
            if let failure { throw failure }
        }
    )
}

@MainActor
struct AccountTests {
    @Test("The profile shows the lead position as the role and the employee's organisation")
    func mobileProfileFillsTheSession() {
        let session = Session()
        let profile = MobileProfileResponse(
            id: 214, username: "artha", name: "Artha", position: "Developer", jobTitle: "Developer",
            jobPositions: [.init(code: "gp-hd", name: "Engineer"), .init(code: "gp-hd-m", name: "Lead engineer"), .init(code: "gp-qa", name: "QA")],
            organisation: .init(id: 2, code: "aw", name: "AW Advantage")
        )

        session.apply(profile)

        #expect(session.profile.name == "Artha")
        #expect(session.profile.role == "Lead engineer")
        #expect(session.profile.organisation == "AW Advantage")
    }

    @Test("The job title is the role when there are no positions")
    func jobTitleWithoutPositions() {
        let session = Session()

        session.apply(MobileProfileResponse(id: 1, username: "sam", name: nil, position: "Packer", jobTitle: "Packer", jobPositions: [], organisation: nil))

        #expect(session.profile.name == "sam")
        #expect(session.profile.role == "Packer")
    }

    @Test("A chosen warehouse keeps its organisation when the profile refreshes")
    func warehouseOrganisationWins() {
        let session = Session()
        session.apply(warehouse: "Main Warehouse", code: "WH1", organisation: "AW UK")

        session.apply(MobileProfileResponse(id: 1, username: "a", name: "A", position: nil, jobTitle: nil, jobPositions: [], organisation: .init(id: 2, code: nil, name: "AW Advantage")))

        #expect(session.profile.organisation == "AW UK")
        #expect(session.warehouseLabel == "Main Warehouse · WH1")
    }

    @Test("Change password checks the three fields before calling the API")
    func changePasswordIsValidated() async {
        let calls = AccountCalls()
        let store = AuthStore(service: accountService(calls: calls), tokens: InMemoryTokenStore(token: "1|t"), deviceName: "iPhone")

        let error = await store.changePassword(current: "", password: "new-secret", confirmation: "other")

        #expect(error?.field(PasswordField.current) == "Enter your current password.")
        #expect(error?.field(PasswordField.confirmation) == "The two new passwords don’t match.")
        #expect(await calls.changes.isEmpty)
    }

    @Test("Change password sends the confirmation and shows the server's field error")
    func changePasswordShowsServerFieldErrors() async throws {
        let calls = AccountCalls()
        let refused = FormValidationError(message: "The current password is incorrect.", fields: [PasswordField.current: "The current password is incorrect."])
        let store = AuthStore(service: accountService(calls: calls, failure: refused), tokens: InMemoryTokenStore(token: "1|t"), deviceName: "iPhone")

        let error = await store.changePassword(current: "old", password: "new-secret", confirmation: "new-secret")

        #expect(error?.field(PasswordField.current) == "The current password is incorrect.")
        #expect(error?.message == nil)
        let body = try #require(await calls.changes.first)
        #expect(body.passwordConfirmation == "new-secret")
    }

    @Test("A password reset sends the code and the new password for the login")
    func resetSendsCodeAndPassword() async throws {
        let calls = AccountCalls()
        let store = AuthStore(service: accountService(calls: calls), tokens: InMemoryTokenStore(), deviceName: "iPhone")

        #expect(await store.sendPasswordResetCode(login: "  ")?.field(PasswordField.login) == "Enter your username or email.")
        #expect(await store.sendPasswordResetCode(login: " artha ") == nil)
        #expect(await calls.codeRequests == ["artha"])
        #expect(await store.resetPassword(login: "artha", code: "", password: "a", confirmation: "a")?.field(PasswordField.code) == "Enter the 6-digit code from the email.")
        #expect(await store.resetPassword(login: "artha", code: " 123456 ", password: "new-secret", confirmation: "new-secret") == nil)

        let body = try #require(await calls.resets.first)
        #expect(body.code == "123456")
        #expect(body.login == "artha")
    }

    @Test("No connection while resetting says it needs internet")
    func resetOffline() async {
        let store = AuthStore(service: accountService(calls: AccountCalls(), failure: APIError.offline), tokens: InMemoryTokenStore(), deviceName: "iPhone")

        let error = await store.sendPasswordResetCode(login: "artha")

        #expect(error?.message == "Resetting your password needs internet — check Wi-Fi and try again.")
    }

    @Test("A username login is filled back in on sign in, an email is not")
    func usernameDetection() {
        #expect(PasswordRules.isUsername("artha"))
        #expect(PasswordRules.isUsername("artha@aw-advantage.com") == false)
    }

    @Test("A 422 response turns into one message per field")
    func formErrorsDecode() {
        let body = #"{"message":"The password field must be at least 8 characters.","errors":{"password":["The password field must be at least 8 characters.","Second rule"],"current_password":["The current password is incorrect."]}}"#

        let error = FormValidationError.decode(from: Data(body.utf8))

        #expect(error.fields["password"] == "The password field must be at least 8 characters.")
        #expect(error.fields["current_password"] == "The current password is incorrect.")
    }

    @Test("The mobile profile decodes")
    func mobileProfileDecodes() throws {
        let json = #"{"data":{"id":214,"username":"artha","name":"Artha","position":"Developer","job_title":"Developer","job_positions":[{"code":"gp-hd-m","name":"Lead engineer"}],"organisation":{"id":2,"code":"aw","name":"AW Advantage"}}}"#
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase

        let profile = try decoder.decode(DataEnvelope<MobileProfileResponse>.self, from: Data(json.utf8)).value

        #expect(profile.jobPositions.first?.name == "Lead engineer")
        #expect(profile.organisation?.name == "AW Advantage")
    }

    @Test("Password forms post to the mobile password routes")
    func passwordRoutes() throws {
        let client = APIClient(configuration: AppConfiguration(apiBaseURL: URL(string: "http://localhost/app/api")!))
        let change = client.makeRequest(for: .jsonBody("mobile/password", method: .post, PasswordChangeBody(currentPassword: "a", password: "b", passwordConfirmation: "b")))
        let body = try JSONSerialization.jsonObject(with: try #require(change.httpBody)) as? [String: String]

        #expect(change.url?.absoluteString == "http://localhost/app/api/mobile/password")
        #expect(body?["current_password"] == "a")
        #expect(body?["password_confirmation"] == "b")
    }
}
