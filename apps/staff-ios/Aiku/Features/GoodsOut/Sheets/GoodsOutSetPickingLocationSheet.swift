import SwiftUI

struct GoodsOutSetPickingLocationSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let line: GoodsOutLine
    let present: (GoodsOutNoteSheet) -> Void
    private let store = GoodsOutStore.shared
    @State private var sections: [GoodsOutPickingLocationSection]

    init(note: GoodsOutDeliveryNote, line: GoodsOutLine, present: @escaping (GoodsOutNoteSheet) -> Void) {
        self.note = note
        self.line = line
        self.present = present
        _sections = State(initialValue: GoodsOutSampleData.pickingLocationSections(for: line, channel: note.channel))
    }

    var body: some View {
        SheetScaffold(title: "Picking location · \(line.sko)", subtitle: "\(line.name) · one per channel") {
            HStack(spacing: 12) {
                ProductThumb(size: 56, label: "Product photo of \(line.sko)")
                Text("Pickers are sent to the active picking location of the order’s channel.")
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            }
            ForEach($sections) { $section in
                SheetSectionLabel(section.channel.title)
                ForEach(section.options) { option in
                    RadioCard(option.code, subtitle: option.stockText, isSelected: section.selected == option.code) {
                        section.selected = option.code
                    }
                }
            }
            HelperText("Same as Stocks management on the web. Only users who can edit stock can change it.")
        } footer: {
            AikuButton("Save picking locations") {
                if let own = sections.first(where: { $0.channel == note.channel }) {
                    store.setPickingLocation(note.reference, line: line.id, location: own.selected)
                }
                toasts.show("Picking locations saved for \(line.sko)")
                present(.pickLocation(line: line.id))
            }
            .disabled(!store.rights.canEditStock)
            AikuButton("Cancel", kind: .tertiary) {
                present(.pickLocation(line: line.id))
            }
        }
    }
}
