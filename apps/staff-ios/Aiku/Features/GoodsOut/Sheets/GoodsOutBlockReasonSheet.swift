import SwiftUI

struct GoodsOutBlockReasonSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let line: GoodsOutLine
    let present: (GoodsOutNoteSheet) -> Void
    let close: () -> Void
    private let store = GoodsOutStore.shared
    @State private var reason: GoodsOutWaitingReason
    @State private var text = ""

    init(note: GoodsOutDeliveryNote, line: GoodsOutLine, initialReason: GoodsOutWaitingReason, present: @escaping (GoodsOutNoteSheet) -> Void, close: @escaping () -> Void) {
        self.note = note
        self.line = line
        self.present = present
        self.close = close
        _reason = State(initialValue: initialReason)
        _text = State(initialValue: line.waitingNote ?? "")
    }

    var body: some View {
        SheetScaffold(title: L10n.format("Set %@ as waiting", line.sko), subtitle: L10n.format("Delivery note %@ · %lld not found in %@", note.reference, line.remaining, line.pickLocation)) {
            ForEach(GoodsOutWaitingReason.allCases, id: \.self) { option in
                RadioCard(option.title, subtitle: option.detail, isSelected: reason == option) {
                    reason = option
                }
            }
            AikuTextArea("Note", text: $text, placeholder: L10n.text("Location empty, check overstock"))
            HelperText("When picking finishes with waiting lines, the delivery note moves to Waiting.")
        } footer: {
            AikuButton("Set as waiting", icon: .pause, isLoading: store.isWorking(on: note.reference)) {
                if note.isRemote {
                    guard let action = line.waitingAction(kind: reason == .warehouse ? .warehouse : .crm, note: text) else { return }
                    GoodsOutRun.action(action, on: note.reference, fallback: waitingMessage, toasts: toasts) { close() }
                    return
                }
                let previous = store.setWaiting(note.reference, line: line.id, reason: reason, note: text)
                close()
                GoodsOutFeedback.done(waitingMessage, tone: .warn, previous: previous, toasts: toasts)
            }
            .accessibilityIdentifier("goodsOut.setWaiting")
            AikuButton("Cancel", kind: .tertiary) {
                present(.itemActions(line: line.id))
            }
        }
    }

    private var waitingMessage: String {
        switch reason {
        case .warehouse: L10n.format("%@ set as waiting · warehouse", line.sko)
        case .customerService: L10n.format("%@ set as waiting · cs", line.sko)
        }
    }
}
