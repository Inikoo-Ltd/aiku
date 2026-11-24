import Foundation
import Testing
@testable import Aiku

struct DatePatternTests {
    private let utc = TimeZone(identifier: "UTC")!

    private func day(_ value: Int) -> Date {
        Calendar.gregorian(in: utc).date(from: DateComponents(year: 2026, month: 11, day: value))!
    }

    private func text(_ date: Date, pattern: String, locale: Locale) -> String {
        let formatter = DateFormatter()
        formatter.locale = locale
        formatter.timeZone = utc
        formatter.dateFormat = L10n.datePattern(pattern, locale: locale)
        return formatter.string(from: date)
    }

    @Test("English keeps the app's fixed date patterns")
    func englishKeepsFixedPatterns() {
        let english = Locale(identifier: "en_GB")

        #expect(L10n.datePattern("d MMM yyyy", locale: english) == "d MMM yyyy")
        #expect(text(day(21), pattern: "d MMM yyyy", locale: english) == "21 Nov 2026")
    }

    @Test("Japanese and Hungarian dates put the year first")
    func yearFirstLanguagesPutTheYearFirst() {
        #expect(text(day(21), pattern: "d MMM yyyy", locale: Locale(identifier: "ja")) == "2026年11月21日")
        #expect(text(day(21), pattern: "d MMM yyyy", locale: Locale(identifier: "hu")).hasPrefix("2026."))
    }

    @Test("Nepali shows Latin digits and Turkish keeps its own capitals")
    func languageLocalesKeepDigitsAndCase() {
        let nepali = AppLanguages.language(id: "ne").locale

        #expect(text(day(21), pattern: "d MMM yyyy", locale: nepali).contains("21"))
        #expect("Tarih".uppercased(with: AppLanguages.language(id: "tr").locale) == "TARİH")
    }

    @Test("A week range follows the language's own order")
    func weekRangeFollowsTheLanguage() {
        let range = L10n.dateRange(from: day(23), to: day(29), template: "dMMMy", timeZone: utc, locale: Locale(identifier: "ja"))

        #expect(range.hasPrefix("2026年11月23日"))
        #expect(range.contains("29日"))
    }
}
