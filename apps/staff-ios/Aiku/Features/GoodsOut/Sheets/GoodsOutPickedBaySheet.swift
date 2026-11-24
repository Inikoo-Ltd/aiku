import SwiftUI

struct GoodsOutPickedBaySheet: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let setsPicked: Bool
    let close: () -> Void
    var onDone: ((GoodsOutDeliveryNote) -> Void)?
    private let store = GoodsOutStore.shared
    @State private var selected: String?

    init(note: GoodsOutDeliveryNote, setsPicked: Bool, close: @escaping () -> Void, onDone: ((GoodsOutDeliveryNote) -> Void)? = nil) {
        self.note = note
        self.setsPicked = setsPicked
        self.close = close
        self.onDone = onDone
        let choices = GoodsOutStore.shared.bayChoices(for: note.reference)
        _selected = State(initialValue: note.pickedBay ?? choices.first?.id)
    }

    var body: some View {
        SheetScaffold(title: "Select picked bay", subtitle: L10n.format("Delivery note %@ · leave the picked goods here for packing", note.reference)) {
            let choices = store.bayChoices(for: note.reference)
            if let error = store.resourcesError, choices.isEmpty {
                GoodsOutLoadProblem(title: "Couldn’t load picked bays", message: error) {
                    Task { await store.loadPickedBays() }
                }
            } else if store.isRemote, store.isLoadingResources, choices.isEmpty {
                GoodsOutSkeletonCard(rows: 2)
            } else if store.isRemote, choices.isEmpty {
                HelperText("This warehouse has no picked bays.")
            }
            ForEach(choices) { choice in
                RadioCard(choice.title, subtitle: choice.subtitle, isSelected: selected == choice.id) {
                    selected = choice.id
                }
            }
            HelperText("Only when the warehouse uses picked bays. With waiting lines, this is where the goods stay until the missing products arrive.")
            LinkButton("All picked bays") {
                close()
                router.open(DispatchRoute.pickedBays)
            }
        } footer: {
            if store.isRemote {
                AikuButton(primaryTitle, look: setsPicked ? GoodsOutActionKey.setAsPicked.look : AikuButtonLook(.primary), isLoading: store.isWorking(on: note.reference)) {
                    sendRemote()
                }
                .accessibilityIdentifier("goodsOut.confirmPickedBay")
            } else {
            AikuButton(primaryTitle) {
                guard let bay = selected else { return }
                if setsPicked {
                    let previous = store.setAsPicked(note.reference, bay: bay)
                    close()
                    let updated = store.note(note.reference)
                    if updated?.state == .waiting {
                        GoodsOutFeedback.done(L10n.format("%@ is waiting · goods stay in %@", note.reference, bay), tone: .warn, previous: previous, toasts: toasts)
                    } else {
                        GoodsOutFeedback.done(L10n.format("%@ set as picked · %@", note.reference, bay), previous: previous, toasts: toasts)
                    }
                    if let updated, previous != nil {
                        onDone?(updated)
                    }
                } else {
                    let previous = store.changePickedBay(note.reference, to: bay)
                    close()
                    GoodsOutFeedback.done(L10n.format("Picked goods moved to %@", bay), previous: previous, toasts: toasts)
                }
            }
            .disabled(selected == nil)
            .accessibilityIdentifier("goodsOut.confirmPickedBay")
            }
            AikuButton("Cancel", kind: .tertiary, action: close)
        }
        .task {
            guard store.isRemote else { return }
            await store.loadPickedBays()
            let choices = store.bayChoices(for: note.reference)
            if selected == nil || !choices.contains(where: { $0.id == selected }) {
                selected = note.pickedBay.flatMap { bay in choices.first { $0.id == bay }?.id } ?? choices.first?.id
            }
        }
    }

    private func sendRemote() {
        let bayID = store.pickedBayID(named: selected)
        let reference = note.reference
        let action: GoodsOutNoteAction = setsPicked ? .setAsPicked(pickedBayID: bayID) : .changePickedBay(pickedBayID: bayID)
        let fallback = setsPicked
            ? L10n.format("%@ set as picked", reference)
            : selected.map { L10n.format("Picked goods moved to %@", $0) } ?? L10n.text("Picked goods moved to no bay")
        GoodsOutRun.action(action, on: reference, fallback: fallback, toasts: toasts) {
            close()
            if setsPicked, let updated = store.note(reference) {
                onDone?(updated)
            }
        }
    }

    private var primaryTitle: String {
        guard let bay = selected else {
            return setsPicked ? "Set as picked" : "No picked bay"
        }
        return setsPicked ? L10n.format("Set as picked · %@", bay) : L10n.format("Move to %@", bay)
    }
}
