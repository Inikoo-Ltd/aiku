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
            if note.isRemote, step != .setAsPicked {
                HelperText(remoteHint)
            } else {
                HelperText("Picked quantities are kept, you can pick, change or mark lines as not picked again. The other undo steps work the same way: Undo packing (back to picked), Unpack (removes the parcels), Undispatch (back to finalised).")
            }
        } footer: {
            AikuButton(step.confirmTitle, icon: .undo, isLoading: store.isWorking(on: note.reference)) {
                if note.isRemote {
                    runRemote()
                    return
                }
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

    private var remoteHint: String {
        switch step {
        case .packing: "The delivery note is picked again. If something on it is still waiting, it is set as waiting instead."
        case .unpack: "The delivery note goes back to packing, so its lines and parcels can be changed."
        default: ""
        }
    }

    private func runRemote() {
        let reference = note.reference
        switch step {
        case .setAsPicked:
            GoodsOutRun.action(.undoSetAsPicked, on: reference, fallback: "\(reference) is back to picking", toasts: toasts) { close() }
        case .packing:
            GoodsOutRun.action(.undoPacking, on: reference, fallback: "Packing undone. The delivery note is picked again.", toasts: toasts) { close() }
        case .unpack:
            GoodsOutRun.action(.unpack, on: reference, fallback: "Unpacked. The delivery note is being packed again.", toasts: toasts) { close() }
        case .dispatch:
            close()
            toasts.show("Undispatch isn’t available in the app", tone: .warn)
        }
    }
}
