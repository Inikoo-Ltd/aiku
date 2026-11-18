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
        let store = GoodsOutStore.shared
        if store.isRemote {
            _selected = State(initialValue: (note.pickerID ?? store.userID).map(String.init) ?? "")
        } else {
            _selected = State(initialValue: note.picker ?? store.currentUser)
        }
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
            if !store.isRemote {
                SheetSectionLabel("Packer")
                RadioCard("Same as picker", subtitle: "Change it later from the delivery note", isSelected: packerSameAsPicker) {
                    packerSameAsPicker.toggle()
                }
            }
        } footer: {
            if store.isRemote {
                remoteFooter
            } else {
            AikuButton("Assign \(selected)") {
                let previous = store.assignPicker(note.reference, picker: selected, packerSameAsPicker: packerSameAsPicker)
                close()
                GoodsOutFeedback.done("\(selected) is picking \(note.reference)", previous: previous, toasts: toasts)
            }
            .accessibilityIdentifier("goodsOut.assignPicker")
            if note.picker != nil {
                AikuButton("Remove picker", look: GoodsOutActionKey.removePicker.look) {
                    let previous = store.removePicker(note.reference)
                    close()
                    GoodsOutFeedback.done("Picker removed · \(note.reference) is back to To do", tone: .info, previous: previous, toasts: toasts)
                }
            }
            }
            AikuButton("Cancel", kind: .tertiary, action: close)
        }
        .task {
            guard store.isRemote else { return }
            async let personnel: Void = store.loadPersonnel()
            await store.loadPickers()
            await personnel
        }
    }

    private var selectedTitle: String {
        store.pickerChoices().first { $0.id == selected }?.title ?? "picker"
    }

    @ViewBuilder
    private var remoteFooter: some View {
        let working = store.isWorking(on: note.reference)
        if store.isRemote, store.isLoadingResources, store.pickers.isEmpty {
            GoodsOutSkeletonCard(rows: 1)
        }
        if let error = store.resourcesError, store.pickers.isEmpty {
            GoodsOutLoadProblem(title: "Couldn’t load pickers", message: error) {
                Task { await store.loadPickers() }
            }
        }
        if let userID = Int(selected) {
            AikuButton("Assign \(selectedTitle)", isLoading: working) {
                GoodsOutRun.action(.setPicker(userID: userID), on: note.reference, fallback: "\(selectedTitle) is picking \(note.reference)", toasts: toasts, onDone: { close() })
            }
            .accessibilityIdentifier("goodsOut.assignPicker")
        }
        if note.action(.removePicker) != nil {
            AikuButton("Remove picker", look: GoodsOutActionKey.removePicker.look) {
                GoodsOutRun.action(.setPicker(userID: nil), on: note.reference, fallback: "Picker removed · \(note.reference) is back to To do", toasts: toasts, onDone: { close() })
            }
        }
    }
}
