import Foundation
import Testing
@testable import Aiku

struct LoginFormTests {
    @Test func emptyFormShowsBothErrors() {
        let form = LoginForm()

        #expect(form.canSubmit == false)
        #expect(form.usernameError == "Enter your username.")
        #expect(form.passwordError == "Enter your password.")
    }

    @Test func blankUsernameIsMissing() {
        #expect(LoginForm(username: "   ", password: "secret").usernameError == "Enter your username.")
    }

    @Test func unfinishedEmailIsNotAValidUsername() {
        #expect(LoginForm(username: "alex@", password: "secret").usernameError == "Enter a valid username.")
        #expect(LoginForm(username: "alex morgan", password: "secret").canSubmit == false)
    }

    @Test func canSubmitWithUsernameAndPassword() {
        let form = LoginForm(username: " artha ", password: "secret")

        #expect(form.canSubmit)
        #expect(form.trimmedUsername == "artha")
    }

    @Test func failureMessagesMatchTheDesign() {
        #expect(LoginFailure.invalidCredentials(message: "x").title == "Incorrect username or password")
        #expect(LoginFailure.offline.message.contains("Sign in needs internet"))
        #expect(LoginState.failed(.offline).failure == .offline)
        #expect(LoginState.signingIn.isSigningIn)
    }

    @Test func forgotPasswordNeedsAnIdentifier() {
        #expect(ForgotPasswordForm().error == "Enter your username or email.")
        #expect(ForgotPasswordForm(identifier: " alex.morgan ").error == nil)
    }

    @Test func quoteIsStableWithinADay() {
        let calendar = Calendar(identifier: .gregorian)
        let date = Date(timeIntervalSince1970: 1_800_000_000)

        #expect(AuthQuotes.line(for: date, calendar: calendar) == AuthQuotes.line(for: date.addingTimeInterval(60), calendar: calendar))
        #expect(AuthQuotes.lines.contains("a box packed well is a promise kept."))
    }
}
