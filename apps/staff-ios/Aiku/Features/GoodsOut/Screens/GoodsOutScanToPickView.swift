import SwiftUI

struct GoodsOutScanToPickView: View {
    let reference: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var log: [GoodsOutScanLogEntry] = []
    @State private var focusLine: GoodsOutLine.ID?
    @State private var amount = 1
    @State private var scannedCounts: [String: Int] = [:]
    private let store = GoodsOutStore.shared

    var body: some View {
        if let note = store.note(reference) {
            AppScreen(title: "Scan to pick", kicker: "Delivery note \(note.reference) · \(note.pickedUnits) / \(note.units) picked") {
                content(note)
            } footer: {
                panel(note)
            }
        }
    }

    private func currentLine(_ note: GoodsOutDeliveryNote) -> GoodsOutLine? {
        if let focusLine, let line = note.line(focusLine), !line.isHandled {
            return line
        }
        return note.nextLineToPick
    }

    @ViewBuilder
    private func content(_ note: GoodsOutDeliveryNote) -> some View {
        if note.state != .picking {
            InlineAlert(tone: .warn, icon: .warning, title: "\(note.reference) is \(note.state.title.lowercased())", message: "Scanning picks only while the delivery note is being picked.")
        } else if let line = currentLine(note) {
            GoodsOutScanPromptCard(
                title: "Listening · camera or Bluetooth scanner",
                location: line.pickLocation,
                detail: "Each scan picks 1 · tap here to simulate a scan of \(line.sko)"
            ) {
                scan(line.sko, in: note)
            }
            .accessibilityIdentifier("goodsOut.simulateScan")
            LinkButton("Simulate a scan of the wrong item", size: 13) {
                scan(GoodsOutScanLogEntry.wrongItemCode(for: note), in: note)
            }
            .frame(minHeight: 44)
        }
        GoodsOutSectionTitle(title: "Scanned")
        if log.isEmpty {
            HelperText("Nothing scanned yet.")
        } else {
            Card {
                ForEach(Array(log.prefix(20).enumerated()), id: \.element.id) { index, entry in
                    if index > 0 {
                        RowDivider()
                    }
                    ListRow(entry.sko, subtitle: entry.detail, chips: [entry.chip]) {
                        ProductThumb(size: 48, label: "Product photo of \(entry.sko)")
                    } trailing: {
                        EmptyView()
                    }
                }
            }
        }
        HelperText("A Bluetooth scanner types the code and Enter; the screen captures it without tapping a field, like the web. No on-screen keyboard is needed.")
    }

    @ViewBuilder
    private func panel(_ note: GoodsOutDeliveryNote) -> some View {
        if note.state == .picking, let line = currentLine(note) {
            let pickAmount = min(max(1, amount), max(1, line.remaining))
            HStack(spacing: 12) {
                ProductThumb(size: 56, label: "Product photo of \(line.sko)")
                VStack(alignment: .leading, spacing: 2) {
                    HStack(spacing: 8) {
                        Text(line.sko)
                            .font(.aiku(16, .bold))
                            .foregroundStyle(AikuColor.text)
                        if let count = scannedCounts[line.sko], count > 0 {
                            StatusChip("Picked \(count)", tone: .success)
                        }
                    }
                    Text("\(line.pickLocation) · \(line.name)")
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                        .lineLimit(2)
                    Text("\(line.remaining) left on this item")
                        .font(.aiku(14, .bold))
                        .foregroundStyle(AikuColor.warn)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
            }
            AikuButton("Pick all \(line.remaining)", icon: .check) {
                let previous = store.pickAll(note.reference, line: line.id)
                GoodsOutFeedback.done("\(line.sko) picked ×\(line.remaining)", previous: previous, toasts: toasts)
                amount = 1
            }
            .accessibilityIdentifier("goodsOut.scan.pickAll")
            HStack(spacing: 6) {
                stepButton("−", label: "Less", isEnabled: pickAmount > 1) { amount = pickAmount - 1 }
                Text("\(pickAmount)")
                    .font(.aiku(22, .bold))
                    .foregroundStyle(AikuColor.text)
                    .monospacedDigit()
                    .frame(maxWidth: .infinity)
                    .accessibilityLabel("Amount \(pickAmount)")
                stepButton("+", label: "More", isEnabled: pickAmount < line.remaining) { amount = pickAmount + 1 }
                AikuButton("Pick \(pickAmount)", kind: .tertiary, size: .regular) {
                    let previous = store.pick(note.reference, line: line.id, quantity: pickAmount)
                    GoodsOutFeedback.done("\(line.sko) picked ×\(pickAmount)", previous: previous, toasts: toasts)
                    amount = 1
                }
                .frame(maxWidth: .infinity)
                .layoutPriority(1)
            }
            Text("Keep scanning to count one by one · the panel updates in place")
                .font(.aiku(11))
                .foregroundStyle(AikuColor.text4)
                .multilineTextAlignment(.center)
                .frame(maxWidth: .infinity)
        } else {
            GoodsOutFooterStatus(
                title: note.state == .picking ? "Every line handled" : "Nothing to pick",
                detail: "Go back to the delivery note to carry on."
            )
            AikuButton("Back to delivery note", kind: .secondary) {
                router.back()
            }
        }
    }

    private func stepButton(_ symbol: String, label: String, isEnabled: Bool, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Text(symbol)
                .font(.aiku(22))
                .foregroundStyle(isEnabled ? AikuColor.text : AikuColor.faint)
                .frame(width: 52, height: 48)
                .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 10))
                .overlay(RoundedRectangle(cornerRadius: 10).strokeBorder(AikuColor.borderStrong))
                .contentShape(RoundedRectangle(cornerRadius: 10))
        }
        .buttonStyle(.plain)
        .disabled(!isEnabled)
        .accessibilityLabel(label)
    }

    private func scan(_ code: String, in note: GoodsOutDeliveryNote) {
        let outcome = store.scan(note.reference, code: code)
        log.insert(GoodsOutScanLogEntry(code: code, time: store.now(), outcome: outcome), at: 0)
        switch outcome {
        case .picked(let sko, _):
            scannedCounts[sko, default: 0] += 1
            focusLine = sko
        case .lineComplete(let sko):
            toasts.show("\(sko) is already picked", tone: .warn)
        case .notOnNote(let code):
            toasts.show("\(code) is not on this delivery note", tone: .danger, sticky: false)
        case .notPicking:
            toasts.show("\(note.reference) is not being picked", tone: .warn)
        }
    }
}
