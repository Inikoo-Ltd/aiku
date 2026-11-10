import SwiftUI

enum AuthLinks {
    static let github = URL(string: "https://github.com/Inikoo-Ltd/aiku")
    static let documentation = URL(string: "https://aiku.io/docs")
    static let email = URL(string: "mailto:hello@aiku.io")
}

struct AuthLayout<Card: View>: View {
    @ViewBuilder var card: Card
    @State private var isLanguagePresented = false
    @State private var fullHeight: CGFloat = 0
    private let quote = AuthQuotes.line(for: .now)

    init(@ViewBuilder card: () -> Card) {
        self.card = card()
    }

    var body: some View {
        ScrollView {
                VStack(spacing: 0) {
                    brand
                    Text(quote)
                        .font(.aiku(22))
                        .foregroundStyle(ThemeStore.shared.isDark ? AikuColor.text3 : Color(hex: 0x4B4458))
                        .frame(maxWidth: .infinity, alignment: .leading)
                        .padding(.horizontal, 24)
                        .padding(.top, 6)
                    Spacer(minLength: 24)
                    VStack(alignment: .leading, spacing: 18) {
                        card
                    }
                    .padding(.horizontal, 20)
                    .padding(.vertical, 24)
                    .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 12))
                    .overlay(RoundedRectangle(cornerRadius: 12).strokeBorder(AikuColor.border))
                    .shadow(color: AikuColor.shadow, radius: 16, y: 12)
                    .padding(.horizontal, 16)
                    Spacer(minLength: 24)
                    footer
                }
                .frame(minHeight: fullHeight)
        }
        .scrollDismissesKeyboard(.interactively)
        .onGeometryChange(for: CGFloat.self) { proxy in
            proxy.size.height
        } action: { height in
            fullHeight = max(fullHeight, height)
        }
        .background(AuthBackdrop())
        .aikuSheet(isPresented: $isLanguagePresented) {
            LanguageSheet { isLanguagePresented = false }
        }
    }

    private var brand: some View {
        HStack(spacing: 12) {
            Image("AikuLogo")
                .resizable()
                .interpolation(.none)
                .scaledToFit()
                .frame(width: 40, height: 40)
                .accessibilityHidden(true)
            Text("aiku")
                .font(.aikuWordmark(34))
                .foregroundStyle(AikuColor.text)
            Spacer()
            Button {
                isLanguagePresented = true
            } label: {
                HStack(spacing: 4) {
                    AppIcon.globe.image.font(.system(size: 14))
                    Text("English").font(.aiku(13))
                }
                .foregroundStyle(AikuColor.text3)
                .padding(.horizontal, 10)
                .frame(height: 44)
                .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .accessibilityLabel("Language: English")
            .accessibilityIdentifier("login.language")
        }
        .padding(.horizontal, 24)
        .padding(.top, 12)
    }

    private var footer: some View {
        VStack(spacing: 8) {
            HStack(spacing: 8) {
                Image("AikuLogo")
                    .resizable()
                    .interpolation(.none)
                    .scaledToFit()
                    .frame(width: 16, height: 16)
                    .accessibilityHidden(true)
                Text("aiku is open source software (AGPL-3.0)")
            }
            HStack(spacing: 14) {
                footerLink("GitHub", url: AuthLinks.github)
                footerLink("Documentation", url: AuthLinks.documentation)
                footerLink("hello@aiku.io", url: AuthLinks.email)
            }
        }
        .font(.aiku(12))
        .foregroundStyle(AikuColor.text3)
        .padding(.horizontal, 24)
        .padding(.top, 12)
        .padding(.bottom, 12)
    }

    @ViewBuilder
    private func footerLink(_ title: String, url: URL?) -> some View {
        if let url {
            Link(title, destination: url)
                .underline()
                .foregroundStyle(AikuColor.text3)
        }
    }
}

struct AuthPrimaryButtonStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.aiku(15, .semibold))
            .foregroundStyle(AikuColor.onPrimary)
            .frame(maxWidth: .infinity, minHeight: 48)
            .background(AikuColor.primary, in: RoundedRectangle(cornerRadius: 6))
            .overlay(RoundedRectangle(cornerRadius: 6).strokeBorder(AikuColor.primaryBd))
            .opacity(configuration.isPressed ? 0.85 : 1)
            .contentShape(RoundedRectangle(cornerRadius: 6))
    }
}

struct AuthField<Field: View, Accessory: View>: View {
    let label: String
    var error: String?
    var isFocused = false
    var isLocked = false
    @ViewBuilder var field: Field
    @ViewBuilder var accessory: Accessory

    init(
        _ label: String,
        error: String? = nil,
        isFocused: Bool = false,
        isLocked: Bool = false,
        @ViewBuilder field: () -> Field,
        @ViewBuilder accessory: () -> Accessory
    ) {
        self.label = label
        self.error = error
        self.isFocused = isFocused
        self.isLocked = isLocked
        self.field = field()
        self.accessory = accessory()
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 6) {
            FieldLabel(label, isDisabled: isLocked)
            FieldBox(isFocused: isFocused, hasError: error != nil, isDisabled: isLocked, height: 48, radius: 6) {
                field
                    .font(.aiku(16))
                    .foregroundStyle(isLocked ? AikuColor.text4 : AikuColor.text)
                    .padding(.horizontal, 12)
                    .frame(maxWidth: .infinity, minHeight: 48)
                accessory
            }
            .shadow(color: AikuColor.shadow, radius: 1, y: 1)
            if let error {
                FieldError(error)
            }
        }
    }
}

extension AuthField where Accessory == EmptyView {
    init(
        _ label: String,
        error: String? = nil,
        isFocused: Bool = false,
        isLocked: Bool = false,
        @ViewBuilder field: () -> Field
    ) {
        self.init(label, error: error, isFocused: isFocused, isLocked: isLocked, field: field) { EmptyView() }
    }
}

struct AuthTextLink: View {
    let title: String
    let action: () -> Void

    init(_ title: String, action: @escaping () -> Void) {
        self.title = title
        self.action = action
    }

    var body: some View {
        Button(action: action) {
            Text(title)
                .font(.aiku(13))
                .italic()
                .foregroundStyle(AikuColor.text3)
                .frame(minHeight: 32)
                .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
    }
}

struct LanguageSheet: View {
    let onClose: () -> Void

    var body: some View {
        SheetScaffold(title: "Language", subtitle: "Changes the app language for you only.") {
            RadioCard("English", subtitle: "English", isSelected: true, action: onClose)
            RadioCard("Español", subtitle: "Spanish · coming later", isSelected: false, action: {})
                .disabled(true)
                .opacity(0.6)
            RadioCard("Slovenčina", subtitle: "Slovak · coming later", isSelected: false, action: {})
                .disabled(true)
                .opacity(0.6)
        } footer: {
            AikuButton("Done", kind: .tertiary, action: onClose)
        }
    }
}
