import SwiftUI

struct GoodsOutPackingStateView: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let present: (GoodsOutNoteSheet) -> Void
    private let store = GoodsOutStore.shared

    var body: some View {
        GoodsOutStateStepper(state: note.state)
        if !note.packingMaterials.isEmpty {
            GoodsOutPackerMustAdd(materials: note.packingMaterials)
        }
        Card {
            ListRow(note.pickedBay ?? "No picked bay", subtitle: "Picked bay", icon: .warehouse) {
                present(.pickedBay(setsPicked: false))
            } trailing: {
                GoodsOutLinkLabel("Change")
            }
        }
        if let next = note.nextLineToPack {
            GoodsOutScanPromptCard(
                title: "Scan to pack",
                location: note.currentParcel?.title ?? "Parcel 1",
                detail: "\(next.sko) · \(next.packRemaining) left on this item",
                caption: "Put it in this box"
            ) {
                scan()
            }
            .accessibilityIdentifier("goodsOut.scanToPack")
            .accessibilityHint("Simulates scanning one unit into the parcel")
        }
        GoodsOutSectionTitle(title: "Lines", detail: note.packedProgress)
        GoodsOutPackLinesCard(lines: note.packableLines) { line in
            present(.packLine(line: line.id))
        }
        GoodsOutSectionTitle(title: "Parcels (\(note.parcels.count))", detail: "kg · L × W × H")
        Card {
            ForEach(note.parcels) { parcel in
                GoodsOutParcelEditorRow(reference: note.reference, parcel: parcel)
                    .id(parcel.number)
                RowDivider()
            }
            Button {
                store.addParcel(note.reference)
            } label: {
                Text("+ Add another parcel")
                    .font(.aiku(14, .semibold))
                    .foregroundStyle(AikuColor.link)
                    .frame(maxWidth: .infinity, minHeight: 48)
                    .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .accessibilityIdentifier("goodsOut.addParcel")
        }
        HelperText("Enter length, width and height of every parcel, they are needed to set as packed. Weight starts from the estimated weight of the items.")
    }

    private func scan() {
        switch store.scanToPack(note.reference) {
        case .packed:
            break
        case .allPacked:
            toasts.show("Every item is packed", tone: .info)
        case .notOnNote(let code):
            toasts.show("\(code) is not on this delivery note", tone: .danger)
        case .notPacking:
            toasts.show("\(note.reference) is not being packed", tone: .warn)
        }
    }
}

struct GoodsOutPackingFooter: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    private let store = GoodsOutStore.shared

    var body: some View {
        if let blocker = note.packingBlocker {
            GoodsOutFooterStatus(title: blocker, detail: "Pack every line into a parcel and enter its dimensions. Then Set as packed appears.")
        } else {
            AikuButton("Set as packed") {
                let previous = store.setAsPacked(note.reference)
                GoodsOutFeedback.done("\(note.reference) set as packed", previous: previous, toasts: toasts)
                if previous != nil, note.needsShipment, !note.shipsViaPlatform {
                    router.open(GoodsOutRoute.addShipment(reference: note.reference))
                }
            }
            .accessibilityIdentifier("goodsOut.setAsPacked")
        }
    }
}
