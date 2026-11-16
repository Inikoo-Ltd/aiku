import SwiftUI

struct ClockingAdjustmentsView: View {
    @Environment(ToastCenter.self) private var toasts
    @Environment(Session.self) private var session
    @State private var store = ClockingStore.shared
    @State private var isRequesting = false
    @State private var cancelling: ClockingAdjustmentResponse?
    @State private var busyID: Int?

    var body: some View {
        AppScreen(title: "Clocking adjustment", kicker: "Clocking & HR · \(store.organisationName ?? session.profile.organisation)") {
            ClockingSectionTabs(current: .adjustment) {}
        } content: {
            Text("For a day with no clockings at all, request the missing check-in, check-out or both here. To correct a day that already has clockings, use Request correction on the Timesheets tab. Requests can go back one month and apply once they are approved.")
                .font(.aiku(13))
                .foregroundStyle(AikuColor.text3)
            AikuButton("Request adjustment", icon: .plus) { isRequesting = true }
                .accessibilityIdentifier("adjustments.request")
            if let error = store.adjustmentsError {
                InlineAlert(tone: .danger, title: "Couldn’t load your requests", message: error) {
                    LinkButton("Try again", size: 13) { Task { await store.loadAdjustments() } }
                }
            }
            if !store.hasLoadedAdjustments, store.adjustmentsError == nil {
                SkeletonRow().cardSurface()
                SkeletonRow().cardSurface()
            } else if store.adjustmentRequests.isEmpty {
                EmptyState(icon: .history, title: "No clocking adjustments", message: "Requests you send for days with missing clockings appear here.")
            } else {
                CardList(store.adjustmentRequests) { request in
                    row(request)
                }
            }
        }
        .aikuSheet(isPresented: $isRequesting) {
            ClockingAdjustmentFlow { didRequest in
                isRequesting = false
                if didRequest {
                    toasts.show("Adjustment requested · your manager will check it")
                }
            }
        }
        .aikuConfirm(
            "Cancel this request?",
            isPresented: Binding(get: { cancelling != nil }, set: { if !$0 { cancelling = nil } }),
            message: "Your clocking adjustment for \(cancelling.flatMap { LeaveCalculator.rangeLabel(start: $0.date, end: nil) } ?? "this day") will be withdrawn.",
            confirmTitle: "Cancel request",
            cancelTitle: "Keep request",
            isDestructive: true
        ) {
            guard let request = cancelling else { return }
            cancel(request)
        }
        .task {
            await store.loadAdjustments()
            if !store.hasLoaded { await store.load() }
        }
        .screenRefreshable { await store.loadAdjustments() }
    }

    private func row(_ request: ClockingAdjustmentResponse) -> some View {
        VStack(alignment: .leading, spacing: 6) {
            HStack(alignment: .firstTextBaseline, spacing: 8) {
                Text(LeaveCalculator.rangeLabel(start: request.date, end: nil))
                    .font(.aiku(15, .bold))
                    .foregroundStyle(AikuColor.text)
                Spacer(minLength: 8)
                StatusChip(request.statusLabel ?? request.status?.capitalized ?? "", tone: request.tone)
            }
            Text("Recorded: \(range(request.originalStartTimeLocal, request.originalEndTimeLocal) ?? "No clockings")")
                .font(.aiku(13))
                .foregroundStyle(AikuColor.text3)
            Text("Requested: \(range(request.requestedStartTimeLocal, request.requestedEndTimeLocal) ?? "—")")
                .font(.aiku(13, .semibold))
                .foregroundStyle(AikuColor.text2)
            if let reason = request.reason, !reason.isEmpty {
                Text(reason)
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text2)
            }
            if let comment = request.approvalComment, !comment.isEmpty {
                Text("Manager: \(comment)")
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text4)
            }
            ClockingAttachmentList(attachments: request.attachments ?? [])
            if request.status == ClockingAdjustmentStatus.pending.rawValue {
                Button {
                    cancelling = request
                } label: {
                    HStack(spacing: 6) {
                        if busyID == request.id { ProgressView().controlSize(.small) }
                        Text("Cancel request")
                    }
                    .font(.aiku(13, .semibold))
                    .foregroundStyle(AikuColor.danger)
                    .frame(minHeight: 36)
                }
                .buttonStyle(.plain)
                .disabled(busyID != nil)
                .accessibilityIdentifier("adjustments.cancel.\(request.id)")
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .frame(maxWidth: .infinity, alignment: .leading)
    }

    private func range(_ start: String?, _ end: String?) -> String? {
        guard start != nil || end != nil else { return nil }
        return "\(start ?? "—") → \(end ?? "—")"
    }

    private func cancel(_ request: ClockingAdjustmentResponse) {
        cancelling = nil
        busyID = request.id
        Task {
            if let problem = await store.cancelAdjustment(request) {
                toasts.show(problem, tone: .danger)
            } else {
                toasts.show("Request cancelled")
            }
            busyID = nil
        }
    }
}
