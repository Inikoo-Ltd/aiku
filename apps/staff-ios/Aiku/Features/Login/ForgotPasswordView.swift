import SwiftUI

struct ForgotPasswordView: View {
    var onBackToSignIn: () -> Void = {}

    @State private var form = ForgotPasswordForm()
    @State private var hasSubmitted = false
    @State private var isSent = false
    @FocusState private var isFocused: Bool

    var body: some View {
        AuthLayout {
            VStack(alignment: .leading, spacing: 4) {
                Text("Forgot password")
                    .font(.aiku(20, .bold))
                    .foregroundStyle(AikuColor.text)
                    .accessibilityAddTraits(.isHeader)
                Text("Enter your username or email and we’ll send you a link to reset your password.")
                    .font(.aiku(14))
                    .foregroundStyle(AikuColor.text3)
            }
            if isSent {
                sentMessage
                Button("Back to sign in", action: onBackToSignIn)
                    .buttonStyle(AuthPrimaryButtonStyle())
                    .accessibilityIdentifier("forgot.back")
            } else {
                AuthField(
                    "Username or email",
                    error: hasSubmitted ? form.error : nil,
                    isFocused: isFocused
                ) {
                    TextField("Username or email", text: $form.identifier)
                        .textContentType(.username)
                        .keyboardType(.emailAddress)
                        .textInputAutocapitalization(.never)
                        .autocorrectionDisabled()
                        .submitLabel(.send)
                        .focused($isFocused)
                        .onSubmit(submit)
                        .accessibilityIdentifier("forgot.identifier")
                }
                Button("Send reset link", action: submit)
                    .buttonStyle(AuthPrimaryButtonStyle())
                    .accessibilityIdentifier("forgot.submit")
            }
            AuthTextLink("Remembered it? Sign in", action: onBackToSignIn)
                .padding(.top, -8)
                .accessibilityIdentifier("forgot.signIn")
        }
        .accessibilityIdentifier("forgot.root")
    }

    private var sentMessage: some View {
        HStack(alignment: .top, spacing: 10) {
            AppIcon.check.image
                .font(.system(size: 16, weight: .semibold))
                .foregroundStyle(AikuColor.success)
                .accessibilityHidden(true)
            (Text("If ") + Text(form.trimmedIdentifier).fontWeight(.semibold) + Text(" has an account, a reset link is on its way. Check your email."))
                .font(.aiku(14))
                .foregroundStyle(AikuColor.text2)
                .frame(maxWidth: .infinity, alignment: .leading)
        }
        .padding(12)
        .background(AikuColor.successSoft, in: RoundedRectangle(cornerRadius: 6))
        .accessibilityElement(children: .combine)
        .accessibilityIdentifier("forgot.sent")
    }

    private func submit() {
        hasSubmitted = true
        guard form.error == nil else { return }
        isFocused = false
        isSent = true
    }
}

struct AuthFlowView: View {
    @State private var isRecoveringPassword = false

    var body: some View {
        if isRecoveringPassword {
            ForgotPasswordView(onBackToSignIn: { isRecoveringPassword = false })
        } else {
            LoginView(onForgotPassword: { isRecoveringPassword = true })
        }
    }
}
