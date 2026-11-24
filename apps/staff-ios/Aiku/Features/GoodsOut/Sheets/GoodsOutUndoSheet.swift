import SwiftUI

struct GoodsOutUndoSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let step: GoodsOutUndoStep
    let present: (GoodsOutNoteSheet) -> Void
    let close: () -> Void
    private let store = GoodsOutStore.shared

    var body: some View {
        SheetScaffold(title: step.question, subtitle: goesBackText) {
            if note.isRemote, step != .setAsPicked {
                HelperText(remoteHint)
            } else {
                HelperText("Picked quantities are kept, you can pick, change or mark lines as not picked again. The other undo steps work the same way: Undo packing (back to picked), Unpack (removes the parcels), Undispatch (back to finalised).")
            }
        } footer: {
            AikuButton(step.confirmTitle, look: GoodsOutActionKey.stepBack, isLoading: store.isWorking(on: note.reference)) {
                if note.isRemote {
                    runRemote()
                    return
                }
                let previous = store.undo(note.reference, step: step)
                close()
                GoodsOutFeedback.done(isBackText, tone: .info, previous: previous, toasts: toasts, offersUndo: false)
            }
            .accessibilityIdentifier("goodsOut.confirmUndo")
            AikuButton("Cancel", kind: .tertiary) {
                present(.moreActions)
            }
        }
    }

    private var goesBackText: String {
        switch step {
        case .setAsPicked: L10n.format("Delivery note %@ goes back to picking", note.reference)
        case .packing: L10n.format("Delivery note %@ goes back to picked", note.reference)
        case .unpack: L10n.format("Delivery note %@ goes back to packing", note.reference)
        case .dispatch: L10n.format("Delivery note %@ goes back to finalised", note.reference)
        }
    }

    private var isBackText: String {
        switch step {
        case .setAsPicked: L10n.format("%@ is back to picking", note.reference)
        case .packing: L10n.format("%@ is back to picked", note.reference)
        case .unpack: L10n.format("%@ is back to packing", note.reference)
        case .dispatch: L10n.format("%@ is back to finalised", note.reference)
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
            GoodsOutRun.action(.undoSetAsPicked, on: reference, fallback: L10n.format("%@ is back to picking", reference), toasts: toasts) { close() }
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
