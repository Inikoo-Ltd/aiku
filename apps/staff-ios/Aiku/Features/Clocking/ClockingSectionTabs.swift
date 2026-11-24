import SwiftUI

enum ClockingSection: String, CaseIterable {
    case clock
    case timesheets
    case leave
    case adjustment
    case overtime
    case calendar

    @MainActor
    var title: String {
        switch self {
        case .clock: L10n.text("Clock in / out")
        case .timesheets: L10n.text("Timesheets")
        case .leave: L10n.text("Leave")
        case .adjustment: L10n.text("Clocking adjustment")
        case .overtime: L10n.text("Overtime")
        case .calendar: L10n.text("Calendar")
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
            case .adjustment: router.replace(with: ClockingRoute.adjustments)
            case .overtime: router.replace(with: ClockingRoute.overtime)
            case .calendar: router.replace(with: ClockingRoute.calendar)
            }
        }
        .accessibilityLabel("Clocking sections")
    }
}
