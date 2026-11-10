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
        SheetScaffold(title: "Set \(line.sko) as waiting", subtitle: "Delivery note \(note.reference) · \(line.remaining) not found in \(line.pickLocation)") {
            ForEach(GoodsOutWaitingReason.allCases, id: \.self) { option in
                RadioCard(option.title, subtitle: option.detail, isSelected: reason == option) {
                    reason = option
                }
            }
            AikuTextArea("Note", text: $text, placeholder: "Location empty, check overstock")
            HelperText("When picking finishes with waiting lines, the delivery note moves to Waiting.")
        } footer: {
            AikuButton("Set as waiting", icon: .pause) {
                let previous = store.setWaiting(note.reference, line: line.id, reason: reason, note: text)
                close()
                GoodsOutFeedback.done("\(line.sko) set as \(reason.chipText.lowercased())", tone: .warn, previous: previous, toasts: toasts)
            }
            .accessibilityIdentifier("goodsOut.setWaiting")
            AikuButton("Cancel", kind: .tertiary) {
                present(.itemActions(line: line.id))
            }
        }
    }
}
