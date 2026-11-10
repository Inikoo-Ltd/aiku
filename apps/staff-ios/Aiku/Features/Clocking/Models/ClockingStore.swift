import Foundation
import Observation

@MainActor
@Observable
final class ClockingStore {
    static let shared = ClockingStore()
    static let scanScript: [ClockingScanOutcomeKind] = [.success, .rejected, .success, .offline]

    private(set) var today: ClockingDay
    let pastWeeks: [ClockingWeek]
    var enabledMethods: [ClockingMethod]
    var selectedMethod: ClockingMethod?
    var pin: String?
    var employeeCode: String?
    var location: ClockingLocationState = .found
    let coordinates = ClockingSampleData.coordinates
    var hasActiveShifts = true
    var schedule: ClockingSchedule = .standard
    let shifts = ClockingSampleData.shifts
    let rejection = ClockingSampleData.rejection
    private(set) var adjustmentRequests: [ClockingAdjustmentRequest] = []
    var pendingAction: ClockingPendingAction?
    var openedErrorFromClocking = false
    var isShowingDetails = true
    private(set) var scanAttempts = 0
    private(set) var locationAttempts = 0
    @ObservationIgnored private let clock: @Sendable () -> Int

    init(
        today: ClockingDay = ClockingSampleData.today,
        pastWeeks: [ClockingWeek] = ClockingSampleData.pastWeeks,
        enabledMethods: [ClockingMethod] = ClockingMethod.allCases,
        pin: String? = ClockingSampleData.pin,
        employeeCode: String? = ClockingSampleData.employeeCode,
        clock: @escaping @Sendable () -> Int = { ClockingFormat.secondsSinceMidnight() }
    ) {
        self.today = today
        self.pastWeeks = pastWeeks
        self.enabledMethods = enabledMethods
        self.pin = pin
        self.employeeCode = employeeCode
        self.clock = clock
    }

    var now: Int { max(clock(), today.events.map(\.time).max() ?? 0) }

    var isClockedIn: Bool { today.isClockedIn }

    var activeMethod: ClockingMethod? {
        if let selectedMethod, enabledMethods.contains(selectedMethod) {
            return selectedMethod
        }
        return enabledMethods.first
    }

    var workingToday: Int { today.working(now: now) }

    var thisWeek: ClockingWeek {
        ClockingWeek(label: ClockingSampleData.thisWeekLabel, range: ClockingSampleData.thisWeekRange, days: today.events.isEmpty ? [] : [today])
    }

    var weeks: [ClockingWeek] { [thisWeek] + pastWeeks }

    var statusSince: String {
        if isClockedIn, let last = today.trackers.last {
            return "Since: \(ClockingFormat.time(last.inAt))"
        }
        if let lastOut = today.lastOut {
            return "Last clock out \(ClockingFormat.time(lastOut))"
        }
        return "Not clocked in today"
    }

    var workingHoursLabel: String {
        switch schedule {
        case .standard: ClockingSampleData.standardHours
        case .shift(let name): shifts.first { $0.name == name }?.hours ?? ClockingSampleData.standardHours
        }
    }

    var adjustmentDay: ClockingDay {
        weeks.flatMap(\.days).first { $0.status == .error } ?? today
    }

    func day(for date: String) -> ClockingDay {
        weeks.flatMap(\.days).first { $0.date == date } ?? adjustmentDay
    }

    func adjustments(for date: String) -> [ClockingAdjustmentRequest] {
        adjustmentRequests.filter { $0.date == date }
    }

    func code(for method: ClockingMethod) -> String? {
        switch method {
        case .qrCode: "machine"
        case .pin: pin
        case .barcode, .cameraQr: employeeCode
        }
    }

    func simulateScan() -> ClockingScanOutcome {
        let kind = Self.scanScript[scanAttempts % Self.scanScript.count]
        scanAttempts += 1
        switch kind {
        case .success: return .success(recordClocking())
        case .rejected: return .rejected
        case .offline: return .offline
        }
    }

    @discardableResult
    func recordClocking(place: String = "Main Warehouse") -> ClockingEvent {
        let lastTime = today.events.map(\.time).max() ?? -1
        let event = ClockingEvent(
            time: max(clock(), lastTime + 1),
            kind: isClockedIn ? .clockOut : .clockIn,
            viaMobileApp: true,
            place: place
        )
        today.events.append(event)
        return event
    }

    func saveNote(_ note: String, for eventID: UUID) {
        let trimmed = note.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty, let index = today.events.firstIndex(where: { $0.id == eventID }) else { return }
        today.events[index].note = trimmed
    }

    func startDetectingLocation() {
        location = .detecting
    }

    @discardableResult
    func finishDetectingLocation() -> Bool {
        locationAttempts += 1
        let isGranted = locationAttempts > 1
        location = isGranted ? .found : .needed
        return isGranted
    }

    @discardableResult
    func requestAdjustment(date: String, kind: ClockingEventKind, time: String, reason: String) -> Result<ClockingAdjustmentRequest, ClockingAdjustmentError> {
        guard let seconds = ClockingFormat.parseTime(time) else { return .failure(.invalidTime) }
        let trimmedReason = reason.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmedReason.isEmpty else { return .failure(.missingReason) }
        let request = ClockingAdjustmentRequest(date: date, kind: kind, time: seconds, reason: trimmedReason)
        adjustmentRequests.append(request)
        return .success(request)
    }
}

enum ClockingScanOutcomeKind: Hashable, Sendable {
    case success
    case rejected
    case offline
}
