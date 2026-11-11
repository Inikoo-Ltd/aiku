import SwiftUI

enum ClockingSection: String, CaseIterable {
    case clock
    case timesheets
    case leave
    case adjustment
    case overtime
    case calendar

    var title: String {
        switch self {
        case .clock: "Clock in / out"
        case .timesheets: "Timesheets"
        case .leave: "Leave"
        case .adjustment: "Clocking adjustment"
        case .overtime: "Overtime"
        case .calendar: "Calendar"
        }
    }
}

struct ClockingSectionTabs: View {
    @Environment(AppRouter.self) private var router
    let current: ClockingSection
    let onAdjust: () -> Void

    var body: some View {
        PillTabs(ClockingSection.allCases.map { PillTab(id: $0.rawValue, title: $0.title) }, selectedID: current.rawValue) { tab in
            guard let section = ClockingSection(rawValue: tab.id), section != current else { return }
            switch section {
            case .clock: router.replace(with: ClockingRoute.clocking)
            case .timesheets: router.replace(with: ClockingRoute.timesheets)
            case .leave: router.replace(with: ClockingRoute.leave)
            case .adjustment: onAdjust()
            case .overtime: router.replace(with: ClockingRoute.overtime)
            case .calendar: router.replace(with: ClockingRoute.calendar)
            }
        }
        .accessibilityLabel("Clocking sections")
    }
}
