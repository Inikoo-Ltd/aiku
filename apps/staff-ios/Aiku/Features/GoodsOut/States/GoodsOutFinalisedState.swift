import SwiftUI

struct GoodsOutFinalisedStateView: View {
    let note: GoodsOutDeliveryNote

    var body: some View {
        GoodsOutStateStepper(state: note.state)
        if note.isCollection {
            GoodsOutSummaryCard(title: "Ready for the customer to collect", detail: "\(note.parcelCountText) · \(note.shippedWeightText)")
            HelperText("When the customer picks it up, set it as collected. That is the collection version of Dispatch.")
        } else {
            GoodsOutSummaryCard(title: "Ready to dispatch", detail: note.dispatchSummary)
            Card {
                ListRow("\(note.dispatchedUnits) items dispatched", subtitle: note.notPickedSummary ?? "Every item was picked", icon: .check, iconTone: .success)
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
        if note.isCollection {
            AikuButton("Set as collected", icon: .check) {
                let previous = store.dispatch(note.reference)
                GoodsOutFeedback.done("\(note.reference) collected by the customer", previous: previous, toasts: toasts)
            }
            .accessibilityIdentifier("goodsOut.setAsCollected")
        } else {
            HStack(spacing: 8) {
                if note.canGenerateInvoice {
                    AikuButton("Generate invoice", icon: .document, kind: .tertiary) {
                        let previous = store.generateInvoice(note.reference)
                        GoodsOutFeedback.done("Invoice generated for \(note.reference)", previous: previous, toasts: toasts)
                    }
                    .accessibilityIdentifier("goodsOut.generateInvoice")
                }
                AikuButton("Dispatch", icon: .deliveryNote) {
                    let previous = store.dispatch(note.reference)
                    GoodsOutFeedback.done("\(note.reference) dispatched", previous: previous, toasts: toasts)
                }
                .accessibilityIdentifier("goodsOut.dispatch")
            }
        }
    }
}

struct GoodsOutDispatchedStateView: View {
    @Environment(AppRouter.self) private var router
    let note: GoodsOutDeliveryNote
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
            Text(note.isCollection ? "\(note.reference) collected" : "\(note.reference) dispatched")
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
            if let next = store.nextNoteToDispatch(after: note.reference) {
                router.replace(with: GoodsOutRoute.deliveryNote(reference: next.reference))
            } else {
                router.replace(with: GoodsOutRoute.deliveryNotes)
            }
        }
        .accessibilityIdentifier("goodsOut.nextDeliveryNote")
        AikuButton("Back to the backlog", kind: .tertiary) {
            router.replace(with: GoodsOutRoute.backlog)
        }
        if !note.emails.isEmpty {
            Card {
                ListRow("Dispatched emails", subtitle: emailsText, icon: .email, iconTone: .success, showsChevron: true) {
                    router.open(GoodsOutRoute.dispatchedEmails(reference: note.reference))
                }
            }
            .accessibilityIdentifier("goodsOut.dispatchedEmails")
        }
    }

    private var detail: String {
        if note.isCollection { return "\(note.parcelCountText) collected by the customer" }
        return "\(note.parcelCountText) with \(note.shipperTitle)"
    }

    private var emailsText: String {
        let count = note.emails.count
        return "\(count) \(count == 1 ? "email" : "emails") sent to the customer"
    }
}
