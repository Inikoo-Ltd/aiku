import Foundation

struct HardwareScanBuffer: Sendable {
    static let staleAfter: TimeInterval = 1

    private(set) var characters = ""
    private var lastKeyAt: Date?

    mutating func append(_ typed: String, at time: Date) {
        if let lastKeyAt, time.timeIntervalSince(lastKeyAt) > Self.staleAfter {
            characters = ""
        }
        characters += typed.filter { !$0.isNewline && !$0.unicodeScalars.allSatisfy(CharacterSet.controlCharacters.contains) }
        lastKeyAt = time
    }

    mutating func finish(at time: Date) -> String? {
        defer {
            characters = ""
            lastKeyAt = nil
        }
        guard let lastKeyAt, time.timeIntervalSince(lastKeyAt) <= Self.staleAfter else { return nil }
        let code = characters.trimmingCharacters(in: .whitespaces)
        return code.isEmpty ? nil : code
    }

    mutating func clear() {
        characters = ""
        lastKeyAt = nil
    }
}

extension ScanInputMode {
    var listensToScannerDevice: Bool { self != .camera }
}
