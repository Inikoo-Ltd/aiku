import Foundation

struct LoginForm: Equatable {
    var username = ""
    var password = ""

    var trimmedUsername: String {
        username.trimmingCharacters(in: .whitespacesAndNewlines)
    }

    var canSubmit: Bool {
        !trimmedUsername.isEmpty && !password.isEmpty
    }
}
