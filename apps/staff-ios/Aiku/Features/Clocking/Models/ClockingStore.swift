import Foundation
import Observation

@MainActor
@Observable
final class ClockingStore {
    static let shared = ClockingStore(service: .live(.shared))
    static let historyDays = 28

    private(set) var today: ClockingDay
    private(set) var pastDays: [ClockingDay] = []
    private(set) var enabledMethods: [ClockingMethod] = []
    var selectedMethod: ClockingMethod?
    private(set) var pin: String?
    private(set) var employeeCode: String?
    private(set) var employeeName: String?
    private(set) var organisationName: String?
    private(set) var canEditNotes = false
    private(set) var hasActiveShifts = false
    private(set) var shifts: [ClockingShift] = []
    var schedule: ClockingSchedule = .standard
    private(set) var location: ClockingLocationState = .needed
    private(set) var coordinate: ClockingCoordinate?
    private(set) var rejection = ClockingRejection(message: "", scanned: "")
    private(set) var workingHoursLabel = "—"
    private(set) var adjustmentRequests: [ClockingAdjustmentResponse] = []
    private(set) var isLoading = false
    private(set) var hasLoaded = false
    private(set) var loadError: String?
    var pendingAction: ClockingPendingAction?
    var openedErrorFromClocking = false
    var isShowingDetails = true
    private(set) var timeZone: TimeZone = .current

    @ObservationIgnored private let service: ClockingService
    @ObservationIgnored private let clock: @Sendable () -> Date

    init(service: ClockingService, clock: @escaping @Sendable () -> Date = { .now }) {
        self.service = service
        self.clock = clock
        today = ClockingMapper.day(date: ClockingMapper.dayString(clock(), in: .current), sessions: [], in: .current, isToday: true)
    }

    var now: Int {
        let seconds = ClockingMapper.secondsSinceMidnight(ISO8601DateFormatter().string(from: clock()), in: timeZone) ?? 0
        return max(seconds, today.events.map(\.time).max() ?? 0)
    }

    var isClockedIn: Bool { today.isClockedIn }

    var activeMethod: ClockingMethod? {
        if let selectedMethod, enabledMethods.contains(selectedMethod) {
            return selectedMethod
        }
        return enabledMethods.first
    }

    var coordinates: String { coordinate?.label ?? "" }

    var weeks: [ClockingWeek] {
        let days = today.events.isEmpty ? pastDays : [today] + pastDays
        let weeks = ClockingMapper.weeks(from: days, today: today.date)
        if weeks.first?.label == "This week" { return weeks }
        return [ClockingWeek(label: "This week", range: ClockingMapper.weekRange(start: ClockingMapper.weekStart(of: today.date) ?? today.date), days: [])] + weeks
    }

    var statusSince: String {
        if isClockedIn, let last = today.trackers.last {
            return "Since: \(ClockingFormat.time(last.inAt))"
        }
        if let lastOut = today.lastOut {
            return "Last clock out \(ClockingFormat.time(lastOut))"
        }
        return "Not clocked in today"
    }

    var adjustmentDay: ClockingDay {
        pastDays.first { $0.status == .error } ?? today
    }

    var todayDate: String { ClockingMapper.dayString(clock(), in: timeZone) }

    var earliestAdjustmentDate: String { ClockingMapper.monthBefore(todayDate) ?? todayDate }

    func day(for date: String) -> ClockingDay {
        if date == today.date { return today }
        return pastDays.first { $0.date == date } ?? adjustmentDay
    }

    func recordedDay(on date: String) -> ClockingDay? {
        if date == today.date, today.timesheetID != nil || !today.events.isEmpty { return today }
        return pastDays.first { $0.date == date }
    }

    func adjustments(for date: String) -> [ClockingAdjustmentResponse] {
        adjustmentRequests.filter { $0.date == date }
    }

    func code(for method: ClockingMethod) -> String? {
        switch method {
        case .qrCode: nil
        case .pin: pin
        case .barcode, .cameraQr: employeeCode
        }
    }

    func load() async {
        guard !isLoading else { return }
        isLoading = true
        defer { isLoading = false }
        do {
            let status = try await service.status()
            apply(status)
            let to = today.date
            let from = ClockingMapper.parseDay(to).map { ClockingMapper.dayString($0.addingTimeInterval(-Double(Self.historyDays - 1) * 86_400), in: TimeZone(identifier: "UTC")!) } ?? to
            let history = try await service.timesheets(from, to)
            today.timesheetID = history.timesheets.first { $0.date == today.date }?.id
            pastDays = history.timesheets
                .filter { $0.date != today.date }
                .map { ClockingMapper.day(date: $0.date, sessions: $0.sessions ?? [], in: timeZone, isToday: false, timesheetID: $0.id) }
                .sorted { $0.date > $1.date }
            adjustmentRequests = (try? await service.adjustments()) ?? adjustmentRequests
            if hasActiveShifts {
                shifts = ((try? await service.shifts()) ?? [])
                    .filter { $0.isActive ?? true }
                    .map { ClockingShift(name: $0.name, hours: $0.type?.capitalized ?? "", scheduleID: $0.id) }
            }
            loadError = nil
            hasLoaded = true
        } catch where error.isCancellation {
            return
        } catch {
            loadError = error.localizedDescription
        }
    }

    func apply(_ status: ClockingStatusResponse) {
        timeZone = ClockingMapper.timeZone(status.timezone)
        let todayDate = status.today?.date ?? ClockingMapper.dayString(clock(), in: timeZone)
        today = ClockingMapper.day(date: todayDate, sessions: status.sessions, in: timeZone, isToday: true)
        enabledMethods = status.methods.compactMap(ClockingMapper.method)
        pin = status.code
        employeeCode = status.code
        employeeName = status.employee?.name
        organisationName = status.employee?.organisation?.name
        canEditNotes = status.canEditNotes
        hasActiveShifts = status.hasShifts
    }

    func setLocation(_ coordinate: ClockingCoordinate?) {
        self.coordinate = coordinate
        location = coordinate == nil ? .needed : .found
    }

    func startDetectingLocation() {
        location = .detecting
    }

    func clock(code: String) async -> ClockingScanOutcome {
        let scheduleID: Int? = if case .shift(let name) = schedule { shifts.first { $0.name == name }?.scheduleID } else { nil }
        let request = ClockingQrRequest(qrCode: code, latitude: coordinate?.latitude, longitude: coordinate?.longitude, workScheduleId: scheduleID)
        do {
            let response = try await service.clockByQr(request)
            if let start = ClockingMapper.shortTime(response.workingHours?.start), let end = ClockingMapper.shortTime(response.workingHours?.end) {
                workingHoursLabel = "\(start) - \(end)"
            }
            let kind: ClockingEventKind = response.clocking?.type == "clock_out" ? .clockOut : .clockIn
            let time = ClockingMapper.secondsSinceMidnight(response.clocking?.clockedAt, in: timeZone) ?? now
            let event = ClockingEvent(time: time, kind: kind, viaMobileApp: true, place: response.machine?.name ?? "Clocking machine", clockingID: response.clocking?.id, isLate: response.clocking?.isLate ?? false)
            await load()
            return .success(event)
        } catch APIError.offline {
            return .offline
        } catch {
            let message = error.localizedDescription
            rejection = ClockingRejection(message: message, scanned: code)
            if message.localizedCaseInsensitiveContains("location") {
                setLocation(nil)
                return .locationNeeded
            }
            return .rejected
        }
    }

    func saveNote(_ note: String, for event: ClockingEvent) async -> Bool {
        let trimmed = note.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty, let clockingID = event.clockingID else { return false }
        do {
            try await service.updateNotes(clockingID, trimmed)
            await load()
            return true
        } catch {
            return false
        }
    }

    func validateAdjustment(date: String, start: String?, end: String?, reason: String) -> ClockingAdjustmentError? {
        if date > todayDate { return .futureDate }
        if date < earliestAdjustmentDate { return .tooOld }
        if start == nil, end == nil { return .missingTime }
        if let start, let end, end <= start { return .endBeforeStart }
        if reason.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty { return .missingReason }
        if adjustments(for: date).contains(where: { $0.status == ClockingAdjustmentStatus.pending.rawValue }) { return .pendingExists }
        return nil
    }

    func requestAdjustment(date: String, start: String?, end: String?, reason: String) async -> Result<ClockingAdjustmentResponse, ClockingAdjustmentError> {
        if let error = validateAdjustment(date: date, start: start, end: end, reason: reason) {
            return .failure(error)
        }
        let body = ClockingAdjustmentRequestBody(
            date: date,
            timesheetId: recordedDay(on: date)?.timesheetID,
            requestedStartAt: start,
            requestedEndAt: end,
            reason: reason.trimmingCharacters(in: .whitespacesAndNewlines)
        )
        do {
            let created = try await service.requestAdjustment(body)
            adjustmentRequests.insert(created, at: 0)
            return .success(created)
        } catch {
            return .failure(.server(error.localizedDescription))
        }
    }
}
