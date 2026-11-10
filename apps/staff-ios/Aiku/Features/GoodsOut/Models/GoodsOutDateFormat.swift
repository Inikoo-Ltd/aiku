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
        date.formatted(
            Date.VerbatimFormatStyle(
                format: "\(day: .defaultDigits) \(month: .abbreviated) \(hour: .twoDigits(clock: .twentyFourHour, hourCycle: .zeroBased)):\(minute: .twoDigits)",
                locale: Locale(identifier: "en_GB"),
                timeZone: timeZone,
                calendar: Calendar(identifier: .gregorian)
            )
        )
    }

    static func time(_ date: Date) -> String {
        date.formatted(
            Date.FormatStyle(timeZone: timeZone)
                .locale(Locale(identifier: "en_GB"))
                .hour(.twoDigits(amPM: .omitted))
                .minute(.twoDigits)
                .second(.twoDigits)
        )
    }

    static func shortTime(_ date: Date) -> String {
        date.formatted(
            Date.VerbatimFormatStyle(
                format: "\(hour: .twoDigits(clock: .twentyFourHour, hourCycle: .zeroBased)):\(minute: .twoDigits)",
                locale: Locale(identifier: "en_GB"),
                timeZone: timeZone,
                calendar: Calendar(identifier: .gregorian)
            )
        )
    }
}
