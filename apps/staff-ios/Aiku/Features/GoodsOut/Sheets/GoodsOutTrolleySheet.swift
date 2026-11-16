import SwiftUI

struct GoodsOutTrolleySheet: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let startsPicking: Bool
    let close: () -> Void
    private let store = GoodsOutStore.shared
    @State private var selected: String?

    init(note: GoodsOutDeliveryNote, startsPicking: Bool, close: @escaping () -> Void) {
        self.note = note
        self.startsPicking = startsPicking
        self.close = close
        let available = GoodsOutStore.shared.trolleyChoices(for: note.reference).filter(\.isAvailable)
        let current = note.trolley.flatMap { code in available.first { $0.id == code }?.id }
        _selected = State(initialValue: current ?? available.first?.id)
    }

    var body: some View {
        let choices = store.trolleyChoices(for: note.reference)
        let available = choices.filter(\.isAvailable)
        let unavailable = choices.filter { !$0.isAvailable }
        SheetScaffold(title: startsPicking ? "Select trolley to start picking" : "Change trolley", subtitle: "Delivery note \(note.reference)") {
            SheetSectionLabel("Available trolleys")
            if let error = store.resourcesError, choices.isEmpty {
                GoodsOutLoadProblem(title: "Couldn’t load trolleys", message: error) {
                    Task { await store.loadTrolleys() }
                }
            } else if store.isRemote, store.isLoadingResources, choices.isEmpty {
                GoodsOutSkeletonCard(rows: 2)
            } else if available.isEmpty {
                HelperText("Every trolley is in use.")
            }
            ForEach(available) { choice in
                RadioCard(choice.title, subtitle: choice.subtitle, isSelected: selected == choice.id) {
                    selected = choice.id
                }
            }
            if !unavailable.isEmpty {
                SheetSectionLabel("Unavailable trolleys")
                VStack(spacing: 8) {
                    ForEach(unavailable) { choice in
                        RadioCard(choice.title, subtitle: choice.subtitle, isSelected: false) {}
                            .disabled(true)
                    }
                }
                .opacity(0.55)
            }
            HelperText("Only when this warehouse uses trolleys. Otherwise Start picking begins straight away.")
            LinkButton("All trolleys") {
                close()
                router.open(DispatchRoute.trolleys)
            }
        } footer: {
            if store.isRemote {
                remoteButtons(hasAvailable: !available.isEmpty)
            } else {
            AikuButton(primaryTitle) {
                if startsPicking {
                    let previous = store.startPicking(note.reference, trolley: selected)
                    close()
                    GoodsOutFeedback.done("Picking \(note.reference)\(selected.map { " with \($0)" } ?? "")", previous: previous, toasts: toasts)
                } else {
                    let previous = store.changeTrolley(note.reference, to: selected)
                    close()
                    GoodsOutFeedback.done("Picking into \(selected ?? "no trolley")", previous: previous, toasts: toasts)
                }
            }
            .accessibilityIdentifier("goodsOut.confirmTrolley")
            }
            AikuButton("Cancel", kind: .tertiary, action: close)
        }
        .task {
            guard store.isRemote else { return }
            await store.loadTrolleys()
            if selected == nil || !store.trolleyChoices(for: note.reference).contains(where: { $0.id == selected && $0.isAvailable }) {
                let available = store.trolleyChoices(for: note.reference).filter(\.isAvailable)
                selected = note.trolley.flatMap { code in available.first { $0.id == code }?.id } ?? available.first?.id
            }
        }
    }

    @ViewBuilder
    private func remoteButtons(hasAvailable: Bool) -> some View {
        let working = store.isWorking(on: note.reference)
        if hasAvailable, let selected {
            AikuButton(startsPicking ? "Start picking with \(selected)" : "Use \(selected)", isLoading: working) {
                send(store.trolleyID(named: selected), label: selected)
            }
            .accessibilityIdentifier("goodsOut.confirmTrolley")
        }
        if startsPicking || note.trolley != nil {
            AikuButton(startsPicking ? "Start without a trolley" : "Remove the trolley", kind: hasAvailable ? .tertiary : .primary, isLoading: working && !hasAvailable) {
                send(nil, label: nil)
            }
        }
    }

    private func send(_ trolleyID: Int?, label: String?) {
        let action: GoodsOutNoteAction = startsPicking ? .startPicking(trolleyID: trolleyID) : .changeTrolley(trolleyID: trolleyID)
        let fallback = startsPicking ? "Picking \(note.reference)\(label.map { " with \($0)" } ?? "")" : "Picking into \(label ?? "no trolley")"
        GoodsOutRun.action(action, on: note.reference, fallback: fallback, toasts: toasts, onDone: { close() })
    }

    private var primaryTitle: String {
        if startsPicking {
            return selected.map { "Start picking with \($0)" } ?? "Start picking"
        }
        return selected.map { "Use \($0)" } ?? "Pick without a trolley"
    }
}
