import SwiftUI

struct ClockingDayView: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @Environment(Session.self) private var session
    @State private var store = ClockingStore.shared
    @State private var isAdjusting = false
    let date: String

    var body: some View {
        let day = store.day(for: date)
        AppScreen(title: day.title, kicker: "Timesheet · \(session.profile.name)", spacing: 16) {
            HStack(spacing: 8) {
                stat(ClockingFormat.duration(day.working(now: store.now)), label: "Working")
                stat(day.breaks > 0 ? ClockingFormat.duration(day.breaks) : "—", label: "Breaks")
                stat("\(day.clockingCount)", label: "Clockings")
            }
            ForEach(store.adjustments(for: day.date)) { request in
                InlineAlert(tone: request.tone, icon: .bell, title: "Clocking adjustment · \(request.statusLabel ?? request.status ?? "")", message: request.summary)
            }
            SheetSectionLabel("Time trackers")
            Card {
                ForEach(Array(day.trackers.enumerated()), id: \.offset) { index, tracker in
                    if index > 0 {
                        RowDivider()
                    }
                    trackerRow(tracker, isToday: day.isToday)
                }
            }
            SheetSectionLabel("Clockings")
            CardList(day.events.sorted { $0.time < $1.time }) { event in
                eventRow(event)
            }
            Card {
                KeyValueRow("Workplace", day.workplace)
                KeyValueRow("Shift", day.shift)
            }
        } footer: {
            AikuButton("Request clocking adjustment", icon: .bell, kind: .secondary) {
                isAdjusting = true
            }
            .accessibilityIdentifier("clocking.day.adjust")
            AikuButton("Back to history", kind: .tertiary, size: .small) {
                router.back()
            }
        }
        .aikuSheet(isPresented: $isAdjusting) {
            ClockingAdjustmentFlow(day: day) { didRequest in
                isAdjusting = false
                if didRequest {
                    toasts.show("Adjustment requested · your manager will check it")
                }
            }
        }
        .task { if !store.hasLoaded { await store.load() } }
    }

    private func stat(_ value: String, label: String) -> some View {
        VStack(spacing: 2) {
            Text(value)
                .font(.aiku(18, .bold))
                .foregroundStyle(AikuColor.text)
                .lineLimit(1)
                .minimumScaleFactor(0.7)
            Text(label)
                .font(.aiku(11))
                .foregroundStyle(AikuColor.text3)
        }
        .padding(.horizontal, 4)
        .padding(.vertical, 10)
        .frame(maxWidth: .infinity)
        .cardSurface(radius: 10)
        .accessibilityElement(children: .combine)
    }

    private func trackerRow(_ tracker: ClockingTracker, isToday: Bool) -> some View {
        let chip: Chip = tracker.isOpen ? (isToday ? Chip("Ongoing", .info) : Chip("No clock out", .danger)) : Chip("Closed", .success)
        let outLabel = tracker.outAt.map(ClockingFormat.shortTime) ?? "—"
        return HStack(spacing: 12) {
            VStack(alignment: .leading, spacing: 2) {
                Text("\(ClockingFormat.shortTime(tracker.inAt)) → \(outLabel)")
                    .font(.aiku(15, .bold))
                    .monospacedDigit()
                    .foregroundStyle(AikuColor.text)
                Text(tracker.isOpen ? "Clock in → no clock out" : "Clock in → clock out")
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            VStack(alignment: .trailing, spacing: 4) {
                Text(tracker.isOpen && !isToday ? "—" : ClockingFormat.duration(tracker.duration(now: isToday ? store.now : nil)))
                    .font(.aiku(14, .bold))
                    .foregroundStyle(AikuColor.text)
                StatusChip(chip)
            }
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 12)
        .accessibilityElement(children: .combine)
    }

    private func eventRow(_ event: ClockingEvent) -> some View {
        HStack(spacing: 12) {
            Group {
                if event.viaMobileApp {
                    AppIcon.camera.image.font(.system(size: 16))
                } else {
                    Text("—").font(.aiku(11))
                }
            }
            .foregroundStyle(AikuColor.text3)
            .frame(width: 40, height: 40)
            .background(AikuColor.surface2, in: Circle())
            .overlay(Circle().strokeBorder(AikuColor.border))
            .accessibilityHidden(true)
            VStack(alignment: .leading, spacing: 2) {
                Text(ClockingFormat.shortTime(event.time))
                    .font(.aiku(15, .bold))
                    .monospacedDigit()
                    .foregroundStyle(AikuColor.text)
                Text(event.detail)
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text3)
                if let note = event.note {
                    Text(note)
                        .font(.aiku(12))
                        .foregroundStyle(AikuColor.text2)
                }
            }
            .frame(maxWidth: .infinity, alignment: .leading)
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 10)
        .accessibilityElement(children: .combine)
    }
}
