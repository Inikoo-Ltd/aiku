import Foundation
import Testing
@testable import Aiku

private actor OvertimeCalls {
    var created: [OvertimeRequestBody] = []
    var updated: [(Int, OvertimeRequestBody)] = []
    func create(_ body: OvertimeRequestBody) { created.append(body) }
    func update(_ id: Int, _ body: OvertimeRequestBody) { updated.append((id, body)) }
}

private let makassar = TimeZone(identifier: "Asia/Makassar")!
private let fixedNow = Date(timeIntervalSince1970: 1_794_384_000)

private func overtime(_ id: Int, status: String, date: String, start: String? = nil, end: String? = nil, minutes: Int = 120, recorded: Int? = nil, lieu: Int = 0) -> OvertimeRequestResponse {
    OvertimeRequestResponse(
        id: id, overtimeTypeId: 1, overtimeTypeName: "Overtime", requestedDate: date,
        requestedStartAt: start ?? "\(date)T17:00:00+08:00", requestedEndAt: end ?? "\(date)T19:00:00+08:00",
        requestedDurationMinutes: minutes, recordedStartAt: nil, recordedEndAt: nil, recordedDurationMinutes: recorded,
        lieuRequestedMinutes: lieu, reason: "Stock count", status: status, statusLabel: status.capitalized, approvedAt: nil, rejectedAt: nil
    )
}

private func service(calls: OvertimeCalls = OvertimeCalls(), requests: [OvertimeRequestResponse] = [], refuse: APIError? = nil) -> OvertimeService {
    OvertimeService(
        list: { OvertimeRequestsResponse(overtimeRequests: requests, typeOptions: [.init(value: 1, label: "Overtime"), .init(value: 2, label: "Weekend")]) },
        request: { body in
            await calls.create(body)
            if let refuse { throw refuse }
            return overtime(99, status: "pending", date: body.requestedDate, minutes: body.requestedDurationMinutes)
        },
        update: { id, body in
            await calls.update(id, body)
            if let refuse { throw refuse }
            return overtime(id, status: "pending", date: body.requestedDate, minutes: body.requestedDurationMinutes)
        }
    )
}

@MainActor
private func store(_ service: OvertimeService) -> OvertimeStore {
    OvertimeStore(service: service, timeZone: { makassar }, clock: { fixedNow })
}

@MainActor
struct OvertimeTests {
    @Test("Durations and times read like the web")
    func durationsAndTimes() {
        #expect(OvertimeCalculator.durationLabel(480) == "8h")
        #expect(OvertimeCalculator.durationLabel(450) == "7h 30m")
        #expect(OvertimeCalculator.durationLabel(45) == "45m")
        #expect(OvertimeCalculator.durationLabel(0) == "—")
        #expect(OvertimeCalculator.durationLabel(nil) == "—")
        #expect(OvertimeCalculator.durationMinutes(start: 17 * 60, end: 19 * 60 + 30) == 150)
        #expect(OvertimeCalculator.durationMinutes(start: 22 * 60, end: 2 * 60) == 240)
        #expect(OvertimeCalculator.durationMinutes(start: 600, end: 600) == 0)
        #expect(OvertimeCalculator.endsNextDay(start: 22 * 60, end: 2 * 60))
        #expect(OvertimeCalculator.timeRange(start: "2026-06-06T09:00:00+08:00", end: "2026-06-06T17:00:00+08:00", duration: 480, in: makassar) == "09:00 – 17:00")
        #expect(OvertimeCalculator.timeRange(start: "2026-06-06T22:00:00+08:00", end: nil, duration: 240, in: makassar) == "22:00 – 02:00 (next day)")
        #expect(OvertimeCalculator.isoString(day: "2026-11-12", minutes: 17 * 60 + 30, in: makassar) == "2026-11-12T17:30:00+08:00")
    }

    @Test("Requests are split into waiting and history, newest first, with this month's totals")
    func requestsAreGrouped() async {
        let overtimeStore = store(service(requests: [
            overtime(1, status: "pending", date: "2026-11-03", minutes: 90),
            overtime(2, status: "approved", date: "2026-11-05", minutes: 120, recorded: 100, lieu: 30),
            overtime(3, status: "rejected", date: "2026-11-07"),
            overtime(4, status: "approved", date: "2026-10-20", minutes: 480),
            overtime(5, status: "pending", date: "2026-11-09", minutes: 60),
        ]))

        await overtimeStore.load()

        #expect(overtimeStore.today == "2026-11-11")
        #expect(overtimeStore.pending.map(\.id) == [5, 1])
        #expect(overtimeStore.history.map(\.id) == [3, 2, 4])
        #expect(overtimeStore.monthSummary == OvertimeSummary(approvedMinutes: 100, pendingMinutes: 150, lieuMinutes: 30))
        #expect(OvertimeStatus(rawValue: "recorded")?.tone == .info)
    }

    @Test("A request needs a type, a date and a duration above zero")
    func requestIsValidated() async {
        let overtimeStore = store(service())
        await overtimeStore.load()
        let valid = OvertimeDraft(typeID: 1, date: "2026-11-10", startMinutes: 17 * 60, endMinutes: 19 * 60, reason: "")

        #expect(overtimeStore.validate(OvertimeDraft(typeID: nil, date: "2026-11-10", startMinutes: 0, endMinutes: 60, reason: ""))?.message == "Choose an overtime type")
        #expect(overtimeStore.validate(OvertimeDraft(typeID: 7, date: "2026-11-10", startMinutes: 0, endMinutes: 60, reason: ""))?.message == "Choose an overtime type")
        #expect(overtimeStore.validate(OvertimeDraft(typeID: 1, date: "", startMinutes: 0, endMinutes: 60, reason: ""))?.message == "Choose the date you worked")
        #expect(overtimeStore.validate(OvertimeDraft(typeID: 1, date: "2026-11-10", startMinutes: 600, endMinutes: 600, reason: ""))?.message == "Duration must be greater than zero.")
        #expect(overtimeStore.validate(valid) == nil)
    }

    @Test("A new request sends the web's fields in the organisation's time zone")
    func newRequestBody() async throws {
        let calls = OvertimeCalls()
        let overtimeStore = store(service(calls: calls))
        await overtimeStore.load()

        let result = await overtimeStore.submit(OvertimeDraft(typeID: 2, date: "2026-11-10", startMinutes: 22 * 60, endMinutes: 1 * 60 + 30, reason: "  Night delivery "))

        #expect((try? result.get())?.id == 99)
        let body = try #require(await calls.created.first)
        #expect(body == OvertimeRequestBody(
            overtimeTypeId: 2,
            requestedDate: "2026-11-10",
            requestedStartAt: "2026-11-10T22:00:00+08:00",
            requestedEndAt: "2026-11-11T01:30:00+08:00",
            requestedDurationMinutes: 210,
            reason: "Night delivery"
        ))
        let encoder = JSONEncoder()
        encoder.keyEncodingStrategy = .convertToSnakeCase
        let json = try #require(try JSONSerialization.jsonObject(with: encoder.encode(body)) as? [String: Any])
        #expect(Set(json.keys) == ["overtime_type_id", "requested_date", "requested_start_at", "requested_end_at", "requested_duration_minutes", "reason"])
    }

    @Test("Editing a pending request prefills it and sends a PATCH with its id")
    func editPendingRequest() async throws {
        let calls = OvertimeCalls()
        let pending = overtime(8, status: "pending", date: "2026-11-06", start: "2026-11-06T09:00:00+08:00", end: "2026-11-06T17:00:00+08:00", minutes: 480)
        let overtimeStore = store(service(calls: calls, requests: [pending]))
        await overtimeStore.load()

        var draft = overtimeStore.draft(for: pending)
        #expect(draft == OvertimeDraft(typeID: 1, date: "2026-11-06", startMinutes: 540, endMinutes: 1020, reason: "Stock count"))
        draft.endMinutes = 1050
        draft.reason = " "

        let result = await overtimeStore.submit(draft, editing: pending)

        #expect((try? result.get())?.id == 8)
        let update = try #require(await calls.updated.first)
        #expect(update.0 == 8)
        #expect(update.1.requestedDurationMinutes == 510)
        #expect(update.1.reason == nil)
    }

    @Test("Only pending requests can be edited and refusals show the server message")
    func refusals() async {
        let approved = overtime(3, status: "approved", date: "2026-11-05")
        let overtimeStore = store(service(requests: [approved], refuse: .rejected(message: "Only pending overtime requests can be edited.")))
        await overtimeStore.load()
        let draft = overtimeStore.draft(for: approved)

        #expect(await overtimeStore.submit(draft, editing: approved) == .failure(OvertimeFormError(message: "Only pending overtime requests can be edited.")))
        #expect(await overtimeStore.submit(draft) == .failure(OvertimeFormError(message: "Only pending overtime requests can be edited.")))
    }

    @Test("A new draft picks the only type and today's date")
    func newDraft() async {
        let overtimeStore = OvertimeStore(service: OvertimeService(
            list: { OvertimeRequestsResponse(overtimeRequests: [], typeOptions: [.init(value: 1, label: "Overtime")]) },
            request: { _ in throw APIError.offline },
            update: { _, _ in throw APIError.offline }
        ), timeZone: { makassar }, clock: { fixedNow })
        await overtimeStore.load()

        #expect(overtimeStore.newDraft().typeID == 1)
        #expect(overtimeStore.newDraft().date == "2026-11-11")
    }

    @Test("The overtime list decodes the mobile API response")
    func overtimeDecodes() throws {
        let json = ##"{"data":{"overtime_requests":[{"id":8,"overtime_type_id":1,"overtime_type_name":"Overtime","requested_date":"2026-06-06","requested_start_at":"2026-06-06T09:00:00+08:00","requested_end_at":"2026-06-06T17:00:00+08:00","requested_duration_minutes":480,"recorded_start_at":"2026-06-06T09:00:00+08:00","recorded_end_at":"2026-06-06T17:00:00+08:00","recorded_duration_minutes":480,"lieu_requested_minutes":0,"reason":"raul ask me to do it","status":"approved","status_label":"Approved","approved_at":"2026-06-12T10:05:52+08:00","rejected_at":null}],"pagination":{"current_page":1,"last_page":1,"per_page":100,"total":1},"type_options":[{"value":1,"label":"Overtime"}]}}"##
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase

        let response = try decoder.decode(DataEnvelope<OvertimeRequestsResponse>.self, from: Data(json.utf8)).value

        #expect(response.overtimeRequests.first?.requestedDurationMinutes == 480)
        #expect(response.overtimeRequests.first?.statusLabel == "Approved")
        #expect(response.overtimeRequests.first?.rejectedAt == nil)
        #expect(response.typeOptions == [.init(value: 1, label: "Overtime")])
    }
}
