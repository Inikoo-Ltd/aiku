import SwiftUI

struct LoginView: View {
    var onSignIn: () -> Void = {}

    @State private var form = LoginForm()
    @State private var isPasswordVisible = false
    @FocusState private var focusedField: Field?

    private enum Field {
        case username
        case password
    }

    var body: some View {
        ScrollView {
            VStack(spacing: 32) {
                header
                fields
                signInButton
            }
            .padding(.horizontal, 24)
            .padding(.top, 72)
            .padding(.bottom, 24)
        }
        .scrollDismissesKeyboard(.interactively)
        .background(Color(.systemGroupedBackground))
        .accessibilityIdentifier("login.root")
    }

    private var header: some View {
        VStack(spacing: 12) {
            Image("AikuLogo")
                .resizable()
                .interpolation(.none)
                .scaledToFit()
                .frame(width: 88, height: 88)
                .accessibilityHidden(true)
            Text("Aiku")
                .font(.largeTitle.bold())
            Text("Sign in to your account")
                .font(.subheadline)
                .foregroundStyle(.secondary)
        }
    }

    private var fields: some View {
        VStack(alignment: .leading, spacing: 16) {
            VStack(alignment: .leading, spacing: 6) {
                Text("Username")
                    .font(.subheadline.weight(.medium))
                TextField("Username", text: $form.username)
                    .textContentType(.username)
                    .textInputAutocapitalization(.never)
                    .autocorrectionDisabled()
                    .submitLabel(.next)
                    .focused($focusedField, equals: .username)
                    .onSubmit { focusedField = .password }
                    .fieldStyle()
                    .accessibilityIdentifier("login.username")
            }

            VStack(alignment: .leading, spacing: 6) {
                Text("Password")
                    .font(.subheadline.weight(.medium))
                HStack {
                    Group {
                        if isPasswordVisible {
                            TextField("Password", text: $form.password)
                        } else {
                            SecureField("Password", text: $form.password)
                        }
                    }
                    .textContentType(.password)
                    .textInputAutocapitalization(.never)
                    .autocorrectionDisabled()
                    .submitLabel(.go)
                    .focused($focusedField, equals: .password)
                    .onSubmit(submit)
                    .accessibilityIdentifier("login.password")

                    Button {
                        isPasswordVisible.toggle()
                    } label: {
                        Image(systemName: isPasswordVisible ? "eye.slash" : "eye")
                            .foregroundStyle(.secondary)
                    }
                    .accessibilityLabel(isPasswordVisible ? "Hide password" : "Show password")
                }
                .fieldStyle()
            }
        }
    }

    private var signInButton: some View {
        Button(action: submit) {
            Text("Sign in")
                .font(.headline)
                .frame(maxWidth: .infinity)
                .padding(.vertical, 6)
        }
        .buttonStyle(.borderedProminent)
        .disabled(!form.canSubmit)
        .accessibilityIdentifier("login.submit")
    }

    private func submit() {
        guard form.canSubmit else { return }
        focusedField = nil
        onSignIn()
    }
}

private extension View {
    func fieldStyle() -> some View {
        padding(.horizontal, 14)
            .padding(.vertical, 12)
            .background(Color(.secondarySystemGroupedBackground), in: RoundedRectangle(cornerRadius: 10))
            .overlay(RoundedRectangle(cornerRadius: 10).stroke(Color(.separator)))
    }
}

#Preview {
    LoginView()
}
