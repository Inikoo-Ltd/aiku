import SwiftUI

struct ClockingTimesheetsView: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @Environment(Session.self) private var session
    @State private var store = ClockingStore.shared
    @State private var weekIndex = 0
    @State private var isAdjusting = false

    var body: some View {
        let weeks = store.weeks
        let week = weeks[min(weekIndex, weeks.count - 1)]
        AppScreen(title: "Timesheets", kicker: "Clocking & HR · \(session.profile.organisation)") {
            ClockingSectionTabs(current: .timesheets) { isAdjusting = true }
        } content: {
            weekSwitcher(week, count: weeks.count)
            summary(week)
            if week.days.isEmpty {
                EmptyState(icon: .clock, title: "No clockings this week", message: "Clock in to start this week’s timesheet.")
            } else {
                CardList(week.days) { day in
                    dayRow(day)
                }
            }
            Text("Tap a day for its time trackers and clockings. To correct a clocking, request a clocking adjustment.")
                .font(.aiku(12))
                .foregroundStyle(AikuColor.text3)
                .padding(.horizontal, 4)
        }
        .aikuSheet(isPresented: $isAdjusting) {
            ClockingAdjustmentFlow(day: store.adjustmentDay) { didRequest in
                isAdjusting = false
                if didRequest {
                    toasts.show("Adjustment requested · your manager will check it")
                }
            }
        }
    }

    private func weekSwitcher(_ week: ClockingWeek, count: Int) -> some View {
        HStack(spacing: 8) {
            Button {
                weekIndex = min(count - 1, weekIndex + 1)
            } label: {
                AppIcon.back.image
                    .font(.system(size: 18, weight: .semibold))
                    .foregroundStyle(weekIndex < count - 1 ? AikuColor.text2 : AikuColor.faint)
                    .frame(width: 44, height: 44)
                    .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .disabled(weekIndex >= count - 1)
            .accessibilityLabel("Previous week")
            VStack(spacing: 0) {
                Text(week.label)
                    .font(.aiku(15, .bold))
                    .foregroundStyle(AikuColor.text)
                Text(week.range)
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text3)
            }
            .frame(maxWidth: .infinity)
            .accessibilityElement(children: .combine)
            Button {
                weekIndex = max(0, weekIndex - 1)
            } label: {
                AppIcon.chevronRight.image
                    .font(.system(size: 18, weight: .semibold))
                    .foregroundStyle(weekIndex == 0 ? AikuColor.faint : AikuColor.text2)
                    .frame(width: 44, height: 44)
                    .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .disabled(weekIndex == 0)
            .accessibilityLabel("Next week")
        }
        .padding(4)
        .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 10))
        .overlay(RoundedRectangle(cornerRadius: 10).strokeBorder(AikuColor.border))
    }

    private func summary(_ week: ClockingWeek) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack(alignment: .firstTextBaseline) {
                Text("Working time")
                Spacer()
                Text("Target \(ClockingFormat.duration(ClockingWeek.targetSeconds))")
            }
            .font(.aiku(13))
            .foregroundStyle(AikuColor.text3)
            Text(ClockingFormat.duration(week.working(now: store.now)))
                .font(.aiku(30, .bold))
                .monospacedDigit()
                .foregroundStyle(AikuColor.text)
            HStack(alignment: .bottom, spacing: 6) {
                ForEach(ClockingWeek.weekdays, id: \.self) { weekday in
                    bar(weekday: weekday, day: week.day(on: weekday))
                }
            }
            .frame(height: 72)
            .accessibilityElement(children: .ignore)
            .accessibilityLabel("Hours per day")
            HStack(spacing: 16) {
                (Text("Breaks ") + Text(week.breaks > 0 ? ClockingFormat.duration(week.breaks) : "—").bold().foregroundColor(AikuColor.text))
                (Text("Days ") + Text(week.daysLabel).bold().foregroundColor(AikuColor.text))
            }
            .font(.aiku(12))
            .foregroundStyle(AikuColor.text3)
        }
        .padding(16)
        .cardSurface()
    }

    private func bar(weekday: String, day: ClockingDay?) -> some View {
        let hours = Double(day?.working(now: store.now) ?? 0) / 3600
        let fraction = min(hours / 9, 1)
        return VStack(spacing: 4) {
            Spacer(minLength: 0)
            UnevenRoundedRectangle(topLeadingRadius: 4, topTrailingRadius: 4)
                .fill(barColor(day))
                .frame(height: max(3, 52 * fraction))
            Text(String(weekday.prefix(1)))
                .font(.aiku(11))
                .foregroundStyle(AikuColor.text3)
        }
        .frame(maxWidth: .infinity)
    }

    private func barColor(_ day: ClockingDay?) -> Color {
        guard let day else { return AikuColor.divider }
        switch day.status {
        case .error: return AikuColor.danger
        case .open: return AikuColor.primary
        case .ok: return AikuColor.success
        }
    }

    private func dayRow(_ day: ClockingDay) -> some View {
        Button {
            router.open(ClockingRoute.day(date: day.date))
        } label: {
            HStack(spacing: 12) {
                VStack(spacing: 0) {
                    Text(day.weekday.uppercased())
                        .font(.aiku(11, .bold))
                        .foregroundStyle(AikuColor.text3)
                    Text(day.dayOfMonth)
                        .font(.aiku(18, .bold))
                        .foregroundStyle(AikuColor.text)
                }
                .frame(width: 44)
                VStack(alignment: .leading, spacing: 2) {
                    Text(day.rangeLabel)
                        .font(.aiku(14, .semibold))
                        .monospacedDigit()
                        .foregroundStyle(AikuColor.text)
                    Text(day.summary)
                        .font(.aiku(12))
                        .foregroundStyle(summaryColor(day))
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                Text(ClockingFormat.duration(day.working(now: store.now)))
                    .font(.aiku(15, .bold))
                    .monospacedDigit()
                    .foregroundStyle(AikuColor.text)
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 12)
            .frame(minHeight: 44)
            .background(day.status == .error ? AikuColor.dangerSoft : AikuColor.surface)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityIdentifier("clocking.day.\(day.date)")
    }

    private func summaryColor(_ day: ClockingDay) -> Color {
        switch day.status {
        case .error: AikuColor.danger
        case .open: AikuColor.link
        case .ok: AikuColor.text3
        }
    }
}
