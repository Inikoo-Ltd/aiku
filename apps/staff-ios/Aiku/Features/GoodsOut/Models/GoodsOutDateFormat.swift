import Foundation

enum GoodsOutDateFormat {
    static let timeZone = TimeZone(identifier: "Europe/London") ?? .gmt

    static func date(day: Int, month: Int = 11, year: Int = 2026, hour: Int, minute: Int, second: Int = 0) -> Date {
        var calendar = Calendar(identifier: .gregorian)
        calendar.timeZone = timeZone
        let components = DateComponents(year: year, month: month, day: day, hour: hour, minute: minute, second: second)
        return calendar.date(from: components) ?? Date(timeIntervalSince1970: 0)
    }

    static func stamp(_ date: Date) -> String {
        let formatter = DateFormatter()
        formatter.locale = L10n.formatLocale
        formatter.timeZone = timeZone
        formatter.calendar = Calendar(identifier: .gregorian)
        formatter.dateFormat = L10n.datePattern("d MMM HH:mm")
        return formatter.string(from: date)
    }

    static func time(_ date: Date) -> String {
        date.formatted(
            Date.FormatStyle(timeZone: timeZone)
                .locale(L10n.formatLocale)
                .hour(.twoDigits(amPM: .omitted))
                .minute(.twoDigits)
                .second(.twoDigits)
        )
    }

    static func shortTime(_ date: Date) -> String {
        date.formatted(
            Date.VerbatimFormatStyle(
                format: "\(hour: .twoDigits(clock: .twentyFourHour, hourCycle: .zeroBased)):\(minute: .twoDigits)",
                locale: L10n.formatLocale,
                timeZone: timeZone,
                calendar: Calendar(identifier: .gregorian)
            )
        )
    }
}
