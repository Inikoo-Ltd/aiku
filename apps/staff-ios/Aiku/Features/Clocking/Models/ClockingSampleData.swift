import Foundation

enum ClockingSampleData {
    static let todayDate = "2026-11-09"
    static let thisWeekLabel = "This week"
    static let thisWeekRange = "09 – 15 Nov 2026"
    static let coordinates = "51.5072, −0.1276"
    static let standardHours = "08:00 - 17:00"
    static let pin: String? = nil
    static let employeeCode = "[EMPLOYEE-CODE]"
    static let rejection = ClockingRejection(message: "[Server message, e.g. not a clocking machine]", scanned: "[LOC-A-03-02]")

    static let shifts = [
        ClockingShift(name: "[Early shift]", hours: "06:00 - 14:00"),
        ClockingShift(name: "[Late shift]", hours: "14:00 - 22:00"),
    ]

    static var today: ClockingDay {
        ClockingDay(
            date: todayDate, weekday: "Mon", dayOfMonth: "09", monthYear: "Nov 2026",
            events: [
                ClockingEvent(time: ClockingFormat.seconds(8, 2, 14), kind: .clockIn),
                ClockingEvent(time: ClockingFormat.seconds(12, 30, 5), kind: .clockOut, viaMobileApp: false, place: "Goods-in door"),
                ClockingEvent(time: ClockingFormat.seconds(13, 1, 40), kind: .clockIn),
            ],
            isToday: true
        )
    }

    static var pastWeeks: [ClockingWeek] {
        [
            ClockingWeek(label: "Last week", range: "02 – 08 Nov 2026", days: [
                day("2026-11-06", "Fri", "06", [(7, 58), (12, 30), (13, 2), (16, 31)]),
                day("2026-11-05", "Thu", "05", [(8, 5), (12, 30), (12, 58)]),
                day("2026-11-04", "Wed", "04", [(8, 0), (12, 30), (13, 0), (16, 30)]),
                day("2026-11-03", "Tue", "03", [(8, 10), (15, 5)]),
                day("2026-11-02", "Mon", "02", [(7, 55), (12, 15)], note: "half day"),
            ]),
            ClockingWeek(label: "2 weeks ago", range: "26 Oct – 01 Nov 2026", days: [
                day("2026-10-30", "Fri", "30", [(8, 0), (12, 30), (13, 0), (16, 35)], month: "Oct 2026"),
                day("2026-10-29", "Thu", "29", [(8, 2), (12, 30), (13, 0), (16, 30)], month: "Oct 2026"),
                day("2026-10-28", "Wed", "28", [(7, 59), (12, 30), (13, 0), (16, 28)], month: "Oct 2026"),
                day("2026-10-27", "Tue", "27", [(8, 1), (14, 0)], note: "manual fix", month: "Oct 2026"),
                day("2026-10-26", "Mon", "26", [(8, 0), (12, 20), (13, 0), (16, 31)], month: "Oct 2026"),
            ]),
        ]
    }

    private static func day(
        _ date: String,
        _ weekday: String,
        _ dayOfMonth: String,
        _ times: [(Int, Int)],
        note: String? = nil,
        month: String = "Nov 2026"
    ) -> ClockingDay {
        let events = times.enumerated().map { index, time in
            let isIn = index % 2 == 0
            return ClockingEvent(
                time: ClockingFormat.seconds(time.0, time.1),
                kind: isIn ? .clockIn : .clockOut,
                viaMobileApp: isIn,
                place: isIn ? "Main Warehouse" : "Goods-in door"
            )
        }
        return ClockingDay(date: date, weekday: weekday, dayOfMonth: dayOfMonth, monthYear: month, events: events, note: note)
    }
}
