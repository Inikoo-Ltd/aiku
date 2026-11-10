import SwiftUI

struct GoodsOutPickerSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let close: () -> Void
    private let store = GoodsOutStore.shared
    @State private var search = ""
    @State private var selected: String
    @State private var packerSameAsPicker: Bool

    init(note: GoodsOutDeliveryNote, close: @escaping () -> Void) {
        self.note = note
        self.close = close
        _selected = State(initialValue: note.picker ?? GoodsOutStore.shared.currentUser)
        _packerSameAsPicker = State(initialValue: note.packerSameAsPicker)
    }

    private var choices: [GoodsOutChoice] {
        let all = store.pickerChoices()
        let query = search.trimmingCharacters(in: .whitespaces)
        guard !query.isEmpty else { return all }
        return all.filter { $0.title.localizedCaseInsensitiveContains(query) }
    }

    var body: some View {
        SheetScaffold(
            title: note.picker == nil ? "Assign picker" : "Change picker",
            subtitle: "Delivery note \(note.reference) · picker now: \(note.picker ?? "nobody")"
        ) {
            SearchField(text: $search, placeholder: "Search pickers")
            if choices.isEmpty {
                HelperText("No picker matches “\(search)”.")
            }
            ForEach(choices) { choice in
                RadioCard(choice.title, subtitle: choice.subtitle, isSelected: selected == choice.id) {
                    selected = choice.id
                }
            }
            SheetSectionLabel("Packer")
            RadioCard("Same as picker", subtitle: "Change it later from the delivery note", isSelected: packerSameAsPicker) {
                packerSameAsPicker.toggle()
            }
        } footer: {
            AikuButton("Assign \(selected)") {
                let previous = store.assignPicker(note.reference, picker: selected, packerSameAsPicker: packerSameAsPicker)
                close()
                GoodsOutFeedback.done("\(selected) is picking \(note.reference)", previous: previous, toasts: toasts)
            }
            .accessibilityIdentifier("goodsOut.assignPicker")
            if note.picker != nil {
                AikuButton("Remove picker", kind: .danger) {
                    let previous = store.removePicker(note.reference)
                    close()
                    GoodsOutFeedback.done("Picker removed · \(note.reference) is back to To do", tone: .info, previous: previous, toasts: toasts)
                }
            }
            AikuButton("Cancel", kind: .tertiary, action: close)
        }
    }
}
