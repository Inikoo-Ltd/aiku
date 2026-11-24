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
            subtitle: L10n.format("Delivery note %@ · %lld waiting lines", note.reference, count)
        ) {
            HelperText(note.pickedBay.map { L10n.format("The picker can then pick the waiting lines again. Goods already picked stay in %@.", $0) }
                ?? "The picker can then pick the waiting lines again. Goods already picked stay in the picked bay.")
        } footer: {
            AikuButton("Back to picking", look: GoodsOutActionKey.backToPicking.look, isLoading: store.isWorking(on: note.reference)) {
                if note.isRemote {
                    GoodsOutRun.action(.backToPicking, on: note.reference, fallback: L10n.format("%@ is back to picking", note.reference), toasts: toasts) { close() }
                    return
                }
                let previous = store.backToPicking(note.reference)
                close()
                GoodsOutFeedback.done(L10n.format("%@ is back to picking", note.reference), tone: .info, previous: previous, toasts: toasts)
            }
            .accessibilityIdentifier("goodsOut.confirmBackToPicking")
            AikuButton("No, keep waiting", kind: .tertiary, action: close)
        }
    }
}
