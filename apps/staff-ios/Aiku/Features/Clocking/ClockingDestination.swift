import SwiftUI

struct ClockingDestination: View {
    let route: ClockingRoute

    var body: some View {
        switch route {
        case .clocking: ClockingView()
        case .timesheets: ClockingTimesheetsView()
        case .day(let date): ClockingDayView(date: date)
        case .qrInvalid: ClockingQrInvalidView()
        case .locationBlocked: ClockingLocationBlockedView()
        case .noPin: ClockingNoPinView()
        case .noConnection: ClockingNoConnectionView()
        }
    }
}
