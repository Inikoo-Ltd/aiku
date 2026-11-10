import SwiftUI

struct ScanTabView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(ToastCenter.self) private var toasts
    @AppStorage(ScanSettingsKey.inputMode) private var inputMode = ScanInputMode.automatic
    @AppStorage(ScanSettingsKey.vibrate) private var vibrates = true
    @State private var isCameraOpen = false
    @State private var cycle = ShellScanCycle()
    @State private var lastCode: String?
    @State private var cameraMissCode: String?
    @State private var typedCode = ""
    @State private var scanCount = 0
    @State private var missCount = 0
    @FocusState private var isTypingCode: Bool

    var body: some View {
        AppScreen(title: lastCode == nil ? "Scan" : "Result", kicker: "Scan · \(session.warehouseLabel)", back: .none, spacing: 16) {
            if session.canScan {
                content
            } else {
                EmptyState(icon: .scan, title: "Scanning is not available", message: "Scanning is for accounts with warehouse or fulfilment access.")
            }
        }
        .fullScreenCover(isPresented: $isCameraOpen) {
            camera
        }
        .sensoryFeedback(.success, trigger: scanCount) { _, _ in vibrates }
        .sensoryFeedback(.error, trigger: missCount) { _, _ in vibrates }
        .onAppear {
            if inputMode == .camera, lastCode == nil, session.canScan {
                isCameraOpen = true
            }
        }
    }

    @ViewBuilder
    private var content: some View {
        if let lastCode {
            if let target = ShellScanCatalog.target(for: lastCode) {
                ShellScanResultContent(target: target)
            } else {
                ShellScanNotFoundCard(code: lastCode) {
                    isTypingCode = true
                } onReport: {
                    report(lastCode)
                }
            }
        }
        listeningCard
        typeCodeField
        ShellScanCapabilities()
    }

    private var listeningCard: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack(spacing: 8) {
                AppIcon.scan.image
                    .font(.system(size: 18, weight: .semibold))
                    .accessibilityHidden(true)
                Text(listeningTitle)
                    .font(.aiku(14, .bold))
            }
            Text("SKO, location, delivery note, pallet. Opens the right screen.")
                .font(.aiku(13))
            AikuButton("Scan with camera", icon: .camera, kind: .primary, size: .regular) {
                cameraMissCode = nil
                isCameraOpen = true
            }
            .accessibilityIdentifier("shell.scan.camera")
        }
        .foregroundStyle(AikuColor.onScan)
        .padding(14)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(AikuColor.scan, in: RoundedRectangle(cornerRadius: 12))
    }

    private var listeningTitle: String {
        switch inputMode {
        case .automatic: "Listening · camera or Bluetooth scanner"
        case .scannerDevice: "Listening · Bluetooth scanner"
        case .camera: "Camera scanning"
        }
    }

    private var typeCodeField: some View {
        VStack(alignment: .leading, spacing: 8) {
            FieldLabel("Type code")
            FieldBox(isFocused: isTypingCode, height: 48) {
                AppIcon.keyboard.image
                    .font(.system(size: 16))
                    .foregroundStyle(AikuColor.text4)
                    .padding(.leading, 14)
                    .accessibilityHidden(true)
                TextField("SKO, location, delivery note or pallet", text: $typedCode)
                    .font(.aiku(15))
                    .foregroundStyle(AikuColor.text)
                    .textInputAutocapitalization(.characters)
                    .autocorrectionDisabled()
                    .submitLabel(.go)
                    .focused($isTypingCode)
                    .onSubmit(submitTypedCode)
                    .padding(.horizontal, 10)
                    .accessibilityLabel("Type code")
                    .accessibilityIdentifier("shell.scan.codeField")
                Button("Open", action: submitTypedCode)
                    .font(.aiku(14, .semibold))
                    .foregroundStyle(AikuColor.link)
                    .frame(minWidth: 56, minHeight: 44)
                    .disabled(typedCode.trimmingCharacters(in: .whitespaces).isEmpty)
            }
        }
    }

    private var camera: some View {
        CameraScanScreen(
            kicker: session.warehouseLabel,
            title: "Scan",
            hint: "Point at the barcode",
            onClose: { isCameraOpen = false },
            onUseScanner: {
                inputMode = .scannerDevice
                isCameraOpen = false
                toasts.show("Bluetooth scanner selected", tone: .info)
            },
            onSimulateScan: simulateScan
        ) {
            if let cameraMissCode {
                ShellScanNotFoundCard(code: cameraMissCode) {
                    isCameraOpen = false
                    isTypingCode = true
                } onReport: {
                    report(cameraMissCode)
                }
            }
        } panel: {
            HelperText("Tap the viewfinder to scan a sample code.")
        }
    }

    private func simulateScan() {
        let code = cycle.next()
        if ShellScanCatalog.target(for: code) == nil {
            cameraMissCode = code
            missCount += 1
        } else {
            cameraMissCode = nil
            show(code)
            isCameraOpen = false
        }
    }

    private func submitTypedCode() {
        let code = typedCode.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !code.isEmpty else { return }
        isTypingCode = false
        typedCode = ""
        if ShellScanCatalog.target(for: code) == nil {
            missCount += 1
            lastCode = code
        } else {
            show(code)
        }
    }

    private func show(_ code: String) {
        lastCode = code
        scanCount += 1
    }

    private func report(_ code: String) {
        toasts.show("Reported \(code)")
    }
}
