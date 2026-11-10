import SwiftUI

struct ClockingView: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @Environment(Session.self) private var session
    @State private var store = ClockingStore.shared
    @State private var isChoosingWorkplace = false
    @State private var isScanning = false
    @State private var isAdjusting = false

    var body: some View {
        AppScreen(title: "Clocking", kicker: "\(session.profile.name) · \(session.profile.organisation)") {
            ClockingSectionTabs(current: .clock) { isAdjusting = true }
        } content: {
            if store.enabledMethods.isEmpty {
                noMethods
            } else {
                TimelineView(.everyMinute) { _ in
                    ClockingStatusCard(
                        day: store.today,
                        now: store.now,
                        since: store.statusSince,
                        isShowingDetails: $store.isShowingDetails
                    )
                }
                SegmentedControl(options: store.enabledMethods, selection: methodBinding) { $0.title }
                    .accessibilityLabel("Clocking method")
                methodPanel
            }
        }
        .aikuSheet(isPresented: $isChoosingWorkplace) {
            ClockingWorkplaceSheet(
                schedule: store.schedule,
                shifts: store.shifts,
                standardHours: ClockingSampleData.standardHours,
                onConfirm: { schedule in
                    store.schedule = schedule
                    isChoosingWorkplace = false
                    present { isScanning = true }
                },
                onCancel: { isChoosingWorkplace = false }
            )
        }
        .aikuSheet(isPresented: $isAdjusting) {
            ClockingAdjustmentFlow(day: store.adjustmentDay) { didRequest in
                isAdjusting = false
                if didRequest {
                    toasts.show("Adjustment requested · your manager will check it")
                }
            }
        }
        .fullScreenCover(isPresented: $isScanning) {
            ClockingScanFlow(
                workingHours: store.workingHoursLabel,
                onScan: { store.simulateScan() },
                onSaveNote: { event, note in
                    store.saveNote(note, for: event.id)
                    toasts.show("Note saved")
                },
                onClose: { isScanning = false },
                onFailure: { route in
                    isScanning = false
                    present { openError(route) }
                }
            )
        }
        .onAppear(perform: handlePendingAction)
    }

    private var methodBinding: Binding<ClockingMethod> {
        Binding {
            store.activeMethod ?? .qrCode
        } set: { method in
            store.selectedMethod = method
        }
    }

    @ViewBuilder
    private var methodPanel: some View {
        switch store.activeMethod ?? .qrCode {
        case .qrCode:
            ClockingQrPanel(location: store.location, coordinates: store.coordinates, onDetect: detectLocation, onOpenCamera: openCamera)
        case .pin:
            if let pin = store.pin {
                ClockingCodePanel(instruction: "Enter this PIN on the clocking machine to clock in or out") {
                    ClockingPinCode(pin: pin)
                }
            } else {
                ClockingNoCodeMessage(method: .pin)
            }
        case .barcode:
            if let code = store.employeeCode {
                ClockingCodePanel(instruction: "Show this barcode to the scanner to clock in or out") {
                    ClockingBarcodeImage(code: code)
                }
            } else {
                ClockingNoCodeMessage(method: .barcode)
            }
        case .cameraQr:
            if store.employeeCode != nil {
                ClockingCodePanel(instruction: "Show this QR code to the camera to clock in or out") {
                    ClockingQrImage()
                }
            } else {
                ClockingNoCodeMessage(method: .cameraQr)
            }
        }
    }

    private var noMethods: some View {
        VStack(spacing: 10) {
            AppIcon.clock.image
                .font(.system(size: 22))
                .foregroundStyle(AikuColor.text3)
                .frame(width: 52, height: 52)
                .background(AikuColor.bg, in: Circle())
                .accessibilityHidden(true)
            Text("No clocking method")
                .font(.aiku(16, .bold))
                .foregroundStyle(AikuColor.text)
            Text("No clocking method is currently enabled for your organisation. Please contact HR.")
                .font(.aiku(14))
                .foregroundStyle(AikuColor.text3)
        }
        .multilineTextAlignment(.center)
        .padding(.horizontal, 20)
        .padding(.vertical, 28)
        .frame(maxWidth: .infinity)
        .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 16))
        .overlay(RoundedRectangle(cornerRadius: 16).strokeBorder(AikuColor.border))
    }

    private func openCamera() {
        if store.hasActiveShifts {
            isChoosingWorkplace = true
        } else {
            isScanning = true
        }
    }

    private func detectLocation() {
        guard store.location != .detecting else { return }
        store.startDetectingLocation()
        Task {
            try? await Task.sleep(for: .milliseconds(800))
            if !store.finishDetectingLocation() {
                openError(.locationBlocked)
            }
        }
    }

    private func openError(_ route: ClockingRoute) {
        store.openedErrorFromClocking = true
        router.open(route)
    }

    private func present(_ action: @escaping @MainActor () -> Void) {
        Task {
            try? await Task.sleep(for: .milliseconds(450))
            action()
        }
    }

    private func handlePendingAction() {
        store.openedErrorFromClocking = false
        guard let action = store.pendingAction else { return }
        store.pendingAction = nil
        present {
            switch action {
            case .openCamera: isScanning = true
            case .chooseWorkplace: openCamera()
            case .detectLocation: detectLocation()
            }
        }
    }
}

struct ClockingAdjustmentFlow: View {
    @State private var store = ClockingStore.shared
    let day: ClockingDay
    let onClose: (Bool) -> Void

    var body: some View {
        ClockingAdjustmentSheet(day: day) { kind, time, reason in
            switch store.requestAdjustment(date: day.date, kind: kind, time: time, reason: reason) {
            case .success:
                onClose(true)
                return nil
            case .failure(let error):
                return error
            }
        } onCancel: {
            onClose(false)
        }
    }
}
