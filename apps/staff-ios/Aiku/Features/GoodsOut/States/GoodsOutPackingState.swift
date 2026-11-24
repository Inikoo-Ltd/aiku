import SwiftUI

struct GoodsOutPackingStateView: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let present: (GoodsOutNoteSheet) -> Void
    private let store = GoodsOutStore.shared

    var body: some View {
        if note.isRemote {
            GoodsOutRemotePackingStateView(note: note, present: present)
        } else {
            sampleBody
        }
    }

    @ViewBuilder
    private var sampleBody: some View {
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
                location: note.currentParcel?.title ?? L10n.format("Parcel %lld", 1),
                detail: [next.sko, L10n.format("%lld left on this item", next.packRemaining)].joined(separator: " · "),
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
        GoodsOutSectionTitle(title: L10n.format("Parcels (%lld)", note.parcels.count), detail: "kg · L × W × H")
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
            toasts.show(L10n.format("%@ is not on this delivery note", code), tone: .danger)
        case .notPacking:
            toasts.show(L10n.format("%@ is not being packed", note.reference), tone: .warn)
        }
    }
}

struct GoodsOutPackingFooter: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    private let store = GoodsOutStore.shared

    var body: some View {
        if note.isRemote {
            GoodsOutRemotePackingFooter(note: note)
        } else if let blocker = note.packingBlocker {
            GoodsOutFooterStatus(title: blocker, detail: "Pack every line into a parcel and enter its dimensions. Then Set as packed appears.")
        } else {
            AikuButton("Set as packed") {
                let previous = store.setAsPacked(note.reference)
                GoodsOutFeedback.done(L10n.format("%@ set as packed", note.reference), previous: previous, toasts: toasts)
                if previous != nil, note.needsShipment, !note.shipsViaPlatform {
                    router.open(GoodsOutRoute.addShipment(reference: note.reference))
                }
            }
            .accessibilityIdentifier("goodsOut.setAsPacked")
        }
    }
}

struct GoodsOutRemotePackingStateView: View {
    let note: GoodsOutDeliveryNote
    let present: (GoodsOutNoteSheet) -> Void
    @Environment(ToastCenter.self) private var toasts
    @State private var opener = GoodsOutDocumentOpener()
    @State private var isScanning = false

    var body: some View {
        GoodsOutStateStepper(state: note.state)
        if !note.packingMaterials.isEmpty {
            GoodsOutPackerMustAdd(materials: note.packingMaterials)
        }
        Card {
            ListRow(note.pickedBay ?? "No picked bay", subtitle: "Picked bay", icon: .warehouse)
            RowDivider()
            ListRow("Packer", subtitle: note.packer ?? "No packer yet", icon: .person)
        }
        GoodsOutInsertsBlock(note: note, opener: opener)
        GoodsOutBoxPackingListBlock(note: note, opener: opener)
        if note.canScanToPack {
            GoodsOutScanPromptCard(
                title: "Scan to pack",
                location: note.nextLineToPack?.sko ?? L10n.text("Every line is packed"),
                detail: note.nextLineToPack.map { L10n.format("%@ left to pack · tap to open the camera", $0.packing?.toPackText ?? String($0.packRemaining)) } ?? "Nothing is left to pack on this delivery note",
                caption: "Camera or Bluetooth scanner"
            ) {
                isScanning = true
            }
            .accessibilityIdentifier("goodsOut.scanToPack")
            .hardwareScanner(isEnabled: !isScanning, onCode: packScanned)
        }
        GoodsOutSectionTitle(title: "Lines", detail: note.packedProgress)
        GoodsOutPackLinesCard(lines: note.packableLines, onTap: note.canPackLines ? { present(.packLine(line: $0.id)) } : nil)
        if note.packingDetails?.parcelsEditable == true {
            GoodsOutRemoteParcelsEditor(note: note)
        } else if !note.parcels.isEmpty {
            GoodsOutSectionTitle(title: L10n.format("Parcels (%lld)", note.parcels.count))
            GoodsOutParcelsCard(parcels: note.parcels)
        }
        if let footNote = note.packingFootNote {
            HelperText(footNote)
        }
        Color.clear
            .frame(height: 0)
            .goodsOutDocumentPreview(opener)
            .fullScreenCover(isPresented: $isScanning) {
                GoodsOutPackScanScreen(reference: note.reference) { isScanning = false }
            }
    }

    private func packScanned(_ code: String) {
        Task {
            switch await GoodsOutStore.shared.scanToPack(note.reference, barcode: code) {
            case .packed(let sko, let message, let lineDone):
                toasts.show(message ?? (lineDone ? L10n.format("%@ · line packed", sko) : "\(sko) +1"), sticky: false)
            case .refused(let message):
                toasts.show(message, tone: .danger, sticky: false)
            }
        }
    }
}
