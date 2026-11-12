import Foundation
import Testing
@testable import Aiku

private actor ClockingCalls {
    var qrRequests: [ClockingQrRequest] = []
    var adjustments: [ClockingAdjustmentRequestBody] = []
    var notes: [(Int, String?)] = []
    var timesheetRanges: [(String, String)] = []

    func recordQr(_ request: ClockingQrRequest) { qrRequests.append(request) }
    func recordAdjustment(_ body: ClockingAdjustmentRequestBody) { adjustments.append(body) }
    func recordNotes(_ id: Int, _ notes: String?) { self.notes.append((id, notes)) }
    func recordRange(_ from: String, _ to: String) { timesheetRanges.append((from, to)) }
}

private func record(_ id: Int, _ time: String, notes: String? = nil, place: String? = "Main Warehouse") -> ClockingRecordResponse {
    ClockingRecordResponse(id: id, clockedAt: time, type: "clocking-machine", isLate: false, notes: notes, workplace: place.map { .init(id: 1, name: $0) })
}

private let todaySessions = [
    ClockingSessionResponse(id: 1, sequence: 1, isOpen: false, startsAt: nil, endsAt: nil, duration: nil, clockIn: record(10, "2026-11-11T08:02:14+00:00"), clockOut: record(11, "2026-11-11T12:30:05+00:00", place: "Goods-in door")),
    ClockingSessionResponse(id: 2, sequence: 2, isOpen: true, startsAt: nil, endsAt: nil, duration: nil, clockIn: record(12, "2026-11-11T13:01:40+00:00"), clockOut: nil),
]

private func status(sessions: [ClockingSessionResponse] = todaySessions, methods: [String] = ["qr_code", "pin"], code: String? = "4829", hasShifts: Bool = false) -> ClockingStatusResponse {
    ClockingStatusResponse(
        employee: .init(id: 5, name: "Artha", organisation: .init(id: 1, code: "aw", name: "AW UK")),
        timezone: "Europe/London",
        status: "clocked_in",
        since: nil,
        today: ClockingTimesheetResponse(date: "2026-11-11", firstClockIn: nil, lastClockOut: nil, workingSeconds: 0, breaksSeconds: 0),
        sessions: sessions,
        methods: methods,
        code: code,
        hasShifts: hasShifts,
        canEditNotes: true
    )
}

private let pastTimesheets = ClockingTimesheetsResponse(
    from: "2026-10-15",
    to: "2026-11-11",
    timezone: "Europe/London",
    targetSeconds: nil,
    timesheets: [
        ClockingTimesheetResponse(id: 1, date: "2026-11-05", firstClockIn: nil, lastClockOut: nil, workingSeconds: 0, breaksSeconds: 0, sessions: [
            ClockingSessionResponse(id: 3, sequence: 1, isOpen: true, startsAt: nil, endsAt: nil, duration: nil, clockIn: record(20, "2026-11-05T08:05:00+00:00"), clockOut: nil),
        ]),
        ClockingTimesheetResponse(id: 2, date: "2026-11-03", firstClockIn: nil, lastClockOut: nil, workingSeconds: 0, breaksSeconds: 0, sessions: [
            ClockingSessionResponse(id: 4, sequence: 1, isOpen: false, startsAt: nil, endsAt: nil, duration: nil, clockIn: record(21, "2026-11-03T08:00:00+00:00"), clockOut: record(22, "2026-11-03T16:30:00+00:00")),
        ]),
        ClockingTimesheetResponse(id: 3, date: "2026-11-11", firstClockIn: nil, lastClockOut: nil, workingSeconds: 0, breaksSeconds: 0, sessions: todaySessions),
    ]
)

private func service(
    calls: ClockingCalls = ClockingCalls(),
    status statusResult: Result<ClockingStatusResponse, APIError> = .success(status()),
    qr: Result<ClockingQrResponse, APIError> = .failure(.offline),
    adjustment: APIError? = nil
) -> ClockingService {
    ClockingService(
        status: { try statusResult.get() },
        timesheets: { from, to in
            await calls.recordRange(from, to)
            return pastTimesheets
        },
        shifts: { [ClockingShiftResponse(id: 9, name: "Early shift", type: "shift", isActive: true)] },
        clockByQr: { request in
            await calls.recordQr(request)
            return try qr.get()
        },
        requestAdjustment: { body in
            await calls.recordAdjustment(body)
            if let adjustment { throw adjustment }
            return ClockingAdjustmentResponse(id: 50, date: body.date, timesheetId: body.timesheetId, originalStartTimeLocal: nil, originalEndTimeLocal: nil, requestedStartTimeLocal: body.requestedStartAt, requestedEndTimeLocal: body.requestedEndAt, reason: body.reason, status: "pending", statusLabel: "Pending", approvalComment: nil)
        },
        adjustments: { [ClockingAdjustmentResponse(id: 40, date: "2026-11-03", timesheetId: 2, originalStartTimeLocal: "08:00", originalEndTimeLocal: "16:30", requestedStartTimeLocal: nil, requestedEndTimeLocal: "17:00", reason: "Stayed late", status: "pending", statusLabel: "Pending", approvalComment: nil)] },
        updateNotes: { id, notes in await calls.recordNotes(id, notes) }
    )
}

private let nowAt1415 = ClockingMapper.date(fromISO: "2026-11-11T14:15:40+00:00")!

@MainActor
struct ClockingTests {
    @Test("Durations read as hours and two-digit minutes")
    func durationsAreFormatted() {
        #expect(ClockingFormat.duration(ClockingFormat.seconds(4, 27, 51)) == "4h 27m")
        #expect(ClockingFormat.duration(ClockingFormat.seconds(0, 31, 35)) == "31m")
        #expect(ClockingFormat.time(ClockingFormat.seconds(8, 2, 14)) == "08:02:14")
    }

    @Test("Adjustment times must be valid 24-hour times")
    func timesAreParsed() {
        #expect(ClockingFormat.parseTime("16:30") == ClockingFormat.seconds(16, 30))
        #expect(ClockingFormat.parseTime("24:00") == nil)
        #expect(ClockingFormat.parseTime("late") == nil)
    }

    @Test("Clocking times are read in the organisation's timezone")
    func timesUseTheOrganisationTimezone() {
        let jakarta = TimeZone(identifier: "Asia/Jakarta")!

        #expect(ClockingMapper.secondsSinceMidnight("2026-11-11T08:02:14+00:00", in: jakarta) == ClockingFormat.seconds(15, 2, 14))
        #expect(ClockingMapper.secondsSinceMidnight("2026-11-11T15:02:14+07:00", in: jakarta) == ClockingFormat.seconds(15, 2, 14))
        #expect(ClockingMapper.secondsSinceMidnight(nil, in: jakarta) == nil)
    }

    @Test("Sessions become in and out clockings with their place and id")
    func sessionsBecomeEvents() {
        let day = ClockingMapper.day(date: "2026-11-11", sessions: todaySessions, in: TimeZone(identifier: "UTC")!, isToday: true)

        #expect(day.events.map(\.kind) == [.clockIn, .clockOut, .clockIn])
        #expect(day.events[1].place == "Goods-in door")
        #expect(day.events[0].clockingID == 10)
        #expect(day.title == "Wed 11 Nov 2026")
        #expect(day.isClockedIn)
    }

    @Test("Methods map from the API names and unknown ones are dropped")
    func methodsMap() {
        #expect(["qr_code", "pin", "barcode", "camera_qr", "nfc"].compactMap(ClockingMapper.method) == [.qrCode, .pin, .barcode, .cameraQr])
    }

    @Test("Weeks group days from Monday with readable labels")
    func weeksGroupFromMonday() {
        #expect(ClockingMapper.weekStart(of: "2026-11-11") == "2026-11-09")
        #expect(ClockingMapper.weekStart(of: "2026-11-09") == "2026-11-09")
        #expect(ClockingMapper.weekRange(start: "2026-11-09") == "09 – 15 Nov 2026")
        #expect(ClockingMapper.weekRange(start: "2026-10-26") == "26 Oct – 01 Nov 2026")
        #expect(ClockingMapper.weekLabel(start: "2026-11-02", thisWeekStart: "2026-11-09") == "Last week")
        #expect(ClockingMapper.weekLabel(start: "2026-10-26", thisWeekStart: "2026-11-09") == "2 weeks ago")
    }

    @Test("Loading fills today, the methods, the code and four weeks of history")
    func loadingFillsTheStore() async throws {
        let calls = ClockingCalls()
        let store = ClockingStore(service: service(calls: calls), clock: { nowAt1415 })

        await store.load()

        #expect(store.hasLoaded)
        #expect(store.isClockedIn)
        #expect(store.enabledMethods == [.qrCode, .pin])
        #expect(store.pin == "4829")
        #expect(store.employeeName == "Artha")
        #expect(store.statusSince == "Since: 13:01:40")
        #expect(store.pastDays.map(\.date) == ["2026-11-05", "2026-11-03"])
        #expect(store.day(for: "2026-11-05").status == .error)
        #expect(store.adjustmentDay.date == "2026-11-05")
        let range = try #require(await calls.timesheetRanges.first)
        #expect(range.0 == "2026-10-15")
        #expect(range.1 == "2026-11-11")
        #expect(store.weeks.first?.label == "This week")
        #expect(store.weeks.first?.days.first?.isToday == true)
    }

    @Test("A failed load keeps the screen and shows the error")
    func failedLoadShowsTheError() async {
        let store = ClockingStore(service: service(status: .failure(.offline)), clock: { nowAt1415 })

        await store.load()

        #expect(store.hasLoaded == false)
        #expect(store.loadError == "No connection")
    }

    @Test("A QR clocking sends the code with the location and returns the clocking")
    func qrClockingSendsLocation() async throws {
        let calls = ClockingCalls()
        let response = ClockingQrResponse(
            success: true, message: "QR Code valid",
            machine: .init(id: 3, name: "Goods-in door"),
            isVisiting: false,
            workingHours: .init(start: "08:00:00", end: "17:00:00", name: "Default"),
            clocking: .init(id: 99, clockedAt: "2026-11-11T14:20:00+00:00", type: "clock_out", isLate: false)
        )
        let store = ClockingStore(service: service(calls: calls, qr: .success(response)), clock: { nowAt1415 })
        await store.load()
        store.setLocation(ClockingCoordinate(latitude: 51.5072, longitude: -0.1276))

        let outcome = await store.clock(code: "https://aiku.io/app/clocking-scan/abc123")

        guard case .success(let event) = outcome else {
            Issue.record("Expected a successful clocking")
            return
        }
        #expect(event.kind == .clockOut)
        #expect(event.clockingID == 99)
        #expect(event.place == "Goods-in door")
        #expect(store.workingHoursLabel == "08:00 - 17:00")
        let request = try #require(await calls.qrRequests.first)
        #expect(request.qrCode == "https://aiku.io/app/clocking-scan/abc123")
        #expect(request.latitude == 51.5072)
    }

    @Test("A rejected QR code keeps the server message for the error screen")
    func rejectedQrKeepsTheMessage() async {
        let store = ClockingStore(service: service(qr: .failure(.rejected(message: "Invalid QR Code."))), clock: { nowAt1415 })

        let outcome = await store.clock(code: "LOC-A-03-02")

        #expect(outcome == .rejected)
        #expect(store.rejection.message == "Invalid QR Code.")
        #expect(store.rejection.scanned == "LOC-A-03-02")
    }

    @Test("A QR code that needs the location asks for it again")
    func locationRequiredAsksAgain() async {
        let store = ClockingStore(service: service(qr: .failure(.rejected(message: "Location access is required to validate this QR code."))), clock: { nowAt1415 })
        store.setLocation(ClockingCoordinate(latitude: 1, longitude: 2))

        let outcome = await store.clock(code: "abc")

        #expect(outcome == .locationNeeded)
        #expect(store.location == .needed)
    }

    @Test("No connection while clocking is reported as offline")
    func offlineClocking() async {
        let store = ClockingStore(service: service(qr: .failure(.offline)), clock: { nowAt1415 })

        #expect(await store.clock(code: "abc") == .offline)
    }

    @Test("An adjustment checks the date, the times and the reason like the web")
    func adjustmentIsValidated() async {
        let store = ClockingStore(service: service(), clock: { nowAt1415 })
        await store.load()

        #expect(store.earliestAdjustmentDate == "2026-10-11")
        #expect(store.validateAdjustment(date: "2026-11-12", start: "08:00", end: nil, reason: "x") == .futureDate)
        #expect(store.validateAdjustment(date: "2026-10-10", start: "08:00", end: nil, reason: "x") == .tooOld)
        #expect(store.validateAdjustment(date: "2026-11-05", start: nil, end: nil, reason: "x") == .missingTime)
        #expect(store.validateAdjustment(date: "2026-11-05", start: "17:00", end: "08:00", reason: "x") == .endBeforeStart)
        #expect(store.validateAdjustment(date: "2026-11-05", start: nil, end: "17:04", reason: "  ") == .missingReason)
        #expect(store.validateAdjustment(date: "2026-11-03", start: nil, end: "17:30", reason: "x") == .pendingExists)
        #expect(store.validateAdjustment(date: "2026-11-05", start: nil, end: "17:04", reason: "Forgot") == nil)
    }

    @Test("A day with clockings is corrected through its timesheet")
    func correctionSendsTheTimesheet() async throws {
        let calls = ClockingCalls()
        let store = ClockingStore(service: service(calls: calls), clock: { nowAt1415 })
        await store.load()

        let result = await store.requestAdjustment(date: "2026-11-05", start: nil, end: "17:04", reason: " Forgot to clock out ")

        #expect((try? result.get())?.summary == "Check-out 17:04 · Forgot to clock out")
        let body = try #require(await calls.adjustments.first)
        #expect(body.timesheetId == 1)
        #expect(body.requestedEndAt == "17:04")
        #expect(body.requestedStartAt == nil)
        #expect(store.adjustments(for: "2026-11-05").count == 1)
    }

    @Test("A day without clockings is sent by date only")
    func freeAdjustmentHasNoTimesheet() async throws {
        let calls = ClockingCalls()
        let store = ClockingStore(service: service(calls: calls), clock: { nowAt1415 })
        await store.load()

        _ = await store.requestAdjustment(date: "2026-11-04", start: "08:00", end: "17:00", reason: "Clocking machine was down")

        let body = try #require(await calls.adjustments.first)
        #expect(body.date == "2026-11-04")
        #expect(body.timesheetId == nil)
        #expect(store.recordedDay(on: "2026-11-04") == nil)
        #expect(store.recordedDay(on: "2026-11-03")?.timesheetID == 2)
    }

    @Test("A refused adjustment shows the server message and is not kept")
    func refusedAdjustment() async {
        let store = ClockingStore(service: service(adjustment: .validation(message: "You have approved leave on this date. Adjustments are not allowed.", code: nil)), clock: { nowAt1415 })
        await store.load()

        let result = await store.requestAdjustment(date: "2026-11-05", start: "08:00", end: nil, reason: "Late badge")

        #expect(result == .failure(.server("You have approved leave on this date. Adjustments are not allowed.")))
        #expect(store.adjustments(for: "2026-11-05").isEmpty)
    }

    @Test("Notes are saved on the clocking they belong to")
    func notesAreSaved() async throws {
        let calls = ClockingCalls()
        let store = ClockingStore(service: service(calls: calls), clock: { nowAt1415 })

        let saved = await store.saveNote("  Left via goods-in ", for: ClockingEvent(time: 0, kind: .clockOut, clockingID: 11))

        #expect(saved)
        let note = try #require(await calls.notes.first)
        #expect(note.0 == 11)
        #expect(note.1 == "Left via goods-in")
        #expect(await store.saveNote("note", for: ClockingEvent(time: 0, kind: .clockOut)) == false)
    }

    @Test("The clocking codes match the design pattern size")
    func codePatternSizes() {
        #expect(ClockingCodePattern.bars.count == 44)
        #expect(ClockingCodePattern.qrCells.count == 121)
    }
}

struct ClockingAPITests {
    @Test("The status decodes the mobile clocking response")
    func statusDecodes() throws {
        let json = #"{"data":{"employee":{"id":595,"name":"Artha","organisation":{"id":1,"code":"aw","name":"AW UK"}},"timezone":"Asia/Makassar","status":"clocked_in","since":"2026-11-11T08:02:14+08:00","today":{"date":"2026-11-11","first_clock_in":"2026-11-11T08:02:14+08:00","last_clock_out":null,"working_seconds":3600,"breaks_seconds":0},"sessions":[{"id":1,"sequence":1,"status":"open","is_open":true,"starts_at":"2026-11-11T08:02:14+08:00","ends_at":null,"duration":null,"clock_in":{"id":7,"clocked_at":"2026-11-11T08:02:14+08:00","type":"clocking-machine","is_late":false,"notes":null},"clock_out":null}],"methods":["qr_code"],"code":null,"has_shifts":false,"can_edit_notes":false}}"#
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase

        let status = try decoder.decode(DataEnvelope<ClockingStatusResponse>.self, from: Data(json.utf8)).value

        #expect(status.employee?.organisation?.name == "AW UK")
        #expect(status.sessions.first?.clockIn?.id == 7)
        #expect(status.methods == ["qr_code"])
    }

    @Test("A 400 with a message becomes a rejection with that message")
    func badRequestKeepsTheMessage() async {
        struct Transport: HTTPTransport {
            func data(for request: URLRequest) async throws -> (Data, URLResponse) {
                (Data(#"{"success":false,"message":"Invalid QR Code."}"#.utf8), HTTPURLResponse(url: request.url!, statusCode: 400, httpVersion: nil, headerFields: nil)!)
            }
        }
        let client = APIClient(configuration: AppConfiguration(apiBaseURL: URL(string: "http://localhost/app/api")!), transport: Transport())

        await #expect(throws: APIError.rejected(message: "Invalid QR Code.")) {
            try await client.clockByQr(ClockingQrRequest(qrCode: "x", latitude: nil, longitude: nil))
        }
    }

    @Test("The QR request uses the API field names")
    func qrRequestFields() throws {
        let request = APIClient(configuration: AppConfiguration(apiBaseURL: URL(string: "http://localhost/app/api")!))
            .makeRequest(for: .jsonBody("mobile/clocking/qr", method: .post, ClockingQrRequest(qrCode: "abc", latitude: 1.5, longitude: 2.5, workScheduleId: 9)))
        let body = try JSONSerialization.jsonObject(with: try #require(request.httpBody)) as? [String: Any]

        #expect(request.url?.absoluteString == "http://localhost/app/api/mobile/clocking/qr")
        #expect(body?["qr_code"] as? String == "abc")
        #expect(body?["work_schedule_id"] as? Int == 9)
    }
}
