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
    let day: ClockingDay
    let onSubmit: (ClockingEventKind, String, String) -> ClockingAdjustmentError?
    let onCancel: () -> Void
    @State private var kind: ClockingEventKind
    @State private var time: String
    @State private var reason = ""
    @State private var error: ClockingAdjustmentError?

    init(
        day: ClockingDay,
        onSubmit: @escaping (ClockingEventKind, String, String) -> ClockingAdjustmentError?,
        onCancel: @escaping () -> Void
    ) {
        self.day = day
        self.onSubmit = onSubmit
        self.onCancel = onCancel
        let initialKind: ClockingEventKind = day.events.isEmpty ? .clockIn : .clockOut
        _kind = State(initialValue: initialKind)
        _time = State(initialValue: Self.shiftTime(day.shift, for: initialKind))
    }

    var body: some View {
        SheetScaffold(title: "Clocking adjustment · \(day.shortTitle)", subtitle: "Your manager checks it and corrects the timesheet in Aiku") {
            VStack(alignment: .leading, spacing: 6) {
                FieldLabel("Missing clocking")
                SegmentedControl(options: [ClockingEventKind.clockIn, .clockOut], selection: kindBinding) { option in
                    option == .clockIn ? "Clock in" : "Clock out"
                }
            }
            VStack(alignment: .leading, spacing: 6) {
                AikuTextField(
                    kind == .clockOut ? "Time you left" : "Time you arrived",
                    text: $time,
                    placeholder: "16:30",
                    error: error == .invalidTime ? error?.message : nil,
                    keyboard: .numbersAndPunctuation
                )
                Text(kind == .clockOut ? "Your shift ended \(Self.shiftTime(day.shift, for: .clockOut))" : "Your shift started \(Self.shiftTime(day.shift, for: .clockIn))")
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            }
            AikuTextField(
                "Reason",
                text: $reason,
                placeholder: "Forgot to clock out at the door",
                error: error == .missingReason ? error?.message : nil
            )
        } footer: {
            AikuButton("Request adjustment") {
                error = onSubmit(kind, time, reason)
            }
            .accessibilityIdentifier("clocking.adjustment.request")
            AikuButton("Cancel", kind: .tertiary, size: .small, action: onCancel)
        }
    }

    private var kindBinding: Binding<ClockingEventKind> {
        Binding {
            kind
        } set: { newKind in
            if time == Self.shiftTime(day.shift, for: kind) {
                time = Self.shiftTime(day.shift, for: newKind)
            }
            kind = newKind
        }
    }

    static func shiftTime(_ shift: String, for kind: ClockingEventKind) -> String {
        let parts = shift.components(separatedBy: " – ")
        guard parts.count == 2 else { return "" }
        return kind == .clockIn ? parts[0] : parts[1]
    }
}
