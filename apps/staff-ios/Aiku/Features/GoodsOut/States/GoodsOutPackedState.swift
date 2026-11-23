import SwiftUI

struct GoodsOutPackedStateView: View {
    @Environment(AppRouter.self) private var router
    let note: GoodsOutDeliveryNote
    let present: (GoodsOutNoteSheet) -> Void

    var body: some View {
        if note.isRemote {
            GoodsOutRemoteShippingStateView(note: note, present: present)
        } else {
            sampleBody
        }
    }

    @ViewBuilder
    private var sampleBody: some View {
        GoodsOutStateStepper(state: note.state)
        if note.isCollection {
            GoodsOutSummaryCard(title: "Collection", detail: "The customer collects it from the warehouse. No shipping, no shipment.", icon: .locations)
            GoodsOutParcelsCard(parcels: note.parcels)
            printSection
            HelperText("Collection is set on the order. On a replacement delivery note it can be switched on the delivery note itself. Finalise for collection leaves it ready for the customer.")
        } else if note.shipments.isEmpty {
            if note.shipsViaPlatform, let platform = note.platform {
                let otherPlatform = platform == "Faire" ? "TikTok" : "Faire"
                GoodsOutSummaryCard(title: "Shipped by the platform", detail: L10n.format("The label comes from %@, not from our shippers.", platform), icon: .deliveryNote)
                GoodsOutParcelsCard(parcels: note.parcels)
                printSection
                HelperText(L10n.format("The same applies to %@ orders (“Get shipment from %@”). After the shipment arrives, print the label and finalise as usual.", otherPlatform, otherPlatform))
            } else {
                Card {
                    ListRow("No shipment yet", subtitle: "Add a shipment to create the label", icon: .deliveryNote, iconTone: .warn, showsChevron: true) {
                        router.open(GoodsOutRoute.addShipment(reference: note.reference))
                    }
                }
                GoodsOutSectionTitle(title: "Parcels")
                GoodsOutParcelsCard(parcels: note.parcels)
                printSection
            }
        } else {
            GoodsOutShipmentsCard(note: note)
            if !note.shipsViaPlatform {
                AikuButton("Add another shipment", kind: .tertiary) {
                    router.open(GoodsOutRoute.addShipment(reference: note.reference))
                }
                .accessibilityIdentifier("goodsOut.addAnotherShipment")
            }
            printSection
            HelperText("While any insert is unprinted, Finalise is blocked: “Print every insert before continuing”. If the carrier refuses, the label error shows its answer.")
        }
    }

    @ViewBuilder
    private var printSection: some View {
        if !note.leaflets.isEmpty {
            GoodsOutSectionTitle(title: "Print")
            GoodsOutLeafletsCard(note: note)
        }
    }
}

struct GoodsOutPackedFooter: View {
    @Environment(AppRouter.self) private var router
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

    @ViewBuilder
    private var sampleFooter: some View {
        if note.needsShipment, note.shipments.isEmpty {
            if note.shipsViaPlatform, let platform = note.platform {
                AikuButton(L10n.format("Get shipment from %@", platform), icon: .deliveryNote) {
                    let previous = store.getPlatformShipment(note.reference)
                    let tracking = store.note(note.reference)?.shipments.last?.tracking ?? ""
                    GoodsOutFeedback.done(L10n.format("Shipment from %@ added · %@", platform, tracking), previous: previous, toasts: toasts)
                }
                .accessibilityIdentifier("goodsOut.getPlatformShipment")
            } else {
                AikuButton("Add shipment · create label", icon: .deliveryNote) {
                    router.open(GoodsOutRoute.addShipment(reference: note.reference))
                }
                .accessibilityIdentifier("goodsOut.addShipment")
            }
        } else if !note.unprintedLeaflets.isEmpty {
            AikuButton("Print every insert", icon: .printer) {
                let previous = store.printAllLeaflets(note.reference)
                GoodsOutFeedback.done("Every insert printed", previous: previous, toasts: toasts)
            }
            .accessibilityIdentifier("goodsOut.printInserts")
            GoodsOutFooterCaption("Print every insert before continuing")
        } else if note.isCollection {
            AikuButton("Finalise for collection") {
                let previous = store.finalise(note.reference)
                GoodsOutFeedback.done(L10n.format("%@ finalised for collection", note.reference), previous: previous, toasts: toasts)
            }
            .accessibilityIdentifier("goodsOut.finaliseForCollection")
        } else {
            HStack(spacing: 8) {
                AikuButton("Finalise", kind: .tertiary) {
                    let previous = store.finalise(note.reference)
                    GoodsOutFeedback.done(L10n.format("%@ finalised", note.reference), previous: previous, toasts: toasts)
                }
                .accessibilityIdentifier("goodsOut.finalise")
                AikuButton("Finalise and Dispatch") {
                    let previous = store.finaliseAndDispatch(note.reference)
                    GoodsOutFeedback.done(L10n.format("%@ finalised and dispatched", note.reference), previous: previous, toasts: toasts)
                }
                .accessibilityIdentifier("goodsOut.finaliseAndDispatch")
            }
        }
    }
}

struct GoodsOutRemoteShippingStateView: View {
    let note: GoodsOutDeliveryNote
    let present: (GoodsOutNoteSheet) -> Void
    @State private var opener = GoodsOutDocumentOpener()

    var body: some View {
        GoodsOutStateStepper(state: note.state)
        if note.state == .finalised {
            GoodsOutSummaryCard(
                title: note.isCollection ? "Ready for the customer to collect" : "Ready to dispatch",
                detail: [note.shipperTitle, note.parcelCountText, note.shippedWeightText].joined(separator: " · ")
            )
        }
        if note.isCollection {
            GoodsOutSummaryCard(title: "Collection", detail: "The customer collects it from the warehouse. No shipping, no shipment.", icon: .locations)
        } else if note.shipments.isEmpty {
            if note.isShippingByExternal {
                GoodsOutSummaryCard(title: externalShippingTitle, detail: externalShippingDetail, icon: .deliveryNote)
            } else if note.allows(.addShipment) {
                Card {
                    ListRow("No shipment yet", subtitle: "Add a shipment to create the label", icon: .deliveryNote, iconTone: .warn)
                }
            }
        }
        GoodsOutRemoteShipmentsBlock(note: note, opener: opener)
        GoodsOutBoxPackingListBlock(note: note, opener: opener)
        if note.state == .packed, note.packingDetails?.parcelsEditable == true {
            GoodsOutRemoteParcelsEditor(note: note)
        } else if !note.parcels.isEmpty {
            GoodsOutSectionTitle(title: L10n.format("Parcels (%lld)", note.parcels.count))
            GoodsOutParcelsCard(parcels: note.parcels)
        }
        if !note.packableLines.isEmpty {
            GoodsOutSectionTitle(title: "Lines", detail: note.packedProgress)
            GoodsOutPackLinesCard(lines: note.packableLines, onTap: note.canPackLines ? { present(.packLine(line: $0.id)) } : nil)
        }
        if let footNote = note.packingFootNote {
            HelperText(footNote)
        }
        Color.clear
            .frame(height: 0)
            .goodsOutDocumentPreview(opener)
    }

    private var externalShippingTitle: String {
        guard let engine = note.externalEngine else { return "Shipped by the platform" }
        return L10n.format("Shipped by %@", engine)
    }

    private var externalShippingDetail: String {
        guard let engine = note.externalEngine else {
            return "The shipment and its label come from the platform. If it isn’t there yet, try again in a moment. Then print the label and finalise as usual."
        }
        return L10n.format("The shipment and its label come from %@. If it isn’t there yet, try again in a moment. Then print the label and finalise as usual.", engine)
    }
}
