import SwiftUI

struct GoodsOutRemoteLoading: ViewModifier {
    let onReady: @MainActor () async -> Void
    @State private var warehouseStore = WarehouseStore.shared
    @Environment(AuthStore.self) private var authStore
    @Environment(Session.self) private var session
    @Environment(\.apiClient) private var apiClient

    func body(content: Content) -> some View {
        content.task(id: GoodsOutLoadKey(userID: authStore.userID ?? 0, warehouseID: warehouseStore.selectedID)) {
            let store = GoodsOutStore.shared
            guard let userID = authStore.userID, userID > 0 else {
                store.resetToSample()
                return
            }
            await warehouseStore.load(for: userID, apiClient: apiClient)
            if let warehouse = warehouseStore.selected {
                session.apply(warehouse: warehouse.name, code: warehouse.code, organisation: warehouse.organisation.name)
            }
            store.configure(userID: userID, warehouse: warehouseStore.selected, currentUser: session.profile.name)
            await onReady()
        }
    }
}

struct GoodsOutLoadKey: Hashable {
    let userID: Int
    let warehouseID: Int?
}

extension View {
    func loadsGoodsOut(_ onReady: @escaping @MainActor () async -> Void = {}) -> some View {
        modifier(GoodsOutRemoteLoading(onReady: onReady))
    }
}

struct GoodsOutLoadProblem: View {
    let title: String
    let message: String
    let retry: () -> Void

    var body: some View {
        InlineAlert(tone: .danger, title: title, message: message) {
            LinkButton("Try again", size: 13, action: retry)
                .frame(minHeight: 44, alignment: .leading)
        }
    }
}

struct GoodsOutSkeletonCard: View {
    var rows = 4

    var body: some View {
        Card {
            ForEach(0..<rows, id: \.self) { index in
                if index > 0 {
                    RowDivider()
                }
                SkeletonRow()
            }
        }
        .accessibilityLabel("Loading")
    }
}

@MainActor
enum GoodsOutRemoteFeedback {
    @discardableResult
    static func show(_ outcome: GoodsOutActionOutcome, fallback: String, toasts: ToastCenter) -> Bool {
        switch outcome {
        case .done(let message):
            toasts.show(message ?? fallback, sticky: false)
            return true
        case .nothingChanged(let message):
            toasts.show(message ?? "Nothing changed", tone: .warn, sticky: false)
            return true
        case .failed(let message):
            if !message.isEmpty {
                toasts.show(message, tone: .danger)
            }
            return false
        }
    }
}

@MainActor
enum GoodsOutRun {
    static func action(
        _ action: GoodsOutNoteAction,
        on reference: String,
        fallback: String,
        toasts: ToastCenter,
        onDone: @escaping @MainActor () -> Void = {}
    ) {
        run([action], on: reference, fallback: fallback, toasts: toasts, onDone: onDone)
    }

    static func run(
        _ actions: [GoodsOutNoteAction],
        on reference: String,
        fallback: String,
        toasts: ToastCenter,
        onDone: @escaping @MainActor () -> Void = {}
    ) {
        Task {
            let outcome = await GoodsOutStore.shared.perform(actions, on: reference)
            if GoodsOutRemoteFeedback.show(outcome, fallback: fallback, toasts: toasts) {
                onDone()
            }
        }
    }

    static func openChat(_ note: GoodsOutDeliveryNote, router: AppRouter) {
        if let chat = note.chatRoute {
            router.open(ShellRoute.staffChat(kind: chat.kind, contextID: chat.id, title: note.reference))
        } else {
            router.open(GoodsOutRoute.askCRM(reference: note.reference))
        }
    }
}
