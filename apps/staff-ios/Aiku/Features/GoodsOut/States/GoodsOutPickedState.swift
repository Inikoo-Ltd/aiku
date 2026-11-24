import SwiftUI

struct GoodsOutPickedStateView: View {
    let note: GoodsOutDeliveryNote
    let present: (GoodsOutNoteSheet) -> Void

    var body: some View {
        GoodsOutStateStepper(state: note.state)
        if note.handlingBlocked {
            InlineAlert(tone: .danger, icon: .warning, title: "Handling blocked", message: "Set as waiting from the packing queue. A supervisor needs to look at it before packing.")
        }
        Card {
            if note.allows(.changePickedBay) {
                ListRow(note.pickedBay ?? "No picked bay", subtitle: "Picked goods are waiting here for packing", icon: .warehouse) {
                    present(.pickedBay(setsPicked: false))
                } trailing: {
                    Text("Change")
                        .font(.aiku(14, .semibold))
                        .foregroundStyle(AikuColor.link)
                }
            } else {
                ListRow(note.pickedBay ?? "No picked bay", subtitle: "Picked goods are waiting here for packing", icon: .warehouse)
            }
            RowDivider()
            ListRow("Packer", subtitle: packerText, icon: .person)
        }
        GoodsOutSectionTitle(title: "Picked")
        GoodsOutLinesCard(lines: note.linesByLocation)
        HelperText("Undo set as picked (back to picking) is in the ⋯ menu.")
    }

    private var packerText: String {
        guard let picker = note.picker else { return "Not assigned yet" }
        return note.packerSameAsPicker ? L10n.format("Same as picker · %@", picker) : "Chosen by the supervisor"
    }
}

struct GoodsOutPickedFooter: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    private let store = GoodsOutStore.shared

    var body: some View {
        if note.isRemote {
            GoodsOutRemotePackingFooter(note: note)
        } else {
            sampleFooter
        }
    }

    private var sampleFooter: some View {
        AikuButton("Start packing", icon: .sko) {
            let previous = store.startPacking(note.reference)
            GoodsOutFeedback.done(L10n.format("Packing started for %@", note.reference), previous: previous, toasts: toasts)
        }
        .accessibilityIdentifier("goodsOut.startPacking")
    }
}
