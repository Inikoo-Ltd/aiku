import SwiftUI

struct DispatchChooseDeliveryNotesSheet: View {
    enum Mode {
        case create
        case add(PickingSessionStore)
    }

    let mode: Mode
    let onDone: (MobilePickingSession?) -> Void
    @Environment(ToastCenter.self) private var toasts
    @State private var chosen: Set<Int> = []
    @State private var problem: String?
    @State private var isAdding = false
    private let store = PickingSessionsStore.shared

    var body: some View {
        SheetScaffold(title: title, subtitle: "Only delivery notes waiting to be picked, outside any picking session, can be chosen") {
            if let error = store.candidatesError {
                DispatchLoadProblem(title: "Couldn’t load the delivery notes", message: error) { await store.loadCandidates() }
            } else if store.isLoadingCandidates && store.candidates.isEmpty {
                DispatchSkeletonList(rows: 3)
            } else if store.candidates.isEmpty {
                EmptyState(icon: .deliveryNote, title: "No delivery notes to add", message: "Delivery notes waiting to be picked show here.")
            } else {
                ScrollView {
                    CardList(store.candidates) { note in
                        ListRow(
                            note.reference,
                            subtitle: subtitle(note),
                            chips: note.stateLabel.map { [Chip($0, .neutral)] } ?? [],
                            action: { toggle(note.id) }
                        ) {
                            IconTile(chosen.contains(note.id) ? .checkCircle : .deliveryNote, tone: chosen.contains(note.id) ? .success : .neutral)
                        } trailing: {
                            EmptyView()
                        }
                        .accessibilityAddTraits(chosen.contains(note.id) ? .isSelected : [])
                    }
                }
                .frame(maxHeight: 420)
            }
            if let problem {
                FieldError(problem)
            }
        } footer: {
            AikuButton(confirmTitle, isLoading: isAdding || store.isCreating) {
                Task { await confirm() }
            }
            .disabled(chosen.isEmpty)
            AikuButton("Cancel", kind: .tertiary) { onDone(nil) }
        }
        .task { await store.loadCandidates() }
    }

    private var title: String {
        switch mode {
        case .create: "New picking session"
        case .add: "Add delivery notes"
        }
    }

    private var confirmTitle: String {
        let count = PickingSessionFormat.count(chosen.count, "delivery note")
        switch mode {
        case .create: return chosen.isEmpty ? "Create picking session" : "Create with \(count)"
        case .add: return chosen.isEmpty ? "Add delivery notes" : "Add \(count)"
        }
    }

    private func subtitle(_ note: MobileSessionCandidateNote) -> String {
        [note.customerName, note.numberItems.map { PickingSessionFormat.count($0, "item") }]
            .compactMap { $0 }
            .filter { !$0.isEmpty }
            .joined(separator: PickingSessionFormat.separator)
    }

    private func toggle(_ id: Int) {
        if chosen.contains(id) {
            chosen.remove(id)
        } else {
            chosen.insert(id)
        }
    }

    private func confirm() async {
        problem = nil
        let ids = store.candidates.map(\.id).filter(chosen.contains)
        switch mode {
        case .create:
            switch await store.createSession(deliveryNoteIDs: ids) {
            case .success(let session):
                toasts.show("Picking session \(session.reference) created")
                onDone(session)
            case .failure(let failure):
                problem = failure.message
            }
        case .add(let sessionStore):
            isAdding = true
            defer { isAdding = false }
            switch await sessionStore.act(.addDeliveryNotes(ids)) {
            case .success(let message):
                toasts.show(message ?? "Delivery notes added.")
                await store.loadCandidates()
                onDone(nil)
            case .failure(let failure):
                problem = failure.message
            }
        }
    }
}
