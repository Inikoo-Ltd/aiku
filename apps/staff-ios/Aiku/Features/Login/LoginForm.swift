import Foundation

struct LoginForm: Equatable {
    var username = ""
    var password = ""

    var trimmedUsername: String {
        username.trimmingCharacters(in: .whitespacesAndNewlines)
    }

    var usernameError: String? {
        if trimmedUsername.isEmpty {
            return "Enter your username."
        }
        if trimmedUsername.contains(where: \.isWhitespace) || trimmedUsername.hasSuffix("@") {
            return "Enter a valid username."
        }
        return nil
    }

    var passwordError: String? {
        password.isEmpty ? "Enter your password." : nil
    }

    var canSubmit: Bool {
        usernameError == nil && passwordError == nil
    }
}

enum LoginFailure: Error, Equatable, Sendable {
    case invalidCredentials(message: String)
    case otpRequired(message: String)
    case tooManyAttempts(message: String)
    case offline
    case server(message: String)

    init(_ error: APIError) {
        switch error {
        case .validation(let message, let code):
            self = code == "otp_required" ? .otpRequired(message: message) : .invalidCredentials(message: message)
        case .tooManyAttempts(let message):
            self = .tooManyAttempts(message: message)
        case .rejected(let message):
            self = .server(message: message)
        case .offline:
            self = .offline
        case .unauthorized, .invalidResponse, .http, .unexpectedBody:
            self = .server(message: error.localizedDescription)
        }
    }

    var title: String {
        switch self {
        case .invalidCredentials: "Incorrect username or password"
        case .otpRequired: "Two-factor code needed"
        case .tooManyAttempts: "Too many attempts"
        case .offline: "No connection"
        case .server: "Couldn’t sign in"
        }
    }

    var message: String {
        switch self {
        case .invalidCredentials: "Check your details and try again."
        case .otpRequired(let message), .tooManyAttempts(let message), .server(let message): message
        case .offline: "Sign in needs internet — check Wi-Fi and try again."
        }
    }
}

enum LoginState: Equatable, Sendable {
    case idle
    case signingIn
    case failed(LoginFailure)

    var isSigningIn: Bool { self == .signingIn }

    var failure: LoginFailure? {
        if case .failed(let failure) = self { return failure }
        return nil
    }
}

struct ForgotPasswordForm: Equatable {
    var identifier = ""

    var trimmedIdentifier: String {
        identifier.trimmingCharacters(in: .whitespacesAndNewlines)
    }

    var error: String? {
        trimmedIdentifier.isEmpty ? "Enter your username or email." : nil
    }
}

enum AuthQuotes {
    static let lines = [
        "ship it before the light goes.",
        "a box packed well is a promise kept.",
        "every shelf remembers what it held.",
        "count twice, carry once.",
        "the day is a queue; clear it kindly.",
        "small things, sent far, arrive as big things.",
        "stock moves; so do we.",
        "what is picked is half-way home.",
        "a good label is a love letter to a stranger.",
        "the order was placed by a person. pack it like one.",
        "slow is smooth, smooth is fast.",
        "make the next line on the list easier than this one.",
        "a barcode is a tiny poem only the scanner can read.",
        "leave the bay tidier than you found it.",
        "tomorrow's dispatch starts with today's put-away.",
        "be the person who updates the stock count.",
        "nothing ships itself. thank you."
    ]

    static func line(for date: Date, calendar: Calendar = .current) -> String {
        let day = calendar.ordinality(of: .day, in: .year, for: date) ?? 1
        return lines[(day - 1) % lines.count]
    }
}
