import SwiftUI

struct GoodsOutLinkLabel: View {
    let text: String
    var tone: Tone = .info

    init(_ text: String, tone: Tone = .info) {
        self.text = text
        self.tone = tone
    }

    var body: some View {
        Text(text)
            .font(.aiku(14, .semibold))
            .foregroundStyle(tone == .danger ? AikuColor.danger : AikuColor.link)
            .lineLimit(1)
            .fixedSize()
    }
}

struct GoodsOutPackerMustAdd: View {
    let materials: [String]

    var body: some View {
        FlowLayout(spacing: 8, lineSpacing: 8) {
            Text("Packer must add".uppercased())
                .font(.aiku(12, .bold))
                .tracking(0.6)
                .foregroundStyle(AikuColor.warnInk)
            ForEach(materials, id: \.self) { material in
                Text(material)
                    .font(.aiku(13, .semibold))
                    .foregroundStyle(AikuColor.warnInk)
                    .padding(.horizontal, 8)
                    .padding(.vertical, 3)
                    .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 6))
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(AikuColor.warnSoft, in: RoundedRectangle(cornerRadius: 12))
        .overlay(RoundedRectangle(cornerRadius: 12).strokeBorder(AikuColor.warnBd))
        .accessibilityElement(children: .combine)
    }
}

struct GoodsOutSummaryCard: View {
    let title: String
    let detail: String
    var icon: AppIcon?

    var body: some View {
        HStack(spacing: 12) {
            if let icon {
                IconTile(icon, tone: .info)
            }
            VStack(alignment: .leading, spacing: icon == nil ? 12 : 2) {
                Text(title)
                    .font(.aiku(16, .bold))
                    .foregroundStyle(AikuColor.text)
                Text(detail)
                    .font(.aiku(icon == nil ? 14 : 13))
                    .foregroundStyle(AikuColor.text3)
                    .fixedSize(horizontal: false, vertical: true)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .cardSurface()
        .accessibilityElement(children: .combine)
    }
}

struct GoodsOutPackLineRow: View {
    let line: GoodsOutLine
    var action: (() -> Void)?

    var body: some View {
        if let action {
            Button(action: action) { content }
                .buttonStyle(.plain)
                .accessibilityHint("Choose the parcel for this line")
        } else {
            content
        }
    }

    private var quantityColor: Color {
        if line.isFullyPacked { return AikuColor.success }
        return line.packed > 0 ? AikuColor.ink : AikuColor.text
    }

    private var content: some View {
        HStack(spacing: 12) {
            ProductThumb(size: 48, label: "Product photo of \(line.sko)")
            VStack(alignment: .leading, spacing: 3) {
                FlowLayout(spacing: 8, lineSpacing: 4) {
                    Text(line.sko)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                    if line.isFullyPacked {
                        StatusChip("Packed", tone: .success)
                    }
                }
                Text("\(line.name) · \(line.parcelText)")
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
                    .multilineTextAlignment(.leading)
                    .fixedSize(horizontal: false, vertical: true)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            (Text("\(line.packed)")
                .font(.aiku(16, .bold))
                .foregroundStyle(quantityColor)
                + Text(" / \(line.picked)")
                .font(.aiku(13, .medium))
                .foregroundStyle(AikuColor.text3))
                .monospacedDigit()
                .lineLimit(1)
                .accessibilityLabel("\(line.packed) of \(line.picked) packed")
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
        .frame(minHeight: 60)
        .contentShape(Rectangle())
    }
}

struct GoodsOutPackLinesCard: View {
    let lines: [GoodsOutLine]
    var onTap: ((GoodsOutLine) -> Void)?

    var body: some View {
        Card {
            ForEach(Array(lines.enumerated()), id: \.element.id) { index, line in
                if index > 0 {
                    RowDivider()
                }
                GoodsOutPackLineRow(line: line, action: onTap.map { tap in { tap(line) } })
            }
        }
    }
}

struct GoodsOutParcelEditorRow: View {
    let reference: String
    let parcel: GoodsOutParcel
    @State private var weight: String
    @State private var length: String
    @State private var width: String
    @State private var height: String
    private let store = GoodsOutStore.shared

    init(reference: String, parcel: GoodsOutParcel) {
        self.reference = reference
        self.parcel = parcel
        _weight = State(initialValue: parcel.weightKg.formatted(.number.precision(.fractionLength(1)).locale(Locale(identifier: "en_GB"))))
        _length = State(initialValue: parcel.length.map(String.init) ?? "")
        _width = State(initialValue: parcel.width.map(String.init) ?? "")
        _height = State(initialValue: parcel.height.map(String.init) ?? "")
    }

    var body: some View {
        HStack(spacing: 6) {
            VStack(alignment: .leading, spacing: 2) {
                Text(parcel.title)
                    .font(.aiku(15, .bold))
                    .foregroundStyle(AikuColor.text)
                if let dimensions = parcel.dimensionsText {
                    Text(dimensions)
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                        .lineLimit(1)
                        .minimumScaleFactor(0.8)
                } else {
                    Text("dimensions missing")
                        .font(.aiku(12, .semibold))
                        .foregroundStyle(AikuColor.warn)
                        .lineLimit(1)
                        .minimumScaleFactor(0.8)
                }
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            field($weight, placeholder: "kg", label: "weight in kilograms", isMissing: false, keyboard: .decimalPad)
            field($length, placeholder: "L", label: "length in centimetres", isMissing: parcel.length == nil, keyboard: .numberPad)
            field($width, placeholder: "W", label: "width in centimetres", isMissing: parcel.width == nil, keyboard: .numberPad)
            field($height, placeholder: "H", label: "height in centimetres", isMissing: parcel.height == nil, keyboard: .numberPad)
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
        .onChange(of: weight) { _, value in
            guard let kilograms = Double(value.replacingOccurrences(of: ",", with: ".")) else { return }
            store.updateParcel(reference, parcel: parcel.number) { $0.weightKg = kilograms }
        }
        .onChange(of: length) { _, value in
            store.updateParcel(reference, parcel: parcel.number) { $0.length = Self.centimetres(value) }
        }
        .onChange(of: width) { _, value in
            store.updateParcel(reference, parcel: parcel.number) { $0.width = Self.centimetres(value) }
        }
        .onChange(of: height) { _, value in
            store.updateParcel(reference, parcel: parcel.number) { $0.height = Self.centimetres(value) }
        }
    }

    private static func centimetres(_ text: String) -> Int? {
        guard let value = Int(text.trimmingCharacters(in: .whitespaces)), value > 0 else { return nil }
        return value
    }

    private func field(_ text: Binding<String>, placeholder: String, label: String, isMissing: Bool, keyboard: UIKeyboardType) -> some View {
        TextField("", text: text, prompt: Text(placeholder).foregroundStyle(AikuColor.faint))
            .font(.aiku(14, .semibold))
            .foregroundStyle(AikuColor.text)
            .multilineTextAlignment(.center)
            .keyboardType(keyboard)
            .monospacedDigit()
            .frame(width: 56, height: 44)
            .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 8))
            .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(isMissing ? AikuColor.warn : AikuColor.borderStrong))
            .accessibilityLabel("\(parcel.title) \(label)")
    }
}

struct GoodsOutParcelsCard: View {
    let parcels: [GoodsOutParcel]

    var body: some View {
        Card {
            ForEach(Array(parcels.enumerated()), id: \.element.id) { index, parcel in
                if index > 0 {
                    RowDivider()
                }
                ListRow(parcel.title, subtitle: parcel.summary, icon: .sko)
            }
        }
    }
}

struct GoodsOutLeafletsCard: View {
    let note: GoodsOutDeliveryNote
    @Environment(ToastCenter.self) private var toasts
    private let store = GoodsOutStore.shared

    var body: some View {
        Card {
            ForEach(Array(note.leaflets.enumerated()), id: \.element.id) { index, leaflet in
                if index > 0 {
                    RowDivider()
                }
                ListRow(
                    leaflet.title,
                    subtitle: "Goes in the parcel",
                    icon: .printer,
                    iconTone: leaflet.isPrinted ? .success : .warn,
                    chips: [leaflet.chip],
                    action: leaflet.isPrinted ? nil : { printLeaflet(leaflet) }
                ) {
                    if !leaflet.isPrinted {
                        GoodsOutLinkLabel("Print")
                    }
                }
            }
        }
    }

    private func printLeaflet(_ leaflet: GoodsOutLeaflet) {
        let previous = store.printLeaflet(note.reference, leaflet: leaflet.id)
        GoodsOutFeedback.done("\(leaflet.title) printed", previous: previous, toasts: toasts)
    }
}

struct GoodsOutShipmentsCard: View {
    let note: GoodsOutDeliveryNote
    var canDelete = true
    @Environment(ToastCenter.self) private var toasts
    private let store = GoodsOutStore.shared

    var body: some View {
        Card {
            ForEach(Array(note.shipments.enumerated()), id: \.element.id) { index, shipment in
                if index > 0 {
                    RowDivider()
                }
                ListRow(shipment.shipper, subtitle: shipment.subtitle, icon: .deliveryNote, iconTone: .success, chips: [shipment.chip]) {
                    if canDelete, note.state == .packed {
                        Button {
                            let previous = store.deleteShipment(note.reference, shipment: shipment.id)
                            GoodsOutFeedback.done("Shipment \(shipment.tracking) deleted", tone: .danger, previous: previous, toasts: toasts)
                        } label: {
                            GoodsOutLinkLabel("Delete", tone: .danger)
                                .frame(minWidth: 44, minHeight: 44)
                                .contentShape(Rectangle())
                        }
                        .buttonStyle(.plain)
                        .accessibilityLabel("Delete shipment \(shipment.tracking)")
                    }
                }
            }
        }
    }
}
