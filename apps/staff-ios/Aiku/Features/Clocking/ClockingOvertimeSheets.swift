import SwiftUI

struct ClockingOvertimeRequestSheet: View {
    let store: OvertimeStore
    let editing: OvertimeRequestResponse?
    let onClose: (OvertimeRequestResponse?) -> Void
    @State private var draft: OvertimeDraft
    @State private var error: OvertimeFormError?
    @State private var isSending = false

    init(store: OvertimeStore, editing: OvertimeRequestResponse?, onClose: @escaping (OvertimeRequestResponse?) -> Void) {
        self.store = store
        self.editing = editing
        self.onClose = onClose
        _draft = State(initialValue: editing.map { store.draft(for: $0) } ?? store.newDraft())
    }

    private var duration: Int { OvertimeCalculator.durationMinutes(start: draft.startMinutes, end: draft.endMinutes) }

    private var durationText: String {
        guard duration > 0 else { return "Pick when you started and finished" }
        let nextDay = OvertimeCalculator.endsNextDay(start: draft.startMinutes, end: draft.endMinutes) ? " · ends the next day" : ""
        return "Duration \(OvertimeCalculator.durationLabel(duration))\(nextDay)"
    }

    private var dateBinding: Binding<Date> {
        Binding(
            get: { ClockingMapper.parseDay(draft.date).map(Self.localDay(fromUTC:)) ?? Calendar.current.startOfDay(for: .now) },
            set: { draft.date = ClockingMapper.dayString($0, in: .current) }
        )
    }

    private func timeBinding(_ keyPath: WritableKeyPath<OvertimeDraft, Int>) -> Binding<Date> {
        Binding(
            get: { Calendar.current.date(byAdding: .minute, value: draft[keyPath: keyPath], to: Calendar.current.startOfDay(for: .now)) ?? .now },
            set: { value in
                let parts = Calendar.current.dateComponents([.hour, .minute], from: value)
                draft[keyPath: keyPath] = (parts.hour ?? 0) * 60 + (parts.minute ?? 0)
            }
        )
    }

    private static func localDay(fromUTC date: Date) -> Date {
        var utc = Calendar(identifier: .gregorian)
        utc.timeZone = TimeZone(identifier: "UTC")!
        let parts = utc.dateComponents([.year, .month, .day], from: date)
        return Calendar.current.date(from: parts) ?? date
    }

    var body: some View {
        SheetScaffold(title: editing == nil ? "New overtime request" : "Edit overtime request", subtitle: "Your manager approves it in Aiku") {
            SheetSectionLabel("Overtime type")
            VStack(spacing: 8) {
                ForEach(store.typeOptions, id: \.value) { option in
                    RadioCard(option.label, isSelected: draft.typeID == option.value) {
                        draft.typeID = option.value
                    }
                }
            }
            VStack(spacing: 0) {
                DatePicker("Date worked", selection: dateBinding, displayedComponents: .date)
                    .padding(.horizontal, 14)
                    .frame(minHeight: 52)
                RowDivider()
                DatePicker("Start time", selection: timeBinding(\.startMinutes), displayedComponents: .hourAndMinute)
                    .padding(.horizontal, 14)
                    .frame(minHeight: 52)
                RowDivider()
                DatePicker("End time", selection: timeBinding(\.endMinutes), displayedComponents: .hourAndMinute)
                    .padding(.horizontal, 14)
                    .frame(minHeight: 52)
            }
            .font(.aiku(15))
            .foregroundStyle(AikuColor.text)
            .tint(AikuColor.primary)
            .environment(\.locale, Locale(identifier: "en_GB"))
            .cardSurface()
            Text(durationText)
                .font(.aiku(13))
                .foregroundStyle(AikuColor.text3)
            AikuTextArea("Reason", text: $draft.reason, placeholder: "Why did you work overtime?")
            if let error {
                InlineAlert(tone: .danger, title: "Can’t send this request", message: error.message)
            }
        } footer: {
            AikuButton(editing == nil ? "Submit request" : "Update request", isLoading: isSending) {
                isSending = true
                Task {
                    switch await store.submit(draft, editing: editing) {
                    case .success(let saved):
                        onClose(saved)
                    case .failure(let failure):
                        error = failure
                    }
                    isSending = false
                }
            }
            .accessibilityIdentifier("overtime.submit")
            AikuButton("Cancel", kind: .tertiary, size: .small) { onClose(nil) }
        }
    }
}
