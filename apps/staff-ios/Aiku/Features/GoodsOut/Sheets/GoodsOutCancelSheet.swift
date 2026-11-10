import SwiftUI

struct GoodsOutCancelSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let present: (GoodsOutNoteSheet) -> Void
    let close: () -> Void
    private let store = GoodsOutStore.shared

    var body: some View {
        SheetScaffold(title: "Cancel delivery note \(note.reference)?", subtitle: "This cannot be undone from the app") {
            HelperText("Only a dispatching supervisor, an org admin, or someone with order or CRM edit rights can cancel. Dispatched notes can’t be cancelled. Picks already made can be reused if the order gets a replacement delivery note.")
        } footer: {
            AikuButton("Cancel delivery note", kind: .danger) {
                let previous = store.cancel(note.reference)
                close()
                GoodsOutFeedback.done("\(note.reference) cancelled", tone: .danger, previous: previous, toasts: toasts, offersUndo: false)
            }
            .accessibilityIdentifier("goodsOut.confirmCancel")
            AikuButton("Keep it", kind: .tertiary) {
                present(.moreActions)
            }
        }
    }
}
