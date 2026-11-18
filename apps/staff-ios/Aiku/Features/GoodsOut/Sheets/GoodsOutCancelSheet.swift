import SwiftUI

struct GoodsOutCancelSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let present: (GoodsOutNoteSheet) -> Void
    let close: () -> Void
    private let store = GoodsOutStore.shared

    var body: some View {
        if note.isRemote {
            GoodsOutRemoteCancelSheet(note: note, present: present, close: close)
        } else {
            sampleBody
        }
    }

    private var sampleBody: some View {
        SheetScaffold(title: "Cancel delivery note \(note.reference)?", subtitle: "This cannot be undone from the app") {
            HelperText("Only a dispatching supervisor, an org admin, or someone with order or CRM edit rights can cancel. Dispatched notes can’t be cancelled. Picks already made can be reused if the order gets a replacement delivery note.")
        } footer: {
            AikuButton("Cancel delivery note", look: GoodsOutActionKey.cancel.look.forConfirm) {
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

struct GoodsOutRemoteCancelSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let present: (GoodsOutNoteSheet) -> Void
    let close: () -> Void
    @State private var createReturn = true
    @State private var problem: String?
    private let store = GoodsOutStore.shared

    var body: some View {
        SheetScaffold(title: "Cancel delivery note \(note.reference)?", subtitle: "This cannot be undone from the app") {
            HelperText("Packing is undone, the trolley and picked bay are freed, and the order is rolled back unless it is already invoiced.")
            ToggleRow(
                "Put picked items back via a return",
                subtitle: createReturn
                    ? "A return note is made for the picked items. Their stock comes back when the return is put away."
                    : "The picks are undone, so their stock counts as back in its locations at once. Put the goods back on the shelf now.",
                isOn: $createReturn
            )
            if let problem {
                InlineAlert(tone: .danger, title: problem)
            }
        } footer: {
            AikuButton(note.action(.cancel)?.label ?? "Cancel delivery note", look: GoodsOutActionKey.cancel.look.forConfirm, isLoading: store.isWorking(on: note.reference)) {
                Task {
                    let result = await store.write(.cancel(createReturn: createReturn), on: note.reference)
                    if GoodsOutWriteFeedback.show(result, fallback: "Delivery note cancelled", toasts: toasts, showsFailure: false) {
                        close()
                    } else {
                        problem = result.failureMessage
                    }
                }
            }
            .accessibilityIdentifier("goodsOut.confirmCancel")
            AikuButton("Keep it", kind: .tertiary) {
                present(.moreActions)
            }
        }
    }
}
