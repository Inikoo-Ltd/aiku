import SwiftUI

struct InventorySkoRow: View {
    let reference: String
    let chip: Chip?
    let subtitle: String
    var quantityText: String?
    var action: (() -> Void)?

    init(reference: String, chip: Chip?, subtitle: String, quantity: Int?, action: (() -> Void)? = nil) {
        self.init(reference: reference, chip: chip, subtitle: subtitle, quantityText: quantity?.grouped, action: action)
    }

    init(reference: String, chip: Chip?, subtitle: String, quantityText: String?, action: (() -> Void)? = nil) {
        self.reference = reference
        self.chip = chip
        self.subtitle = subtitle
        self.quantityText = quantityText
        self.action = action
    }

    var body: some View {
        ListRow(reference, subtitle: subtitle, chips: chip.map { [$0] } ?? [], action: action) {
            ProductThumb(size: 48, label: "Product photo of \(reference)")
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
        .accessibilityIdentifier("inventory.sko.\(reference)")
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
            ProductThumb(size: 48, label: "Product photo of \(movement.title)")
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
            TextField(placeholder, text: $text)
                .font(.aiku(15))
                .foregroundStyle(AikuColor.text)
                .textInputAutocapitalization(.never)
                .autocorrectionDisabled()
                .submitLabel(.search)
                .focused(isFocused)
                .onSubmit { onSubmit?() }
                .padding(.horizontal, 10)
                .accessibilityLabel(placeholder)
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

struct InventoryDangerNote: View {
    let text: String

    var body: some View {
        Text(text)
            .font(.aiku(14))
            .foregroundStyle(AikuColor.dangerInk)
            .padding(.horizontal, 14)
            .padding(.vertical, 12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(AikuColor.dangerSoft, in: RoundedRectangle(cornerRadius: 10))
            .accessibilityAddTraits(.isStaticText)
    }
}

struct InventoryQuantityField: View {
    let title: String
    @Binding var value: Int
    let range: ClosedRange<Int>

    var body: some View {
        VStack(alignment: .leading, spacing: 6) {
            FieldLabel(title)
            QuantityStepper(value: $value, range: range)
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
