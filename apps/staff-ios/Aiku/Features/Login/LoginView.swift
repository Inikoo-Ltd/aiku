import SwiftUI

struct LoginView: View {
    var onForgotPassword: () -> Void = {}

    @Environment(AuthStore.self) private var authStore

    @State private var form = LoginForm()
    @State private var state = LoginState.idle
    @State private var hasSubmitted = false
    @State private var isPasswordVisible = false
    @AppStorage("auth.rememberMe") private var rememberMe = true
    @FocusState private var focusedField: Field?

    private enum Field {
        case username
        case password
    }

    var body: some View {
        AuthLayout {
            if let failure = state.failure {
                InlineAlert(tone: .danger, title: failure.title, message: failure.message)
                    .accessibilityIdentifier("login.error")
            }
            usernameField
            passwordField
            rememberRow
            VStack(spacing: 10) {
                signInButton
                passkeyButton
            }
        }
        .accessibilityIdentifier("login.root")
    }

    private var isLocked: Bool { state.isSigningIn }

    private var usernameField: some View {
        AuthField(
            "Username",
            error: hasSubmitted ? form.usernameError : nil,
            isFocused: focusedField == .username,
            isLocked: isLocked
        ) {
            TextField("Username", text: $form.username)
                .textContentType(.username)
                .textInputAutocapitalization(.never)
                .autocorrectionDisabled()
                .submitLabel(.next)
                .focused($focusedField, equals: .username)
                .onSubmit { focusedField = .password }
                .disabled(isLocked)
                .accessibilityIdentifier("login.username")
        }
    }

    private var passwordField: some View {
        VStack(alignment: .leading, spacing: 6) {
            AuthField(
                "Password",
                error: hasSubmitted ? form.passwordError : nil,
                isFocused: focusedField == .password,
                isLocked: isLocked
            ) {
                Group {
                    if isPasswordVisible {
                        TextField("••••••••", text: $form.password)
                    } else {
                        SecureField("••••••••", text: $form.password)
                    }
                }
                .textContentType(.password)
                .textInputAutocapitalization(.never)
                .autocorrectionDisabled()
                .submitLabel(.go)
                .focused($focusedField, equals: .password)
                .onSubmit(submit)
                .disabled(isLocked)
                .accessibilityIdentifier("login.password")
            } accessory: {
                if !isLocked {
                    Rectangle().fill(AikuColor.borderStrong).frame(width: 1)
                    Button {
                        isPasswordVisible.toggle()
                    } label: {
                        (isPasswordVisible ? AppIcon.eyeOff : AppIcon.eye).image
                            .font(.system(size: 17))
                            .foregroundStyle(AikuColor.text4)
                            .frame(width: 48)
                            .frame(maxHeight: .infinity)
                            .background(AikuColor.surface2)
                            .contentShape(Rectangle())
                    }
                    .buttonStyle(.plain)
                    .accessibilityLabel(isPasswordVisible ? "Hide password" : "Show password")
                }
            }
            AuthTextLink("Forgot password?", action: onForgotPassword)
                .disabled(isLocked)
                .accessibilityIdentifier("login.forgot")
        }
    }

    private var rememberRow: some View {
        CheckboxRow("Remember me", isOn: $rememberMe)
            .disabled(isLocked)
            .padding(.top, -10)
            .accessibilityIdentifier("login.remember")
    }

    private var signInButton: some View {
        Button(action: submit) {
            HStack(spacing: 10) {
                if isLocked {
                    ProgressView().tint(AikuColor.onPrimary)
                }
                Text(buttonTitle)
            }
        }
        .buttonStyle(AuthPrimaryButtonStyle())
        .opacity(isLocked ? 0.85 : 1)
        .disabled(isLocked)
        .accessibilityIdentifier("login.submit")
    }

    private var buttonTitle: String {
        if isLocked { return "Signing in…" }
        return state.failure == nil ? "Sign in" : "Try again"
    }

    private var passkeyButton: some View {
        Button {
            focusedField = nil
            state = .failed(.server(message: "Passkey sign in isn’t available in the app yet. Use your username and password."))
        } label: {
            HStack(spacing: 8) {
                AppIcon.passkey.image.font(.system(size: 16))
                Text("Sign in with passkey")
            }
            .font(.aiku(15, .medium))
            .foregroundStyle(isLocked ? AikuColor.faint : AikuColor.text2)
            .frame(maxWidth: .infinity, minHeight: 48)
            .background(isLocked ? AikuColor.surface2 : AikuColor.surface, in: RoundedRectangle(cornerRadius: 6))
            .overlay(RoundedRectangle(cornerRadius: 6).strokeBorder(isLocked ? AikuColor.border : AikuColor.borderStrong))
            .contentShape(RoundedRectangle(cornerRadius: 6))
        }
        .buttonStyle(.plain)
        .disabled(isLocked)
        .accessibilityIdentifier("login.passkey")
    }

    private func submit() {
        hasSubmitted = true
        guard form.canSubmit, !isLocked else { return }
        focusedField = nil
        state = .signingIn
        Task {
            do throws(LoginFailure) {
                try await authStore.signIn(username: form.trimmedUsername, password: form.password, remember: rememberMe)
                state = .idle
            } catch {
                form.password = ""
                hasSubmitted = false
                state = .failed(error)
            }
        }
    }
}
