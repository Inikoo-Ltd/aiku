import Foundation
import Testing
@testable import Aiku

struct HyphenationTests {
    private let german = Locale(identifier: "de")

    @Test("A long German word gets break points and reads the same once they are hidden")
    func longGermanWordGetsBreakPoints() {
        let hyphenated = Hyphenation.softHyphens(in: "Warenausgang", locale: german)

        #expect(hyphenated.contains(Hyphenation.softHyphen))
        #expect(hyphenated.replacingOccurrences(of: Hyphenation.softHyphen, with: "") == "Warenausgang")
    }

    @Test("Short words stay whole, so a label wraps at its spaces")
    func shortWordsStayWhole() {
        #expect(Hyphenation.softHyphens(in: "Menü", locale: german) == "Menü")
        #expect(Hyphenation.softHyphens(in: "Meine Arbeit", locale: german) == "Meine Arbeit")
        #expect(Hyphenation.softHyphens(in: "Activitatea mea", locale: Locale(identifier: "ro")) == "Activitatea mea")
        #expect(Hyphenation.softHyphens(in: "Home", locale: Locale(identifier: "en_GB")) == "Home")
    }

    @Test("Every word of a label is split on its own")
    func everyWordIsSplitOnItsOwn() {
        let hyphenated = Hyphenation.softHyphens(in: "Salida de mercancías", locale: Locale(identifier: "es"))

        #expect(hyphenated.replacingOccurrences(of: Hyphenation.softHyphen, with: "") == "Salida de mercancías")
        #expect(hyphenated.split(separator: " ").count == 3)
    }
}
