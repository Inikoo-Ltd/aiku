import SwiftUI

struct ClockingCalendarGrid: View {
    let store: CalendarStore
    let onPrevious: () -> Void
    let onNext: () -> Void
    let onToday: () -> Void

    private let columns = Array(repeating: GridItem(.flexible(), spacing: 4), count: 7)

    var body: some View {
        Card(padding: 12, spacing: 10) {
            HStack(spacing: 4) {
                IconButton(.back, label: "Previous month", action: onPrevious)
                    .accessibilityIdentifier("calendar.previous")
                Spacer(minLength: 4)
                VStack(spacing: 2) {
                    Text(store.month.title)
                        .font(.aiku(17, .bold))
                        .foregroundStyle(AikuColor.text)
                        .lineLimit(1)
                        .minimumScaleFactor(0.8)
                    if store.isShowingCurrentMonth {
                        Text("This month")
                            .font(.aiku(12))
                            .foregroundStyle(AikuColor.text4)
                    } else {
                        LinkButton("Back to this month", size: 12, action: onToday)
                            .accessibilityIdentifier("calendar.today")
                    }
                }
                Spacer(minLength: 4)
                IconButton(.chevronRight, label: "Next month", action: onNext)
                    .accessibilityIdentifier("calendar.next")
            }
            LazyVGrid(columns: columns, spacing: 4) {
                ForEach(Array(CalendarGrid.weekdayLabels.enumerated()), id: \.offset) { index, label in
                    Text(label)
                        .font(.aiku(11, .semibold))
                        .foregroundStyle(index >= 5 ? AikuColor.text4 : AikuColor.text3)
                        .frame(maxWidth: .infinity, minHeight: 20)
                        .accessibilityHidden(true)
                }
                ForEach(store.grid) { day in
                    if day.isInMonth {
                        ClockingCalendarDayCell(day: day, summary: store.summary(for: day.date), isSelected: store.selectedDate == day.date) {
                            store.select(day.date)
                        }
                    } else {
                        Text("\(day.dayOfMonth)")
                            .font(.aiku(13))
                            .foregroundStyle(AikuColor.faint)
                            .frame(maxWidth: .infinity, minHeight: 46)
                            .accessibilityHidden(true)
                    }
                }
            }
            .opacity(store.isLoading && store.loadedMonth != store.month ? 0.55 : 1)
            .animation(.easeInOut(duration: 0.2), value: store.isLoading)
        }
    }
}

private struct ClockingCalendarDayCell: View {
    let day: CalendarGridDay
    let summary: CalendarDaySummary
    let isSelected: Bool
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            VStack(spacing: 4) {
                Text("\(day.dayOfMonth)")
                    .font(.aiku(14, summary.isToday ? .bold : .medium))
                    .foregroundStyle(numberColour)
                    .frame(width: 28, height: 28)
                    .background(Circle().fill(summary.isToday ? AikuColor.primary : .clear))
                HStack(spacing: 3) {
                    ForEach(Array(summary.markers.enumerated()), id: \.offset) { _, marker in
                        ClockingCalendarMarkerDot(marker: marker)
                    }
                }
                .frame(height: 6)
            }
            .frame(maxWidth: .infinity, minHeight: 46)
            .background(RoundedRectangle(cornerRadius: 8).fill(backgroundColour))
            .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(isSelected ? AikuColor.primary : .clear, lineWidth: 2))
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityLabel(accessibilityText)
        .accessibilityAddTraits(isSelected ? .isSelected : [])
    }

    private var numberColour: Color {
        if summary.isToday { return AikuColor.onPrimary }
        if summary.isHoliday { return AikuColor.dangerInk }
        return summary.isWeekend ? AikuColor.text3 : AikuColor.text
    }

    private var backgroundColour: Color {
        if summary.isHoliday { return AikuColor.dangerSoft }
        if isSelected { return AikuColor.soft }
        return summary.isWeekend ? AikuColor.surface2 : .clear
    }

    private var accessibilityText: String {
        var parts = [summary.title]
        if summary.isToday { parts.append("Today") }
        if let holiday = summary.holiday { parts.append(holiday) }
        parts += summary.leaves.map { "\($0.typeLabel ?? "Leave"), \($0.statusLabel ?? "")" }
        if let worked = summary.workedLabel { parts.append(worked) }
        return parts.joined(separator: ", ")
    }
}

struct ClockingCalendarMarkerDot: View {
    let marker: CalendarMarker
    var size: CGFloat = 6

    var body: some View {
        switch marker {
        case .leave(let hex, let isPending) where isPending:
            Circle()
                .strokeBorder(AikuColor.warn, lineWidth: 1.5)
                .background(Circle().fill(CalendarText.hexValue(hex).map { Color(hex: $0) } ?? AikuColor.warnSoft))
                .frame(width: size + 2, height: size + 2)
        case .leave(let hex, _):
            Circle()
                .fill(CalendarText.hexValue(hex).map { Color(hex: $0) } ?? marker.tone.foreground)
                .frame(width: size, height: size)
        default:
            Circle()
                .fill(marker.tone.foreground)
                .frame(width: size, height: size)
        }
    }
}

struct ClockingCalendarLegend: View {
    let leaves: [CalendarLegendLeave]

    var body: some View {
        FlowLayout(spacing: 12, lineSpacing: 8) {
            item(.worked, "Worked")
            ForEach(leaves, id: \.self) { leave in
                item(.leave(colorHex: leave.colorHex, isPending: false), leave.label)
            }
            if leaves.isEmpty {
                item(.leave(colorHex: nil, isPending: false), "Leave")
            }
            item(.leave(colorHex: nil, isPending: true), "Waiting for approval")
            item(.holiday, "Public holiday")
            HStack(spacing: 6) {
                RoundedRectangle(cornerRadius: 3)
                    .fill(AikuColor.surface2)
                    .overlay(RoundedRectangle(cornerRadius: 3).strokeBorder(AikuColor.border))
                    .frame(width: 12, height: 12)
                legendText("Weekend")
            }
            HStack(spacing: 6) {
                Circle().fill(AikuColor.primary).frame(width: 12, height: 12)
                legendText("Today")
            }
        }
        .accessibilityElement(children: .combine)
    }

    private func item(_ marker: CalendarMarker, _ label: String) -> some View {
        HStack(spacing: 6) {
            ClockingCalendarMarkerDot(marker: marker, size: 8)
            legendText(label)
        }
    }

    private func legendText(_ label: String) -> some View {
        Text(label)
            .font(.aiku(12))
            .foregroundStyle(AikuColor.text3)
            .lineLimit(1)
    }
}
