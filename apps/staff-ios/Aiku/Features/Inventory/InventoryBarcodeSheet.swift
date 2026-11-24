import SwiftUI

struct InventoryShownBarcode: Hashable, Sendable {
    let title: String
    let value: String
}

struct InventoryBarcodeSheet: View {
    let barcode: InventoryShownBarcode
    let subtitle: String
    let onClose: () -> Void
    @State private var modules: [Bool]?

    init(barcode: InventoryShownBarcode, subtitle: String, onClose: @escaping () -> Void) {
        self.barcode = barcode
        self.subtitle = subtitle
        self.onClose = onClose
        _modules = State(initialValue: LinearBarcodeModules.of(barcode.value))
    }

    var body: some View {
        SheetScaffold(title: barcode.title, subtitle: subtitle) {
            VStack(spacing: 10) {
                if let modules {
                    LinearBarcodeView(modules: modules)
                        .accessibilityElement()
                        .accessibilityLabel(L10n.format("Barcode %@", barcode.value))
                } else {
                    Text(L10n.text("This code can’t be drawn as a barcode. Type it in instead."))
                        .font(.aiku(13))
                        .foregroundStyle(LinearBarcodeView.ink)
                        .multilineTextAlignment(.center)
                }
                Text(BarcodeEncoding.trimmed(barcode.value))
                    .font(.system(size: 20, weight: .semibold, design: .monospaced))
                    .tracking(2)
                    .foregroundStyle(LinearBarcodeView.ink)
                    .lineLimit(1)
                    .minimumScaleFactor(0.5)
                    .textSelection(.enabled)
            }
            .padding(24)
            .frame(maxWidth: .infinity)
            .background(LinearBarcodeView.paper, in: RoundedRectangle(cornerRadius: 12))
            .overlay(RoundedRectangle(cornerRadius: 12).strokeBorder(AikuColor.border))
        } footer: {
            AikuButton("Close", kind: .tertiary, action: onClose)
                .accessibilityIdentifier("inventory.barcode.close")
        }
        .fullScreenBrightness()
    }
}
