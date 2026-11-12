import Foundation
import Testing
@testable import Aiku

private actor CalendarCalls {
    var months: [String] = []
    var ranges: [String] = []
    func month(_ value: String) { months.append(value) }
    func range(_ from: String, _ to: String) { ranges.append("\(from)…\(to)") }
}

private let sampleJSON = ##"""
{
    "data": {
        "month": "2026-11",
        "holidays": [
            { "date": "2026-11-20", "label": "Galungan" }
        ],
        "holiday_ranges": [
            { "from": "2026-11-20", "to": "2026-11-20", "label": "Galungan" }
        ],
        "holiday_year": { "id": 3, "label": "2026/27", "start_date": "2026-04-01", "end_date": "2027-03-31 00:00:00" },
        "leaves": [
            {
                "id": 100,
                "type": "hd-afternoon",
                "type_label": "Halfday Afternoon",
                "type_color_hex": "#4F46E5",
                "start_date": "2026-11-12",
                "end_date": "2026-11-12",
                "duration_days": 1,
                "is_half_day": false,
                "leave_type_value": 0.5,
                "session": "Full",
                "status": "pending",
                "status_label": "Pending"
            },
            {
                "id": 101,
                "type": "holiday-vacation",
                "type_label": "Holiday / Vacation",
                "type_color_hex": "#16A34A",
                "start_date": "2026-11-26",
                "end_date": "2026-12-01",
                "duration_days": 4,
                "is_half_day": false,
                "leave_type_value": 1,
                "session": "Full",
                "status": "approved",
                "status_label": "Approved"
            }
        ]
    }
}
"""##

private func decodedSample() throws -> CalendarResponse {
    let decoder = JSONDecoder()
    decoder.keyDecodingStrategy = .convertFromSnakeCase
    return try decoder.decode(DataEnvelope<CalendarResponse>.self, from: Data(sampleJSON.utf8)).value
}

private func timesheet(_ date: String, seconds: Int, open: Int = 0) -> CalendarTimesheet {
    CalendarTimesheet(date: date, startTimeLocal: "07:58", endTimeLocal: open > 0 ? nil : "17:14", workingSeconds: seconds, numberOpenTimeTrackers: open, hasPendingAdjustment: false)
}

private func service(calls: CalendarCalls = CalendarCalls()) throws -> CalendarService {
    let sample = try decodedSample()
    return CalendarService(
        calendar: { month in
            await calls.month(month)
            return sample
        },
        timesheets: { from, to in
            await calls.range(from, to)
            return [timesheet("2026-11-02", seconds: 33_358), timesheet("2026-11-11", seconds: 0, open: 1)]
        }
    )
}

@MainActor
struct CalendarTests {
    @Test("The month grid starts on Monday and fills whole weeks with the days around it")
    func monthGridStartsOnMonday() {
        let november = CalendarGrid.days(for: CalendarMonth(year: 2026, month: 11))
        #expect(november.count == 42)
        #expect(november.first?.date == "2026-10-26")
        #expect(november.first?.isInMonth == false)
        #expect(november[6].date == "2026-11-01")
        #expect(november[6].isWeekend)
        #expect(november.last?.date == "2026-12-06")
        #expect(november.filter(\.isInMonth).count == 30)

        let february = CalendarGrid.days(for: CalendarMonth(year: 2027, month: 2))
        #expect(february.count == 28)
        #expect(february.first?.date == "2027-02-01")
        #expect(february.filter(\.isInMonth).count == 28)
    }

    @Test("Months move across years and give the API its month parameter")
    func monthNavigation() {
        let december = CalendarMonth(year: 2026, month: 12)
        #expect(december.adding(1) == CalendarMonth(year: 2027, month: 1))
        #expect(CalendarMonth(year: 2027, month: 1).adding(-1) == december)
        #expect(december.adding(1).param == "2027-01")
        #expect(CalendarMonth("2026-02")?.lastDay == "2026-02-28")
        #expect(december.title == "December 2026")
        #expect(Endpoint.clockingCalendar(month: "2027-01").queryItems == [URLQueryItem(name: "month", value: "2027-01")])
    }

    @Test("Worked days are only asked for up to today")
    func timesheetRange() {
        let current = CalendarStore.timesheetRange(for: CalendarMonth(year: 2026, month: 11), today: "2026-11-11")
        #expect(current?.from == "2026-11-01")
        #expect(current?.to == "2026-11-11")
        let past = CalendarStore.timesheetRange(for: CalendarMonth(year: 2026, month: 10), today: "2026-11-11")
        #expect(past?.from == "2026-10-01")
        #expect(past?.to == "2026-10-31")
        #expect(CalendarStore.timesheetRange(for: CalendarMonth(year: 2026, month: 12), today: "2026-11-11") == nil)
    }

    @Test("A real shaped calendar response decodes")
    func decodesResponse() throws {
        let response = try decodedSample()
        #expect(response.month == "2026-11")
        #expect(response.holidays.first?.label == "Galungan")
        #expect(response.holidayYear?.label == "2026/27")
        #expect(response.leaves.first?.typeColorHex == "#4F46E5")
        #expect(response.leaves.first?.leaveTypeValue == 0.5)
        #expect(CalendarText.isHalfDay(response.leaves[0]))
        #expect(CalendarText.hexValue("#4F46E5") == 0x4F46E5)
        #expect(CalendarText.hexValue("blue") == nil)
    }

    @Test("Days are marked as worked, leave, pending leave, holiday or weekend")
    func dayClassification() async throws {
        let calls = CalendarCalls()
        let store = CalendarStore(service: try service(calls: calls), today: { "2026-11-11" })
        await store.load()

        #expect(await calls.months == ["2026-11"])
        #expect(await calls.ranges == ["2026-11-01…2026-11-11"])

        let worked = store.summary(for: "2026-11-02")
        #expect(worked.status == .worked)
        #expect(worked.markers == [.worked])
        #expect(worked.workedLabel == "Worked 9h 15m · 07:58 – 17:14")

        let today = store.summary(for: "2026-11-11")
        #expect(today.isToday)
        #expect(today.isStillWorking)

        let pending = store.summary(for: "2026-11-12")
        #expect(pending.status == .leavePending)
        #expect(pending.markers == [.leave(colorHex: "#4F46E5", isPending: true)])
        #expect(CalendarText.leaveDetail(pending.leaves[0]) == "Half day · Thu 12 Nov 2026")

        #expect(store.summary(for: "2026-11-20").status == .holiday)
        #expect(store.summary(for: "2026-11-26").status == .leave)
        #expect(store.summary(for: "2026-11-28").status == .weekend)
        #expect(store.summary(for: "2026-11-28").leaves.isEmpty)
        #expect(store.summary(for: "2026-11-10").status == .regular)
        #expect(store.summary(for: "2026-11-30").isFuture)

        #expect(store.monthHolidays == [CalendarHolidaySummary(dates: "Fri 20 Nov", label: "Galungan")])
        #expect(store.legendLeaves.map(\.label) == ["Halfday Afternoon", "Holiday / Vacation"])
        #expect(store.holidayYearLabel == "2026/27 · 1 Apr 2026 – 31 Mar 2027")
    }

    @Test("Changing month reloads it and keeps today selected only in the current month")
    func changingMonth() async throws {
        let calls = CalendarCalls()
        let store = CalendarStore(service: try service(calls: calls), today: { "2026-11-11" })
        await store.load()
        #expect(store.selectedSummary?.date == "2026-11-11")

        await store.showNextMonth()
        #expect(store.month.param == "2026-12")
        #expect(store.selectedDate == nil)
        #expect(!store.isShowingCurrentMonth)
        store.select("2026-11-30")
        #expect(store.selectedDate == nil)
        store.select("2026-12-01")
        #expect(store.selectedSummary?.date == "2026-12-01")

        await store.showCurrentMonth()
        #expect(store.isShowingCurrentMonth)
        #expect(store.selectedDate == "2026-11-11")
        #expect(await calls.months == ["2026-11", "2026-12", "2026-11"])
        #expect(await calls.ranges == ["2026-11-01…2026-11-11", "2026-11-01…2026-11-11"])
    }
}
