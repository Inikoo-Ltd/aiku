import Foundation
import Observation

struct AppLanguage: Identifiable, Hashable, Sendable {
    let id: String
    let nativeName: String
    let englishName: String

    var locale: Locale { Locale(identifier: id) }
}

enum AppLanguages {
    static let defaultID = "en"

    static let all: [AppLanguage] = [
        AppLanguage(id: "en", nativeName: "English", englishName: "English"),
        AppLanguage(id: "bg", nativeName: "Български", englishName: "Bulgarian"),
        AppLanguage(id: "cs", nativeName: "Čeština", englishName: "Czech"),
        AppLanguage(id: "de", nativeName: "Deutsch", englishName: "German"),
        AppLanguage(id: "es", nativeName: "Español", englishName: "Spanish"),
        AppLanguage(id: "fr", nativeName: "Français", englishName: "French"),
        AppLanguage(id: "hi", nativeName: "हिन्दी", englishName: "Hindi"),
        AppLanguage(id: "hr", nativeName: "Hrvatski", englishName: "Croatian"),
        AppLanguage(id: "hu", nativeName: "Magyar", englishName: "Hungarian"),
        AppLanguage(id: "id", nativeName: "Bahasa Indonesia", englishName: "Indonesian"),
        AppLanguage(id: "it", nativeName: "Italiano", englishName: "Italian"),
        AppLanguage(id: "ja", nativeName: "日本語", englishName: "Japanese"),
        AppLanguage(id: "lv", nativeName: "Latviešu", englishName: "Latvian"),
        AppLanguage(id: "ne", nativeName: "नेपाली", englishName: "Nepali"),
        AppLanguage(id: "nl", nativeName: "Nederlands", englishName: "Dutch"),
        AppLanguage(id: "pl", nativeName: "Polski", englishName: "Polish"),
        AppLanguage(id: "pt", nativeName: "Português", englishName: "Portuguese"),
        AppLanguage(id: "ro", nativeName: "Română", englishName: "Romanian"),
        AppLanguage(id: "sk", nativeName: "Slovenčina", englishName: "Slovak"),
        AppLanguage(id: "sv", nativeName: "Svenska", englishName: "Swedish"),
        AppLanguage(id: "tr", nativeName: "Türkçe", englishName: "Turkish"),
        AppLanguage(id: "uk", nativeName: "Українська", englishName: "Ukrainian"),
        AppLanguage(id: "zh-Hans", nativeName: "简体中文", englishName: "Chinese (Simplified)")
    ]

    static func language(id: String) -> AppLanguage {
        all.first { $0.id == id } ?? all[0]
    }

    static func supportedID(_ code: String?) -> String? {
        guard let code, !code.isEmpty else { return nil }
        if all.contains(where: { $0.id == code }) { return code }
        let lowercased = code.lowercased()
        if lowercased.hasPrefix("zh") { return "zh-Hans" }
        let base = String(lowercased.prefix { $0 != "-" && $0 != "_" })
        return all.first { $0.id == base }?.id
    }
}

@MainActor
@Observable
final class LanguageStore {
    static let shared = LanguageStore()
    nonisolated static let storageKey = "app.language"
    nonisolated static let pendingKey = "app.language.pending"

    nonisolated static func savedLanguageID(in defaults: UserDefaults = .standard) -> String? {
        AppLanguages.supportedID(defaults.string(forKey: storageKey))
    }

    private(set) var languageID: String
    private(set) var pendingID: String?
    private var translations: Bundle?
    @ObservationIgnored private var accountLanguageID: String?
    @ObservationIgnored private var isSendingPending = false
    @ObservationIgnored private let defaults: UserDefaults
    @ObservationIgnored private let bundle: Bundle

    init(defaults: UserDefaults = .standard, bundle: Bundle = .main, preferredLanguages: [String] = Locale.preferredLanguages) {
        self.defaults = defaults
        self.bundle = bundle
        let stored = Self.savedLanguageID(in: defaults)
        let preferred = preferredLanguages.lazy.compactMap { AppLanguages.supportedID($0) }.first
        let id = stored ?? preferred ?? AppLanguages.defaultID
        languageID = id
        pendingID = AppLanguages.supportedID(defaults.string(forKey: Self.pendingKey))
        translations = Self.translations(for: id, in: bundle)
    }

    var current: AppLanguage { AppLanguages.language(id: languageID) }

    var locale: Locale { current.locale }

    func isTranslated(_ id: String) -> Bool {
        id == AppLanguages.defaultID || Self.translations(for: id, in: bundle) != nil
    }

    func select(_ id: String) {
        let supported = AppLanguages.supportedID(id) ?? AppLanguages.defaultID
        apply(supported)
        setPending(supported)
    }

    func applyAccountLanguage(_ code: String?) {
        guard let id = AppLanguages.supportedID(code) else { return }
        accountLanguageID = id
        guard pendingID == nil, id != languageID else { return }
        apply(id)
    }

    func sendPending(using update: (String) async throws -> String?) async {
        guard !isSendingPending else { return }
        isSendingPending = true
        defer { isSendingPending = false }
        while let pending = pendingID {
            do {
                let accountCode = try await update(pending)
                guard pendingID == pending else { continue }
                setPending(nil)
                applyAccountLanguage(accountCode)
            } catch is FormValidationError {
                guard pendingID == pending else { continue }
                setPending(nil)
                if let accountLanguageID {
                    apply(accountLanguageID)
                }
            } catch {
                return
            }
        }
    }

    func forgetAccount() {
        accountLanguageID = nil
        setPending(nil)
    }

    private func apply(_ id: String) {
        languageID = id
        translations = Self.translations(for: id, in: bundle)
        defaults.set(id, forKey: Self.storageKey)
    }

    private func setPending(_ id: String?) {
        pendingID = id
        if let id {
            defaults.set(id, forKey: Self.pendingKey)
        } else {
            defaults.removeObject(forKey: Self.pendingKey)
        }
    }

    func text(_ key: String) -> String {
        guard let translations, !key.isEmpty else { return key }
        return translations.localizedString(forKey: key, value: key, table: nil)
    }

    private static func translations(for id: String, in bundle: Bundle) -> Bundle? {
        guard id != AppLanguages.defaultID, let path = bundle.path(forResource: id, ofType: "lproj") else { return nil }
        return Bundle(path: path)
    }
}

enum L10n {
    @MainActor
    static func text(_ key: String) -> String {
        LanguageStore.shared.text(key)
    }
}
