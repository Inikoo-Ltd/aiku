import SwiftUI

struct ClockingOvertimeView: View {
    @Environment(ToastCenter.self) private var toasts
    @Environment(Session.self) private var session
    @State private var store = OvertimeStore.shared
    @State private var isAdjusting = false
    @State private var form: ClockingOvertimeFormMode?

    var body: some View {
        AppScreen(title: "Overtime", kicker: "Clocking & HR · \(ClockingStore.shared.organisationName ?? session.profile.organisation)") {
            ClockingSectionTabs(current: .overtime) { isAdjusting = true }
        } content: {
            if let error = store.loadError {
                InlineAlert(tone: .danger, title: "Couldn’t load overtime", message: error) {
                    LinkButton("Try again", size: 13) { Task { await store.load() } }
                }
            }
            if !store.hasLoaded {
                SkeletonRow().cardSurface()
                SkeletonRow().cardSurface()
            } else {
                summaryCard
                AikuButton("New overtime request", icon: .plus) { form = .new }
                    .accessibilityIdentifier("overtime.request")
                if !store.pending.isEmpty {
                    SectionHeading("Waiting for approval", detail: "\(store.pending.count)")
                    CardList(store.pending) { request in
                        Button { form = .edit(request) } label: {
                            ClockingOvertimeRow(request: request, timeZone: store.timeZone, isEditable: true)
                        }
                        .buttonStyle(.plain)
                        .accessibilityHint("Edit this request")
                    }
                }
                if store.requests.isEmpty {
                    EmptyState(icon: .clock, title: "No overtime requests found", message: "Overtime you request shows here with its approval.")
                }
                if !store.history.isEmpty {
                    SectionHeading("History", detail: "\(store.history.count)")
                    CardList(store.history) { request in
                        ClockingOvertimeRow(request: request, timeZone: store.timeZone, isEditable: false)
                    }
                }
            }
        }
        .aikuSheet(item: $form) { mode in
            ClockingOvertimeRequestSheet(store: store, editing: mode.editing) { saved in
                form = nil
                if saved != nil {
                    toasts.show(mode.editing == nil ? "Overtime requested · your manager will check it" : "Overtime request updated")
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
        .task { await store.load() }
        .screenRefreshable { await store.load() }
    }

    private var summaryCard: some View {
        let summary = store.monthSummary
        return Card(padding: 14, spacing: 12) {
            Text("This month")
                .font(.aiku(13))
                .foregroundStyle(AikuColor.text3)
            HStack(spacing: 8) {
                stat(OvertimeCalculator.durationLabel(summary.approvedMinutes), "Approved")
                stat(OvertimeCalculator.durationLabel(summary.pendingMinutes), "Waiting")
                stat(OvertimeCalculator.durationLabel(summary.lieuMinutes), "Lieu requested")
            }
        }
    }

    private func stat(_ value: String, _ label: String) -> some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(value).font(.aiku(20, .bold)).foregroundStyle(AikuColor.text)
                .lineLimit(1)
                .minimumScaleFactor(0.7)
            Text(label).font(.aiku(12)).foregroundStyle(AikuColor.text3)
                .lineLimit(2)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
    }
}

enum ClockingOvertimeFormMode: Identifiable {
    case new
    case edit(OvertimeRequestResponse)

    var id: String {
        switch self {
        case .new: "new"
        case .edit(let request): "edit-\(request.id)"
        }
    }

    var editing: OvertimeRequestResponse? {
        if case .edit(let request) = self { return request }
        return nil
    }
}

struct ClockingOvertimeRow: View {
    let request: OvertimeRequestResponse
    let timeZone: TimeZone
    let isEditable: Bool

    private var status: OvertimeStatus? { request.status.flatMap(OvertimeStatus.init(rawValue:)) }

    private var requestedLine: String {
        let range = OvertimeCalculator.timeRange(start: request.requestedStartAt, end: request.requestedEndAt, duration: request.requestedDurationMinutes, in: timeZone)
        let duration = OvertimeCalculator.durationLabel(request.requestedDurationMinutes)
        return [LeaveCalculator.rangeLabel(start: request.requestedDate, end: nil), range, duration].compactMap { $0 }.joined(separator: " · ")
    }

    private var recordedLine: String? {
        guard request.recordedStartAt != request.requestedStartAt || request.recordedDurationMinutes != request.requestedDurationMinutes,
              request.recordedStartAt != nil || request.recordedDurationMinutes != nil else { return nil }
        let range = OvertimeCalculator.timeRange(start: request.recordedStartAt, end: request.recordedEndAt, duration: request.recordedDurationMinutes, in: timeZone)
        return "Recorded " + [range, OvertimeCalculator.durationLabel(request.recordedDurationMinutes)].compactMap { $0 }.joined(separator: " · ")
    }

    var body: some View {
        HStack(alignment: .center, spacing: 8) {
            VStack(alignment: .leading, spacing: 4) {
                HStack(alignment: .firstTextBaseline, spacing: 8) {
                    Text(request.overtimeTypeName ?? "Overtime")
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                    Spacer(minLength: 8)
                    StatusChip(request.statusLabel ?? request.status?.capitalized ?? "", tone: status?.tone ?? .neutral)
                }
                Text(requestedLine)
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
                if let recordedLine {
                    Text(recordedLine)
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                }
                if let lieu = request.lieuRequestedMinutes, lieu > 0 {
                    Text("Lieu requested \(OvertimeCalculator.durationLabel(lieu))")
                        .font(.aiku(12))
                        .foregroundStyle(AikuColor.text4)
                }
                if let reason = request.reason, !reason.isEmpty {
                    Text(reason)
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text2)
                        .multilineTextAlignment(.leading)
                }
            }
            if isEditable {
                AppIcon.edit.image
                    .font(.aiku(14, .semibold))
                    .foregroundStyle(AikuColor.link)
                    .frame(width: 24)
                    .accessibilityHidden(true)
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .frame(maxWidth: .infinity, minHeight: 44, alignment: .leading)
        .contentShape(Rectangle())
        .accessibilityElement(children: .combine)
    }
}
