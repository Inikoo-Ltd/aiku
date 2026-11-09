import Testing
@testable import Aiku

struct LoginFormTests {
    @Test func cannotSubmitWhenEmpty() {
        #expect(LoginForm().canSubmit == false)
    }

    @Test func cannotSubmitWithBlankUsername() {
        #expect(LoginForm(username: "   ", password: "secret").canSubmit == false)
    }

    @Test func cannotSubmitWithoutPassword() {
        #expect(LoginForm(username: "artha", password: "").canSubmit == false)
    }

    @Test func canSubmitWithUsernameAndPassword() {
        let form = LoginForm(username: " artha ", password: "secret")

        #expect(form.canSubmit)
        #expect(form.trimmedUsername == "artha")
    }
}
