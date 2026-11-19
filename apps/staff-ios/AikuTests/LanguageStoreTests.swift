import Foundation
import Testing
@testable import Aiku

@MainActor
struct LanguageStoreTests {
    private func isolatedDefaults() -> UserDefaults {
        let name = "aiku.tests.language.\(UUID().uuidString)"
        let defaults = UserDefaults(suiteName: name)!
        defaults.removePersistentDomain(forName: name)
        return defaults
    }

    private func bundle(with translations: [String: [String: String]]) throws -> Bundle {
        let root = FileManager.default.temporaryDirectory.appending(path: "aiku-language-\(UUID().uuidString)")
        for (language, strings) in translations {
            let folder = root.appending(path: "\(language).lproj")
            try FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true)
            let body = strings.map { "\"\($0.key)\" = \"\($0.value)\";" }.joined(separator: "\n")
            try body.write(to: folder.appending(path: "Localizable.strings"), atomically: true, encoding: .utf8)
        }
        return try #require(Bundle(path: root.path(percentEncoded: false)))
    }

    @Test func theAppOffersTheWebsLanguages() {
        #expect(AppLanguages.all.map(\.id) == ["en", "bg", "cs", "de", "es", "fr", "hi", "hr", "hu", "id", "it", "ja", "lv", "ne", "nl", "pl", "pt", "ro", "sk", "sv", "tr", "uk", "zh-Hans"])
    }

    @Test func deviceLanguagesMapToTheSupportedOnes() {
        #expect(AppLanguages.supportedID("de-DE") == "de")
        #expect(AppLanguages.supportedID("pt_BR") == "pt")
        #expect(AppLanguages.supportedID("zh-Hans-CN") == "zh-Hans")
        #expect(AppLanguages.supportedID("fi") == nil)
        #expect(AppLanguages.supportedID(nil) == nil)
    }

    @Test func aSavedChoiceWinsOverTheDeviceLanguage() throws {
        let defaults = isolatedDefaults()
        defaults.set("es", forKey: LanguageStore.storageKey)

        let store = LanguageStore(defaults: defaults, bundle: try bundle(with: [:]), preferredLanguages: ["de-DE"])

        #expect(store.languageID == "es")
    }

    @Test func withoutAChoiceTheDeviceLanguageIsUsedThenEnglish() throws {
        let german = LanguageStore(defaults: isolatedDefaults(), bundle: try bundle(with: [:]), preferredLanguages: ["fi-FI", "de-AT"])
        let finnish = LanguageStore(defaults: isolatedDefaults(), bundle: try bundle(with: [:]), preferredLanguages: ["fi-FI"])

        #expect(german.languageID == "de")
        #expect(finnish.languageID == "en")
    }

    @Test func textsAreTranslatedAndFallBackToEnglish() throws {
        let defaults = isolatedDefaults()
        let store = LanguageStore(defaults: defaults, bundle: try bundle(with: ["de": ["Save": "Speichern"]]), preferredLanguages: ["en"])
        #expect(store.text("Save") == "Save")

        store.select("de")

        #expect(store.text("Save") == "Speichern")
        #expect(store.text("Not in the catalogue") == "Not in the catalogue")
        #expect(store.text("") == "")
        #expect(defaults.string(forKey: LanguageStore.storageKey) == "de")
    }

    @Test func onlyLanguagesWithTranslationsCountAsTranslated() throws {
        let store = LanguageStore(defaults: isolatedDefaults(), bundle: try bundle(with: ["de": ["Save": "Speichern"]]), preferredLanguages: ["en"])

        #expect(store.isTranslated("en"))
        #expect(store.isTranslated("de"))
        #expect(!store.isTranslated("es"))
    }

    @Test func anUnknownChoiceFallsBackToEnglish() throws {
        let store = LanguageStore(defaults: isolatedDefaults(), bundle: try bundle(with: [:]), preferredLanguages: ["de"])

        store.select("xx")

        #expect(store.languageID == "en")
        #expect(store.current.nativeName == "English")
    }
}
