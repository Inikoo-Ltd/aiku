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
        if note.isRemote {
            GoodsOutRemotePackLineSheet(note: note, line: line, close: close)
        } else {
            sampleBody
        }
    }

    private var sampleBody: some View {
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

struct GoodsOutRemotePackLineSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let line: GoodsOutLine
    let close: () -> Void
    @State private var quantity = 1
    @State private var boxes: [GoodsOutBoxDraft]
    @State private var nextBoxID: Int
    @State private var problem: String?
    @State private var confirmsUnpack = false
    private let store = GoodsOutStore.shared

    init(note: GoodsOutDeliveryNote, line: GoodsOutLine, close: @escaping () -> Void) {
        self.note = note
        self.line = line
        self.close = close
        let existing = (line.packing?.boxes ?? []).enumerated().map { GoodsOutBoxDraft(id: $0.offset, box: String($0.element.box), quantity: GoodsOutLinePacking.text($0.element.quantity)) }
        _boxes = State(initialValue: existing)
        _nextBoxID = State(initialValue: existing.count)
    }

    private var packing: GoodsOutLinePacking { line.packing ?? GoodsOutLinePacking() }
    private var canPack: Bool { note.state == .packing && packing.quantityToPack > 0 }
    private var canEditBoxes: Bool { note.packingDetails?.hasBoxPackingList == true && packing.quantityPicked > 0 }
    private var maxAmount: Int { max(1, Int(packing.quantityToPack.rounded(.down))) }
    private var working: Bool { store.isWorking(on: note.reference) }

    var body: some View {
        SheetScaffold(title: "\(line.sko) · \(packing.packedText) of \(packing.pickedText) packed", subtitle: line.name.isEmpty ? nil : line.name) {
            if let toPackers = packing.noteToPackers, !toPackers.isEmpty {
                InlineAlert(tone: .info, icon: .note, title: "Note to packers", message: toPackers)
            }
            if canPack {
                SheetSectionLabel("Pack")
                AikuButton("Pack all \(packing.toPackText)", icon: .packed, isLoading: working) {
                    pack(nil)
                }
                .accessibilityIdentifier("goodsOut.packAll")
                if packing.quantityToPack > 1 {
                    QuantityStepper(value: $quantity, range: 1...maxAmount)
                    AikuButton("Pack \(min(quantity, maxAmount))", kind: .secondary, isLoading: working) {
                        pack(Double(min(quantity, maxAmount)))
                    }
                    .accessibilityIdentifier("goodsOut.packAmount")
                }
            } else if line.isFullyPacked {
                InlineAlert(tone: .success, icon: .checkCircle, title: "Every unit is packed")
            }
            if canEditBoxes {
                SheetSectionLabel("Boxes")
                ForEach($boxes) { $box in
                    HStack(spacing: 8) {
                        AikuTextField("Box", text: $box.box, keyboard: .numberPad)
                        AikuTextField("Quantity", text: $box.quantity, keyboard: .decimalPad)
                        IconButton(.trash, label: "Remove box \(box.box)", tint: AikuColor.danger) {
                            boxes.removeAll { $0.id == box.id }
                        }
                    }
                }
                LinkButton("+ Add a box") {
                    let used = Set(boxes.compactMap { Int($0.box) })
                    let number = (1...GoodsOutBoxDraft.maxBox).first { !used.contains($0) } ?? 1
                    boxes.append(GoodsOutBoxDraft(id: nextBoxID, box: String(number), quantity: ""))
                    nextBoxID += 1
                }
                .frame(minHeight: 44, alignment: .leading)
                AikuButton("Save boxes", kind: .secondary, isLoading: working) {
                    saveBoxes()
                }
            }
            if let problem {
                InlineAlert(tone: .danger, title: problem)
            }
        } footer: {
            if packing.quantityPacked > 0, note.canPackLines {
                AikuButton("Unpack", look: GoodsOutActionKey.unpack.look) {
                    confirmsUnpack = true
                }
                .accessibilityIdentifier("goodsOut.unpackLine")
            }
            AikuButton("Close", kind: .tertiary, action: close)
        }
        .aikuConfirm(
            "Unpack \(line.sko)?",
            isPresented: $confirmsUnpack,
            message: note.state == .packed
                ? "Everything packed on this line goes back to be packed, and the delivery note goes back to packing."
                : "Everything packed on this line goes back to be packed.",
            confirmTitle: "Unpack",
            isDestructive: true
        ) {
            run(.unpack(line: line.apiID ?? 0), fallback: "Line unpacked")
        }
    }

    private func pack(_ amount: Double?) {
        guard let id = line.apiID else { return }
        run(.pack(line: id, quantity: amount), fallback: "\(line.sko) packed")
    }

    private func saveBoxes() {
        guard let id = line.apiID else { return }
        switch GoodsOutBoxDraft.inputs(boxes, picked: packing.quantityPicked) {
        case .failure(let failure):
            problem = failure.message
        case .success(let inputs):
            run(.boxes(line: id, inputs), fallback: "Boxes saved")
        }
    }

    private func run(_ action: GoodsOutNoteAction, fallback: String) {
        problem = nil
        Task {
            let result = await store.write(action, on: note.reference)
            if GoodsOutWriteFeedback.show(result, fallback: fallback, toasts: toasts, showsFailure: false) {
                close()
            } else {
                problem = result.fieldErrors.sorted { $0.key < $1.key }.first?.value ?? result.failureMessage
            }
        }
    }
}
