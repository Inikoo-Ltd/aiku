import SwiftUI

struct LoginView: View {
    var onForgotPassword: () -> Void = {}
    var notice: String?
    var initialUsername = ""

    @Environment(AuthStore.self) private var authStore

    @State private var flow = SignInFlow()
    @State private var isPasswordVisible = false
    @AppStorage("auth.rememberMe") private var rememberMe = true
    @FocusState private var focusedField: Field?

    private enum Field {
        case username
        case password
    }

    var body: some View {
        AuthLayout {
            switch flow.step {
            case .credentials:
                credentialsStep
            case .oneTimeCode:
                TwoFactorCodeStep(flow: flow) { verifyCode() }
            case .twoFactorSetup(let setup):
                TwoFactorSetupStep(flow: flow, setup: setup) { verifyCode() }
            }
        }
        .accessibilityIdentifier("login.root")
        .onAppear {
            if flow.form.username.isEmpty, !initialUsername.isEmpty {
                flow.form.username = initialUsername
                focusedField = .password
            }
        }
        .onDisappear {
            flow.clearSecrets()
        }
    }

    @ViewBuilder
    private var credentialsStep: some View {
        if let notice, flow.state.failure == nil {
            InlineAlert(tone: .success, title: notice)
                .accessibilityIdentifier("login.notice")
        }
        if let failure = flow.state.failure {
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

    private var isLocked: Bool { flow.isSigningIn }

    private var usernameField: some View {
        AuthField(
            "Username",
            error: flow.showsFieldErrors ? flow.form.usernameError : nil,
            isFocused: focusedField == .username,
            isLocked: isLocked
        ) {
            TextField("Username", text: $flow.form.username)
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
                error: flow.showsFieldErrors ? flow.form.passwordError : nil,
                isFocused: focusedField == .password,
                isLocked: isLocked
            ) {
                Group {
                    if isPasswordVisible {
                        TextField("••••••••", text: $flow.form.password)
                    } else {
                        SecureField("••••••••", text: $flow.form.password)
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
        if isLocked { return L10n.text("Signing in…") }
        return flow.state.failure == nil ? L10n.text("Sign in") : L10n.text("Try again")
    }

    private var passkeyButton: some View {
        Button {
            focusedField = nil
            flow.show(.server(message: "Passkey sign in isn’t available in the app yet. Use your username and password."))
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
        guard !isLocked else { return }
        if flow.form.canSubmit {
            focusedField = nil
        }
        Task {
            await flow.signIn(with: authStore, remember: rememberMe)
        }
    }

    private func verifyCode() {
        guard !isLocked else { return }
        Task {
            await flow.verifyCode(with: authStore, remember: rememberMe)
        }
    }
}
