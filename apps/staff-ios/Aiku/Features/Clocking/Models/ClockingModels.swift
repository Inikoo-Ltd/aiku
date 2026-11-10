import Foundation

enum ClockingMethod: String, CaseIterable, Hashable, Sendable {
    case qrCode
    case pin
    case barcode
    case cameraQr

    var title: String {
        switch self {
        case .qrCode: "QR Code"
        case .pin: "PIN"
        case .barcode: "Barcode"
        case .cameraQr: "Camera QR"
        }
    }
}

enum ClockingEventKind: Hashable, Sendable {
    case clockIn
    case clockOut

    var label: String {
        switch self {
        case .clockIn: "In"
        case .clockOut: "Out"
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

    init(time: Int, kind: ClockingEventKind, viaMobileApp: Bool = true, place: String = "Main Warehouse", note: String? = nil) {
        self.id = UUID()
        self.time = time
        self.kind = kind
        self.viaMobileApp = viaMobileApp
        self.place = place
        self.note = note
    }

    var detail: String {
        "\(kind.label) · \(viaMobileApp ? "Mobile app" : "Clocking machine") · \(place)"
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

    var id: String { date }
    var title: String { "\(weekday) \(dayOfMonth) \(monthYear)" }
    var shortTitle: String { "\(weekday) \(dayOfMonth) \(monthYear.split(separator: " ").first.map(String.init) ?? "")" }

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

    var rangeLabel: String {
        guard let firstIn else { return "—" }
        let end: String
        if let lastOut {
            end = ClockingFormat.shortTime(lastOut)
        } else {
            end = isToday ? "now" : "—"
        }
        return "\(ClockingFormat.shortTime(firstIn)) → \(end)"
    }

    var summary: String {
        let clockings = clockingCount == 1 ? "1 clocking" : "\(clockingCount) clockings"
        switch status {
        case .open: return "Open · \(clockings)"
        case .error: return "Error · no clock out"
        case .ok:
            if let note { return "\(clockings) · \(note)" }
            return "\(clockings) · break \(breaks > 0 ? ClockingFormat.duration(breaks) : "—")"
        }
    }
}

struct ClockingWeek: Identifiable, Hashable, Sendable {
    let label: String
    let range: String
    var days: [ClockingDay]

    static let weekdays = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"]
    static let workingDays = 5
    static let targetSeconds = ClockingFormat.seconds(37, 30)

    var id: String { label }

    func working(now: Int?) -> Int {
        days.reduce(0) { $0 + $1.working(now: now) }
    }

    var breaks: Int { days.reduce(0) { $0 + $1.breaks } }

    var daysLabel: String { "\(days.count) of \(Self.workingDays)" }

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
    case offline
}

struct ClockingRejection: Hashable, Sendable {
    let message: String
    let scanned: String
}

struct ClockingShift: Identifiable, Hashable, Sendable {
    let name: String
    let hours: String

    var id: String { name }
}

enum ClockingSchedule: Hashable, Sendable {
    case standard
    case shift(String)
}

struct ClockingAdjustmentRequest: Identifiable, Hashable, Sendable {
    let id: UUID
    let date: String
    let kind: ClockingEventKind
    let time: Int
    let reason: String

    init(date: String, kind: ClockingEventKind, time: Int, reason: String) {
        self.id = UUID()
        self.date = date
        self.kind = kind
        self.time = time
        self.reason = reason
    }

    var summary: String {
        "\(kind == .clockIn ? "Clock in" : "Clock out") \(ClockingFormat.shortTime(time)) · \(reason)"
    }
}

enum ClockingAdjustmentError: Error, Hashable, Sendable {
    case invalidTime
    case missingReason

    var message: String {
        switch self {
        case .invalidTime: "Use the 24-hour format, e.g. 16:30"
        case .missingReason: "Add a reason for your manager"
        }
    }
}

enum ClockingPendingAction: Hashable, Sendable {
    case openCamera
    case chooseWorkplace
    case detectLocation
}
