import SwiftUI

struct InventorySkoRow: View {
    let reference: String
    let chip: Chip?
    let subtitle: String
    var quantityText: String?
    var location: InventorySkoListLocation?
    var action: (() -> Void)?

    init(reference: String, chip: Chip?, subtitle: String, quantity: Double?, action: (() -> Void)? = nil) {
        self.init(reference: reference, chip: chip, subtitle: subtitle, quantityText: quantity?.grouped, action: action)
    }

    init(reference: String, chip: Chip?, subtitle: String, quantityText: String?, location: InventorySkoListLocation? = nil, action: (() -> Void)? = nil) {
        self.reference = reference
        self.chip = chip
        self.subtitle = subtitle
        self.quantityText = quantityText
        self.location = location
        self.action = action
    }

    private var locationLine: Text? {
        guard let location else { return nil }
        guard let quantity = location.quantityText else { return Text(verbatim: location.code) }
        return Text(verbatim: "\(location.code) · ")
            + Text(verbatim: quantity)
                .fontWeight(.bold)
                .foregroundStyle(location.isNegative ? Tone.danger.ink : AikuColor.text)
    }

    var body: some View {
        ListRow(reference, subtitle: subtitle.isEmpty ? nil : subtitle, detail: locationLine, chips: chip.map { [$0] } ?? [], action: action) {
            ProductThumb(size: 48, label: L10n.format("Product photo of %@", reference))
        } trailing: {
            if let quantityText {
                Text(quantityText)
                    .font(.aiku(15, .bold))
                    .foregroundStyle(AikuColor.text)
                    .monospacedDigit()
                    .lineLimit(1)
                    .fixedSize()
            }
        }
        .accessibilityIdentifier(location.map { "inventory.sko.\(reference).\($0.code)" } ?? "inventory.sko.\(reference)")
    }
}

struct InventoryMovementRow: View {
    let movement: InventoryMovementDisplay
    var action: (() -> Void)?

    var body: some View {
        ListRow(
            movement.title,
            subtitle: movement.subtitle,
            chips: [movement.chip],
            action: action
        ) {
            ProductThumb(size: 48, label: L10n.format("Product photo of %@", movement.title))
        } trailing: {
            EmptyView()
        }
    }
}

struct InventoryInfoLine: Identifiable {
    let icon: AppIcon
    let text: String

    var id: String { text }
}

struct InventoryInfoCard: View {
    let title: String
    let lines: [InventoryInfoLine]

    var body: some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(title)
                .font(.aiku(13, .bold))
                .foregroundStyle(AikuColor.text)
            ForEach(lines) { line in
                HStack(spacing: 10) {
                    line.icon.image
                        .font(.system(size: 14))
                        .foregroundStyle(AikuColor.text3)
                        .frame(width: 16)
                        .accessibilityHidden(true)
                    Text(line.text)
                        .font(.aiku(14))
                        .foregroundStyle(AikuColor.text2)
                        .frame(maxWidth: .infinity, alignment: .leading)
                }
                .frame(minHeight: 28)
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .frame(maxWidth: .infinity, alignment: .leading)
        .cardSurface()
    }
}

struct InventorySearchField: View {
    @Binding var text: String
    var placeholder: String
    var isFocused: FocusState<Bool>.Binding
    var onScan: (() -> Void)?
    var onSubmit: (() -> Void)?

    var body: some View {
        FieldBox(isFocused: isFocused.wrappedValue, height: 46) {
            AppIcon.search.image
                .font(.system(size: 17))
                .foregroundStyle(AikuColor.text4)
                .padding(.leading, 14)
                .accessibilityHidden(true)
            TextField(L10n.text(placeholder), text: $text)
                .font(.aiku(15))
                .foregroundStyle(AikuColor.text)
                .textInputAutocapitalization(.never)
                .autocorrectionDisabled()
                .submitLabel(.search)
                .focused(isFocused)
                .onSubmit { onSubmit?() }
                .padding(.horizontal, 10)
                .accessibilityLabel(L10n.text(placeholder))
            if !text.isEmpty {
                Button {
                    text = ""
                } label: {
                    AppIcon.close.image
                        .font(.system(size: 12, weight: .bold))
                        .foregroundStyle(AikuColor.text4)
                        .frame(width: 32, height: 44)
                }
                .buttonStyle(.plain)
                .accessibilityLabel("Clear search")
            }
            if let onScan {
                ScanButton(size: 36, action: onScan)
                    .padding(.trailing, 5)
            }
        }
    }
}

struct InventoryScanCover: View {
    let kicker: String
    let onClose: () -> Void
    let onScan: () -> Void
    let onTypeCode: () -> Void

    var body: some View {
        CameraScanScreen(
            kicker: kicker,
            title: "Scan SKO",
            hint: "Point at the SKO barcode",
            onClose: onClose,
            onSimulateScan: onScan
        ) {
            AikuButton("Type code", icon: .keyboard, kind: .tertiary, size: .regular, action: onTypeCode)
                .accessibilityIdentifier("inventory.scan.typeCode")
        }
    }
}

struct InventoryScopeChips<Option: Hashable>: View {
    let options: [Option]
    let selection: Option
    let title: (Option) -> String
    let count: (Option) -> Int?
    let onSelect: (Option) -> Void

    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 6) {
                ForEach(options, id: \.self) { option in
                    FilterChip(title(option), count: count(option), isSelected: option == selection) {
                        onSelect(option)
                    }
                }
            }
            .padding(.horizontal, 16)
        }
        .scrollBounceBehavior(.basedOnSize, axes: [.horizontal, .vertical])
        .padding(.horizontal, -16)
    }
}

struct InventoryQuantityField: View {
    let title: String
    @Binding var value: Double
    let range: ClosedRange<Double>
    var packedIn: Double?

    var body: some View {
        VStack(alignment: .leading, spacing: 6) {
            FieldLabel(title)
            DecimalQuantityStepper(value: $value, valueText: StockConfirmText.quantityLabel(value, packedIn: packedIn), range: range)
        }
    }
}

struct InventoryLoadAlert: View {
    let title: String
    let message: String
    let retry: () -> Void

    var body: some View {
        InlineAlert(tone: .danger, title: title, message: message) {
            LinkButton("Try again", size: 13, action: retry)
                .frame(minHeight: 44, alignment: .leading)
        }
    }
}

struct InventorySkeletonCard: View {
    var rows = 4

    var body: some View {
        Card {
            ForEach(0..<rows, id: \.self) { index in
                if index > 0 {
                    RowDivider()
                }
                SkeletonRow()
            }
        }
        .accessibilityLabel("Loading")
    }
}

struct InventoryFamilyStatusSections: View {
    let skoStatus: [InventoryFamilyStatusItem]
    let quantityStatus: [InventoryFamilyStatusItem]

    var body: some View {
        if !skoStatus.isEmpty {
            SectionHeading("SKO status")
            StatGrid(columns: 2) {
                ForEach(Array(skoStatus.enumerated()), id: \.offset) { _, item in
                    InventoryFamilyStatusTile(item: item)
                }
            }
        }
        if !quantityStatus.isEmpty {
            SectionHeading("Quantity status")
            StatGrid(columns: 2) {
                ForEach(Array(quantityStatus.enumerated()), id: \.offset) { _, item in
                    InventoryFamilyQuantityStatusRow(item: item)
                }
            }
        }
    }
}

struct InventoryFamilyStatusTile: View {
    let item: InventoryFamilyStatusItem

    var body: some View {
        HStack(spacing: 12) {
            Image(systemName: item.symbol)
                .font(.system(size: 24))
                .foregroundStyle(item.tone.statusTint)
                .accessibilityHidden(true)
            VStack(alignment: .leading, spacing: 2) {
                Text(item.count.grouped)
                    .font(.aiku(22, .bold))
                    .foregroundStyle(AikuColor.text)
                    .monospacedDigit()
                    .lineLimit(1)
                    .minimumScaleFactor(0.7)
                Text(item.title)
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
                    .lineLimit(2)
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .frame(maxWidth: .infinity, minHeight: 64, alignment: .leading)
        .cardSurface(radius: 10)
        .accessibilityElement(children: .combine)
    }
}

struct InventoryFamilyQuantityStatusRow: View {
    let item: InventoryFamilyStatusItem

    var body: some View {
        HStack(spacing: 10) {
            Image(systemName: item.symbol)
                .font(.system(size: 16))
                .foregroundStyle(item.tone.statusTint)
                .accessibilityHidden(true)
            Text(item.title)
                .font(.aiku(14))
                .foregroundStyle(AikuColor.text2)
                .lineLimit(2)
                .frame(maxWidth: .infinity, alignment: .leading)
            Text(item.count.grouped)
                .font(.aiku(15, .bold))
                .foregroundStyle(AikuColor.text)
                .monospacedDigit()
                .lineLimit(1)
                .fixedSize()
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .frame(maxWidth: .infinity, minHeight: 48, alignment: .leading)
        .cardSurface(radius: 10)
        .accessibilityElement(children: .combine)
    }
}

private extension Tone {
    @MainActor var statusTint: Color {
        self == .neutral ? AikuColor.text3 : foreground
    }
}
