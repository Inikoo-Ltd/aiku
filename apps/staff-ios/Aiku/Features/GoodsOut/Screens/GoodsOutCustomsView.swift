import SwiftUI

struct GoodsOutCustomsView: View {
    let reference: String
    @Environment(ToastCenter.self) private var toasts
    @State private var tariffOverrides: [String: String] = [:]
    @State private var editing: GoodsOutCustomsRow?
    private let store = GoodsOutStore.shared

    private func rows(_ note: GoodsOutDeliveryNote) -> [GoodsOutCustomsRow] {
        GoodsOutCustoms.rows(for: note, tradeUnits: store.tradeUnits).map { row in
            var copy = row
            if let override = tariffOverrides[row.sko], !override.isEmpty {
                copy.tariff = override
            }
            return copy
        }
    }

    var body: some View {
        if let note = store.note(reference) {
            let rows = rows(note)
            let missing = GoodsOutCustoms.missingCodes(rows)
            AppScreen(title: "Customs items file", kicker: "Delivery note \(note.reference)") {
                HStack(spacing: 8) {
                    setting("Format", "[DHL CSV]")
                    setting("Field delimiter", ",")
                    setting("Text delimiter", "\"")
                }
                GoodsOutSectionTitle(title: "Preview")
                GoodsOutCustomsTable(rows: rows) { row in
                    editing = row
                }
                if !missing.isEmpty {
                    GoodsOutSectionTitle(title: "Fix these before downloading")
                    InlineAlert(
                        tone: .danger,
                        icon: .error,
                        title: "No tariff code or origin",
                        message: "\(missing.joined(separator: ", ")) · tap the cell to set it for this file, or fix the trade unit."
                    )
                }
                HelperText("Click a cell to change it for this file only, the product is not changed. Delimiters must match the ones set in DHL. Problems are listed under “Fix these before downloading”.")
            } footer: {
                if missing.isEmpty {
                    AikuButton("Download", icon: .document) {
                        toasts.show("Customs items file for \(note.reference) downloaded · [DHL CSV]", sticky: false)
                    }
                    .accessibilityIdentifier("goodsOut.downloadCustoms")
                } else {
                    GoodsOutFooterStatus(title: "Fix these before downloading", detail: "Missing tariff code or origin: \(missing.joined(separator: ", ")).")
                }
            }
            .aikuSheet(item: $editing) { row in
                GoodsOutCustomsCellSheet(row: row) { value in
                    tariffOverrides[row.sko] = value
                    editing = nil
                } close: {
                    editing = nil
                }
            }
        }
    }

    private func setting(_ label: String, _ value: String) -> some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(label)
                .font(.aiku(11))
                .foregroundStyle(AikuColor.text3)
                .lineLimit(1)
                .minimumScaleFactor(0.8)
            Text(value)
                .font(.aiku(14, .semibold))
                .foregroundStyle(AikuColor.text)
                .lineLimit(1)
                .minimumScaleFactor(0.8)
        }
        .padding(.horizontal, 10)
        .padding(.vertical, 8)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 8))
        .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(AikuColor.borderStrong))
        .accessibilityElement(children: .combine)
    }
}

private struct GoodsOutCustomsTable: View {
    let rows: [GoodsOutCustomsRow]
    let onEditTariff: (GoodsOutCustomsRow) -> Void

    private let widths: [CGFloat] = [44, 210, 84, 64, 44, 64]
    private let titles = ["Row", "Description", "Tariff", "Origin", "Qty", "Value"]

    var body: some View {
        ScrollView(.horizontal, showsIndicators: true) {
            VStack(alignment: .leading, spacing: 0) {
                HStack(spacing: 0) {
                    ForEach(Array(titles.enumerated()), id: \.offset) { index, title in
                        cell(title, width: widths[index], color: AikuColor.text3, weight: .semibold)
                    }
                }
                Rectangle().fill(AikuColor.border).frame(height: 1)
                ForEach(Array(rows.enumerated()), id: \.element.id) { index, row in
                    if index > 0 {
                        Rectangle().fill(AikuColor.divider).frame(height: 1)
                    }
                    HStack(spacing: 0) {
                        cell("\(row.row)", width: widths[0])
                        cell(row.name, width: widths[1])
                        Button {
                            onEditTariff(row)
                        } label: {
                            cell(row.tariff ?? "—", width: widths[2], color: row.tariff == nil ? AikuColor.danger : AikuColor.text, weight: row.tariff == nil ? .bold : .regular)
                                .frame(minHeight: 44)
                                .contentShape(Rectangle())
                        }
                        .buttonStyle(.plain)
                        .accessibilityLabel("Tariff of \(row.sko): \(row.tariff ?? "missing")")
                        .accessibilityHint("Changes it for this file only")
                        cell(row.origin ?? "—", width: widths[3], color: row.origin == nil ? AikuColor.danger : AikuColor.text, weight: row.origin == nil ? .bold : .regular)
                        cell("\(row.quantity)", width: widths[4])
                        cell("[£]", width: widths[5])
                    }
                }
            }
        }
        .cardSurface()
    }

    private func cell(_ text: String, width: CGFloat, color: Color = AikuColor.text, weight: Font.Weight = .regular) -> some View {
        Text(text)
            .font(.aiku(12, weight))
            .foregroundStyle(color)
            .fixedSize(horizontal: false, vertical: true)
            .padding(.horizontal, 10)
            .padding(.vertical, 8)
            .frame(width: width, alignment: .leading)
    }
}

private struct GoodsOutCustomsCellSheet: View {
    let row: GoodsOutCustomsRow
    let save: (String) -> Void
    let close: () -> Void
    @State private var tariff: String

    init(row: GoodsOutCustomsRow, save: @escaping (String) -> Void, close: @escaping () -> Void) {
        self.row = row
        self.save = save
        self.close = close
        _tariff = State(initialValue: row.tariff ?? "")
    }

    var body: some View {
        SheetScaffold(title: "Tariff for this file", subtitle: "\(row.sko) · \(row.name)") {
            AikuTextField("Tariff code", text: $tariff, placeholder: "3406 00")
            HelperText("Only this customs items file changes, the product keeps its trade unit.")
        } footer: {
            AikuButton("Use in this file") {
                save(tariff.trimmingCharacters(in: .whitespacesAndNewlines))
            }
            .accessibilityIdentifier("goodsOut.saveCustomsCell")
            AikuButton("Cancel", kind: .tertiary, action: close)
        }
    }
}
