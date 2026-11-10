import SwiftUI

struct GoodsOutPickedBaySheet: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let setsPicked: Bool
    let close: () -> Void
    var onDone: ((GoodsOutDeliveryNote) -> Void)?
    private let store = GoodsOutStore.shared
    @State private var selected: String?

    init(note: GoodsOutDeliveryNote, setsPicked: Bool, close: @escaping () -> Void, onDone: ((GoodsOutDeliveryNote) -> Void)? = nil) {
        self.note = note
        self.setsPicked = setsPicked
        self.close = close
        self.onDone = onDone
        let choices = GoodsOutStore.shared.bayChoices(for: note.reference)
        _selected = State(initialValue: note.pickedBay ?? choices.first?.id)
    }

    var body: some View {
        SheetScaffold(title: "Select picked bay", subtitle: "Delivery note \(note.reference) · leave the picked goods here for packing") {
            ForEach(store.bayChoices(for: note.reference)) { choice in
                RadioCard(choice.title, subtitle: choice.subtitle, isSelected: selected == choice.id) {
                    selected = choice.id
                }
            }
            HelperText("Only when the warehouse uses picked bays. With waiting lines, this is where the goods stay until the missing products arrive.")
            LinkButton("All picked bays") {
                close()
                router.open(DispatchRoute.pickedBays)
            }
        } footer: {
            AikuButton(primaryTitle) {
                guard let bay = selected else { return }
                if setsPicked {
                    let previous = store.setAsPicked(note.reference, bay: bay)
                    close()
                    let updated = store.note(note.reference)
                    if updated?.state == .waiting {
                        GoodsOutFeedback.done("\(note.reference) is waiting · goods stay in \(bay)", tone: .warn, previous: previous, toasts: toasts)
                    } else {
                        GoodsOutFeedback.done("\(note.reference) set as picked · \(bay)", previous: previous, toasts: toasts)
                    }
                    if let updated, previous != nil {
                        onDone?(updated)
                    }
                } else {
                    let previous = store.changePickedBay(note.reference, to: bay)
                    close()
                    GoodsOutFeedback.done("Picked goods moved to \(bay)", previous: previous, toasts: toasts)
                }
            }
            .disabled(selected == nil)
            .accessibilityIdentifier("goodsOut.confirmPickedBay")
            AikuButton("Cancel", kind: .tertiary, action: close)
        }
    }

    private var primaryTitle: String {
        let bay = selected ?? "bay"
        return setsPicked ? "Set as picked · \(bay)" : "Move to \(bay)"
    }
}
