import SwiftUI

struct GoodsOutTakeOverStateView: View {
    let note: GoodsOutDeliveryNote

    var body: some View {
        GoodsOutStateStepper(state: note.state)
        HStack(spacing: 12) {
            IconTile(.lock, tone: .warn)
            VStack(alignment: .leading, spacing: 2) {
                Text(note.picker.map { L10n.format("Picked by %@", $0) } ?? L10n.text("Picked by someone else"))
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
        GoodsOutSectionTitle(title: "Items by location", detail: L10n.format("%@ · read-only", note.pickedProgress))
        GoodsOutLinesCard(lines: note.linesByLocation)
    }
}

struct GoodsOutTakeOverFooter: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    private let store = GoodsOutStore.shared

    var body: some View {
        AikuButton(note.action(.takeOver)?.label ?? "Unlock to pick", icon: .lock, isLoading: store.isWorking(on: note.reference)) {
            if note.isRemote {
                GoodsOutRun.action(.takeOver, on: note.reference, fallback: L10n.format("You can work on %@", note.reference), toasts: toasts)
            } else {
                let previous = store.takeOver(note.reference)
                GoodsOutFeedback.done(L10n.format("You are now picking %@", note.reference), previous: previous, toasts: toasts)
            }
        }
        .accessibilityIdentifier("goodsOut.unlockToPick")
    }
}
