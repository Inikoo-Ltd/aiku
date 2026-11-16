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
                GoodsOutSummaryCard(title: "Shipped by the platform", detail: "The label comes from \(platform), not from our shippers.", icon: .deliveryNote)
                GoodsOutParcelsCard(parcels: note.parcels)
                printSection
                HelperText("The same applies to \(platform == "Faire" ? "TikTok" : "Faire") orders (“Get shipment from \(platform == "Faire" ? "TikTok" : "Faire")”). After the shipment arrives, print the label and finalise as usual.")
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
                AikuButton("Get shipment from \(platform)", icon: .deliveryNote) {
                    let previous = store.getPlatformShipment(note.reference)
                    let tracking = store.note(note.reference)?.shipments.last?.tracking ?? ""
                    GoodsOutFeedback.done("Shipment from \(platform) added · \(tracking)", previous: previous, toasts: toasts)
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
                GoodsOutFeedback.done("\(note.reference) finalised for collection", previous: previous, toasts: toasts)
            }
            .accessibilityIdentifier("goodsOut.finaliseForCollection")
        } else {
            HStack(spacing: 8) {
                AikuButton("Finalise", kind: .tertiary) {
                    let previous = store.finalise(note.reference)
                    GoodsOutFeedback.done("\(note.reference) finalised", previous: previous, toasts: toasts)
                }
                .accessibilityIdentifier("goodsOut.finalise")
                AikuButton("Finalise and Dispatch") {
                    let previous = store.finaliseAndDispatch(note.reference)
                    GoodsOutFeedback.done("\(note.reference) finalised and dispatched", previous: previous, toasts: toasts)
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
                let engine = note.externalEngine ?? "the platform"
                GoodsOutSummaryCard(title: "Shipped by \(engine)", detail: "The shipment and its label come from \(engine). If it isn’t there yet, try again in a moment. Then print the label and finalise as usual.", icon: .deliveryNote)
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
            GoodsOutSectionTitle(title: "Parcels (\(note.parcels.count))")
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
}
