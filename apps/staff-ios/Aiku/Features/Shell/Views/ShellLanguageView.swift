import SwiftUI

struct ShellLanguageView: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var selectedID: String?
    private let languages = LanguageStore.shared

    var body: some View {
        let chosen = AppLanguages.language(id: selectedID ?? languages.languageID)
        AppScreen(title: "Language", kicker: "Profile", spacing: 12) {
            Text("Changes the language of your Aiku account, on the web and in the app. Notifications follow it too. Product names, customer names and notes stay as they are.")
                .font(.aiku(13))
                .foregroundStyle(AikuColor.text3)
                .padding(.horizontal, 4)
            Card {
                ForEach(Array(AppLanguages.all.enumerated()), id: \.element.id) { index, language in
                    if index > 0 {
                        RowDivider()
                    }
                    row(language, isOn: language.id == chosen.id)
                }
            }
        } footer: {
            AikuButton(L10n.format("Use %@", chosen.nativeName), icon: .check) {
                save(chosen)
            }
            .accessibilityIdentifier("shell.language.save")
        }
    }

    private func row(_ language: AppLanguage, isOn: Bool) -> some View {
        Button {
            selectedID = language.id
        } label: {
            HStack(spacing: 12) {
                VStack(alignment: .leading, spacing: 2) {
                    Text(language.nativeName)
                        .font(.aiku(15, .semibold))
                        .foregroundStyle(AikuColor.text)
                    Text(language.name(in: languages.locale))
                        .font(.aiku(12))
                        .foregroundStyle(AikuColor.text3)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                ShellRadioDot(isOn: isOn)
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 6)
            .frame(minHeight: 56)
            .background(isOn ? AikuColor.soft : AikuColor.surface)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(isOn ? .isSelected : [])
    }

    private func save(_ language: AppLanguage) {
        languages.select(language.id)
        if languages.isTranslated(language.id) {
            toasts.show(L10n.format("Language set to %@", language.nativeName))
        } else {
            toasts.show("\(language.nativeName) saved · the app stays in English until translations arrive", tone: .info)
        }
        router.back()
    }
}
