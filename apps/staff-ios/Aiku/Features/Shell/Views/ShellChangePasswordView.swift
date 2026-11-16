import SwiftUI

struct ShellChangePasswordView: View {
    @Environment(AppRouter.self) private var router
    @Environment(AuthStore.self) private var authStore
    @Environment(ToastCenter.self) private var toasts
    @State private var current = ""
    @State private var password = ""
    @State private var confirmation = ""
    @State private var error = PasswordFormError()
    @State private var isSaving = false

    var body: some View {
        AppScreen(title: "Change password", kicker: "Profile · Security") {
            Text("Choose a new password for your Aiku account. Other phones signed in to your account will be signed out; this one stays signed in.")
                .font(.aiku(14))
                .foregroundStyle(AikuColor.text3)
            if let message = error.message {
                InlineAlert(tone: .danger, title: "Couldn’t change your password", message: message)
            }
            PasswordInput("Current password", text: $current, error: error.field(PasswordField.current), contentType: .password)
                .accessibilityIdentifier("password.current")
            PasswordInput("New password", text: $password, error: error.field(PasswordField.password), contentType: .newPassword)
                .accessibilityIdentifier("password.new")
            PasswordInput("Confirm new password", text: $confirmation, error: error.field(PasswordField.confirmation), contentType: .newPassword)
                .accessibilityIdentifier("password.confirm")
        } footer: {
            AikuButton(isSaving ? "Changing…" : "Change password", isLoading: isSaving) {
                save()
            }
            .accessibilityIdentifier("password.save")
        }
        .onChange(of: current) { _, _ in error.fields[PasswordField.current] = nil }
        .onChange(of: password) { _, _ in error.fields[PasswordField.password] = nil }
        .onChange(of: confirmation) { _, _ in error.fields[PasswordField.confirmation] = nil }
    }

    private func save() {
        guard !isSaving else { return }
        isSaving = true
        Task {
            let result = await authStore.changePassword(current: current, password: password, confirmation: confirmation)
            isSaving = false
            if let result {
                error = result
            } else {
                toasts.show("Password changed.")
                router.back()
            }
        }
    }
}

struct PasswordInput: View {
    let label: String
    @Binding var text: String
    var error: String?
    var contentType: UITextContentType = .password
    @State private var isVisible = false
    @FocusState private var isFocused: Bool

    init(_ label: String, text: Binding<String>, error: String? = nil, contentType: UITextContentType = .password) {
        self.label = label
        _text = text
        self.error = error
        self.contentType = contentType
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 6) {
            FieldLabel(label)
            FieldBox(isFocused: isFocused, hasError: error != nil) {
                Group {
                    if isVisible {
                        TextField(label, text: $text)
                    } else {
                        SecureField(label, text: $text)
                    }
                }
                .font(.aiku(16))
                .foregroundStyle(AikuColor.text)
                .textContentType(contentType)
                .textInputAutocapitalization(.never)
                .autocorrectionDisabled()
                .focused($isFocused)
                .padding(.horizontal, 12)
                Rectangle().fill(AikuColor.borderStrong).frame(width: 1)
                Button {
                    isVisible.toggle()
                } label: {
                    (isVisible ? AppIcon.eyeOff : AppIcon.eye).image
                        .font(.system(size: 17))
                        .foregroundStyle(AikuColor.text4)
                        .frame(width: 48)
                        .frame(maxHeight: .infinity)
                        .background(AikuColor.surface2)
                        .contentShape(Rectangle())
                }
                .buttonStyle(.plain)
                .accessibilityLabel(isVisible ? "Hide password" : "Show password")
            }
            if let error {
                FieldError(error)
            }
        }
    }
}
