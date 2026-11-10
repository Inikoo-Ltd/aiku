import SwiftUI

struct ClockingQrPanel: View {
    let location: ClockingLocationState
    let coordinates: String
    let onDetect: () -> Void
    let onOpenCamera: () -> Void

    var body: some View {
        VStack(spacing: 12) {
            VStack(spacing: 12) {
                HStack(spacing: 12) {
                    AppIcon.pin.image
                        .font(.system(size: 18))
                        .foregroundStyle(isFound ? AikuColor.success : AikuColor.text3)
                        .frame(width: 40, height: 40)
                        .background(AikuColor.surface, in: Circle())
                        .accessibilityHidden(true)
                    VStack(alignment: .leading, spacing: 2) {
                        Text(title)
                            .font(.aiku(15, .bold))
                            .foregroundStyle(AikuColor.text)
                        Text(isFound ? "\(coordinates) — view on map" : "Detect your location before you scan")
                            .font(.aiku(12))
                            .foregroundStyle(AikuColor.text3)
                    }
                    .frame(maxWidth: .infinity, alignment: .leading)
                }
                .accessibilityElement(children: .combine)
                AikuButton(
                    isFound ? "Update location" : "Detect my location",
                    kind: .tertiary,
                    size: .regular,
                    isLoading: location == .detecting,
                    action: onDetect
                )
                .accessibilityIdentifier("clocking.detectLocation")
            }
            .padding(14)
            .background(isFound ? AikuColor.successSoft : AikuColor.surface2, in: RoundedRectangle(cornerRadius: 12))
            .overlay(RoundedRectangle(cornerRadius: 12).strokeBorder(isFound ? AikuColor.success : AikuColor.borderStrong))
            if location == .needed {
                Text("Waiting for your location…")
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            }
            Button(action: onOpenCamera) {
                HStack(spacing: 8) {
                    AppIcon.camera.image.font(.system(size: 18))
                    Text("Open camera").font(.aiku(16, .semibold))
                }
            }
            .buttonStyle(AikuButtonStyle(kind: .primary, size: .large))
            .disabled(location != .found)
            .accessibilityIdentifier("clocking.openCamera")
            Text("Scan the QR code on the clocking machine. The machine decides where you clocked.")
                .font(.aiku(12))
                .foregroundStyle(AikuColor.text4)
                .multilineTextAlignment(.center)
                .frame(maxWidth: .infinity)
        }
    }

    private var isFound: Bool { location == .found }

    private var title: String {
        switch location {
        case .found: "Location found"
        case .detecting: "Detecting your location…"
        case .needed: "Location needed"
        }
    }
}

struct ClockingCodePanel<Code: View>: View {
    let instruction: String
    @ViewBuilder var code: Code

    var body: some View {
        VStack(spacing: 14) {
            Text(instruction)
                .font(.aiku(14))
                .foregroundStyle(AikuColor.text3)
                .multilineTextAlignment(.center)
            code
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 20)
        .frame(maxWidth: .infinity)
        .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 16))
        .overlay(RoundedRectangle(cornerRadius: 16).strokeBorder(AikuColor.border))
    }
}

struct ClockingPinCode: View {
    let pin: String

    var body: some View {
        Text(pin)
            .font(.aiku(44, .bold))
            .tracking(13)
            .monospacedDigit()
            .foregroundStyle(AikuColor.text)
            .padding(.leading, 13)
            .accessibilityLabel("PIN \(pin.map(String.init).joined(separator: " "))")
    }
}

struct ClockingBarcodeImage: View {
    let code: String

    var body: some View {
        VStack(spacing: 14) {
            HStack(spacing: 2) {
                ForEach(ClockingCodePattern.bars) { bar in
                    Rectangle()
                        .fill(bar.isDark ? Color(hex: 0x111827) : Color.white)
                        .frame(width: CGFloat(bar.width))
                }
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 10)
            .frame(width: 280, height: 96)
            .background(Color.white, in: RoundedRectangle(cornerRadius: 8))
            .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(AikuColor.border))
            .accessibilityElement()
            .accessibilityLabel("Your clocking barcode")
            Text(code)
                .font(.aiku(13, .semibold))
                .tracking(2.6)
                .foregroundStyle(AikuColor.text2)
        }
    }
}

struct ClockingQrImage: View {
    var body: some View {
        let size = ClockingCodePattern.qrSize
        let cells = ClockingCodePattern.qrCells
        VStack(spacing: 0) {
            ForEach(0..<size, id: \.self) { row in
                HStack(spacing: 0) {
                    ForEach(0..<size, id: \.self) { column in
                        Rectangle().fill(cells[row * size + column] ? Color(hex: 0x111827) : Color.white)
                    }
                }
            }
        }
        .padding(12)
        .frame(width: 200, height: 200)
        .background(Color.white, in: RoundedRectangle(cornerRadius: 8))
        .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(AikuColor.border))
        .accessibilityElement()
        .accessibilityLabel("Your clocking QR code")
    }
}

struct ClockingNoCodeMessage: View {
    let method: ClockingMethod

    var title: String {
        switch method {
        case .pin, .qrCode: "No PIN yet"
        case .barcode: "No barcode yet"
        case .cameraQr: "No QR code yet"
        }
    }

    var message: String {
        switch method {
        case .pin, .qrCode: "No PIN has been generated for you yet. Please contact HR."
        case .barcode: "No barcode has been generated for you yet. Please contact HR."
        case .cameraQr: "No QR code has been generated for you yet. Please contact HR."
        }
    }

    var body: some View {
        SystemStateBody(
            icon: SystemStateIcon(icon: method == .pin ? .hash : (method == .barcode ? .barcode : .qrCode), foreground: AikuColor.text3, background: AikuColor.surface, size: 72, glyph: 34),
            title: title,
            message: Text(message),
            spacing: 14
        )
        .padding(.vertical, 16)
    }
}
