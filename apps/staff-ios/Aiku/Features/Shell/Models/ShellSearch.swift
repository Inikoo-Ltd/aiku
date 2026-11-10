import Foundation

enum ShellSearchScope: String, Hashable, Sendable, CaseIterable, Identifiable {
    case all
    case skos
    case locations
    case deliveryNotes

    var id: String { rawValue }

    var title: String {
        switch self {
        case .all: "All"
        case .skos: "SKOs"
        case .locations: "Locations"
        case .deliveryNotes: "Delivery notes"
        }
    }
}

enum ShellSearchKind: String, Hashable, Sendable, CaseIterable {
    case sko
    case family
    case location
    case deliveryNote

    var sectionTitle: String {
        switch self {
        case .sko: "SKOs"
        case .family: "SKO families"
        case .location: "Locations"
        case .deliveryNote: "Delivery notes"
        }
    }

    func isIncluded(in scope: ShellSearchScope) -> Bool {
        switch scope {
        case .all: true
        case .skos: self == .sko || self == .family
        case .locations: self == .location
        case .deliveryNotes: self == .deliveryNote
        }
    }
}

struct ShellSearchRecord: Identifiable, Hashable, Sendable {
    var id: String { "\(kind.rawValue).\(code)" }
    let kind: ShellSearchKind
    let code: String
    let name: String
    var detail: String?
    var trailing: String?
    let link: ShellLink

    var title: String {
        kind == .family ? name : code
    }

    var subtitle: String {
        kind == .family ? (detail ?? code) : name
    }
}

struct ShellSearchSection: Identifiable, Hashable, Sendable {
    var id: ShellSearchKind { kind }
    let kind: ShellSearchKind
    let records: [ShellSearchRecord]

    var heading: String { "\(kind.sectionTitle) · \(records.count)" }
}

struct ShellHighlightSegment: Hashable, Sendable {
    let text: String
    let isMatch: Bool
}

enum ShellSearch {
    static let records: [ShellSearchRecord] = [
        ShellSearchRecord(kind: .sko, code: "SKO-00412", name: "Lavender essential oil 10ml", trailing: "1,240", link: .inventory(.sko(reference: "SKO-00412"))),
        ShellSearchRecord(kind: .sko, code: "SKO-00734", name: "Bath bomb – lavender 180g", trailing: "210", link: .inventory(.sko(reference: "SKO-00734"))),
        ShellSearchRecord(kind: .sko, code: "SKO-01190", name: "Soy candle – lavender", trailing: "96", link: .inventory(.sko(reference: "SKO-01190"))),
        ShellSearchRecord(kind: .sko, code: "SKO-00733", name: "Bath bomb – rose 180g", link: .inventory(.sko(reference: "SKO-00733"))),
        ShellSearchRecord(kind: .sko, code: "SKO-01187", name: "Soy wax candle – vanilla", link: .inventory(.sko(reference: "SKO-01187"))),
        ShellSearchRecord(kind: .sko, code: "SKO-01402", name: "[Product name]", trailing: "0", link: .inventory(.sko(reference: "SKO-01402"))),
        ShellSearchRecord(kind: .family, code: "LV", name: "Lavender range", detail: "LV · 14 SKOs", link: .inventory(.family(code: "LV"))),
        ShellSearchRecord(kind: .location, code: "A-03-02", name: "Area A · 6 SKOs", link: .locations(.location(code: "A-03-02"))),
        ShellSearchRecord(kind: .location, code: "A-12-03", name: "Area A · 4 SKOs", link: .locations(.location(code: "A-12-03"))),
        ShellSearchRecord(kind: .location, code: "B-01-04", name: "Area B · 2 SKOs", link: .locations(.location(code: "B-01-04"))),
        ShellSearchRecord(kind: .deliveryNote, code: "AR24811", name: "To pick · 14 items", link: .goodsOut(.deliveryNote(reference: "AR24811"))),
        ShellSearchRecord(kind: .deliveryNote, code: "AR24817", name: "Handling · 14 items", link: .goodsOut(.deliveryNote(reference: "AR24817"))),
        ShellSearchRecord(kind: .deliveryNote, code: "AR24820", name: "Assigned to you", link: .goodsOut(.deliveryNote(reference: "AR24820"))),
        ShellSearchRecord(kind: .deliveryNote, code: "AR24790", name: "Dispatched · 6 items", link: .goodsOut(.deliveryNote(reference: "AR24790")))
    ]

    static let recentSearches = ["AR24817", "A-12-03", "bath bomb"]
    static let emptySuggestions = ["rosemary", "SKO-0", "A-03"]

    static func normalised(_ query: String) -> String {
        query.trimmingCharacters(in: .whitespacesAndNewlines)
    }

    static func matches(_ record: ShellSearchRecord, query: String) -> Bool {
        let needle = normalised(query)
        guard !needle.isEmpty else { return false }
        return [record.code, record.name, record.detail ?? ""].contains {
            $0.range(of: needle, options: [.caseInsensitive, .diacriticInsensitive]) != nil
        }
    }

    static func sections(for query: String, scope: ShellSearchScope, in records: [ShellSearchRecord] = records) -> [ShellSearchSection] {
        ShellSearchKind.allCases.compactMap { kind in
            guard kind.isIncluded(in: scope) else { return nil }
            let found = records.filter { $0.kind == kind && matches($0, query: query) }
            return found.isEmpty ? nil : ShellSearchSection(kind: kind, records: found)
        }
    }

    static func remembering(_ query: String, in recents: [String], limit: Int = 5) -> [String] {
        let term = normalised(query)
        guard !term.isEmpty else { return recents }
        let others = recents.filter { $0.caseInsensitiveCompare(term) != .orderedSame }
        return Array(([term] + others).prefix(limit))
    }

    static func highlight(_ text: String, query: String) -> [ShellHighlightSegment] {
        let needle = normalised(query)
        guard !needle.isEmpty else { return [ShellHighlightSegment(text: text, isMatch: false)] }
        var segments: [ShellHighlightSegment] = []
        var cursor = text.startIndex
        while cursor < text.endIndex,
              let range = text.range(of: needle, options: [.caseInsensitive, .diacriticInsensitive], range: cursor..<text.endIndex) {
            if range.lowerBound > cursor {
                segments.append(ShellHighlightSegment(text: String(text[cursor..<range.lowerBound]), isMatch: false))
            }
            segments.append(ShellHighlightSegment(text: String(text[range]), isMatch: true))
            cursor = range.upperBound
        }
        if cursor < text.endIndex {
            segments.append(ShellHighlightSegment(text: String(text[cursor...]), isMatch: false))
        }
        return segments
    }
}
