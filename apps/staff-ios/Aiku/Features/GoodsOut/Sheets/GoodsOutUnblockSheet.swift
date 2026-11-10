import SwiftUI

struct GoodsOutUnblockSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let close: () -> Void
    private let store = GoodsOutStore.shared

    var body: some View {
        let count = note.waitingLines.count
        SheetScaffold(
            title: "Send this delivery note back to picking?",
            subtitle: "Delivery note \(note.reference) · \(count) waiting \(count == 1 ? "line" : "lines")"
        ) {
            HelperText("The picker can then pick the waiting lines again. Goods already picked stay in \(note.pickedBay ?? "the picked bay").")
        } footer: {
            AikuButton("Back to picking") {
                let previous = store.backToPicking(note.reference)
                close()
                GoodsOutFeedback.done("\(note.reference) is back to picking", tone: .info, previous: previous, toasts: toasts)
            }
            .accessibilityIdentifier("goodsOut.confirmBackToPicking")
            AikuButton("No, keep waiting", kind: .tertiary, action: close)
        }
    }
}
