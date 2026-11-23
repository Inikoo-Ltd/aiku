import SwiftUI

extension Text {
    static func emphasising(_ part: String, in text: String) -> Text {
        guard !part.isEmpty, let range = text.range(of: part) else { return Text(text) }
        return Text(text[..<range.lowerBound]) + Text(part).bold() + Text(text[range.upperBound...])
    }
}
