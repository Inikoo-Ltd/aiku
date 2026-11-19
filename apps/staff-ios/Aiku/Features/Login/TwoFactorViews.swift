import SwiftUI
import UIKit
import UniformTypeIdentifiers

struct TwoFactorCodeStep: View {
    let flow: SignInFlow
    let onVerify: () -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: 18) {
            TwoFactorHeading(
                title: TwoFactorCopy.codeTitle,
                description: TwoFactorCopy.codeDescription(username: flow.form.trimmedUsername)
            )
            TwoFactorFailureAlert(flow: flow)
            OneTimeCodeField(flow: flow, focusesOnAppear: true, onComplete: onVerify)
            TwoFactorActions(flow: flow, onVerify: onVerify)
        }
        .accessibilityIdentifier("login.twoFactorCode")
    }
}

struct TwoFactorSetupStep: View {
    let flow: SignInFlow
    let setup: TwoFactorSetup
    let onVerify: () -> Void

    @Environment(\.openURL) private var openURL
    @State private var isAuthenticatorMissing = false

    var body: some View {
        VStack(alignment: .leading, spacing: 18) {
            TwoFactorHeading(title: TwoFactorCopy.setupTitle, description: TwoFactorCopy.setupDescription)
            TwoFactorFailureAlert(flow: flow)
            VStack(spacing: 10) {
                AikuButton("Open in authenticator app", icon: .shield, kind: .tertiary, size: .regular) {
                    openAuthenticator()
                }
                .accessibilityIdentifier("login.openAuthenticator")
                if isAuthenticatorMissing {
                    InlineAlert(
                        tone: .info,
                        title: "No authenticator app found",
                        message: "Install an authenticator app, such as Google Authenticator, then try again."
                    )
                }
            }
            TwoFactorSetupKeyPanel(setup: setup)
                .id(setup.setupToken)
            OneTimeCodeField(flow: flow, focusesOnAppear: false, onComplete: onVerify)
            TwoFactorActions(flow: flow, onVerify: onVerify)
        }
        .accessibilityIdentifier("login.twoFactorSetup")
        .onChange(of: setup.setupToken) {
            isAuthenticatorMissing = false
        }
    }

    private func openAuthenticator() {
        guard let url = setup.authenticatorURL else {
            isAuthenticatorMissing = true
            return
        }
        openURL(url) { accepted in
            isAuthenticatorMissing = !accepted
        }
    }
}

private struct TwoFactorHeading: View {
    let title: String
    let description: String

    var body: some View {
        HStack(alignment: .top, spacing: 12) {
            IconTile(.shield, tone: .info, size: 40)
            VStack(alignment: .leading, spacing: 4) {
                Text(title)
                    .font(.aiku(20, .bold))
                    .foregroundStyle(AikuColor.text)
                    .accessibilityAddTraits(.isHeader)
                Text(description)
                    .font(.aiku(14))
                    .foregroundStyle(AikuColor.text3)
                    .fixedSize(horizontal: false, vertical: true)
            }
        }
    }
}

private struct TwoFactorFailureAlert: View {
    let flow: SignInFlow

    var body: some View {
        if let failure = flow.state.failure {
            InlineAlert(tone: .danger, title: failure.title, message: failure.message)
                .accessibilityIdentifier("login.error")
        }
    }
}

private struct OneTimeCodeField: View {
    let flow: SignInFlow
    let focusesOnAppear: Bool
    let onComplete: () -> Void

    @FocusState private var isFocused: Bool

    var body: some View {
        AuthField(
            "Authentication code",
            error: flow.codeError,
            isFocused: isFocused,
            isLocked: flow.isSigningIn
        ) {
            TextField("000000", text: codeBinding)
                .textContentType(.oneTimeCode)
                .keyboardType(.numberPad)
                .font(.aiku(20, .semibold))
                .monospacedDigit()
                .focused($isFocused)
                .disabled(flow.isSigningIn)
                .accessibilityIdentifier("login.code")
        }
        .onAppear {
            if focusesOnAppear {
                isFocused = true
            }
        }
    }

    private var codeBinding: Binding<String> {
        Binding(
            get: { flow.code },
            set: { input in
                if flow.updateCode(input) {
                    isFocused = false
                    onComplete()
                }
            }
        )
    }
}

private struct TwoFactorActions: View {
    let flow: SignInFlow
    let onVerify: () -> Void

    var body: some View {
        VStack(spacing: 10) {
            Button {
                onVerify()
            } label: {
                HStack(spacing: 10) {
                    if flow.isSigningIn {
                        ProgressView().tint(AikuColor.onPrimary)
                    }
                    Text(flow.isSigningIn ? "Verifying…" : "Verify")
                }
            }
            .buttonStyle(AuthPrimaryButtonStyle())
            .opacity(flow.isSigningIn ? 0.85 : 1)
            .disabled(flow.isSigningIn)
            .accessibilityIdentifier("login.verify")
            AuthTextLink("Back to sign in") {
                flow.backToCredentials()
            }
            .disabled(flow.isSigningIn)
            .accessibilityIdentifier("login.backToSignIn")
        }
    }
}

private struct TwoFactorSetupKeyPanel: View {
    let setup: TwoFactorSetup

    @State private var qrCode: UIImage?
    @State private var isCopied = false

    var body: some View {
        VStack(spacing: 12) {
            Text("Authenticator on another phone? Scan this code with it, or type in the setup key.")
                .font(.aiku(13))
                .foregroundStyle(AikuColor.text3)
                .multilineTextAlignment(.center)
                .fixedSize(horizontal: false, vertical: true)
            if let qrCode {
                Image(uiImage: qrCode)
                    .interpolation(.none)
                    .resizable()
                    .scaledToFit()
                    .frame(maxWidth: 184)
                    .accessibilityLabel("QR code that adds Aiku to an authenticator app")
                    .accessibilityIdentifier("login.setupQRCode")
            }
            VStack(spacing: 2) {
                Text("Setup key")
                    .font(.aiku(12, .medium))
                    .foregroundStyle(AikuColor.text3)
                Text(setup.groupedSecret)
                    .font(.aiku(16, .semibold))
                    .tracking(1.2)
                    .foregroundStyle(AikuColor.text)
                    .multilineTextAlignment(.center)
                    .textSelection(.enabled)
                    .accessibilityIdentifier("login.setupKey")
            }
            AikuButton(
                isCopied ? "Copied" : "Copy key",
                icon: isCopied ? .check : nil,
                kind: .tertiary,
                size: .small,
                fullWidth: false
            ) {
                copyKey()
            }
            .accessibilityIdentifier("login.copySetupKey")
        }
        .frame(maxWidth: .infinity)
        .padding(14)
        .background(AikuColor.surface2, in: RoundedRectangle(cornerRadius: 6))
        .overlay(RoundedRectangle(cornerRadius: 6).strokeBorder(AikuColor.border))
        .task {
            qrCode = TwoFactorQRCode.image(for: setup)
        }
    }

    private func copyKey() {
        UIPasteboard.general.setItems(
            [[UTType.utf8PlainText.identifier: setup.secret]],
            options: [.localOnly: true, .expirationDate: Date.now.addingTimeInterval(120)]
        )
        isCopied = true
    }
}
