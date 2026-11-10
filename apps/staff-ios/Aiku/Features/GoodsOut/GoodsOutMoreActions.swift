import SwiftUI

enum GoodsOutExternalAction: Hashable {
    case quick(GoodsOutQuickAction)
    case editAddress
    case askCRM

    var title: String {
        switch self {
        case .quick(let action): action.title
        case .editAddress: "Edit delivery address"
        case .askCRM: "Ask CRM"
        }
    }
}

@MainActor
enum GoodsOutMoreActions {
    static func present(_ present: (GoodsOutNoteSheet) -> Void) {
        present(.moreActions)
    }

    static func perform(
        _ action: GoodsOutExternalAction,
        note: GoodsOutDeliveryNote,
        router: AppRouter,
        toasts: ToastCenter,
        present: ((GoodsOutNoteSheet) -> Void)? = nil
    ) {
        switch action {
        case .quick(.packingList):
            if let present {
                present(.printDeliveryNote)
            } else {
                toasts.show("Packing list of \(note.reference) sent to the printer", sticky: false)
            }
        case .quick(.task), .quick(.askCRM), .askCRM:
            router.open(GoodsOutRoute.askCRM(reference: note.reference))
        case .editAddress:
            if let present, note.canEditAddress {
                present(.editAddress)
            } else {
                toasts.show("The delivery address can’t be changed now", tone: .warn)
            }
        }
    }
}

struct GoodsOutMoreActionsSheet: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let present: (GoodsOutNoteSheet) -> Void
    let close: () -> Void
    private let store = GoodsOutStore.shared

    private var canChangePicker: Bool { note.state == .todo || note.state == .picking }
    private var showsEmails: Bool { note.state == .dispatched || !note.emails.isEmpty }
    private var showsCustoms: Bool { !note.isCollection }
    private var canCancel: Bool { note.canCancel && store.rights.canCancelNotes }

    var body: some View {
        SheetScaffold(title: "Delivery note \(note.reference)", subtitle: "Only actions allowed for its state and your rights are shown") {
            if canChangePicker {
                SheetSectionLabel("Picker")
                Card {
                    ListRow(note.picker == nil ? "Assign picker" : "Change picker", subtitle: "Give it to someone else", icon: .person) {
                        present(.picker)
                    }
                    if note.picker != nil {
                        RowDivider()
                        ListRow("Remove picker", subtitle: "Back to To do", icon: .personRemove, iconTone: .neutral) {
                            let previous = store.removePicker(note.reference)
                            close()
                            GoodsOutFeedback.done("Picker removed · \(note.reference) is back to To do", tone: .info, previous: previous, toasts: toasts)
                        }
                    }
                }
            }
            if let step = note.undoStep {
                SheetSectionLabel("Undo")
                Card {
                    ListRow(step.title, subtitle: step.detail, icon: .undo, iconTone: .neutral) {
                        present(.undo(step))
                    }
                }
            }
            SheetSectionLabel("Talk & track")
            Card {
                ListRow("Ask CRM", subtitle: "Chat with customer services about this note", icon: .chat) {
                    go(GoodsOutRoute.askCRM(reference: note.reference))
                }
                RowDivider()
                ListRow("Add task", subtitle: "A staff task linked to this delivery note", icon: .check) {
                    go(GoodsOutRoute.askCRM(reference: note.reference))
                }
                if showsEmails {
                    RowDivider()
                    ListRow("Dispatched emails", subtitle: "Emails sent to the customer", icon: .email) {
                        go(GoodsOutRoute.dispatchedEmails(reference: note.reference))
                    }
                }
            }
            SheetSectionLabel("Documents")
            Card {
                ListRow("Print delivery note", subtitle: "PDF to a printer", icon: .printer) {
                    present(.printDeliveryNote)
                }
                if showsCustoms {
                    RowDivider()
                    ListRow("Tariff codes / Origin", subtitle: "Customs data for each item", icon: .globe) {
                        go(GoodsOutRoute.tariffCodes(reference: note.reference))
                    }
                    RowDivider()
                    ListRow("Customs items file", subtitle: "Preview and download for the carrier", icon: .document) {
                        go(GoodsOutRoute.customsFile(reference: note.reference))
                    }
                }
                if note.canGenerateInvoice {
                    RowDivider()
                    ListRow("Generate invoice", subtitle: "When the order has no invoice yet", icon: .document) {
                        let previous = store.generateInvoice(note.reference)
                        close()
                        GoodsOutFeedback.done("Invoice generated for \(note.reference)", previous: previous, toasts: toasts)
                    }
                }
            }
            if canCancel {
                SheetSectionLabel("Supervisor / CS")
                Card {
                    ListRow("Cancel delivery note", subtitle: "Dispatching supervisor, org admin, or order/CRM edit rights", icon: .close, iconTone: .danger) {
                        present(.cancelNote)
                    }
                    .accessibilityIdentifier("goodsOut.cancelNote")
                }
            }
        } footer: {
            AikuButton("Close", kind: .tertiary, action: close)
        }
    }

    private func go(_ route: GoodsOutRoute) {
        close()
        router.open(route)
    }
}
