import SwiftUI

struct ClockingCalendarView: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @Environment(Session.self) private var session
    @State private var store = CalendarStore.shared
    @State private var isAdjusting = false

    var body: some View {
        AppScreen(title: "Calendar", kicker: "Clocking & HR · \(ClockingStore.shared.organisationName ?? session.profile.organisation)") {
            ClockingSectionTabs(current: .calendar) { isAdjusting = true }
        } content: {
            if let error = store.loadError {
                InlineAlert(tone: .danger, title: "Couldn’t load the calendar", message: error) {
                    LinkButton("Try again", size: 13) { Task { await store.load() } }
                }
            }
            ClockingCalendarGrid(
                store: store,
                onPrevious: { Task { await store.showPreviousMonth() } },
                onNext: { Task { await store.showNextMonth() } },
                onToday: { Task { await store.showCurrentMonth() } }
            )
            ClockingCalendarLegend(leaves: store.legendLeaves)
            if !store.hasLoaded {
                SkeletonRow().cardSurface()
            } else {
                if let summary = store.selectedSummary {
                    dayCard(summary)
                }
                holidaysSection
                leaveSection
                if let holidayYear = store.holidayYearLabel {
                    HelperText("Holiday year: \(holidayYear)")
                }
            }
        }
        .aikuSheet(isPresented: $isAdjusting) {
            ClockingAdjustmentFlow { didRequest in
                isAdjusting = false
                if didRequest {
                    toasts.show("Adjustment requested · your manager will check it")
                }
            }
        }
        .task {
            await store.load()
            if !ClockingStore.shared.hasLoaded {
                await ClockingStore.shared.load()
            }
        }
        .screenRefreshable { await store.load() }
    }

    private func dayCard(_ summary: CalendarDaySummary) -> some View {
        Card(padding: 14, spacing: 10) {
            HStack(alignment: .firstTextBaseline, spacing: 8) {
                Text(summary.title)
                    .font(.aiku(15, .bold))
                    .foregroundStyle(AikuColor.text)
                    .lineLimit(2)
                    .minimumScaleFactor(0.85)
                Spacer(minLength: 8)
                if summary.isToday {
                    StatusChip("Today", tone: .info)
                } else if summary.isWeekend {
                    StatusChip("Weekend")
                }
            }
            if let holiday = summary.holiday {
                detailLine(.holiday, title: holiday, detail: "Public holiday")
            }
            ForEach(summary.leaves) { leave in
                HStack(alignment: .top, spacing: 10) {
                    ClockingCalendarMarkerDot(marker: .leave(colorHex: leave.typeColorHex, isPending: CalendarText.isPending(leave)), size: 10)
                        .padding(.top, 5)
                    VStack(alignment: .leading, spacing: 2) {
                        HStack(alignment: .firstTextBaseline, spacing: 8) {
                            Text(leave.typeLabel ?? leave.type ?? "Leave")
                                .font(.aiku(14, .semibold))
                                .foregroundStyle(AikuColor.text)
                            Spacer(minLength: 8)
                            StatusChip(leave.statusLabel ?? leave.status?.capitalized ?? "", tone: leave.status.flatMap(LeaveStatus.init(rawValue:))?.tone ?? .neutral)
                        }
                        Text(CalendarText.leaveDetail(leave))
                            .font(.aiku(13))
                            .foregroundStyle(AikuColor.text3)
                    }
                }
            }
            if let worked = summary.workedLabel {
                detailLine(.worked, title: worked, detail: summary.timesheet?.hasPendingAdjustment == true ? "Clocking adjustment waiting for approval" : nil)
            }
            if summary.markers.isEmpty {
                Text(emptyText(summary))
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            }
            if summary.hasWorked, ClockingStore.shared.recordedDay(on: summary.date) != nil {
                AikuButton("Open timesheet", icon: .clock, kind: .secondary, size: .small) {
                    router.open(ClockingRoute.day(date: summary.date))
                }
                .accessibilityIdentifier("calendar.openTimesheet")
            }
        }
    }

    private func detailLine(_ marker: CalendarMarker, title: String, detail: String?) -> some View {
        HStack(alignment: .top, spacing: 10) {
            ClockingCalendarMarkerDot(marker: marker, size: 10)
                .padding(.top, 5)
            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(.aiku(14, .semibold))
                    .foregroundStyle(AikuColor.text)
                if let detail {
                    Text(detail)
                        .font(.aiku(13))
                        .foregroundStyle(marker == .worked ? AikuColor.warnInk : AikuColor.text3)
                }
            }
            Spacer(minLength: 0)
        }
        .accessibilityElement(children: .combine)
    }

    private func emptyText(_ summary: CalendarDaySummary) -> String {
        if summary.isFuture { return "Nothing planned for this day" }
        return summary.isWeekend ? "Weekend · no clockings" : "No clockings or leave on this day"
    }

    @ViewBuilder
    private var holidaysSection: some View {
        let holidays = store.monthHolidays
        if !holidays.isEmpty {
            SectionHeading("Public holidays", detail: "\(holidays.count)")
            Card {
                ForEach(Array(holidays.enumerated()), id: \.offset) { index, holiday in
                    if index > 0 { RowDivider() }
                    KeyValueRow(holiday.dates, holiday.label, valueTone: .danger)
                }
            }
        }
    }

    @ViewBuilder
    private var leaveSection: some View {
        let leaves = store.monthLeaves
        if !leaves.isEmpty {
            SectionHeading("Leave this month", detail: "\(leaves.count)")
            CardList(leaves) { leave in
                Button {
                    if let start = leave.startDate {
                        store.select(max(start, store.month.firstDay))
                    }
                } label: {
                    HStack(spacing: 10) {
                        ClockingCalendarMarkerDot(marker: .leave(colorHex: leave.typeColorHex, isPending: CalendarText.isPending(leave)), size: 10)
                        VStack(alignment: .leading, spacing: 2) {
                            Text(leave.typeLabel ?? leave.type ?? "Leave")
                                .font(.aiku(15, .bold))
                                .foregroundStyle(AikuColor.text)
                            Text(CalendarText.leaveDetail(leave))
                                .font(.aiku(13))
                                .foregroundStyle(AikuColor.text3)
                        }
                        Spacer(minLength: 8)
                        StatusChip(leave.statusLabel ?? leave.status?.capitalized ?? "", tone: leave.status.flatMap(LeaveStatus.init(rawValue:))?.tone ?? .neutral)
                    }
                    .padding(.horizontal, 14)
                    .padding(.vertical, 12)
                    .frame(maxWidth: .infinity, minHeight: 44, alignment: .leading)
                    .contentShape(Rectangle())
                }
                .buttonStyle(.plain)
                .accessibilityElement(children: .combine)
            }
        }
    }
}
