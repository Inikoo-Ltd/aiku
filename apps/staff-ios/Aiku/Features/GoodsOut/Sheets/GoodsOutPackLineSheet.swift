import SwiftUI

struct GoodsOutPackLineSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let line: GoodsOutLine
    let close: () -> Void
    @State private var parcel: Int?
    @State private var quantity: Int
    private let store = GoodsOutStore.shared

    init(note: GoodsOutDeliveryNote, line: GoodsOutLine, close: @escaping () -> Void) {
        self.note = note
        self.line = line
        self.close = close
        _parcel = State(initialValue: note.currentParcel?.number)
        _quantity = State(initialValue: max(1, line.packRemaining))
    }

    private var nextParcelNumber: Int { (note.parcels.map(\.number).max() ?? 0) + 1 }
    private var parcelTitle: String { "Parcel \(parcel ?? nextParcelNumber)" }

    var body: some View {
        SheetScaffold(title: "Pack \(line.sko)", subtitle: "\(line.name) · \(line.packed) of \(line.picked) packed") {
            if line.isFullyPacked {
                InlineAlert(tone: .success, icon: .checkCircle, title: "Every unit is packed", message: "In \(line.parcelText).")
            } else {
                SheetSectionLabel("Parcel")
                ForEach(note.parcels) { option in
                    RadioCard(option.title, subtitle: option.summary, isSelected: parcel == option.number) {
                        parcel = option.number
                    }
                }
                RadioCard("New parcel", subtitle: "Parcel \(nextParcelNumber)", isSelected: parcel == nil) {
                    parcel = nil
                }
                SheetSectionLabel("Quantity")
                QuantityStepper(value: $quantity, range: 1...max(1, line.packRemaining))
            }
        } footer: {
            if !line.isFullyPacked {
                AikuButton("Pack \(quantity) into \(parcelTitle)") {
                    let previous: GoodsOutDeliveryNote?
                    if let parcel {
                        previous = store.pack(note.reference, line: line.id, quantity: quantity, into: parcel)
                    } else {
                        previous = store.packIntoNewParcel(note.reference, line: line.id, quantity: quantity)
                    }
                    let title = parcelTitle
                    close()
                    GoodsOutFeedback.done("\(line.sko) ×\(quantity) packed into \(title)", previous: previous, toasts: toasts)
                }
                .accessibilityIdentifier("goodsOut.confirmPackLine")
            }
            AikuButton(line.isFullyPacked ? "Close" : "Cancel", kind: .tertiary, action: close)
        }
    }
}
