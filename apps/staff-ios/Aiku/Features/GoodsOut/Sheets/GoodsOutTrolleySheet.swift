import SwiftUI

struct GoodsOutTrolleySheet: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let startsPicking: Bool
    let close: () -> Void
    private let store = GoodsOutStore.shared
    @State private var selected: String?

    init(note: GoodsOutDeliveryNote, startsPicking: Bool, close: @escaping () -> Void) {
        self.note = note
        self.startsPicking = startsPicking
        self.close = close
        let available = GoodsOutStore.shared.trolleyChoices(for: note.reference).filter(\.isAvailable)
        let current = note.trolley.flatMap { code in available.first { $0.id == code }?.id }
        _selected = State(initialValue: current ?? available.first?.id)
    }

    var body: some View {
        let choices = store.trolleyChoices(for: note.reference)
        let available = choices.filter(\.isAvailable)
        let unavailable = choices.filter { !$0.isAvailable }
        SheetScaffold(title: startsPicking ? "Select trolley to start picking" : "Change trolley", subtitle: "Delivery note \(note.reference)") {
            SheetSectionLabel("Available trolleys")
            if available.isEmpty {
                HelperText("Every trolley is in use.")
            }
            ForEach(available) { choice in
                RadioCard(choice.title, subtitle: choice.subtitle, isSelected: selected == choice.id) {
                    selected = choice.id
                }
            }
            if !unavailable.isEmpty {
                SheetSectionLabel("Unavailable trolleys")
                VStack(spacing: 8) {
                    ForEach(unavailable) { choice in
                        RadioCard(choice.title, subtitle: choice.subtitle, isSelected: false) {}
                            .disabled(true)
                    }
                }
                .opacity(0.55)
            }
            HelperText("Only when this warehouse uses trolleys. Otherwise Start picking begins straight away.")
            LinkButton("All trolleys") {
                close()
                router.open(DispatchRoute.trolleys)
            }
        } footer: {
            AikuButton(primaryTitle) {
                if startsPicking {
                    let previous = store.startPicking(note.reference, trolley: selected)
                    close()
                    GoodsOutFeedback.done("Picking \(note.reference)\(selected.map { " with \($0)" } ?? "")", previous: previous, toasts: toasts)
                } else {
                    let previous = store.changeTrolley(note.reference, to: selected)
                    close()
                    GoodsOutFeedback.done("Picking into \(selected ?? "no trolley")", previous: previous, toasts: toasts)
                }
            }
            .accessibilityIdentifier("goodsOut.confirmTrolley")
            AikuButton("Cancel", kind: .tertiary, action: close)
        }
    }

    private var primaryTitle: String {
        if startsPicking {
            return selected.map { "Start picking with \($0)" } ?? "Start picking"
        }
        return selected.map { "Use \($0)" } ?? "Pick without a trolley"
    }
}
