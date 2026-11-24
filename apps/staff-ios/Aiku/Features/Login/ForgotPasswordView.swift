import SwiftUI

struct ForgotPasswordView: View {
    var onBackToSignIn: () -> Void = {}
    var onPasswordReset: (String) -> Void = { _ in }

    @Environment(AuthStore.self) private var authStore
    @State private var isEnteringCode = false
    @State private var login = ""
    @State private var code = ""
    @State private var password = ""
    @State private var confirmation = ""
    @State private var codeNotice = ""
    @State private var error = PasswordFormError()
    @State private var isSending = false
    @State private var isResending = false
    @State private var resendSeconds = 0
    @FocusState private var isLoginFocused: Bool

    var body: some View {
        AuthLayout {
            VStack(alignment: .leading, spacing: 4) {
                Text(isEnteringCode ? "Choose a new password" : "Forgot password")
                    .font(.aiku(20, .bold))
                    .foregroundStyle(AikuColor.text)
                    .accessibilityAddTraits(.isHeader)
                Text(isEnteringCode ? "Enter the code from the email, then choose your new password." : "Enter your username or email and we’ll email you a 6-digit code to reset your password.")
                    .font(.aiku(14))
                    .foregroundStyle(AikuColor.text3)
            }
            if let message = error.message {
                InlineAlert(tone: .danger, title: isEnteringCode ? "Couldn’t reset your password" : "Couldn’t send the code", message: message)
            }
            if isEnteringCode {
                codeStep
            } else {
                loginStep
            }
            AuthTextLink("Remembered it? Sign in", action: onBackToSignIn)
                .padding(.top, -8)
                .accessibilityIdentifier("forgot.signIn")
        }
        .accessibilityIdentifier("forgot.root")
        .task(id: resendSeconds) {
            guard resendSeconds > 0 else { return }
            try? await Task.sleep(for: .seconds(1))
            if !Task.isCancelled { resendSeconds -= 1 }
        }
    }

    @ViewBuilder
    private var loginStep: some View {
        AuthField("Username or email", error: error.field(PasswordField.login), isFocused: isLoginFocused) {
            TextField("Username or email", text: $login)
                .textContentType(.username)
                .keyboardType(.emailAddress)
                .textInputAutocapitalization(.never)
                .autocorrectionDisabled()
                .submitLabel(.send)
                .focused($isLoginFocused)
                .onSubmit(sendCode)
                .disabled(isSending)
                .accessibilityIdentifier("forgot.identifier")
        }
        Button(action: sendCode) {
            HStack(spacing: 10) {
                if isSending { ProgressView().tint(AikuColor.onPrimary) }
                Text(isSending ? "Sending…" : "Send code")
            }
        }
        .buttonStyle(AuthPrimaryButtonStyle())
        .disabled(isSending)
        .accessibilityIdentifier("forgot.submit")
        Text("No email on your account? Ask your manager to reset your password.")
            .font(.aiku(13))
            .foregroundStyle(AikuColor.text3)
    }

    @ViewBuilder
    private var codeStep: some View {
        InlineAlert(tone: .success, title: codeNotice)
        AuthField("Code from the email", error: error.field(PasswordField.code)) {
            TextField("123456", text: $code)
                .textContentType(.oneTimeCode)
                .keyboardType(.numberPad)
                .accessibilityIdentifier("forgot.code")
        }
        PasswordInput("New password", text: $password, error: error.field(PasswordField.password), contentType: .newPassword)
            .accessibilityIdentifier("forgot.password")
        PasswordInput("Confirm new password", text: $confirmation, error: error.field(PasswordField.confirmation), contentType: .newPassword)
            .accessibilityIdentifier("forgot.confirm")
        Button(action: reset) {
            HStack(spacing: 10) {
                if isSending { ProgressView().tint(AikuColor.onPrimary) }
                Text(isSending ? "Resetting…" : "Reset password")
            }
        }
        .buttonStyle(AuthPrimaryButtonStyle())
        .disabled(isSending)
        .accessibilityIdentifier("forgot.reset")
        AuthTextLink(resendSeconds > 0 ? L10n.format("Resend code in %@", String(format: "%d:%02d", resendSeconds / 60, resendSeconds % 60)) : "Resend code") {
            resend()
        }
        .disabled(resendSeconds > 0 || isResending || isSending)
        .accessibilityIdentifier("forgot.resend")
    }

    private func sendCode() {
        guard !isSending else { return }
        isLoginFocused = false
        isSending = true
        Task {
            if let failure = await authStore.sendPasswordResetCode(login: login) {
                error = failure
            } else {
                error = PasswordFormError()
                codeNotice = "If an account matches, we’ve emailed a 6-digit code to its email address."
                resendSeconds = PasswordRules.resendCooldownSeconds
                isEnteringCode = true
            }
            isSending = false
        }
    }

    private func resend() {
        guard !isResending else { return }
        isResending = true
        Task {
            if let failure = await authStore.sendPasswordResetCode(login: login) {
                error = failure
            } else {
                error = PasswordFormError()
                codeNotice = "If an account matches, we’ve emailed a new 6-digit code to its email address."
                resendSeconds = PasswordRules.resendCooldownSeconds
            }
            isResending = false
        }
    }

    private func reset() {
        guard !isSending else { return }
        isSending = true
        Task {
            if let failure = await authStore.resetPassword(login: login, code: code, password: password, confirmation: confirmation) {
                error = failure
            } else {
                onPasswordReset(login.trimmingCharacters(in: .whitespacesAndNewlines))
            }
            isSending = false
        }
    }
}

struct AuthFlowView: View {
    @State private var isRecoveringPassword = false
    @State private var notice: String?
    @State private var username = ""

    var body: some View {
        if isRecoveringPassword {
            ForgotPasswordView(
                onBackToSignIn: { isRecoveringPassword = false },
                onPasswordReset: { login in
                    notice = "Password changed. Sign in with your new password."
                    username = PasswordRules.isUsername(login) ? login : ""
                    isRecoveringPassword = false
                }
            )
        } else {
            LoginView(
                onForgotPassword: {
                    notice = nil
                    isRecoveringPassword = true
                },
                notice: notice,
                initialUsername: username
            )
        }
    }
}
