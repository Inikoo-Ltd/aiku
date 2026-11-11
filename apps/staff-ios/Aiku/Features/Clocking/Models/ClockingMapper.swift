import Foundation

enum ClockingMapper {
    static func timeZone(_ identifier: String?) -> TimeZone {
        identifier.flatMap(TimeZone.init(identifier:)) ?? .current
    }

    static func date(fromISO text: String?) -> Date? {
        guard let text else { return nil }
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime]
        if let date = formatter.date(from: text) { return date }
        formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        return formatter.date(from: text)
    }

    static func secondsSinceMidnight(_ text: String?, in timeZone: TimeZone) -> Int? {
        guard let date = date(fromISO: text) else { return nil }
        var calendar = Calendar(identifier: .gregorian)
        calendar.timeZone = timeZone
        let parts = calendar.dateComponents([.hour, .minute, .second], from: date)
        return ClockingFormat.seconds(parts.hour ?? 0, parts.minute ?? 0, parts.second ?? 0)
    }

    static func dayString(_ date: Date, in timeZone: TimeZone) -> String {
        formatter("yyyy-MM-dd", timeZone).string(from: date)
    }

    static func parseDay(_ text: String) -> Date? {
        formatter("yyyy-MM-dd", TimeZone(identifier: "UTC")!).date(from: text)
    }

    static func events(from sessions: [ClockingSessionResponse], in timeZone: TimeZone) -> [ClockingEvent] {
        sessions.flatMap { session -> [ClockingEvent] in
            var result: [ClockingEvent] = []
            if let record = session.clockIn, let event = event(record, kind: .clockIn, in: timeZone) {
                result.append(event)
            }
            if let record = session.clockOut, let event = event(record, kind: .clockOut, in: timeZone) {
                result.append(event)
            }
            return result
        }
    }

    static func event(_ record: ClockingRecordResponse, kind: ClockingEventKind, in timeZone: TimeZone) -> ClockingEvent? {
        guard let time = secondsSinceMidnight(record.clockedAt, in: timeZone) else { return nil }
        return ClockingEvent(
            time: time,
            kind: kind,
            viaMobileApp: record.type == "self-check",
            place: record.workplace?.name ?? record.clockingMachine?.name ?? "Clocking machine",
            note: record.notes,
            clockingID: record.id,
            isLate: record.isLate ?? false
        )
    }

    static func day(date: String, sessions: [ClockingSessionResponse], in timeZone: TimeZone, isToday: Bool, timesheetID: Int? = nil) -> ClockingDay {
        let utc = TimeZone(identifier: "UTC")!
        let parsed = parseDay(date) ?? .now
        let events = events(from: sessions, in: timeZone)
        var day = ClockingDay(
            date: date,
            weekday: formatter("EEE", utc).string(from: parsed),
            dayOfMonth: formatter("dd", utc).string(from: parsed),
            monthYear: formatter("MMM yyyy", utc).string(from: parsed),
            events: events,
            isToday: isToday
        )
        day.shift = ""
        day.timesheetID = timesheetID
        day.workplace = sessions.compactMap { $0.clockIn?.workplace?.name ?? $0.clockIn?.clockingMachine?.name }.first ?? ""
        return day
    }

    static func weeks(from days: [ClockingDay], today: String) -> [ClockingWeek] {
        let grouped = Dictionary(grouping: days) { weekStart(of: $0.date) ?? $0.date }
        let thisWeekStart = weekStart(of: today) ?? today
        return grouped.keys.sorted(by: >).map { start in
            ClockingWeek(
                label: weekLabel(start: start, thisWeekStart: thisWeekStart),
                range: weekRange(start: start),
                days: (grouped[start] ?? []).sorted { $0.date > $1.date }
            )
        }
    }

    static func weekStart(of date: String) -> String? {
        guard let parsed = parseDay(date) else { return nil }
        var calendar = Calendar(identifier: .iso8601)
        calendar.timeZone = TimeZone(identifier: "UTC")!
        guard let start = calendar.dateInterval(of: .weekOfYear, for: parsed)?.start else { return nil }
        return formatter("yyyy-MM-dd", calendar.timeZone).string(from: start)
    }

    static func weekLabel(start: String, thisWeekStart: String) -> String {
        if start == thisWeekStart { return "This week" }
        guard let thisWeek = parseDay(thisWeekStart), let week = parseDay(start) else { return start }
        let weeksAgo = Int((thisWeek.timeIntervalSince(week) / (7 * 86_400)).rounded())
        return weeksAgo == 1 ? "Last week" : "\(weeksAgo) weeks ago"
    }

    static func weekRange(start: String) -> String {
        guard let first = parseDay(start) else { return start }
        let last = first.addingTimeInterval(6 * 86_400)
        let utc = TimeZone(identifier: "UTC")!
        let sameMonth = formatter("MM", utc).string(from: first) == formatter("MM", utc).string(from: last)
        let firstText = formatter(sameMonth ? "dd" : "dd MMM", utc).string(from: first)
        return "\(firstText) – \(formatter("dd MMM yyyy", utc).string(from: last))"
    }

    static func method(_ value: String) -> ClockingMethod? {
        switch value {
        case "qr_code": .qrCode
        case "pin": .pin
        case "barcode": .barcode
        case "camera_qr": .cameraQr
        default: nil
        }
    }

    static func monthBefore(_ date: String) -> String? {
        guard let parsed = parseDay(date) else { return nil }
        var calendar = Calendar(identifier: .gregorian)
        calendar.timeZone = TimeZone(identifier: "UTC")!
        guard let earlier = calendar.date(byAdding: .month, value: -1, to: parsed) else { return nil }
        return dayString(earlier, in: calendar.timeZone)
    }

    static func shortTime(_ text: String?) -> String? {
        guard let text, text.count >= 5 else { return nil }
        return String(text.prefix(5))
    }

    private static func formatter(_ format: String, _ timeZone: TimeZone) -> DateFormatter {
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_GB")
        formatter.calendar = Calendar(identifier: .gregorian)
        formatter.timeZone = timeZone
        formatter.dateFormat = format
        return formatter
    }
}
