import Foundation

struct PasswordFormError: Error, Equatable, Sendable {
    var fields: [String: String] = [:]
    var message: String?

    func field(_ name: String) -> String? {
        fields[name]
    }

    init(fields: [String: String] = [:], message: String? = nil) {
        self.fields = fields
        self.message = message
    }

    init(_ error: Error, offlineMessage: String) {
        switch error {
        case let form as FormValidationError:
            self.init(fields: form.fields, message: form.fields.isEmpty ? form.message : nil)
        case APIError.offline:
            self.init(message: offlineMessage)
        default:
            self.init(message: error.localizedDescription)
        }
    }
}

enum PasswordField {
    static let login = "login"
    static let code = "code"
    static let current = "current_password"
    static let password = "password"
    static let confirmation = "password_confirmation"
}

enum PasswordRules {
    static let resendCooldownSeconds = 30

    static func changeErrors(current: String, password: String, confirmation: String) -> PasswordFormError? {
        var fields = newPasswordErrors(password: password, confirmation: confirmation)
        if current.isEmpty { fields[PasswordField.current] = "Enter your current password." }
        return fields.isEmpty ? nil : PasswordFormError(fields: fields)
    }

    static func loginError(_ login: String) -> PasswordFormError? {
        login.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
            ? PasswordFormError(fields: [PasswordField.login: "Enter your username or email."])
            : nil
    }

    static func resetErrors(code: String, password: String, confirmation: String) -> PasswordFormError? {
        var fields = newPasswordErrors(password: password, confirmation: confirmation)
        if code.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty {
            fields[PasswordField.code] = "Enter the 6-digit code from the email."
        }
        return fields.isEmpty ? nil : PasswordFormError(fields: fields)
    }

    static func newPasswordErrors(password: String, confirmation: String) -> [String: String] {
        var fields: [String: String] = [:]
        if password.isEmpty { fields[PasswordField.password] = "Enter a new password." }
        if confirmation.isEmpty {
            fields[PasswordField.confirmation] = "Enter the new password again."
        } else if !password.isEmpty, confirmation != password {
            fields[PasswordField.confirmation] = "The two new passwords don’t match."
        }
        return fields
    }

    static func isUsername(_ login: String) -> Bool {
        !login.contains("@")
    }
}
