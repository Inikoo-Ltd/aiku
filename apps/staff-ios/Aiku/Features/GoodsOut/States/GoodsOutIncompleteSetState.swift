import SwiftUI

struct GoodsOutIncompleteSetStateView: View {
    let note: GoodsOutDeliveryNote

    var body: some View {
        GoodsOutStateStepper(state: note.state)
        if let set = note.incompleteSet {
            VStack(alignment: .leading, spacing: 6) {
                Text("A part of a set sold only complete was not found")
                    .font(.aiku(15, .bold))
                    .foregroundStyle(AikuColor.text)
                Text(set.instruction)
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.warnInk)
                    .fixedSize(horizontal: false, vertical: true)
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(AikuColor.warnSoft, in: RoundedRectangle(cornerRadius: 12))
            .overlay(RoundedRectangle(cornerRadius: 12).strokeBorder(AikuColor.warnBd))
            .accessibilityElement(children: .combine)
            GoodsOutSectionTitle(title: "Set · \(set.name)")
            Card {
                ForEach(Array(set.parts.enumerated()), id: \.element.id) { index, part in
                    if index > 0 {
                        RowDivider()
                    }
                    GoodsOutSetPartRow(part: part, detail: set.detail(for: part))
                }
            }
            HelperText("Before anything was picked the button is “Mark set parts not picked”. A supervisor can instead use Back to picking to give the missing part to the picker to look for again.")
        }
    }
}

private struct GoodsOutSetPartRow: View {
    let part: GoodsOutSetPart
    let detail: String

    var body: some View {
        HStack(spacing: 12) {
            ProductThumb(size: 48, label: "Product photo of \(part.sko)")
            VStack(alignment: .leading, spacing: 3) {
                FlowLayout(spacing: 8, lineSpacing: 4) {
                    Text(part.sko)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                    StatusChip(part.chip)
                }
                Text(detail)
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
                    .fixedSize(horizontal: false, vertical: true)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            (Text("\(part.picked)")
                .font(.aiku(16, .bold))
                .foregroundStyle(part.wasPicked ? AikuColor.success : AikuColor.danger)
                + Text(" / \(part.required)")
                .font(.aiku(13, .medium))
                .foregroundStyle(AikuColor.text3))
                .monospacedDigit()
                .accessibilityLabel("\(part.picked) of \(part.required)")
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
        .frame(minHeight: 60)
        .accessibilityElement(children: .combine)
    }
}

struct GoodsOutIncompleteSetFooter: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    private let store = GoodsOutStore.shared

    var body: some View {
        if let set = note.incompleteSet {
            AikuButton(set.actionTitle, icon: .check) {
                let previous = store.partsPutBack(note.reference)
                GoodsOutFeedback.done(set.hasPicks ? "Set parts put back on the shelf" : "Set parts marked not picked", previous: previous, toasts: toasts)
            }
            .accessibilityIdentifier("goodsOut.partsPutBack")
        }
    }
}
