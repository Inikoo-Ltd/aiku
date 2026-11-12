import Foundation
import Observation

struct CalendarMonth: Hashable, Comparable, Sendable {
    let year: Int
    let month: Int

    init(year: Int, month: Int) {
        let index = year * 12 + (month - 1)
        self.year = Int((Double(index) / 12).rounded(.down))
        self.month = index - self.year * 12 + 1
    }

    init?(_ text: String) {
        let parts = text.split(separator: "-")
        guard parts.count >= 2, let year = Int(parts[0]), let month = Int(parts[1]), (1...12).contains(month) else { return nil }
        self.init(year: year, month: month)
    }

    static func < (lhs: CalendarMonth, rhs: CalendarMonth) -> Bool {
        (lhs.year, lhs.month) < (rhs.year, rhs.month)
    }

    var param: String { String(format: "%04d-%02d", year, month) }

    var firstDay: String { "\(param)-01" }

    var lastDay: String { String(format: "%@-%02d", param, numberOfDays) }

    var numberOfDays: Int {
        guard let first = ClockingMapper.parseDay(firstDay) else { return 30 }
        return CalendarGrid.calendar.range(of: .day, in: .month, for: first)?.count ?? 30
    }

    var title: String {
        guard let first = ClockingMapper.parseDay(firstDay) else { return param }
        return CalendarText.format("MMMM yyyy", first)
    }

    func adding(_ months: Int) -> CalendarMonth {
        CalendarMonth(year: year, month: month + months)
    }

    func contains(_ date: String) -> Bool {
        date.hasPrefix("\(param)-")
    }
}

struct CalendarGridDay: Identifiable, Hashable, Sendable {
    let date: String
    let dayOfMonth: Int
    let isInMonth: Bool
    let isWeekend: Bool

    var id: String { date }
}

enum CalendarGrid {
    static let weekdayLabels = ["Mo", "Tu", "We", "Th", "Fr", "Sa", "Su"]

    static var calendar: Calendar {
        var calendar = Calendar(identifier: .gregorian)
        calendar.timeZone = TimeZone(identifier: "UTC")!
        return calendar
    }

    static func mondayIndex(of date: Date) -> Int {
        (calendar.component(.weekday, from: date) + 5) % 7
    }

    static func isWeekend(_ date: String) -> Bool {
        guard let parsed = ClockingMapper.parseDay(date) else { return false }
        return mondayIndex(of: parsed) >= 5
    }

    static func days(for month: CalendarMonth) -> [CalendarGridDay] {
        guard let first = ClockingMapper.parseDay(month.firstDay) else { return [] }
        let leading = mondayIndex(of: first)
        let total = Int((Double(leading + month.numberOfDays) / 7).rounded(.up)) * 7
        let utc = TimeZone(identifier: "UTC")!
        return (0..<total).map { offset in
            let day = first.addingTimeInterval(Double(offset - leading) * 86_400)
            let date = ClockingMapper.dayString(day, in: utc)
            return CalendarGridDay(
                date: date,
                dayOfMonth: calendar.component(.day, from: day),
                isInMonth: month.contains(date),
                isWeekend: mondayIndex(of: day) >= 5
            )
        }
    }
}

enum CalendarMarker: Hashable, Sendable {
    case worked
    case leave(colorHex: String?, isPending: Bool)
    case holiday

    var tone: Tone {
        switch self {
        case .worked: .success
        case .leave(_, let isPending): isPending ? .warn : .info
        case .holiday: .danger
        }
    }
}

enum CalendarDayStatus: Equatable, Sendable {
    case holiday
    case leave
    case leavePending
    case worked
    case weekend
    case regular
}

struct CalendarDaySummary: Equatable, Sendable {
    let date: String
    let isToday: Bool
    let isFuture: Bool
    let isWeekend: Bool
    let holiday: String?
    let leaves: [CalendarResponse.Leave]
    let timesheet: CalendarTimesheet?

    var isHoliday: Bool { holiday != nil }

    var hasWorked: Bool { timesheet != nil }

    var isStillWorking: Bool { (timesheet?.numberOpenTimeTrackers ?? 0) > 0 }

    var status: CalendarDayStatus {
        if isHoliday { return .holiday }
        if leaves.contains(where: { !CalendarText.isPending($0) }) { return .leave }
        if !leaves.isEmpty { return .leavePending }
        if hasWorked { return .worked }
        return isWeekend ? .weekend : .regular
    }

    var markers: [CalendarMarker] {
        var result: [CalendarMarker] = []
        if hasWorked { result.append(.worked) }
        result += leaves.map { .leave(colorHex: $0.typeColorHex, isPending: CalendarText.isPending($0)) }
        if isHoliday { result.append(.holiday) }
        return Array(result.prefix(3))
    }

    var title: String {
        guard let parsed = ClockingMapper.parseDay(date) else { return date }
        return CalendarText.format("EEEE d MMMM yyyy", parsed)
    }

    var workedLabel: String? {
        guard let timesheet else { return nil }
        let start = timesheet.startTimeLocal ?? "—"
        if isStillWorking {
            return "Clocked in at \(start) · still working"
        }
        return "Worked \(ClockingFormat.duration(timesheet.workingSeconds ?? 0)) · \(start) – \(timesheet.endTimeLocal ?? "—")"
    }
}

struct CalendarLegendLeave: Hashable, Sendable {
    let label: String
    let colorHex: String?
}

struct CalendarHolidaySummary: Hashable, Sendable {
    let dates: String
    let label: String
}

enum CalendarText {
    static func format(_ pattern: String, _ date: Date) -> String {
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_GB")
        formatter.calendar = CalendarGrid.calendar
        formatter.timeZone = TimeZone(identifier: "UTC")!
        formatter.dateFormat = pattern
        return formatter.string(from: date)
    }

    static func isPending(_ leave: CalendarResponse.Leave) -> Bool {
        leave.status == LeaveStatus.pending.rawValue
    }

    static func isHalfDay(_ leave: CalendarResponse.Leave) -> Bool {
        leave.isHalfDay == true || (leave.leaveTypeValue ?? 1) < 1
    }

    static func leaveDetail(_ leave: CalendarResponse.Leave) -> String {
        let length = isHalfDay(leave) ? "Half day" : LeaveCalculator.daysLabel(leave.durationDays ?? 1)
        return "\(length) · \(LeaveCalculator.rangeLabel(start: leave.startDate, end: leave.endDate))"
    }

    static func rangeLabel(from: String, to: String) -> String {
        guard let first = ClockingMapper.parseDay(from) else { return from }
        guard to != from, let last = ClockingMapper.parseDay(to) else { return format("EEE d MMM", first) }
        let sameMonth = from.prefix(7) == to.prefix(7)
        return "\(format(sameMonth ? "d" : "d MMM", first)) – \(format("d MMM", last))"
    }

    static func shortDate(_ text: String?) -> String? {
        guard let text, let parsed = ClockingMapper.parseDay(String(text.prefix(10))) else { return nil }
        return format("d MMM yyyy", parsed)
    }

    static func hexValue(_ text: String?) -> UInt32? {
        guard var text = text?.trimmingCharacters(in: .whitespaces), !text.isEmpty else { return nil }
        if text.hasPrefix("#") { text.removeFirst() }
        guard text.count == 6 else { return nil }
        return UInt32(text, radix: 16)
    }
}

struct CalendarService: Sendable {
    var calendar: @Sendable (String) async throws -> CalendarResponse
    var timesheets: @Sendable (String, String) async throws -> [CalendarTimesheet]

    static func live(_ client: APIClient) -> CalendarService {
        CalendarService(
            calendar: { try await client.clockingCalendar(month: $0) },
            timesheets: { try await client.calendarTimesheets(from: $0, to: $1) }
        )
    }
}

@MainActor
@Observable
final class CalendarStore {
    static let shared = CalendarStore(service: .live(.shared))

    private(set) var month: CalendarMonth
    private(set) var selectedDate: String?
    private(set) var holidays: [CalendarResponse.Holiday] = []
    private(set) var holidayRanges: [CalendarResponse.HolidayRange] = []
    private(set) var holidayYear: CalendarResponse.HolidayYear?
    private(set) var leaves: [CalendarResponse.Leave] = []
    private(set) var timesheets: [String: CalendarTimesheet] = [:]
    private(set) var loadedMonth: CalendarMonth?
    private(set) var isLoading = false
    private(set) var hasLoaded = false
    private(set) var loadError: String?

    @ObservationIgnored private let service: CalendarService
    @ObservationIgnored private let today: @Sendable () -> String
    @ObservationIgnored private var loadGeneration = 0

    init(service: CalendarService, today: @escaping @Sendable () -> String = { ClockingMapper.dayString(.now, in: .current) }) {
        self.service = service
        self.today = today
        let todayDate = today()
        month = CalendarMonth(todayDate) ?? CalendarMonth(year: 2026, month: 1)
        selectedDate = todayDate
    }

    var todayDate: String { today() }

    var currentMonth: CalendarMonth { CalendarMonth(todayDate) ?? month }

    var isShowingCurrentMonth: Bool { month == currentMonth }

    var grid: [CalendarGridDay] { CalendarGrid.days(for: month) }

    var selectedSummary: CalendarDaySummary? {
        guard let selectedDate, month.contains(selectedDate) else { return nil }
        return summary(for: selectedDate)
    }

    var monthLeaves: [CalendarResponse.Leave] {
        leaves.filter { ($0.startDate ?? "") <= month.lastDay && ($0.endDate ?? "") >= month.firstDay }
            .sorted { ($0.startDate ?? "") < ($1.startDate ?? "") }
    }

    var monthHolidays: [CalendarHolidaySummary] {
        let ranges = holidayRanges.filter { $0.from <= month.lastDay && $0.to >= month.firstDay }
        if !ranges.isEmpty {
            return ranges.sorted { $0.from < $1.from }.map { CalendarHolidaySummary(dates: CalendarText.rangeLabel(from: $0.from, to: $0.to), label: $0.label ?? "Holiday") }
        }
        return holidays.filter { month.contains($0.date) }.sorted { $0.date < $1.date }.map {
            CalendarHolidaySummary(dates: CalendarText.rangeLabel(from: $0.date, to: $0.date), label: $0.label ?? "Holiday")
        }
    }

    var legendLeaves: [CalendarLegendLeave] {
        var seen = Set<String>()
        return monthLeaves.compactMap { leave in
            let label = leave.typeLabel ?? leave.type ?? "Leave"
            guard seen.insert(label).inserted else { return nil }
            return CalendarLegendLeave(label: label, colorHex: leave.typeColorHex)
        }
    }

    var holidayYearLabel: String? {
        guard let holidayYear else { return nil }
        let range = [CalendarText.shortDate(holidayYear.startDate), CalendarText.shortDate(holidayYear.endDate)].compactMap { $0 }.joined(separator: " – ")
        let label = holidayYear.label ?? "Holiday year"
        return range.isEmpty ? label : "\(label) · \(range)"
    }

    func summary(for date: String) -> CalendarDaySummary {
        let isWeekend = CalendarGrid.isWeekend(date)
        let holidayLabels = holidays.filter { $0.date == date }.map { $0.label ?? "" }
        let holiday: String? = holidayLabels.isEmpty ? nil : holidayLabels.first { !$0.isEmpty } ?? "Public holiday"
        let isWorkingDay = !isWeekend && holiday == nil
        let dayLeaves = isWorkingDay ? leaves.filter { ($0.startDate ?? "") <= date && ($0.endDate ?? $0.startDate ?? "") >= date } : []
        return CalendarDaySummary(
            date: date,
            isToday: date == todayDate,
            isFuture: date > todayDate,
            isWeekend: isWeekend,
            holiday: holiday,
            leaves: dayLeaves,
            timesheet: timesheets[date]
        )
    }

    func select(_ date: String) {
        guard month.contains(date) else { return }
        selectedDate = date
    }

    func showPreviousMonth() async {
        await show(month.adding(-1))
    }

    func showNextMonth() async {
        await show(month.adding(1))
    }

    func showCurrentMonth() async {
        await show(currentMonth)
    }

    func show(_ newMonth: CalendarMonth) async {
        guard newMonth != month else { return }
        month = newMonth
        selectedDate = newMonth.contains(todayDate) ? todayDate : nil
        await load()
    }

    static func timesheetRange(for month: CalendarMonth, today: String) -> (from: String, to: String)? {
        guard month.firstDay <= today else { return nil }
        return (month.firstDay, min(month.lastDay, today))
    }

    func load() async {
        loadGeneration += 1
        let generation = loadGeneration
        let requested = month
        let service = service
        isLoading = true
        do {
            async let calendarResponse = service.calendar(requested.param)
            var worked: [CalendarTimesheet] = []
            if let range = Self.timesheetRange(for: requested, today: todayDate) {
                worked = try await service.timesheets(range.from, range.to)
            }
            let response = try await calendarResponse
            guard generation == loadGeneration else { return }
            holidays = response.holidays
            holidayRanges = response.holidayRanges
            holidayYear = response.holidayYear
            leaves = response.leaves
            timesheets = Dictionary(worked.map { ($0.date, $0) }, uniquingKeysWith: { first, _ in first })
            loadedMonth = requested
            loadError = nil
            hasLoaded = true
        } catch where error.isCancellation {
        } catch {
            if generation == loadGeneration {
                loadError = error.localizedDescription
            }
        }
        if generation == loadGeneration {
            isLoading = false
        }
    }
}
