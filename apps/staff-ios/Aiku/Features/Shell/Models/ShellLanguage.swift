import Foundation

struct ShellLanguage: Identifiable, Hashable, Sendable {
    let id: String
    let nativeName: String
    let englishName: String
}

enum ShellLanguages {
    static let storageKey = "app.language"
    static let defaultID = "en"
    static let translatedIDs: Set<String> = ["en"]

    static let all: [ShellLanguage] = [
        ShellLanguage(id: "en", nativeName: "English", englishName: "English"),
        ShellLanguage(id: "es", nativeName: "Español", englishName: "Spanish"),
        ShellLanguage(id: "fr", nativeName: "Français", englishName: "French"),
        ShellLanguage(id: "de", nativeName: "Deutsch", englishName: "German"),
        ShellLanguage(id: "nl", nativeName: "Nederlands", englishName: "Dutch"),
        ShellLanguage(id: "pt", nativeName: "Português", englishName: "Portuguese"),
        ShellLanguage(id: "pl", nativeName: "Polski", englishName: "Polish"),
        ShellLanguage(id: "cs", nativeName: "Čeština", englishName: "Czech"),
        ShellLanguage(id: "sk", nativeName: "Slovenčina", englishName: "Slovak"),
        ShellLanguage(id: "hu", nativeName: "Magyar", englishName: "Hungarian"),
        ShellLanguage(id: "hr", nativeName: "Hrvatski", englishName: "Croatian"),
        ShellLanguage(id: "sv", nativeName: "Svenska", englishName: "Swedish"),
        ShellLanguage(id: "id", nativeName: "Bahasa Indonesia", englishName: "Indonesian"),
        ShellLanguage(id: "ja", nativeName: "日本語", englishName: "Japanese"),
        ShellLanguage(id: "zh-Hans", nativeName: "简体中文", englishName: "Chinese (Simplified)")
    ]

    static func language(id: String) -> ShellLanguage {
        all.first { $0.id == id } ?? all[0]
    }

    static func isTranslated(_ id: String) -> Bool {
        translatedIDs.contains(id)
    }
}
