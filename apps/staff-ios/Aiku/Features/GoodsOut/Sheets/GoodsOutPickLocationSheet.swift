import SwiftUI

struct GoodsOutPickLocationSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let line: GoodsOutLine
    let present: (GoodsOutNoteSheet) -> Void
    let close: () -> Void
    private let store = GoodsOutStore.shared
    @State private var selected: String?

    init(note: GoodsOutDeliveryNote, line: GoodsOutLine, present: @escaping (GoodsOutNoteSheet) -> Void, close: @escaping () -> Void) {
        self.note = note
        self.line = line
        self.present = present
        self.close = close
        let locations = GoodsOutSampleData.stockLocations(for: line, channel: note.channel)
        let preferred = locations.first { !$0.isEmpty && $0.code != line.location } ?? locations.first { !$0.isEmpty }
        _selected = State(initialValue: preferred?.code)
    }

    var body: some View {
        let locations = GoodsOutSampleData.stockLocations(for: line, channel: note.channel)
        SheetScaffold(title: "Location list for \(line.sko)", subtitle: "\(line.name) · \(line.remaining) still to pick") {
            HStack(spacing: 12) {
                ProductThumb(size: 56, label: "Product photo of \(line.sko)")
                Text("Pick the rest from any location that has stock.")
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            }
            ForEach(locations) { location in
                RadioCard(location.code, subtitle: location.kind, isSelected: selected == location.code) {
                    selected = location.code
                } trailing: {
                    if selected != location.code {
                        Text(location.stockText)
                            .font(.aiku(12))
                            .foregroundStyle(location.isEmpty ? AikuColor.danger : AikuColor.text3)
                            .padding(.horizontal, 6)
                            .padding(.vertical, 2)
                            .overlay(RoundedRectangle(cornerRadius: 6).strokeBorder(location.isEmpty ? AikuColor.dangerBd : AikuColor.borderStrong))
                    }
                }
                .disabled(location.isEmpty)
                .opacity(location.isEmpty ? 0.5 : 1)
            }
            HelperText("A location with no stock can’t be chosen. To change which location is the picking location, use Change picking location (needs stock edit rights).")
        } footer: {
            AikuButton(selected.map { "Pick from \($0)" } ?? "Pick from another location") {
                guard let selected else { return }
                let previous = store.pick(note.reference, line: line.id, quantity: line.remaining, from: selected)
                close()
                GoodsOutFeedback.done("\(line.sko) picked ×\(line.remaining) from \(selected)", previous: previous, toasts: toasts)
            }
            .disabled(selected == nil)
            .accessibilityIdentifier("goodsOut.pickFromLocation")
            if store.rights.canEditStock {
                AikuButton("Change picking location", kind: .tertiary) {
                    present(.setPickingLocation(line: line.id))
                }
            }
            AikuButton("Cancel", kind: .tertiary) {
                present(.itemActions(line: line.id))
            }
        }
    }
}
