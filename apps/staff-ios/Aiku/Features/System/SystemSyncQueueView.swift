import SwiftUI

struct SystemSyncQueueView: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var store = SystemSyncStore.shared
    @State private var isConfirmingDiscard = false

    var body: some View {
        AppScreen(title: "Offline & sync", kicker: "Profile") {
            banner
            if !store.reviewActions.isEmpty {
                SectionHeading("Needs you", detail: "version changed")
                CardList(store.reviewActions) { action in
                    ListRow(
                        action.title,
                        subtitle: action.detail,
                        icon: action.icon,
                        iconTone: .warn,
                        chips: [Chip("Conflict", .warn)],
                        showsChevron: true
                    ) {
                        router.open(SystemRoute.conflict(reference: action.conflictReference ?? ""))
                    }
                }
            }
            if !store.waitingActions.isEmpty {
                SectionHeading("Waiting to upload", detail: "oldest first")
                CardList(store.waitingActions) { action in
                    ListRow(action.title, subtitle: action.detail, icon: action.icon, iconTone: .neutral, chips: [Chip("Waiting")])
                }
            }
            if store.pendingCount == 0 {
                EmptyState(icon: .checkDouble, title: "Nothing waiting", message: "Everything on this phone is in Aiku.")
            }
            SectionHeading("Last sync")
            CardList(store.history) { record in
                ListRow(record.title, subtitle: record.detail, icon: record.icon, iconTone: record.chip.tone, chips: [record.chip])
            }
            HelperText("Line-level work is saved on the phone while offline: pick, not picked, pack, check, place, audit, move. On reconnect each action is checked against the version it started from: if nothing changed in Aiku it uploads by itself, otherwise it waits for you in Resolve conflicts. Clocking and state changes need a connection.")
            if store.pendingCount > 0 {
                AikuButton("Discard waiting actions", icon: .trash, kind: .danger, size: .regular) {
                    isConfirmingDiscard = true
                }
                .accessibilityIdentifier("system.discardQueue")
                HelperText("You can only log out once the queue is synced or discarded.")
            }
        } footer: {
            footer
        }
        .aikuConfirm(
            "Discard waiting actions?",
            isPresented: $isConfirmingDiscard,
            message: "\(store.pendingCount) actions saved on this phone will not be uploaded to Aiku. They stay in the sync history.",
            confirmTitle: "Discard",
            isDestructive: true
        ) {
            let count = store.discardQueue(at: SystemSyncStore.clockTime())
            toasts.show(count == 1 ? "1 action discarded" : "\(count) actions discarded", tone: .neutral)
        }
    }

    @ViewBuilder
    private var banner: some View {
        if !store.isOnline {
            SystemStatusBanner("Offline since \(store.offlineSince) · \(actionCount(store.pendingCount)) waiting", tone: .warn)
        } else if !store.openConflicts.isEmpty {
            SystemStatusBanner("Back online · \(conflictCount(store.openConflicts.count)) to resolve", tone: .info)
        } else {
            SystemStatusBanner("Online · everything is synced", tone: .success)
        }
    }

    @ViewBuilder
    private var footer: some View {
        if !store.isOnline && store.pendingCount > 0 {
            AikuButton("Sync now", icon: .sync) {
                let result = store.syncNow(at: SystemSyncStore.clockTime())
                let uploaded = result.uploaded == 1 ? "1 action uploaded" : "\(result.uploaded) actions uploaded"
                if result.conflicts > 0 {
                    toasts.show("\(uploaded) · \(conflictCount(result.conflicts)) to resolve", tone: .warn)
                } else {
                    toasts.show(uploaded)
                }
            }
            .accessibilityIdentifier("system.syncNow")
        } else if !store.openConflicts.isEmpty {
            AikuButton("Back online · \(conflictCount(store.openConflicts.count)) to resolve") {
                router.open(SystemRoute.conflicts)
            }
            .accessibilityIdentifier("system.openConflicts")
        }
    }

    private func actionCount(_ count: Int) -> String {
        count == 1 ? "1 action" : "\(count) actions"
    }

    private func conflictCount(_ count: Int) -> String {
        count == 1 ? "1 conflict" : "\(count) conflicts"
    }
}
