import SwiftUI

struct ClockingLeaveView: View {
    @Environment(ToastCenter.self) private var toasts
    @Environment(Session.self) private var session
    @State private var store = LeaveStore.shared
    @State private var isAdjusting = false
    @State private var isRequesting = false

    var body: some View {
        AppScreen(title: "Leave", kicker: "Clocking & HR · \(ClockingStore.shared.organisationName ?? session.profile.organisation)") {
            ClockingSectionTabs(current: .leave) { isAdjusting = true }
        } content: {
            if let error = store.loadError {
                InlineAlert(tone: .danger, title: "Couldn’t load leave", message: error) {
                    LinkButton("Try again", size: 13) { Task { await store.load() } }
                }
            }
            if !store.hasLoaded {
                SkeletonRow().cardSurface()
                SkeletonRow().cardSurface()
            } else {
                balanceCard
                AikuButton("Request leave", icon: .plus) { isRequesting = true }
                    .accessibilityIdentifier("leave.request")
                section("Waiting for approval", store.pending)
                section("Upcoming", store.upcoming)
                if store.leaves.isEmpty {
                    EmptyState(icon: .history, title: "No leave yet", message: "Your leave requests and approved leave show here.")
                }
                section("Past", store.past)
                if !store.upcomingHolidays.isEmpty {
                    SectionHeading("Public holidays")
                    Card {
                        ForEach(Array(store.upcomingHolidays.prefix(6).enumerated()), id: \.offset) { index, holiday in
                            if index > 0 { RowDivider() }
                            KeyValueRow(LeaveCalculator.rangeLabel(start: holiday.date, end: nil), holiday.label ?? "Holiday")
                        }
                    }
                }
            }
        }
        .aikuSheet(isPresented: $isRequesting) {
            ClockingLeaveRequestSheet(store: store) { leave in
                isRequesting = false
                if leave != nil {
                    toasts.show("Leave requested · your manager will check it")
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

    @ViewBuilder
    private var balanceCard: some View {
        if let balance = store.balance {
            Card(padding: 14, spacing: 12) {
                HStack(alignment: .firstTextBaseline) {
                    VStack(alignment: .leading, spacing: 2) {
                        Text("Annual leave left")
                            .font(.aiku(13))
                            .foregroundStyle(AikuColor.text3)
                        Text(LeaveCalculator.daysLabel(balance.annualRemaining))
                            .font(.aiku(28, .bold))
                            .foregroundStyle(AikuColor.text)
                    }
                    Spacer()
                    Text("of \(LeaveCalculator.daysLabel(balance.annualDays))")
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                }
                ProgressBar(value: balance.annualDays > 0 ? balance.annualUsed / balance.annualDays : 0)
                HStack(spacing: 8) {
                    stat(LeaveCalculator.daysLabel(balance.annualUsed), "Annual used")
                    stat(LeaveCalculator.daysLabel(balance.medicalUsed), "Medical")
                    stat(LeaveCalculator.daysLabel(balance.unpaidUsed), "Unpaid")
                }
                if let after = store.remainingAfterSubmission, after != balance.annualRemaining {
                    Text("\(LeaveCalculator.daysLabel(after)) left once pending requests are approved")
                        .font(.aiku(12))
                        .foregroundStyle(AikuColor.text4)
                }
            }
        }
    }

    private func stat(_ value: String, _ label: String) -> some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(value).font(.aiku(15, .bold)).foregroundStyle(AikuColor.text)
            Text(label).font(.aiku(12)).foregroundStyle(AikuColor.text3)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
    }

    @ViewBuilder
    private func section(_ title: String, _ leaves: [LeaveResponse]) -> some View {
        if !leaves.isEmpty {
            SectionHeading(title, detail: "\(leaves.count)")
            CardList(leaves) { leave in
                leaveRow(leave)
            }
        }
    }

    private func leaveRow(_ leave: LeaveResponse) -> some View {
        let status = leave.status.flatMap(LeaveStatus.init(rawValue:))
        return VStack(alignment: .leading, spacing: 4) {
            HStack(alignment: .firstTextBaseline, spacing: 8) {
                Text(leave.typeLabel ?? leave.type ?? "Leave")
                    .font(.aiku(15, .bold))
                    .foregroundStyle(AikuColor.text)
                Spacer(minLength: 8)
                StatusChip(leave.statusLabel ?? leave.status?.capitalized ?? "", tone: status?.tone ?? .neutral)
            }
            Text("\(LeaveCalculator.rangeLabel(start: leave.startDate, end: leave.endDate)) · \(LeaveCalculator.daysLabel(leave.durationDays ?? 0))")
                .font(.aiku(13))
                .foregroundStyle(AikuColor.text3)
            if let reason = leave.reason, !reason.isEmpty {
                Text(reason)
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text2)
            }
            if status == .rejected, let rejection = leave.rejectionReason, !rejection.isEmpty {
                Text("Rejected: \(rejection)")
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.danger)
            } else if status == .pending, let total = leave.approvalTotalSteps, total > 1 {
                Text("Approval \(leave.approvalCompletedSteps ?? 0) of \(total)")
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text4)
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .frame(maxWidth: .infinity, alignment: .leading)
        .accessibilityElement(children: .combine)
    }
}

struct ClockingLeaveRequestSheet: View {
    let store: LeaveStore
    let onClose: (LeaveResponse?) -> Void
    @State private var type: String?
    @State private var start = Calendar.current.startOfDay(for: .now)
    @State private var end = Calendar.current.startOfDay(for: .now)
    @State private var reason = ""
    @State private var error: LeaveFormError?
    @State private var isSending = false

    private var option: LeavesResponse.TypeOption? { store.typeOptions.first { $0.code == type } }
    private var startText: String { ClockingMapper.dayString(start, in: .current) }
    private var endText: String { LeaveCalculator.isSingleDay(option) ? startText : ClockingMapper.dayString(end, in: .current) }

    var body: some View {
        SheetScaffold(title: "Request leave", subtitle: "Your manager approves it in Aiku") {
            SheetSectionLabel("Type")
            VStack(spacing: 8) {
                ForEach(store.typeOptions, id: \.code) { option in
                    RadioCard(option.label, subtitle: option.category?.capitalized, isSelected: type == option.code) {
                        type = option.code
                    }
                }
            }
            VStack(spacing: 0) {
                DatePicker(LeaveCalculator.isSingleDay(option) ? "Day" : "First day", selection: $start, in: Calendar.current.startOfDay(for: .now)..., displayedComponents: .date)
                    .padding(.horizontal, 14)
                    .frame(minHeight: 52)
                if !LeaveCalculator.isSingleDay(option) {
                    RowDivider()
                    DatePicker("Last day", selection: $end, in: start..., displayedComponents: .date)
                        .padding(.horizontal, 14)
                        .frame(minHeight: 52)
                }
            }
            .font(.aiku(15))
            .foregroundStyle(AikuColor.text)
            .tint(AikuColor.primary)
            .cardSurface()
            Text(LeaveCalculator.daysLabel(LeaveCalculator.days(type: option, start: startText, end: endText, holidays: store.holidayDates)) + " · weekends and public holidays are not counted")
                .font(.aiku(13))
                .foregroundStyle(AikuColor.text3)
            AikuTextArea("Reason", text: $reason, placeholder: "Family event")
            if let error {
                InlineAlert(tone: .danger, title: "Can’t send this request", message: error.message)
            }
        } footer: {
            AikuButton("Request leave", isLoading: isSending) {
                isSending = true
                Task {
                    switch await store.request(type: type, start: startText, end: endText, reason: reason) {
                    case .success(let leave):
                        onClose(leave)
                    case .failure(let failure):
                        error = failure
                    }
                    isSending = false
                }
            }
            .accessibilityIdentifier("leave.submit")
            AikuButton("Cancel", kind: .tertiary, size: .small) { onClose(nil) }
        }
        .onChange(of: start) { _, newStart in
            if end < newStart { end = newStart }
        }
    }
}
