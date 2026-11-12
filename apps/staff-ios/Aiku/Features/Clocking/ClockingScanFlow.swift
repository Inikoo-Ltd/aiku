import SwiftUI

struct ClockingScanFlow: View {
    let workingHours: () -> String
    let canAddNote: Bool
    let onScan: (String) async -> ClockingScanOutcome
    let onSaveNote: (ClockingEvent, String) -> Void
    let onClose: () -> Void
    let onFailure: (ClockingRoute) -> Void
    @State private var event: ClockingEvent?
    @State private var isSending = false
    @State private var cameraAllowed: Bool?
    @State private var isTypingCode = false
    @State private var typedCode = ""

    var body: some View {
        if let event {
            ClockingScanSuccessView(event: event, workingHours: workingHours(), canAddNote: canAddNote, onSaveNote: { note in
                onSaveNote(event, note)
            }, onClose: onClose)
            .transition(.opacity)
        } else {
            scanner
        }
    }

    private var scanner: some View {
        ZStack {
            if cameraAllowed == true, QRCodeScanner.isAvailable {
                QRCodeScanner { code in handle(code) }
                    .ignoresSafeArea()
            } else {
                RadialGradient(colors: [Color(hex: 0x374151), Color(hex: 0x111827)], center: UnitPoint(x: 0.5, y: 0.35), startRadius: 10, endRadius: 520)
                    .ignoresSafeArea()
            }
            VStack(spacing: 16) {
                topBar
                Spacer(minLength: 0)
                ScanViewfinder()
                Text(hint)
                    .font(.aiku(14))
                    .foregroundStyle(.white)
                    .multilineTextAlignment(.center)
                    .padding(.horizontal, 24)
                Spacer(minLength: 0)
                Button {
                    typedCode = ""
                    isTypingCode = true
                } label: {
                    HStack(spacing: 8) {
                        AppIcon.keyboard.image
                        Text("Type code")
                    }
                    .font(.aiku(15, .semibold))
                    .foregroundStyle(.white)
                    .frame(maxWidth: .infinity, minHeight: 48)
                    .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(Color.white.opacity(0.6)))
                    .contentShape(Rectangle())
                }
                .buttonStyle(.plain)
                .disabled(isSending)
                .padding(.horizontal, 24)
                .padding(.bottom, 12)
                .accessibilityIdentifier("clocking.scan.typeCode")
            }
            if isSending {
                Color.black.opacity(0.45).ignoresSafeArea()
                VStack(spacing: 12) {
                    ProgressView().tint(.white).scaleEffect(1.3)
                    Text("Clocking…").font(.aiku(16, .semibold)).foregroundStyle(.white)
                }
            }
        }
        .accessibilityIdentifier("scan.camera")
        .task {
            if cameraAllowed == nil {
                cameraAllowed = await CameraPermission.request()
            }
        }
        .alert("Type the clocking code", isPresented: $isTypingCode) {
            TextField("Code or link on the machine", text: $typedCode)
                .textInputAutocapitalization(.never)
                .autocorrectionDisabled()
            Button("Cancel", role: .cancel) {}
            Button("Clock") { handle(typedCode) }
        } message: {
            Text("Use this when the QR code can’t be scanned.")
        }
    }

    private var hint: String {
        if cameraAllowed == false { return "Camera access is off. Allow it in Settings, or type the code." }
        if cameraAllowed == true, !QRCodeScanner.isAvailable { return "This device can’t scan QR codes. Type the code instead." }
        return "Align QR code inside the frame"
    }

    private var topBar: some View {
        HStack(spacing: 8) {
            Button(action: onClose) {
                AppIcon.close.image
                    .font(.system(size: 18, weight: .semibold))
                    .foregroundStyle(.white)
                    .frame(width: 44, height: 44)
                    .background(Color(hex: 0x111827, opacity: 0.6), in: Circle())
            }
            .buttonStyle(.plain)
            .accessibilityLabel("Close scanner")
            VStack(spacing: 0) {
                Text("Clocking · QR Code").font(.aiku(12)).foregroundStyle(Color(hex: 0xD1D5DB))
                Text("Scan QR Code").font(.aiku(16, .bold)).foregroundStyle(.white)
            }
            .frame(maxWidth: .infinity)
            Color.clear.frame(width: 44, height: 44)
        }
        .padding(.horizontal, 16)
        .padding(.top, 8)
    }

    private func handle(_ code: String) {
        let trimmed = code.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty, !isSending else { return }
        isSending = true
        Task {
            let outcome = await onScan(trimmed)
            isSending = false
            switch outcome {
            case .success(let recorded):
                withAnimation(.easeOut(duration: 0.2)) {
                    event = recorded
                }
            case .rejected:
                onFailure(.qrInvalid)
            case .locationNeeded:
                onFailure(.locationBlocked)
            case .offline:
                onFailure(.noConnection)
            }
        }
    }
}

struct ClockingScanSuccessView: View {
    let event: ClockingEvent
    let workingHours: String
    var canAddNote = true
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
                        if canAddNote {
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
                        } else {
                            Button(action: onClose) {
                                Text("Close")
                                    .font(.aiku(15, .bold))
                                    .foregroundStyle(green)
                                    .frame(maxWidth: .infinity, minHeight: 48)
                                    .background(Color.white, in: RoundedRectangle(cornerRadius: 8))
                                    .contentShape(Rectangle())
                            }
                            .buttonStyle(.plain)
                            .accessibilityIdentifier("clocking.success.close")
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
