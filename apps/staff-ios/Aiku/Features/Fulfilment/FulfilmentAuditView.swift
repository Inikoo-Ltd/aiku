import SwiftUI

struct FulfilmentAuditView: View {
    let palletReference: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var isScanning = false
    @State private var scanFeedback: String?
    private let store = FulfilmentStore.shared

    var body: some View {
        let audit = store.audit(forPallet: palletReference)
        AppScreen(title: audit.reference, kicker: "Stored item audit · Pallet · In process") {
            FulfilmentScanCard(
                title: "Scan to count",
                detail: "Scan each item on pallet \(audit.palletReference)",
                highlight: audit.focusItemCode ?? "No items yet",
                identifier: "fulfilment.audit.scan"
            ) {
                scanFeedback = nil
                isScanning = true
            }
            if let line = focusLine(audit) {
                panel(line, audit: audit)
            }
            Card {
                ForEach(audit.lines) { line in
                    ListRow(line.itemCode, subtitle: line.subtitle, chips: line.chip.map { [$0] } ?? []) {
                        ProductThumb(size: 48, label: "Product photo of \(line.itemCode)")
                    } trailing: {
                        FulfilmentCountLabel(done: line.counted ?? 0, total: line.expected, tone: line.countTone)
                    }
                    RowDivider()
                }
                ListRow("Add an item not on the list", subtitle: "Scan it to add (addition)", icon: .scan, iconTone: .neutral, action: {
                    if let code = store.addUnlistedItem(toAuditOf: audit.palletReference) {
                        toasts.show("\(code) added · addition +1")
                    } else {
                        toasts.show("No other stored items for this customer", tone: .warn)
                    }
                })
                .accessibilityIdentifier("fulfilment.audit.addItem")
            }
            FulfilmentNote("An audit covers one pallet or the whole customer. Completing it applies the counted quantities.")
        } footer: {
            AikuButton("Complete audit") {
                store.completeAudit(ofPallet: audit.palletReference)
                toasts.show("\(audit.reference) completed · quantities applied")
                router.back()
            }
            .accessibilityIdentifier("fulfilment.audit.complete")
        }
        .fullScreenCover(isPresented: $isScanning) {
            let current = store.audit(forPallet: palletReference)
            FulfilmentCameraScan(
                kicker: "Audit · \(current.palletReference)",
                title: "Scan to count",
                feedback: scanFeedback,
                onClose: { isScanning = false },
                onScan: {
                    if let code = store.scanNextAuditItem(palletReference: current.palletReference) {
                        scanFeedback = "\(code) · counted 1"
                    }
                }
            ) {
                if let line = focusLine(current) {
                    panel(line, audit: current)
                }
            }
        }
    }

    private func focusLine(_ audit: FulfilmentAudit) -> FulfilmentAuditLine? {
        guard let code = audit.currentItemCode else { return nil }
        return audit.lines.first { $0.itemCode == code }
    }

    private func panel(_ line: FulfilmentAuditLine, audit: FulfilmentAudit) -> some View {
        let left = max(0, line.expected - (line.counted ?? 0))
        return FulfilmentScanPanel(
            title: "\(line.itemCode) · Counted \(line.counted ?? 0)",
            detail: left > 0 ? "\(left) left on this item" : "All \(line.expected) counted",
            allTitle: "Count all \(left)",
            verb: "Count",
            maximum: left,
            onAll: { store.countAll(line.itemCode, inAuditOf: audit.palletReference) },
            onAmount: { store.count(line.itemCode, by: $0, inAuditOf: audit.palletReference) }
        )
    }
}
