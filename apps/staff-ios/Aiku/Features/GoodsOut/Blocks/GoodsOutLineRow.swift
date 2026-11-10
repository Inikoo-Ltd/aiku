import SwiftUI

struct GoodsOutLineRow: View {
    let line: GoodsOutLine
    var detail: String?
    var showsLocationStock = false
    var showsChip = true
    var action: (() -> Void)?

    var body: some View {
        if let action {
            Button(action: action) { content }
                .buttonStyle(.plain)
                .accessibilityHint("Opens the line actions")
        } else {
            content
        }
    }

    private var quantityColor: Color {
        switch line.status {
        case .toPick: AikuColor.text
        case .partlyPicked: AikuColor.ink
        case .picked: AikuColor.success
        case .notPicked: AikuColor.danger
        case .waiting: AikuColor.warn
        }
    }

    private var content: some View {
        HStack(spacing: 12) {
            ProductThumb(size: 48, label: "Product photo of \(line.sko)")
            VStack(alignment: .leading, spacing: 3) {
                FlowLayout(spacing: 8, lineSpacing: 4) {
                    Text(line.sko)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                    if showsChip, let chip = line.status.chip {
                        StatusChip(chip)
                    }
                }
                Text(detail ?? line.detailText(showsLocationStock: showsLocationStock))
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
                    .multilineTextAlignment(.leading)
                    .fixedSize(horizontal: false, vertical: true)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            (Text("\(line.picked)")
                .font(.aiku(16, .bold))
                .foregroundStyle(quantityColor)
                + Text(" / \(line.required)")
                .font(.aiku(13, .medium))
                .foregroundStyle(AikuColor.text3))
                .monospacedDigit()
                .lineLimit(1)
                .accessibilityLabel("\(line.picked) of \(line.required)")
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
        .frame(minHeight: 60)
        .contentShape(Rectangle())
    }
}

struct GoodsOutLinesCard: View {
    let lines: [GoodsOutLine]
    var showsLocationStock = false
    var showsChip = true
    var detail: ((GoodsOutLine) -> String?)?
    var onTap: ((GoodsOutLine) -> Void)?

    var body: some View {
        Card {
            ForEach(Array(lines.enumerated()), id: \.element.id) { index, line in
                if index > 0 {
                    RowDivider()
                }
                GoodsOutLineRow(
                    line: line,
                    detail: detail?(line),
                    showsLocationStock: showsLocationStock,
                    showsChip: showsChip,
                    action: onTap.map { tap in { tap(line) } }
                )
            }
        }
    }
}
