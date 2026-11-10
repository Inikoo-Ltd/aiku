import SwiftUI

struct ClockingScanFlow: View {
    let workingHours: String
    let onScan: () -> ClockingScanOutcome
    let onSaveNote: (ClockingEvent, String) -> Void
    let onClose: () -> Void
    let onFailure: (ClockingRoute) -> Void
    @State private var event: ClockingEvent?

    var body: some View {
        if let event {
            ClockingScanSuccessView(event: event, workingHours: workingHours, onSaveNote: { note in
                onSaveNote(event, note)
            }, onClose: onClose)
            .transition(.opacity)
        } else {
            CameraScanScreen(
                kicker: "Clocking · QR Code",
                title: "Scan QR Code",
                hint: "Align QR code inside the frame",
                onClose: onClose,
                onSimulateScan: handleScan
            ) {
                EmptyView()
            }
        }
    }

    private func handleScan() {
        switch onScan() {
        case .success(let recorded):
            withAnimation(.easeOut(duration: 0.2)) {
                event = recorded
            }
        case .rejected:
            onFailure(.qrInvalid)
        case .offline:
            onFailure(.noConnection)
        }
    }
}

struct ClockingScanSuccessView: View {
    let event: ClockingEvent
    let workingHours: String
    let onSaveNote: (String) -> Void
    let onClose: () -> Void
    @State private var note = ""
    @FocusState private var isNoteFocused: Bool

    private let green = Color(hex: 0x15803D)
    private let paleGreen = Color(hex: 0xDCFCE7)

    var body: some View {
        ZStack {
            green.ignoresSafeArea()
            ScrollView {
                VStack(spacing: 14) {
                    AppIcon.check.image
                        .font(.system(size: 72, weight: .semibold))
                        .frame(width: 88, height: 88)
                        .accessibilityHidden(true)
                    Text(event.kind == .clockIn ? "Clocked in" : "Clocked out")
                        .font(.aiku(34, .bold))
                        .accessibilityAddTraits(.isHeader)
                    Text(ClockingFormat.time(event.time))
                        .font(.aiku(44, .bold))
                        .monospacedDigit()
                    Text("Working hours: \(workingHours)")
                        .font(.aiku(16))
                        .foregroundStyle(paleGreen)
                    VStack(spacing: 10) {
                        TextField("Add a note (optional)", text: $note)
                            .font(.aiku(15))
                            .foregroundStyle(Color(hex: 0x111827))
                            .focused($isNoteFocused)
                            .padding(.horizontal, 14)
                            .frame(height: 48)
                            .background(Color.white, in: RoundedRectangle(cornerRadius: 8))
                            .accessibilityLabel("Note")
                        HStack(spacing: 10) {
                            Button(action: onClose) {
                                Text("Close")
                                    .font(.aiku(15, .semibold))
                                    .foregroundStyle(Color.white)
                                    .frame(maxWidth: .infinity, minHeight: 48)
                                    .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(Color.white.opacity(0.6)))
                                    .contentShape(Rectangle())
                            }
                            .buttonStyle(.plain)
                            .accessibilityIdentifier("clocking.success.close")
                            Button {
                                onSaveNote(note)
                                onClose()
                            } label: {
                                Text("Save note")
                                    .font(.aiku(15, .bold))
                                    .foregroundStyle(green)
                                    .frame(maxWidth: .infinity, minHeight: 48)
                                    .background(Color.white, in: RoundedRectangle(cornerRadius: 8))
                                    .contentShape(Rectangle())
                            }
                            .buttonStyle(.plain)
                            .disabled(note.trimmingCharacters(in: .whitespaces).isEmpty)
                            .accessibilityIdentifier("clocking.success.saveNote")
                        }
                        if isCountingDown {
                            Text("Closes by itself in a few seconds")
                                .font(.aiku(12))
                                .foregroundStyle(paleGreen)
                        }
                    }
                    .frame(maxWidth: 320)
                    .padding(.top, 12)
                }
                .foregroundStyle(Color.white)
                .multilineTextAlignment(.center)
                .padding(24)
                .frame(maxWidth: .infinity)
                .containerRelativeFrame(.vertical, alignment: .center) { length, _ in length }
            }
            .scrollBounceBehavior(.basedOnSize)
        }
        .task(id: isCountingDown) {
            guard isCountingDown else { return }
            try? await Task.sleep(for: .seconds(6))
            guard !Task.isCancelled, isCountingDown else { return }
            onClose()
        }
    }

    private var isCountingDown: Bool { note.isEmpty && !isNoteFocused }
}
