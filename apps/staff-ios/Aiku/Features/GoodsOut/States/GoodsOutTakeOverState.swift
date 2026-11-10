import SwiftUI

struct GoodsOutTakeOverStateView: View {
    let note: GoodsOutDeliveryNote

    var body: some View {
        GoodsOutStateStepper(state: note.state)
        HStack(spacing: 12) {
            IconTile(.lock, tone: .warn)
            VStack(alignment: .leading, spacing: 2) {
                Text("Picked by \(note.picker ?? "someone else")")
                    .font(.aiku(15, .bold))
                    .foregroundStyle(AikuColor.text)
                Text("Assigned to somebody else. Take it over so you can pick and pack it.")
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.warnInk)
                    .fixedSize(horizontal: false, vertical: true)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .background(AikuColor.warnSoft, in: RoundedRectangle(cornerRadius: 12))
        .overlay(RoundedRectangle(cornerRadius: 12).strokeBorder(AikuColor.warnBd))
        .accessibilityElement(children: .combine)
        GoodsOutSectionTitle(title: "Items by location", detail: "\(note.pickedProgress) · read-only")
        GoodsOutLinesCard(lines: note.linesByLocation)
    }
}

struct GoodsOutTakeOverFooter: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    private let store = GoodsOutStore.shared

    var body: some View {
        AikuButton("Unlock to pick", icon: .lock) {
            let previous = store.takeOver(note.reference)
            GoodsOutFeedback.done("You are now picking \(note.reference)", previous: previous, toasts: toasts)
        }
        .accessibilityIdentifier("goodsOut.unlockToPick")
    }
}
