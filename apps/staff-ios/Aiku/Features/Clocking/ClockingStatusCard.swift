import SwiftUI

struct ClockingStatusCard: View {
    let day: ClockingDay
    let now: Int
    let since: String
    @Binding var isShowingDetails: Bool

    var body: some View {
        VStack(spacing: 0) {
            HStack(spacing: 12) {
                (day.isClockedIn ? AppIcon.check : AppIcon.close).image
                    .font(.system(size: 19, weight: .semibold))
                    .foregroundStyle(statusTone.foreground)
                    .frame(width: 44, height: 44)
                    .background(statusTone.background, in: Circle())
                    .accessibilityHidden(true)
                VStack(alignment: .leading, spacing: 2) {
                    Text(day.isClockedIn ? "Clocked in" : "Clocked out")
                        .font(.aiku(17, .bold))
                        .foregroundStyle(statusTone.foreground)
                    Text(since)
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                        .monospacedDigit()
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                VStack(alignment: .trailing, spacing: 2) {
                    Text("Date")
                        .font(.aiku(11))
                        .foregroundStyle(AikuColor.text3)
                    Text(day.title)
                        .font(.aiku(13, .semibold))
                        .foregroundStyle(AikuColor.text)
                        .lineLimit(1)
                        .minimumScaleFactor(0.8)
                }
            }
            .padding(16)
            .accessibilityElement(children: .combine)
            Button {
                withAnimation(.easeInOut(duration: 0.2)) {
                    isShowingDetails.toggle()
                }
            } label: {
                HStack {
                    Text("Clocking details (\(day.trackers.count))")
                        .font(.aiku(14, .semibold))
                    Spacer()
                    AppIcon.chevronDown.image
                        .font(.system(size: 14, weight: .semibold))
                        .rotationEffect(.degrees(isShowingDetails ? 180 : 0))
                        .accessibilityHidden(true)
                }
                .foregroundStyle(AikuColor.text2)
                .padding(.horizontal, 16)
                .frame(maxWidth: .infinity, minHeight: 48)
                .background(AikuColor.surface2)
                .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .overlay(alignment: .top) { RowDivider() }
            .accessibilityValue(isShowingDetails ? "Expanded" : "Collapsed")
            .accessibilityIdentifier("clocking.details")
            if isShowingDetails {
                details
                    .overlay(alignment: .top) { RowDivider() }
            }
        }
        .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 16))
        .clipShape(RoundedRectangle(cornerRadius: 16))
        .overlay(RoundedRectangle(cornerRadius: 16).strokeBorder(AikuColor.border))
    }

    private var statusTone: Tone { day.isClockedIn ? .success : .danger }

    private var details: some View {
        VStack(spacing: 12) {
            LazyVGrid(columns: [GridItem(.flexible(), spacing: 8), GridItem(.flexible(), spacing: 8)], spacing: 8) {
                stat("First clock in", day.firstIn.map(ClockingFormat.time) ?? "—", monospaced: true)
                stat("Last clock out", day.lastOut.map(ClockingFormat.time) ?? "—", monospaced: true)
                stat("Working", ClockingFormat.duration(day.working(now: now)))
                stat("Breaks", day.breaks > 0 ? ClockingFormat.duration(day.breaks) : "—")
            }
            ForEach(Array(day.trackers.enumerated()), id: \.offset) { _, tracker in
                trackerRow(tracker)
            }
        }
        .padding(.horizontal, 16)
        .padding(.top, 14)
        .padding(.bottom, 16)
    }

    private func stat(_ label: String, _ value: String, monospaced: Bool = false) -> some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(label)
                .font(.aiku(11))
                .foregroundStyle(AikuColor.text3)
            Text(value)
                .font(.aiku(15, .bold))
                .foregroundStyle(AikuColor.text)
                .monospacedDigit()
                .lineLimit(1)
                .minimumScaleFactor(0.8)
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(AikuColor.bg, in: RoundedRectangle(cornerRadius: 10))
        .accessibilityElement(children: .combine)
    }

    private func trackerRow(_ tracker: ClockingTracker) -> some View {
        HStack(spacing: 12) {
            HStack(spacing: 8) {
                timeColumn("Clock in", ClockingFormat.time(tracker.inAt))
                timeColumn("Clock out", tracker.outAt.map(ClockingFormat.time) ?? "—")
            }
            Text(tracker.isOpen ? "Ongoing" : "Duration \(ClockingFormat.duration(tracker.duration(now: nil)))")
                .font(.aiku(12, .semibold))
                .foregroundStyle(tracker.isOpen ? AikuColor.ink : AikuColor.success)
                .multilineTextAlignment(.trailing)
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .overlay(RoundedRectangle(cornerRadius: 10).strokeBorder(AikuColor.border))
        .accessibilityElement(children: .combine)
    }

    private func timeColumn(_ label: String, _ value: String) -> some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(label)
                .font(.aiku(11))
                .foregroundStyle(AikuColor.text3)
            Text(value)
                .font(.aiku(14, .bold))
                .foregroundStyle(AikuColor.text)
                .monospacedDigit()
        }
        .frame(maxWidth: .infinity, alignment: .leading)
    }
}
