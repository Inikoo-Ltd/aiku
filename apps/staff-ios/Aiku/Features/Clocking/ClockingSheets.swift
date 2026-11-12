import SwiftUI

struct ClockingWorkplaceSheet: View {
    let shifts: [ClockingShift]
    let standardHours: String
    let onConfirm: (ClockingSchedule) -> Void
    let onCancel: () -> Void
    @State private var usesShift: Bool
    @State private var shiftName: String

    init(
        schedule: ClockingSchedule,
        shifts: [ClockingShift],
        standardHours: String,
        onConfirm: @escaping (ClockingSchedule) -> Void,
        onCancel: @escaping () -> Void
    ) {
        self.shifts = shifts
        self.standardHours = standardHours
        self.onConfirm = onConfirm
        self.onCancel = onCancel
        switch schedule {
        case .standard:
            _usesShift = State(initialValue: false)
            _shiftName = State(initialValue: shifts.first?.name ?? "")
        case .shift(let name):
            _usesShift = State(initialValue: true)
            _shiftName = State(initialValue: name)
        }
    }

    var body: some View {
        SheetScaffold(title: "Select Working Hours", subtitle: "Choose your work schedule for this clocking") {
            VStack(spacing: 10) {
                RadioCard("Default Schedule", subtitle: "Your regular working hours · \(standardHours)", isSelected: !usesShift) {
                    usesShift = false
                }
                RadioCard("Shift Schedule", subtitle: "Select a specific shift", isSelected: usesShift) {
                    usesShift = true
                }
            }
            .accessibilityElement(children: .contain)
            .accessibilityLabel("Work schedule")
            if usesShift {
                VStack(alignment: .leading, spacing: 8) {
                    SheetSectionLabel("Choose a shift")
                    ForEach(shifts) { shift in
                        RadioCard(shift.name, isSelected: shiftName == shift.name) {
                            shiftName = shift.name
                        } trailing: {
                            Text(shift.hours)
                                .font(.aiku(14, .medium))
                                .foregroundStyle(AikuColor.text3)
                        }
                    }
                }
            }
            HStack(spacing: 10) {
                AikuButton("Cancel", kind: .tertiary, size: .regular, action: onCancel)
                AikuButton("Confirm", size: .regular) {
                    onConfirm(usesShift ? .shift(shiftName) : .standard)
                }
                .accessibilityIdentifier("clocking.workplace.confirm")
            }
            .padding(.top, 4)
            Text("Only shown when your organisation has active shifts. Otherwise the camera opens straight away.")
                .font(.aiku(12))
                .foregroundStyle(AikuColor.text4)
        }
    }
}

struct ClockingAdjustmentSheet: View {
    let store: ClockingStore
    let fixedDate: String?
    let onClose: (Bool) -> Void
    @State private var date: Date
    @State private var hasCheckIn = true
    @State private var hasCheckOut = true
    @State private var checkIn: Date
    @State private var checkOut: Date
    @State private var reason = ""
    @State private var error: ClockingAdjustmentError?
    @State private var isSending = false

    init(store: ClockingStore, fixedDate: String?, onClose: @escaping (Bool) -> Void) {
        self.store = store
        self.fixedDate = fixedDate
        self.onClose = onClose
        let initialDate = fixedDate.flatMap(Self.date(from:)) ?? Calendar.current.startOfDay(for: .now)
        _date = State(initialValue: initialDate)
        _checkIn = State(initialValue: Self.time(hour: 8, minute: 0))
        _checkOut = State(initialValue: Self.time(hour: 17, minute: 0))
    }

    private var dateText: String { ClockingMapper.dayString(date, in: .current) }
    private var recorded: ClockingDay? { store.recordedDay(on: dateText) }
    private var startText: String? { hasCheckIn ? Self.hhmm(checkIn) : nil }
    private var endText: String? { hasCheckOut ? Self.hhmm(checkOut) : nil }

    var body: some View {
        SheetScaffold(
            title: recorded == nil ? "Clocking adjustment" : "Request timesheet correction",
            subtitle: "Your manager checks it and corrects the timesheet in Aiku"
        ) {
            VStack(spacing: 0) {
                if fixedDate == nil {
                    DatePicker("Date", selection: $date, in: dateRange, displayedComponents: .date)
                        .padding(.horizontal, 14)
                        .frame(minHeight: 52)
                        .accessibilityIdentifier("clocking.adjustment.date")
                } else {
                    KeyValueRow("Date", recorded?.title ?? dateText)
                }
                if let recorded {
                    RowDivider()
                    KeyValueRow("Recorded", recorded.rangeLabel)
                }
            }
            .font(.aiku(15))
            .foregroundStyle(AikuColor.text)
            .tint(AikuColor.primary)
            .cardSurface()
            VStack(spacing: 0) {
                timeRow("Check-in time", isOn: $hasCheckIn, time: $checkIn)
                RowDivider()
                timeRow("Check-out time", isOn: $hasCheckOut, time: $checkOut)
            }
            .cardSurface()
            Text("Fill in check-in, check-out, or both.")
                .font(.aiku(13))
                .foregroundStyle(AikuColor.text3)
            AikuTextArea("Reason", text: $reason, placeholder: "Please explain what happened")
            ForEach(store.adjustments(for: dateText)) { request in
                InlineAlert(tone: request.tone, icon: .bell, title: "Already requested · \(request.statusLabel ?? request.status ?? "")", message: request.summary)
            }
            if let error {
                InlineAlert(tone: .danger, title: "Can’t send this request", message: error.message)
            }
        } footer: {
            AikuButton("Submit request", isLoading: isSending) {
                isSending = true
                Task {
                    switch await store.requestAdjustment(date: dateText, start: startText, end: endText, reason: reason) {
                    case .success:
                        onClose(true)
                    case .failure(let failure):
                        error = failure
                    }
                    isSending = false
                }
            }
            .accessibilityIdentifier("clocking.adjustment.request")
            AikuButton("Cancel", kind: .tertiary, size: .small) { onClose(false) }
        }
        .onChange(of: dateText, initial: true) { _, _ in
            error = nil
            prefillFromRecorded()
        }
    }

    private var dateRange: ClosedRange<Date> {
        let today = Calendar.current.startOfDay(for: .now)
        let earliest = Self.date(from: store.earliestAdjustmentDate) ?? today
        return earliest...today
    }

    private func timeRow(_ title: String, isOn: Binding<Bool>, time: Binding<Date>) -> some View {
        HStack(spacing: 12) {
            Toggle(isOn: isOn) {
                Text(title)
                    .font(.aiku(15, .semibold))
                    .foregroundStyle(AikuColor.text)
            }
            .tint(AikuColor.primary)
            .fixedSize()
            Spacer(minLength: 8)
            if isOn.wrappedValue {
                DatePicker(title, selection: time, displayedComponents: .hourAndMinute)
                    .labelsHidden()
                    .tint(AikuColor.primary)
            } else {
                Text("Not changed")
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text4)
            }
        }
        .padding(.horizontal, 14)
        .frame(minHeight: 56)
    }

    private func prefillFromRecorded() {
        guard let recorded else { return }
        checkIn = recorded.firstIn.map { Self.time(seconds: $0) } ?? Self.time(hour: 8, minute: 0)
        checkOut = recorded.lastOut.map { Self.time(seconds: $0) } ?? Self.time(hour: 17, minute: 0)
        hasCheckIn = true
        hasCheckOut = true
    }

    static func date(from text: String) -> Date? {
        let parts = text.split(separator: "-").compactMap { Int($0) }
        guard parts.count == 3 else { return nil }
        return Calendar.current.date(from: DateComponents(year: parts[0], month: parts[1], day: parts[2]))
    }

    static func time(hour: Int, minute: Int) -> Date {
        Calendar.current.date(bySettingHour: hour, minute: minute, second: 0, of: .now) ?? .now
    }

    static func time(seconds: Int) -> Date {
        time(hour: seconds / 3600, minute: (seconds % 3600) / 60)
    }

    static func hhmm(_ date: Date) -> String {
        let parts = Calendar.current.dateComponents([.hour, .minute], from: date)
        return String(format: "%02d:%02d", parts.hour ?? 0, parts.minute ?? 0)
    }
}
