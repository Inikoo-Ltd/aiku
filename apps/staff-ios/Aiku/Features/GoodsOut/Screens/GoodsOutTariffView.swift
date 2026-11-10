import SwiftUI

struct GoodsOutTariffView: View {
    let reference: String
    @Environment(AppRouter.self) private var router
    @State private var language: GoodsOutDescriptionLanguage = .en
    @State private var editing: GoodsOutCustomsRow?
    private let store = GoodsOutStore.shared

    var body: some View {
        if let note = store.note(reference) {
            let rows = GoodsOutCustoms.rows(for: note, tradeUnits: store.tradeUnits, language: language)
            let missing = GoodsOutCustoms.missingCodes(rows)
            AppScreen(title: "Tariff codes / Origin", kicker: "Delivery note \(note.reference)") {
                if !missing.isEmpty {
                    (Text("Missing tariff code or country of origin.").bold()
                        + Text(" These parts cannot be declared for customs until they are fixed in their trade unit: \(missing.joined(separator: ", ")).")
                    )
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.dangerInk)
                    .fixedSize(horizontal: false, vertical: true)
                    .padding(.horizontal, 14)
                    .padding(.vertical, 12)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .background(AikuColor.dangerSoft, in: RoundedRectangle(cornerRadius: 12))
                    .overlay(RoundedRectangle(cornerRadius: 12).strokeBorder(AikuColor.dangerBd))
                }
                Text("Description language")
                    .font(.aiku(13, .semibold))
                    .foregroundStyle(AikuColor.text)
                SegmentedControl(options: GoodsOutDescriptionLanguage.allCases, selection: $language, title: \.title)
                Card {
                    ForEach(Array(rows.enumerated()), id: \.element.id) { index, row in
                        if index > 0 {
                            RowDivider()
                        }
                        GoodsOutTariffRow(row: row) {
                            editing = row
                        }
                    }
                }
                HelperText("No tariff code and/or country of origin means customs clearance will fail. Fixing it changes the trade unit for every order.")
            } footer: {
                AikuButton("Export customs items file", icon: .document) {
                    router.open(GoodsOutRoute.customsFile(reference: note.reference))
                }
                .accessibilityIdentifier("goodsOut.exportCustoms")
            }
            .aikuSheet(item: $editing) { row in
                GoodsOutTradeUnitSheet(row: row) { editing = nil }
            }
        }
    }
}

private struct GoodsOutTariffRow: View {
    let row: GoodsOutCustomsRow
    let onEdit: () -> Void

    var body: some View {
        HStack(spacing: 12) {
            ProductThumb(size: 48, label: "Product photo of \(row.sko)")
            VStack(alignment: .leading, spacing: 3) {
                FlowLayout(spacing: 8, lineSpacing: 4) {
                    Text(row.sko)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                    if row.isMissing {
                        StatusChip("Missing", tone: .danger)
                    }
                }
                Text(row.tradeUnitText)
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
                    .fixedSize(horizontal: false, vertical: true)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            if row.isMissing {
                Button(action: onEdit) {
                    GoodsOutLinkLabel("Edit trade unit")
                        .frame(minHeight: 44)
                        .contentShape(Rectangle())
                }
                .buttonStyle(.plain)
                .accessibilityLabel("Edit trade unit of \(row.sko)")
            } else if row.isDangerous {
                StatusChip("Dangerous goods", tone: .warn)
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
        .frame(minHeight: 60)
    }
}

private struct GoodsOutTradeUnitSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let row: GoodsOutCustomsRow
    let close: () -> Void
    @State private var tariff: String
    @State private var origin: String
    @State private var showsErrors = false
    private let store = GoodsOutStore.shared

    init(row: GoodsOutCustomsRow, close: @escaping () -> Void) {
        self.row = row
        self.close = close
        _tariff = State(initialValue: row.tariff ?? "")
        _origin = State(initialValue: row.origin ?? "")
    }

    var body: some View {
        SheetScaffold(title: "Edit trade unit", subtitle: "\(row.sko) · \(row.name)") {
            AikuTextField("Tariff code", text: $tariff, placeholder: "3406 00", error: showsErrors && tariff.trimmingCharacters(in: .whitespaces).isEmpty ? "Enter the tariff code" : nil)
            AikuTextField("Country of origin", text: $origin, placeholder: "GB", error: showsErrors && origin.trimmingCharacters(in: .whitespaces).count != 2 ? "Enter a two-letter country code" : nil)
            HelperText("This changes the trade unit for every order, not just this delivery note.")
        } footer: {
            AikuButton("Save trade unit") {
                guard store.updateTradeUnit(row.sko, tariff: tariff, origin: origin) else {
                    showsErrors = true
                    return
                }
                close()
                toasts.show("Trade unit of \(row.sko) saved", sticky: false)
            }
            .accessibilityIdentifier("goodsOut.saveTradeUnit")
            AikuButton("Cancel", kind: .tertiary, action: close)
        }
    }
}
