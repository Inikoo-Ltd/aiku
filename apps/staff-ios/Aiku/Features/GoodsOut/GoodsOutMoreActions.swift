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
                toasts.show(L10n.format("Packing list of %@ sent to the printer", note.reference), sticky: false)
            }
        case .quick(.task), .quick(.askCRM), .askCRM:
            if note.isRemote {
                GoodsOutRun.openChat(note, router: router)
            } else {
                router.open(GoodsOutRoute.askCRM(reference: note.reference))
            }
        case .editAddress:
            if let present, note.isRemote ? note.allows(.editDeliveryAddress) : note.canEditAddress {
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

    private var canChangePicker: Bool {
        guard note.isRemote else { return note.state == .todo || note.state == .picking }
        return note.allows(.assignPicker) || note.allows(.changePicker) || note.allows(.removePicker)
    }
    private var canRemovePicker: Bool { note.picker != nil && note.allows(.removePicker) }
    private var canChangePacker: Bool { note.isRemote && note.allows(.changePacker) }
    private var canEditAddress: Bool { note.isRemote && note.allows(.editDeliveryAddress) }
    private var editableNotes: [GoodsOutNoteField] {
        [GoodsOutNoteField.warehouse, .shipping].filter { note.packingDetails?.canEdit($0) == true }
    }
    private var canCancel: Bool {
        guard note.isRemote else { return note.canCancel && store.rights.canCancelNotes }
        return note.action(.cancel) != nil
    }

    var body: some View {
        SheetScaffold(title: L10n.format("Delivery note %@", note.reference), subtitle: "Only actions allowed for its state and your rights are shown") {
            if canChangePicker {
                SheetSectionLabel("Picker")
                Card {
                    ListRow(note.picker == nil ? "Assign picker" : "Change picker", subtitle: "Give it to someone else", icon: .person) {
                        present(.picker)
                    }
                    if canRemovePicker {
                        RowDivider()
                        ListRow("Remove picker", subtitle: "Back to To do", icon: .personRemove, iconTone: .neutral) {
                            if note.isRemote {
                                GoodsOutRun.action(.setPicker(userID: nil), on: note.reference, fallback: L10n.format("Picker removed · %@ is back to To do", note.reference), toasts: toasts) { close() }
                                return
                            }
                            let previous = store.removePicker(note.reference)
                            close()
                            GoodsOutFeedback.done(L10n.format("Picker removed · %@ is back to To do", note.reference), tone: .info, previous: previous, toasts: toasts)
                        }
                    }
                }
            }
            if canChangePacker {
                SheetSectionLabel("Packer")
                Card {
                    ListRow("Change packer", subtitle: "Give the packing to someone else", icon: .person) {
                        present(.packer)
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
                    close()
                    GoodsOutMoreActions.perform(.askCRM, note: note, router: router, toasts: toasts)
                }
            }
            if !editableNotes.isEmpty || canEditAddress {
                SheetSectionLabel("Notes & address")
                Card {
                    ForEach(Array(editableNotes.enumerated()), id: \.element) { index, field in
                        if index > 0 {
                            RowDivider()
                        }
                        ListRow(field.title, subtitle: "The change is copied to the order too", icon: .note) {
                            present(.editNote(field))
                        }
                    }
                    if canEditAddress {
                        if !editableNotes.isEmpty {
                            RowDivider()
                        }
                        ListRow("Edit delivery address", subtitle: "Used for the shipping label", icon: .locations) {
                            present(.editAddress)
                        }
                        .accessibilityIdentifier("goodsOut.editAddress")
                    }
                }
            }
            SheetSectionLabel("Documents")
            Card {
                if note.isRemote {
                    ListRow("Documents", subtitle: "Delivery note, packing list and gift message PDFs", icon: .document) {
                        present(.printDeliveryNote)
                    }
                } else {
                    ListRow("Print delivery note", subtitle: "PDF to a printer", icon: .printer) {
                        present(.printDeliveryNote)
                    }
                }
                if note.canGenerateInvoice, !note.isRemote {
                    RowDivider()
                    ListRow("Generate invoice", subtitle: "When the order has no invoice yet", icon: .document) {
                        let previous = store.generateInvoice(note.reference)
                        close()
                        GoodsOutFeedback.done(L10n.format("Invoice generated for %@", note.reference), previous: previous, toasts: toasts)
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
}

extension GoodsOutNoteField {
    var title: String {
        switch self {
        case .warehouse: "Warehouse note (private)"
        case .shipping: "Shipping note"
        }
    }
}

struct GoodsOutPackerSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let close: () -> Void
    @State private var search = ""
    @State private var problem: String?
    private let store = GoodsOutStore.shared

    private var choices: [GoodsOutPickerProfile] {
        let query = search.trimmingCharacters(in: .whitespaces)
        return store.pickers.filter { query.isEmpty || $0.name.localizedCaseInsensitiveContains(query) }
    }

    var body: some View {
        SheetScaffold(
            title: "Change packer",
            subtitle: note.packer.map { L10n.format("Delivery note %@ · packer now: %@", note.reference, $0) } ?? L10n.format("Delivery note %@ · no packer yet", note.reference)
        ) {
            SearchField(text: $search, placeholder: "Search packers")
            if store.isLoadingResources, store.pickers.isEmpty {
                GoodsOutSkeletonCard(rows: 3)
            } else if let error = store.resourcesError, store.pickers.isEmpty {
                GoodsOutLoadProblem(title: "Couldn’t load the packers", message: error) {
                    Task { await store.loadPickers() }
                }
            } else if choices.isEmpty {
                EmptyState(icon: .person, title: "No packer found", message: "Try another name.")
            } else {
                Card {
                    ForEach(Array(choices.enumerated()), id: \.element.userID) { index, picker in
                        if index > 0 {
                            RowDivider()
                        }
                        let isCurrent = picker.userID == note.packingDetails?.packerID
                        ListRow(picker.name, subtitle: isCurrent ? "Packer now" : nil, icon: .person, iconTone: isCurrent ? .success : .neutral) {
                            choose(picker)
                        }
                        .disabled(store.isWorking(on: note.reference))
                    }
                }
            }
            if let problem {
                InlineAlert(tone: .danger, title: problem)
            }
        } footer: {
            AikuButton("Cancel", kind: .tertiary, action: close)
        }
        .task { await store.loadPickers() }
    }

    private func choose(_ picker: GoodsOutPickerProfile) {
        guard let userID = picker.userID else { return }
        Task {
            let result = await store.write(.setPacker(userID: userID), on: note.reference)
            if GoodsOutWriteFeedback.show(result, fallback: "Packer changed", toasts: toasts, showsFailure: false) {
                close()
            } else {
                problem = result.fieldErrors["user_id"] ?? result.failureMessage
            }
        }
    }
}

struct GoodsOutEditNoteSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let field: GoodsOutNoteField
    let close: () -> Void
    @State private var text: String
    @State private var problem: String?
    private let store = GoodsOutStore.shared

    init(note: GoodsOutDeliveryNote, field: GoodsOutNoteField, close: @escaping () -> Void) {
        self.note = note
        self.field = field
        self.close = close
        _text = State(initialValue: (field == .warehouse ? note.warehouseNote : note.shippingLabelNote) ?? "")
    }

    var body: some View {
        SheetScaffold(title: field.title, subtitle: L10n.format("Delivery note %@", note.reference)) {
            AikuTextArea(field.title, text: $text, placeholder: "Write a note", minHeight: 120)
            HelperText("The change is copied to the order too.")
            if let problem {
                InlineAlert(tone: .danger, title: problem)
            }
        } footer: {
            AikuButton("Save note", isLoading: store.isWorking(on: note.reference)) {
                Task {
                    let result = await store.write(.setNote(field, text: text), on: note.reference)
                    if GoodsOutWriteFeedback.show(result, fallback: "Note saved", toasts: toasts, showsFailure: false) {
                        close()
                    } else {
                        problem = result.fieldErrors["text"] ?? result.failureMessage
                    }
                }
            }
            .accessibilityIdentifier("goodsOut.saveNote")
            AikuButton("Cancel", kind: .tertiary, action: close)
        }
    }
}
