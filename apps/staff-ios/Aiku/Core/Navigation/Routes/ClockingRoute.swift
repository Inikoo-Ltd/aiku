import Foundation

enum ClockingRoute: Hashable, Sendable {
    case clocking
    case timesheets
    case leave
    case overtime
    case calendar
    case day(date: String)
    case qrInvalid
    case locationBlocked
    case noPin
    case noConnection
}
