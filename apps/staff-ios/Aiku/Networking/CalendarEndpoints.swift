import Foundation

struct CalendarResponse: Decodable, Sendable {
    struct Holiday: Decodable, Equatable, Sendable {
        let date: String
        let label: String?
    }

    struct HolidayRange: Decodable, Equatable, Sendable {
        let from: String
        let to: String
        let label: String?
    }

    struct HolidayYear: Decodable, Equatable, Sendable {
        let id: Int
        let label: String?
        let startDate: String?
        let endDate: String?
    }

    struct Leave: Decodable, Equatable, Identifiable, Sendable {
        let id: Int
        let type: String?
        let typeLabel: String?
        let typeColorHex: String?
        let startDate: String?
        let endDate: String?
        let durationDays: Double?
        let isHalfDay: Bool?
        let leaveTypeValue: Double?
        let session: String?
        let status: String?
        let statusLabel: String?
    }

    let month: String
    let holidays: [Holiday]
    let holidayRanges: [HolidayRange]
    let holidayYear: HolidayYear?
    let leaves: [Leave]
}

struct CalendarTimesheet: Decodable, Equatable, Sendable {
    let date: String
    let startTimeLocal: String?
    let endTimeLocal: String?
    let workingSeconds: Int?
    let numberOpenTimeTrackers: Int?
    let hasPendingAdjustment: Bool?
}

struct CalendarTimesheetsResponse: Decodable, Sendable {
    let timesheets: [CalendarTimesheet]
}

extension Endpoint {
    static func clockingCalendar(month: String) -> Endpoint {
        Endpoint(path: "mobile/clocking/calendar", queryItems: [URLQueryItem(name: "month", value: month)])
    }

    static func calendarTimesheets(from: String, to: String) -> Endpoint {
        Endpoint(path: "mobile/clocking/timesheets", queryItems: [URLQueryItem(name: "from", value: from), URLQueryItem(name: "to", value: to)])
    }
}

extension APIClient {
    func clockingCalendar(month: String) async throws -> CalendarResponse {
        try await send(.clockingCalendar(month: month), as: DataEnvelope<CalendarResponse>.self).value
    }

    func calendarTimesheets(from: String, to: String) async throws -> [CalendarTimesheet] {
        try await send(.calendarTimesheets(from: from, to: to), as: DataEnvelope<CalendarTimesheetsResponse>.self).value.timesheets
    }
}
