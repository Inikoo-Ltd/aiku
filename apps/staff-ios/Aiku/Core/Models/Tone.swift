import Foundation

enum Tone: String, Hashable, Sendable, CaseIterable {
    case neutral
    case info
    case success
    case warn
    case danger
    case orange
    case lime
}

struct Chip: Hashable, Sendable {
    let text: String
    let tone: Tone

    init(_ text: String, _ tone: Tone = .neutral) {
        self.text = text
        self.tone = tone
    }
}
