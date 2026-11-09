import SwiftUI

struct ScanCameraView: View {
    @Environment(AppRouter.self) private var router
    @Environment(\.dismiss) private var dismiss
    let context: ScanContext
    @State private var isTorchOn = false

    private var deliveryItem: StockDeliveryItem? {
        guard let reference = context.reference, let delivery = SampleData.stockDelivery(reference) else { return nil }
        return delivery.items.first { $0.received == nil }
    }

    var body: some View {
        ZStack {
            RadialGradient(colors: [Color(hex: 0x374151), AikuColor.textPrimary], center: UnitPoint(x: 0.5, y: 0.35), startRadius: 10, endRadius: 520)
                .ignoresSafeArea()

            VStack(spacing: 16) {
                topBar
                modeSwitch
                Spacer()
                viewfinder
                Text("Point at the \(deliveryItem == nil ? "barcode" : "item barcode")")
                    .font(.subheadline.weight(.medium))
                    .foregroundStyle(AikuColor.background)
                if let item = deliveryItem {
                    ScanFeedbackBanner(feedback: .correct(sku: item.skuReference, progress: "+1 · 37 of \(item.expected)"))
                        .padding(.horizontal, 16)
                }
                Spacer()
                bottomSheet
            }
        }
        .accessibilityIdentifier("scan.camera")
    }

    private var topBar: some View {
        HStack(spacing: 12) {
            circleButton("xmark", label: "Close scanner") { dismiss() }
            VStack(spacing: 0) {
                Text(context.title).font(.caption).foregroundStyle(AikuColor.indigoMuted)
                if let reference = context.reference {
                    Text(reference).font(.headline).foregroundStyle(.white)
                }
            }
            .frame(maxWidth: .infinity)
            circleButton(isTorchOn ? "flashlight.on.fill" : "flashlight.off.fill", label: isTorchOn ? "Turn torch off" : "Turn torch on") {
                isTorchOn.toggle()
            }
        }
        .padding(.horizontal, 16)
        .padding(.top, 8)
    }

    private func circleButton(_ systemImage: String, label: String, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Image(systemName: systemImage)
                .font(.system(size: 18, weight: .semibold))
                .foregroundStyle(.white)
                .frame(width: 44, height: 44)
                .background(AikuColor.textPrimary.opacity(0.6), in: Circle())
        }
        .accessibilityLabel(label)
    }

    private var modeSwitch: some View {
        HStack(spacing: 4) {
            Text("Camera")
                .font(.subheadline.weight(.semibold))
                .foregroundStyle(AikuColor.navy)
                .frame(maxWidth: .infinity, minHeight: 36)
                .background(AikuColor.yellow, in: Capsule())
            Button {
                switchToScannerDevice()
            } label: {
                Text("Scanner device")
                    .font(.subheadline.weight(.semibold))
                    .foregroundStyle(AikuColor.background)
                    .frame(maxWidth: .infinity, minHeight: 36)
            }
        }
        .padding(4)
        .background(AikuColor.textPrimary.opacity(0.6), in: Capsule())
        .frame(width: 280)
    }

    private var viewfinder: some View {
        RoundedRectangle(cornerRadius: 16)
            .strokeBorder(AikuColor.yellow, style: StrokeStyle(lineWidth: 4, dash: [44, 160]))
            .frame(width: 280, height: 180)
            .overlay(Rectangle().fill(Color.red.opacity(0.8)).frame(height: 2).padding(.horizontal, 24))
            .accessibilityHidden(true)
    }

    private var bottomSheet: some View {
        VStack(alignment: .leading, spacing: 14) {
            if let item = deliveryItem {
                HStack(alignment: .firstTextBaseline) {
                    VStack(alignment: .leading, spacing: 2) {
                        Text(item.skuReference).font(.headline)
                        Text(item.name).font(.subheadline).foregroundStyle(AikuColor.textSecondary)
                    }
                    Spacer()
                    VStack(alignment: .trailing, spacing: 0) {
                        (Text("37").font(.title.bold()) + Text(" / \(item.expected)").font(.subheadline).foregroundStyle(AikuColor.textSecondary))
                        Text("received").font(.caption).foregroundStyle(AikuColor.textSecondary)
                    }
                }
                .foregroundStyle(AikuColor.textPrimary)
                ProgressBar(value: 37 / Double(item.expected))
            } else {
                VStack(alignment: .leading, spacing: 2) {
                    Text("Scan a barcode").font(.headline).foregroundStyle(AikuColor.textPrimary)
                    Text("SKU, location or delivery note. The right screen opens.")
                        .font(.subheadline)
                        .foregroundStyle(AikuColor.textSecondary)
                }
            }
            HStack(spacing: 10) {
                ActionButton(title: "Type code", kind: .outline) {}
                ActionButton(title: "Enter quantity", kind: .secondary) {}
                    .opacity(deliveryItem == nil ? 0 : 1)
                    .disabled(deliveryItem == nil)
            }
            if deliveryItem != nil {
                ActionButton(title: "Done · back to items") { dismiss() }
            }
        }
        .padding(20)
        .background(
            UnevenRoundedRectangle(topLeadingRadius: 20, topTrailingRadius: 20)
                .fill(AikuColor.surface)
                .ignoresSafeArea(edges: .bottom)
        )
    }

    private func switchToScannerDevice() {
        dismiss()
        if let reference = context.reference, reference.hasPrefix("DN-") {
            router.open(.scannerDevice(deliveryNote: reference))
        }
    }
}

#Preview {
    ScanCameraView(context: ScanContext(title: "Goods in · Receiving", reference: "SD-1093"))
        .environment(AppRouter())
}
