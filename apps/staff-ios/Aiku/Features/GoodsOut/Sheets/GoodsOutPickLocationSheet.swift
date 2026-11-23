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
        let locations = Self.locations(for: line, note: note)
        let preferred = locations.first { !$0.isEmpty && $0.code != line.location } ?? locations.first { !$0.isEmpty }
        _selected = State(initialValue: preferred?.code)
    }

    var body: some View {
        let locations = Self.locations(for: line, note: note)
        SheetScaffold(title: L10n.format("Location list for %@", line.sko), subtitle: L10n.format("%@ · %lld still to pick", line.name, line.remaining)) {
            HStack(spacing: 12) {
                ProductThumb(size: 56, label: L10n.format("Product photo of %@", line.sko))
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
            AikuButton(selected.map { L10n.format("Pick from %@", $0) } ?? "Pick from another location") {
                guard let selected else { return }
                if let lineID = line.apiID {
                    guard let location = locations.first(where: { $0.code == selected })?.locationOrgStockID else { return }
                    let action = GoodsOutNoteAction.pick(line: lineID, locationOrgStockID: location, quantity: line.remainingQuantity)
                    GoodsOutRun.action(action, on: note.reference, fallback: L10n.format("%@ picked ×%lld from %@", line.sko, line.remaining, selected), toasts: toasts) { close() }
                    return
                }
                let previous = store.pick(note.reference, line: line.id, quantity: line.remaining, from: selected)
                close()
                GoodsOutFeedback.done(L10n.format("%@ picked ×%lld from %@", line.sko, line.remaining, selected), previous: previous, toasts: toasts)
            }
            .disabled(selected == nil)
            .accessibilityIdentifier("goodsOut.pickFromLocation")
            if store.rights.canEditStock, !note.isRemote || line.orgStockID != nil {
                AikuButton("Change picking location", kind: .tertiary) {
                    present(.setPickingLocation(line: line.id))
                }
                .accessibilityIdentifier("goodsOut.changePickingLocation")
            }
            AikuButton("Cancel", kind: .tertiary) {
                present(.itemActions(line: line.id))
            }
        }
    }

    static func locations(for line: GoodsOutLine, note: GoodsOutDeliveryNote) -> [GoodsOutStockLocation] {
        note.isRemote ? line.stockLocations : GoodsOutSampleData.stockLocations(for: line, channel: note.channel)
    }
}
