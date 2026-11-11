import Foundation
import Testing
@testable import Aiku

private actor LeaveCalls {
    var requests: [LeaveRequestBody] = []
    func record(_ body: LeaveRequestBody) { requests.append(body) }
}

private let options = [
    LeavesResponse.TypeOption(code: "holiday-vacation", label: "Holiday / Vacation", category: "annual", maxDaysPerYear: 14, value: 1),
    LeavesResponse.TypeOption(code: "hd-morning", label: "Halfday Morning", category: "annual", maxDaysPerYear: 14, value: 0.5),
]

private func leave(_ id: Int, status: String, start: String, end: String) -> LeaveResponse {
    LeaveResponse(id: id, type: "holiday-vacation", typeLabel: "Holiday / Vacation", startDate: start, endDate: end, durationDays: 1, session: "Full", reason: "Trip", status: status, statusLabel: status.capitalized, rejectionReason: nil, approvalTotalSteps: 1, approvalCompletedSteps: 0, approverName: nil)
}

private func response(leaves: [LeaveResponse] = []) -> LeavesResponse {
    LeavesResponse(
        leaves: leaves,
        balance: .init(annualDays: 12, annualUsed: 3.5, annualRemaining: 8.5, medicalUsed: 0, unpaidUsed: 0),
        annualSubmittedDays: 0,
        annualRemainingAfterSubmission: 8.5,
        typeOptions: options,
        holidays: [.init(date: "2026-12-25", label: "Christmas Day")]
    )
}

private func service(calls: LeaveCalls = LeaveCalls(), list: LeavesResponse = response(), refuse: APIError? = nil) -> LeaveService {
    LeaveService(
        list: { list },
        request: { body in
            await calls.record(body)
            if let refuse { throw refuse }
            return leave(99, status: "pending", start: body.startDate, end: body.endDate)
        }
    )
}

@MainActor
struct LeaveTests {
    @Test("Working days skip weekends and public holidays")
    func workingDaysSkipWeekendsAndHolidays() {
        #expect(LeaveCalculator.workingDays(from: "2026-12-21", to: "2026-12-27", holidays: ["2026-12-25"]) == 4)
        #expect(LeaveCalculator.workingDays(from: "2026-11-14", to: "2026-11-15", holidays: []) == 0)
        #expect(LeaveCalculator.days(type: options[1], start: "2026-11-12", end: "2026-11-12", holidays: []) == 0.5)
        #expect(LeaveCalculator.daysLabel(0.5) == "0.5 days")
        #expect(LeaveCalculator.daysLabel(1) == "1 day")
        #expect(LeaveCalculator.daysLabel(8.5) == "8.5 days")
    }

    @Test("Date ranges read like the web")
    func rangeLabels() {
        #expect(LeaveCalculator.rangeLabel(start: "2026-10-22", end: "2026-10-22") == "Thu 22 Oct 2026")
        #expect(LeaveCalculator.rangeLabel(start: "2026-12-21", end: "2026-12-24") == "Mon 21 Dec 2026 – Thu 24 Dec 2026")
    }

    @Test("Leave is grouped into waiting, upcoming and past")
    func leaveIsGrouped() async {
        let store = LeaveStore(service: service(list: response(leaves: [
            leave(1, status: "pending", start: "2026-12-01", end: "2026-12-02"),
            leave(2, status: "approved", start: "2026-12-10", end: "2026-12-10"),
            leave(3, status: "approved", start: "2026-10-22", end: "2026-10-22"),
            leave(4, status: "rejected", start: "2026-11-01", end: "2026-11-01"),
        ])), today: { "2026-11-11" })

        await store.load()

        #expect(store.pending.map(\.id) == [1])
        #expect(store.upcoming.map(\.id) == [2])
        #expect(store.past.map(\.id) == [4, 3])
        #expect(store.balance?.annualRemaining == 8.5)
        #expect(store.upcomingHolidays.first?.label == "Christmas Day")
    }

    @Test("A request needs a type, future dates on working days and a reason")
    func requestIsValidated() async {
        let store = LeaveStore(service: service(), today: { "2026-11-11" })
        await store.load()

        #expect(store.validate(type: nil, start: "2026-11-12", end: "2026-11-12", reason: "x")?.message == "Choose a leave type")
        #expect(store.validate(type: "holiday-vacation", start: "2026-11-10", end: "2026-11-12", reason: "x")?.message == "Leave can’t start in the past")
        #expect(store.validate(type: "holiday-vacation", start: "2026-11-13", end: "2026-11-12", reason: "x")?.message == "The last day must be on or after the first day")
        #expect(store.validate(type: "holiday-vacation", start: "2026-11-14", end: "2026-11-15", reason: "x")?.message == "These dates are only weekends or holidays")
        #expect(store.validate(type: "holiday-vacation", start: "2026-11-12", end: "2026-11-13", reason: "  ")?.message == "Add a reason for your manager")
        #expect(store.validate(type: "holiday-vacation", start: "2026-11-12", end: "2026-11-13", reason: "Trip") == nil)
    }

    @Test("A half day type sends a single day")
    func halfDaySendsOneDay() async throws {
        let calls = LeaveCalls()
        let store = LeaveStore(service: service(calls: calls), today: { "2026-11-11" })
        await store.load()

        let result = await store.request(type: "hd-morning", start: "2026-11-12", end: "2026-11-14", reason: " Dentist ")

        #expect((try? result.get())?.id == 99)
        let body = try #require(await calls.requests.first)
        #expect(body.startDate == "2026-11-12")
        #expect(body.endDate == "2026-11-12")
        #expect(body.reason == "Dentist")
        #expect(body.type == "hd-morning")
    }

    @Test("A refused request shows the server message")
    func refusedRequest() async {
        let store = LeaveStore(service: service(refuse: .validation(message: "The start date must be a date after or equal to today.", code: nil)), today: { "2026-11-11" })
        await store.load()

        let result = await store.request(type: "holiday-vacation", start: "2026-11-12", end: "2026-11-12", reason: "Trip")

        #expect(result == .failure(LeaveFormError(message: "The start date must be a date after or equal to today.")))
    }

    @Test("The leave list decodes the mobile API response")
    func leavesDecode() throws {
        let json = ##"{"data":{"leaves":[{"id":87,"employee_id":595,"type":"hd-morning","type_label":"Halfday Morning","type_color_hex":"#6B7280","start_date":"2026-10-22","end_date":"2026-10-22","duration_days":1,"is_half_day":false,"leave_type_value":0.5,"session":"Full","reason":"Seminar","status":"approved","status_label":"Approved","approval_total_steps":1,"approval_completed_steps":1,"approvals":[],"attachments":[]}],"pagination":{"current_page":1,"last_page":1,"per_page":10,"total":1},"balance":{"id":57,"annual_days":12,"annual_used":3.5,"annual_remaining":8.5,"medical_used":0,"unpaid_used":0,"annual_adjustment":0,"medical_adjustment":0,"unpaid_adjustment":0},"annual_submitted_days":0,"annual_remaining_after_submission":8.5,"medical_request_count":0,"unpaid_request_count":0,"type_options":[{"code":"hd-morning","label":"Halfday Morning","category":"annual","max_days_per_year":14,"value":0.5}],"holidays":[{"date":"2026-12-25","label":"Christmas Day"}]}}"##
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase

        let leaves = try decoder.decode(DataEnvelope<LeavesResponse>.self, from: Data(json.utf8)).value

        #expect(leaves.leaves.first?.typeLabel == "Halfday Morning")
        #expect(leaves.balance?.annualRemaining == 8.5)
        #expect(leaves.typeOptions.first?.value == 0.5)
    }
}
