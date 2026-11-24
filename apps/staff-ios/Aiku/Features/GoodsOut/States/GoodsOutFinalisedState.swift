import SwiftUI

struct GoodsOutFinalisedStateView: View {
    let note: GoodsOutDeliveryNote
    var present: (GoodsOutNoteSheet) -> Void = { _ in }

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
            GoodsOutSummaryCard(title: "Ready for the customer to collect", detail: "\(note.parcelCountText) · \(note.shippedWeightText)")
            HelperText("When the customer picks it up, set it as collected. That is the collection version of Dispatch.")
        } else {
            GoodsOutSummaryCard(title: "Ready to dispatch", detail: note.dispatchSummary)
            Card {
                ListRow(L10n.format("%lld items dispatched", note.dispatchedUnits), subtitle: note.notPickedSummary ?? "Every item was picked", icon: .check, iconTone: .success)
                if note.invoiceGenerated {
                    RowDivider()
                    ListRow("Invoice generated", subtitle: "Goes in the parcel with the delivery note", icon: .document, iconTone: .neutral)
                }
            }
            HelperText("Dispatch when the parcel leaves the warehouse.")
        }
    }
}

struct GoodsOutFinalisedFooter: View {
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
        if note.isCollection {
            AikuButton("Set as collected", icon: .check) {
                let previous = store.dispatch(note.reference)
                GoodsOutFeedback.done(L10n.format("%@ collected by the customer", note.reference), previous: previous, toasts: toasts)
            }
            .accessibilityIdentifier("goodsOut.setAsCollected")
        } else {
            HStack(spacing: 8) {
                if note.canGenerateInvoice {
                    AikuButton("Generate invoice", icon: .document, kind: .tertiary) {
                        let previous = store.generateInvoice(note.reference)
                        GoodsOutFeedback.done(L10n.format("Invoice generated for %@", note.reference), previous: previous, toasts: toasts)
                    }
                    .accessibilityIdentifier("goodsOut.generateInvoice")
                }
                AikuButton("Dispatch", icon: .deliveryNote) {
                    let previous = store.dispatch(note.reference)
                    GoodsOutFeedback.done(L10n.format("%@ dispatched", note.reference), previous: previous, toasts: toasts)
                }
                .accessibilityIdentifier("goodsOut.dispatch")
            }
        }
    }
}

struct GoodsOutDispatchedStateView: View {
    @Environment(AppRouter.self) private var router
    let note: GoodsOutDeliveryNote
    @State private var opener = GoodsOutDocumentOpener()
    private let store = GoodsOutStore.shared

    var body: some View {
        GoodsOutStateStepper(state: note.state)
        VStack(spacing: 10) {
            AppIcon.check.image
                .font(.system(size: 36, weight: .semibold))
                .foregroundStyle(AikuColor.success)
                .frame(width: 72, height: 72)
                .background(AikuColor.successSoft, in: Circle())
                .accessibilityHidden(true)
            Text(note.isCollection ? L10n.format("%@ collected", note.reference) : L10n.format("%@ dispatched", note.reference))
                .font(.aiku(22, .bold))
                .foregroundStyle(AikuColor.text)
                .accessibilityAddTraits(.isHeader)
            Text(detail)
                .font(.aiku(14))
                .foregroundStyle(AikuColor.text3)
        }
        .multilineTextAlignment(.center)
        .padding(.horizontal, 16)
        .padding(.vertical, 28)
        .frame(maxWidth: .infinity)
        .accessibilityElement(children: .combine)
        AikuButton("Next delivery note") {
            if note.isRemote {
                router.replace(with: GoodsOutRoute.deliveryNotes)
            } else if let next = store.nextNoteToDispatch(after: note.reference) {
                router.replace(with: GoodsOutRoute.deliveryNote(reference: next.reference))
            } else {
                router.replace(with: GoodsOutRoute.deliveryNotes)
            }
        }
        .accessibilityIdentifier("goodsOut.nextDeliveryNote")
        AikuButton("Back to the backlog", kind: .tertiary) {
            router.replace(with: GoodsOutRoute.backlog)
        }
        if note.isRemote {
            GoodsOutRemoteShipmentsBlock(note: note, opener: opener)
                .goodsOutDocumentPreview(opener)
        }
    }

    private var detail: String {
        let count = max(note.parcels.count, 1)
        if note.isCollection { return L10n.format("%lld parcels collected by the customer", count) }
        return L10n.format("%lld boxes with %@", count, note.shipperTitle)
    }
}
