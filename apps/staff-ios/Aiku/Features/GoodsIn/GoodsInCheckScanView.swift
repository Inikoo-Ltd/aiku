import SwiftUI

struct GoodsInScanFeedback: Equatable {
    let tone: Tone
    let title: String
    let detail: String
    var note: String?
}

struct GoodsInCheckScanView: View {
    let reference: String
    let onClose: () -> Void
    @State private var feedback: GoodsInScanFeedback?
    @State private var editingItem: GoodsInItem?
    @State private var codeRequest: GoodsInCodeRequest?
    private let store = GoodsInStore.shared

    private var delivery: GoodsInDelivery { store.delivery(reference) }

    var body: some View {
        CameraScanScreen(
            kicker: "Goods in · Checking",
            title: reference,
            hint: "Point at the item barcode",
            onClose: onClose,
            onSimulateScan: simulateScan
        ) {
            if let feedback {
                ScanFeedbackBanner(tone: feedback.tone, title: feedback.title, detail: feedback.detail, note: feedback.note)
            }
        } panel: {
            panel
        }
        .aikuSheet(item: $editingItem) { item in
            GoodsInCheckQuantitySheet(item: item) { quantity in
                store.setChecked(reference, sko: item.sko, quantity: quantity)
                feedback = GoodsInScanFeedback(tone: .success, title: item.sko, detail: "· \(quantity.grouped) of \(item.delivered.grouped) checked")
                editingItem = nil
            }
        }
        .aikuSheet(item: $codeRequest) { _ in
            GoodsInTypeCodeSheet(
                title: "Type code",
                subtitle: "Type the SKO code when the barcode does not scan",
                label: "SKO code",
                placeholder: "SKO-01187",
                actionTitle: "Count 1"
            ) { code in
                register(code)
                codeRequest = nil
            } onCancel: {
                codeRequest = nil
            }
        }
    }

    @ViewBuilder
    private var panel: some View {
        if let item = delivery.currentScanItem {
            let checked = item.checked ?? 0
            HStack(spacing: 12) {
                ProductThumb(size: 48, label: "Product photo of \(item.sko)")
                VStack(alignment: .leading, spacing: 2) {
                    Text(item.sko)
                        .font(.aiku(16, .bold))
                        .foregroundStyle(AikuColor.text)
                    Text(item.name)
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                VStack(alignment: .trailing, spacing: 0) {
                    (Text(checked.grouped).font(.aiku(22, .bold)).foregroundColor(AikuColor.text)
                        + Text(" / \(item.delivered.grouped)").font(.aiku(14, .medium)).foregroundColor(AikuColor.text3))
                        .monospacedDigit()
                    Text("checked")
                        .font(.aiku(11))
                        .foregroundStyle(AikuColor.text3)
                }
            }
            ProgressBar(value: item.delivered > 0 ? Double(checked) / Double(item.delivered) : 0, height: 6)
                .accessibilityLabel("Checked")
            Text("\(item.leftToCheck.grouped) left on this item")
                .font(.aiku(13))
                .foregroundStyle(AikuColor.text3)
                .frame(maxWidth: .infinity, alignment: .leading)
            AikuButton("Check all \(item.leftToCheck.grouped)", kind: .secondary, size: .regular) {
                store.setChecked(reference, sko: item.sko, quantity: item.delivered)
                feedback = GoodsInScanFeedback(tone: .success, title: item.sko, detail: "· all \(item.delivered.grouped) checked")
            }
            .accessibilityIdentifier("goodsIn.scan.checkAll")
            HStack(spacing: 10) {
                AikuButton("Type code", icon: .keyboard, kind: .tertiary, size: .regular) {
                    codeRequest = GoodsInCodeRequest(purpose: .scanItem)
                }
                AikuButton("Set checked quantity", kind: .secondary, size: .regular) {
                    editingItem = item
                }
            }
        } else {
            VStack(alignment: .leading, spacing: 4) {
                Text("All items checked")
                    .font(.aiku(16, .bold))
                    .foregroundStyle(AikuColor.text)
                Text("\(delivery.checkedCount) of \(delivery.items.count) items have a checked quantity.")
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
        }
        AikuButton("Done · back to items", action: onClose)
            .accessibilityIdentifier("goodsIn.scan.done")
    }

    private func simulateScan() {
        guard let item = delivery.currentScanItem else {
            feedback = GoodsInScanFeedback(tone: .warn, title: "Nothing left to check", detail: "· every item has a checked quantity")
            return
        }
        register(item.sko)
    }

    private func register(_ code: String) {
        switch store.registerScan(reference, code: code) {
        case .counted(let sko, let checked, let delivered):
            let complete = checked == delivered ? " · complete" : ""
            feedback = GoodsInScanFeedback(tone: checked > delivered ? .warn : .success, title: sko, detail: "+1 · \(checked.grouped) of \(delivered.grouped)\(complete)", note: "beep + vibrate")
        case .notOnDelivery(let code):
            feedback = GoodsInScanFeedback(tone: .warn, title: "\(code) is not on \(reference)", detail: "· not counted")
        case .alreadyPlaced(let sko):
            feedback = GoodsInScanFeedback(tone: .warn, title: sko, detail: "· already placed, not counted")
        }
    }
}
