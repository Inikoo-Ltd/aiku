import Foundation

enum ClockingMethod: String, CaseIterable, Hashable, Sendable {
    case qrCode
    case pin
    case barcode
    case cameraQr

    @MainActor
    var title: String {
        switch self {
        case .qrCode: L10n.text("QR Code")
        case .pin: L10n.text("PIN")
        case .barcode: L10n.text("Barcode")
        case .cameraQr: L10n.text("Camera QR")
        }
    }
}

enum ClockingEventKind: Hashable, Sendable {
    case clockIn
    case clockOut

    @MainActor
    var label: String {
        switch self {
        case .clockIn: L10n.text("In")
        case .clockOut: L10n.text("Out")
        }
    }
}

struct ClockingEvent: Identifiable, Hashable, Sendable {
    let id: UUID
    let time: Int
    let kind: ClockingEventKind
    let viaMobileApp: Bool
    let place: String
    var note: String?
    var clockingID: Int?
    var isLate = false

    init(time: Int, kind: ClockingEventKind, viaMobileApp: Bool = true, place: String = "Main Warehouse", note: String? = nil, clockingID: Int? = nil, isLate: Bool = false) {
        self.id = UUID()
        self.time = time
        self.kind = kind
        self.viaMobileApp = viaMobileApp
        self.place = place
        self.note = note
        self.clockingID = clockingID
        self.isLate = isLate
    }

    @MainActor
    var detail: String {
        [kind.label, viaMobileApp ? L10n.text("Mobile app") : L10n.text("Clocking machine"), place].joined(separator: " · ")
    }
}

struct ClockingTracker: Hashable, Sendable {
    let inAt: Int
    var outAt: Int?

    var isOpen: Bool { outAt == nil }

    func duration(now: Int?) -> Int {
        if let outAt {
            return max(outAt - inAt, 0)
        }
        guard let now else { return 0 }
        return max(now - inAt, 0)
    }
}

enum ClockingDayStatus: Hashable, Sendable {
    case ok
    case open
    case error
}

struct ClockingDay: Identifiable, Hashable, Sendable {
    let date: String
    let weekday: String
    let dayOfMonth: String
    let monthYear: String
    var events: [ClockingEvent]
    var shift = "08:00 – 16:30"
    var workplace = "Main Warehouse · WH1"
    var note: String?
    var isToday = false
    var timesheetID: Int?

    var id: String { date }
    var title: String { formatted("EEE dd MMM yyyy") ?? "\(weekday) \(dayOfMonth) \(monthYear)" }
    var shortTitle: String { formatted("EEE dd MMM") ?? "\(weekday) \(dayOfMonth) \(monthYear.split(separator: " ").first.map(String.init) ?? "")" }
    var weekdayTitle: String {
        guard let short = formatted("EEE") else { return weekday }
        return short.count > 4 ? formatted("EEEEEE") ?? short : short
    }

    private func formatted(_ pattern: String) -> String? {
        ClockingMapper.parseDay(date).map { CalendarText.format(pattern, $0) }
    }

    var trackers: [ClockingTracker] {
        var result: [ClockingTracker] = []
        for event in events.sorted(by: { $0.time < $1.time }) {
            switch event.kind {
            case .clockIn:
                result.append(ClockingTracker(inAt: event.time))
            case .clockOut:
                if let last = result.indices.last, result[last].isOpen {
                    result[last].outAt = event.time
                }
            }
        }
        return result
    }

    var isClockedIn: Bool { trackers.last?.isOpen ?? false }

    func working(now: Int?) -> Int {
        trackers.reduce(0) { $0 + $1.duration(now: isToday ? now : nil) }
    }

    var breaks: Int {
        let list = trackers
        guard list.count > 1 else { return 0 }
        return zip(list, list.dropFirst()).reduce(0) { total, pair in
            guard let outAt = pair.0.outAt else { return total }
            return total + max(pair.1.inAt - outAt, 0)
        }
    }

    var clockingCount: Int { events.count }

    var firstIn: Int? { trackers.first?.inAt }

    var lastOut: Int? { isClockedIn ? nil : trackers.last?.outAt }

    var status: ClockingDayStatus {
        guard isClockedIn else { return .ok }
        return isToday ? .open : .error
    }

    @MainActor
    var rangeLabel: String {
        guard let firstIn else { return "—" }
        let start = ClockingFormat.shortTime(firstIn)
        if let lastOut {
            return "\(start) → \(ClockingFormat.shortTime(lastOut))"
        }
        return isToday ? L10n.format("%@ → now", start) : "\(start) → —"
    }

    @MainActor
    var summary: String {
        switch status {
        case .open: return L10n.format("Open · %lld clockings", clockingCount)
        case .error: return L10n.text("Error · no clock out")
        case .ok:
            if let note { return L10n.format("%lld clockings · %@", clockingCount, note) }
            return L10n.format("%lld clockings · break %@", clockingCount, breaks > 0 ? ClockingFormat.duration(breaks) : "—")
        }
    }
}

struct ClockingWeek: Identifiable, Hashable, Sendable {
    let label: String
    let range: String
    var days: [ClockingDay]
    var weeksAgo: Int?

    static let weekdays = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"]
    static let workingDays = 5
    static let targetSeconds = ClockingFormat.seconds(37, 30)

    var id: String { label }

    func working(now: Int?) -> Int {
        days.reduce(0) { $0 + $1.working(now: now) }
    }

    var breaks: Int { days.reduce(0) { $0 + $1.breaks } }

    @MainActor
    var title: String {
        switch weeksAgo {
        case 0: L10n.text("This week")
        case 1: L10n.text("Last week")
        case let weeks?: L10n.format("%lld weeks ago", weeks)
        case nil: label
        }
    }

    @MainActor
    var daysLabel: String { L10n.format("%lld of %lld", days.count, Self.workingDays) }

    func day(on weekday: String) -> ClockingDay? {
        days.first { $0.weekday == weekday }
    }
}

enum ClockingLocationState: Hashable, Sendable {
    case needed
    case detecting
    case found
}

enum ClockingScanOutcome: Hashable, Sendable {
    case success(ClockingEvent)
    case rejected
    case locationNeeded
    case offline
}

struct ClockingRejection: Hashable, Sendable {
    let message: String
    let scanned: String
}

struct ClockingShift: Identifiable, Hashable, Sendable {
    let name: String
    let hours: String
    var scheduleID: Int?

    var id: String { name }
}

struct ClockingCoordinate: Hashable, Sendable {
    let latitude: Double
    let longitude: Double

    var label: String {
        String(format: "%.4f, %.4f", latitude, longitude)
    }
}

enum ClockingSchedule: Hashable, Sendable {
    case standard
    case shift(String)
}

enum ClockingAdjustmentStatus: String, Sendable {
    case pending
    case approved
    case rejected
    case cancelled

    var tone: Tone {
        switch self {
        case .pending: .warn
        case .approved: .success
        case .rejected: .danger
        case .cancelled: .neutral
        }
    }
}

extension ClockingAdjustmentResponse {
    @MainActor
    var summary: String {
        var parts: [String] = []
        if let start = requestedStartTimeLocal { parts.append(L10n.format("Check-in %@", start)) }
        if let end = requestedEndTimeLocal { parts.append(L10n.format("Check-out %@", end)) }
        if let reason, !reason.isEmpty { parts.append(reason) }
        return parts.joined(separator: " · ")
    }

    var tone: Tone {
        status.flatMap(ClockingAdjustmentStatus.init(rawValue:))?.tone ?? .neutral
    }
}

enum ClockingAdjustmentError: Error, Hashable, Sendable {
    case missingTime
    case endBeforeStart
    case futureDate
    case tooOld
    case missingReason
    case pendingExists
    case server(String)

    var message: String {
        switch self {
        case .missingTime: "Fill in check-in, check-out, or both."
        case .endBeforeStart: "The check-out time must be after the check-in time."
        case .futureDate: "The date cannot be in the future."
        case .tooOld: "Requests can only go back one month."
        case .missingReason: "Please explain what happened"
        case .pendingExists: "You already have a pending adjustment request for this date."
        case .server(let message): message
        }
    }
}

enum ClockingPendingAction: Hashable, Sendable {
    case openCamera
    case chooseWorkplace
    case detectLocation
}
