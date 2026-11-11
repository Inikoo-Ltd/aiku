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
        AppScreen(title: "Clocking", kicker: kicker) {
            ClockingSectionTabs(current: .clock) { isAdjusting = true }
        } content: {
            if let error = store.loadError {
                InlineAlert(tone: .danger, title: "Couldn’t load clocking", message: error) {
                    LinkButton("Try again", size: 13) { Task { await store.load() } }
                }
            }
            if !store.hasLoaded {
                SkeletonRow().cardSurface()
                SkeletonRow().cardSurface()
            } else if store.enabledMethods.isEmpty {
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
                standardHours: store.workingHoursLabel,
                onConfirm: { schedule in
                    store.schedule = schedule
                    isChoosingWorkplace = false
                    present { isScanning = true }
                },
                onCancel: { isChoosingWorkplace = false }
            )
        }
        .aikuSheet(isPresented: $isAdjusting) {
            ClockingAdjustmentFlow { didRequest in
                isAdjusting = false
                if didRequest {
                    toasts.show("Adjustment requested · your manager will check it")
                }
            }
        }
        .fullScreenCover(isPresented: $isScanning) {
            ClockingScanFlow(
                workingHours: { store.workingHoursLabel },
                canAddNote: store.canEditNotes,
                onScan: { code in await store.clock(code: code) },
                onSaveNote: { event, note in
                    Task {
                        if await store.saveNote(note, for: event) {
                            toasts.show("Note saved")
                        } else {
                            toasts.show("Couldn’t save the note", tone: .danger)
                        }
                    }
                },
                onClose: { isScanning = false },
                onFailure: { route in
                    isScanning = false
                    present { openError(route) }
                }
            )
        }
        .onAppear(perform: handlePendingAction)
        .task { await store.load() }
        .screenRefreshable { await store.load() }
    }

    private var kicker: String {
        let name = store.employeeName ?? session.profile.name
        let organisation = store.organisationName ?? session.profile.organisation
        return "\(name) · \(organisation)"
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
            do {
                store.setLocation(try await LocationProvider.shared.currentCoordinate())
            } catch {
                store.setLocation(nil)
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
    var day: ClockingDay?
    let onClose: (Bool) -> Void

    init(day: ClockingDay? = nil, onClose: @escaping (Bool) -> Void) {
        self.day = day
        self.onClose = onClose
    }

    var body: some View {
        ClockingAdjustmentSheet(store: store, fixedDate: day?.date, onClose: onClose)
    }
}
