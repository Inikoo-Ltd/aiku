import Foundation

enum Hyphenation {
    static let softHyphen = "\u{00AD}"
    static let shortestWordToSplit = 12
    static let languagesSplittingDoubledLetters: Set<String> = ["uk", "bg"]

    static func softHyphens(in text: String, locale: Locale) -> String {
        let cfLocale = locale as CFLocale
        guard CFStringIsHyphenationAvailableForLocale(cfLocale) else { return text }
        let source = text as NSString
        var breakPoints: [Int] = []
        source.enumerateSubstrings(in: NSRange(location: 0, length: source.length), options: .byWords) { _, wordRange, _, _ in
            guard wordRange.length >= shortestWordToSplit else { return }
            let word = CFRange(location: wordRange.location, length: wordRange.length)
            var index = wordRange.location + wordRange.length
            while index > wordRange.location {
                let point = CFStringGetHyphenationLocationBeforeIndex(source as CFString, index, word, 0, cfLocale, nil)
                guard point != kCFNotFound, point > wordRange.location else { break }
                breakPoints.append(point)
                index = point
            }
        }
        if let code = locale.language.languageCode?.identifier, languagesSplittingDoubledLetters.contains(code) {
            breakPoints = breakPoints.map { point in
                point + 1 < source.length && source.character(at: point) == source.character(at: point + 1) ? point + 1 : point
            }
        }
        let result = NSMutableString(string: text)
        for point in Set(breakPoints).sorted(by: >) {
            result.insert(softHyphen, at: point)
        }
        return result as String
    }
}
