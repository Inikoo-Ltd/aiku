import SwiftUI

struct GoodsOutUndoSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let step: GoodsOutUndoStep
    let present: (GoodsOutNoteSheet) -> Void
    let close: () -> Void
    private let store = GoodsOutStore.shared

    var body: some View {
        SheetScaffold(title: step.question, subtitle: "Delivery note \(note.reference) goes back to \(step.target.title.lowercased())") {
            HelperText("Picked quantities are kept, you can pick, change or mark lines as not picked again. The other undo steps work the same way: Undo packing (back to picked), Unpack (removes the parcels), Undispatch (back to finalised).")
        } footer: {
            AikuButton(step.confirmTitle, icon: .undo) {
                let previous = store.undo(note.reference, step: step)
                close()
                GoodsOutFeedback.done("\(note.reference) is back to \(step.target.title.lowercased())", tone: .info, previous: previous, toasts: toasts, offersUndo: false)
            }
            .accessibilityIdentifier("goodsOut.confirmUndo")
            AikuButton("Cancel", kind: .tertiary) {
                present(.moreActions)
            }
        }
    }
}
