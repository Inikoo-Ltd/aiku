import Foundation

struct ListDateRange: Hashable, Sendable {
    var from: Date?
    var to: Date?

    var isSet: Bool { from != nil || to != nil }

    func withFrom(_ date: Date?) -> ListDateRange {
        guard let date, let to, to < date else { return ListDateRange(from: date, to: to) }
        return ListDateRange(from: date, to: nil)
    }

    func withTo(_ date: Date?) -> ListDateRange {
        guard let date, let from, from > date else { return ListDateRange(from: from, to: date) }
        return ListDateRange(from: nil, to: date)
    }

    var queryItems: [URLQueryItem] {
        [from.map { URLQueryItem(name: "from", value: ListDates.apiDay($0)) }, to.map { URLQueryItem(name: "to", value: ListDates.apiDay($0)) }].compactMap { $0 }
    }
}

enum ListDates {
    static func apiDay(_ date: Date, in timeZone: TimeZone = .current) -> String {
        let parts = Calendar.gregorian(in: timeZone).dateComponents([.year, .month, .day], from: date)
        return String(format: "%04d-%02d-%02d", parts.year ?? 0, parts.month ?? 0, parts.day ?? 0)
    }

    static func day(_ text: String, in timeZone: TimeZone = .current) -> Date? {
        let parts = text.split(separator: "-").compactMap { Int($0) }
        guard parts.count == 3 else { return nil }
        return Calendar.gregorian(in: timeZone).date(from: DateComponents(year: parts[0], month: parts[1], day: parts[2]))
    }

    static func instant(_ text: String) -> Date? {
        guard text.count > 10 else { return nil }
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        if let date = formatter.date(from: text) { return date }
        formatter.formatOptions = [.withInternetDateTime]
        return formatter.date(from: text)
    }

    static func recordDate(_ value: String?, in timeZone: TimeZone = .current) -> String? {
        guard let raw = value?.trimmingCharacters(in: .whitespacesAndNewlines), !raw.isEmpty else { return nil }
        guard let date = instant(raw) ?? day(String(raw.prefix(10)), in: timeZone) else { return nil }
        return text(date, in: timeZone)
    }

    static func text(_ date: Date, in timeZone: TimeZone = .current) -> String {
        let formatter = DateFormatter()
        formatter.locale = L10n.dateLocale
        formatter.timeZone = timeZone
        formatter.dateFormat = "d MMM yyyy"
        return formatter.string(from: date)
    }

    static func rangeText(_ range: ListDateRange) -> String? {
        let from = range.from.map { text($0) }
        let to = range.to.map { text($0) }
        switch (from, to) {
        case let (start?, end?) where start == end: return start
        case let (start?, end?): return "\(start) – \(end)"
        case let (start?, nil): return L10n.formatInSavedLanguage("From %@", start)
        case let (nil, end?): return L10n.formatInSavedLanguage("Until %@", end)
        default: return nil
        }
    }
}

extension Calendar {
    static func gregorian(in timeZone: TimeZone) -> Calendar {
        var calendar = Calendar(identifier: .gregorian)
        calendar.timeZone = timeZone
        return calendar
    }
}
