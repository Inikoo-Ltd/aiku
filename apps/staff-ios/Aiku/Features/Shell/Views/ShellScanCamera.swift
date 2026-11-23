import SwiftUI

struct ShellScanCameraView: View {
    let kicker: String
    let lookup: ShellScanLookup
    let onCode: (String) -> Void
    let onClose: () -> Void
    let onUseScanner: () -> Void
    let onTypeCode: () -> Void
    @State private var cameraAllowed: Bool?
    @State private var scannerAttempt = 0

    var body: some View {
        ZStack {
            if cameraAllowed == true, QRCodeScanner.isAvailable {
                QRCodeScanner(symbologies: QRCodeScanner.warehouseSymbologies) { code in
                    onCode(code)
                }
                .id(scannerAttempt)
                .ignoresSafeArea()
            } else {
                RadialGradient(colors: [Color(hex: 0x374151), Color(hex: 0x111827)], center: UnitPoint(x: 0.5, y: 0.35), startRadius: 10, endRadius: 520)
                    .ignoresSafeArea()
            }
            VStack(spacing: 16) {
                topBar
                modeSwitch
                Spacer(minLength: 0)
                ScanViewfinder()
                Text(L10n.text(hint))
                    .font(.aiku(14))
                    .foregroundStyle(.white)
                    .multilineTextAlignment(.center)
                    .padding(.horizontal, 24)
                feedback
                    .padding(.horizontal, 16)
                Spacer(minLength: 0)
                typeCodeButton
            }
            if let code = lookup.lookingUpCode {
                Color.black.opacity(0.45).ignoresSafeArea()
                VStack(spacing: 12) {
                    ProgressView().tint(.white).scaleEffect(1.3)
                    Text("Looking up \(code)…").font(.aiku(16, .semibold)).foregroundStyle(.white)
                }
                .accessibilityElement(children: .combine)
            }
        }
        .accessibilityIdentifier("scan.camera")
        .task {
            if cameraAllowed == nil {
                cameraAllowed = await CameraPermission.request()
            }
        }
        .task(id: lookup.outcome) {
            guard lookup.outcome != nil else { return }
            try? await Task.sleep(for: .seconds(2))
            guard !Task.isCancelled else { return }
            scannerAttempt += 1
        }
    }

    @ViewBuilder
    private var feedback: some View {
        switch lookup.outcome {
        case .notFound(let code):
            ShellScanNotFoundCard(code: code, onTypeCode: onTypeCode)
        case .failed(let code, let message):
            ScanFeedbackBanner(tone: .danger, title: L10n.format("Couldn’t look up %@", code), detail: message)
        case .needsWarehouse:
            ScanFeedbackBanner(tone: .warn, title: "Choose a warehouse", detail: "A scan looks the code up in your warehouse.")
        case .sessionExpired:
            ScanFeedbackBanner(tone: .warn, title: "Your session has ended", detail: "Close the scanner and sign in again.")
        case .found, nil:
            EmptyView()
        }
    }

    private var hint: String {
        if cameraAllowed == false { return "Camera access is off. Allow it in Settings, or type the code." }
        if cameraAllowed == true, !QRCodeScanner.isAvailable { return "This device can’t scan barcodes. Type the code instead." }
        return "Point at a SKO, location or delivery note barcode"
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
                Text(L10n.text(kicker)).font(.aiku(12)).foregroundStyle(Color(hex: 0xD1D5DB))
                Text("Scan").font(.aiku(16, .bold)).foregroundStyle(.white)
            }
            .lineLimit(1)
            .frame(maxWidth: .infinity)
            Color.clear.frame(width: 44, height: 44)
        }
        .padding(.horizontal, 16)
        .padding(.top, 8)
    }

    private var modeSwitch: some View {
        HStack(spacing: 4) {
            HStack(spacing: 6) {
                AppIcon.camera.image.font(.system(size: 14))
                Text("Camera")
            }
            .foregroundStyle(AikuColor.onScan)
            .padding(.horizontal, 12)
            .frame(height: 34)
            .background(AikuColor.scan, in: Capsule())
            Button(action: onUseScanner) {
                HStack(spacing: 6) {
                    AppIcon.scannerDevice.image.font(.system(size: 14))
                    Text("Bluetooth scanner")
                }
                .foregroundStyle(.white)
                .padding(.horizontal, 12)
                .frame(height: 34)
                .contentShape(Capsule())
            }
            .buttonStyle(.plain)
        }
        .font(.aiku(13, .semibold))
        .padding(4)
        .background(Color(hex: 0x111827, opacity: 0.6), in: Capsule())
    }

    private var typeCodeButton: some View {
        Button(action: onTypeCode) {
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
        .padding(.horizontal, 24)
        .padding(.bottom, 12)
        .accessibilityIdentifier("shell.scan.cameraTypeCode")
    }
}
