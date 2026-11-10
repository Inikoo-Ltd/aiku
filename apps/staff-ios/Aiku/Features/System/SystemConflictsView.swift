import SwiftUI

struct SystemConflictsView: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var store = SystemSyncStore.shared

    var body: some View {
        AppScreen(title: "Resolve conflicts", kicker: kicker) {
            if store.hasSynced {
                SystemStatusBanner(
                    "\(store.uploadedCount) uploaded automatically · \(store.openConflicts.count) need you",
                    tone: .info,
                    actionTitle: "View"
                ) {
                    router.back()
                }
            }
            if store.openConflicts.isEmpty {
                EmptyState(
                    icon: .checkDouble,
                    title: store.hasSynced ? "All conflicts resolved" : "Nothing to resolve yet",
                    message: store.hasSynced ? "Every offline action is in Aiku." : "Conflicts show up here after the queue syncs.",
                    actionTitle: "Open sync queue"
                ) {
                    router.back()
                }
            } else {
                CardList(store.openConflicts) { conflict in
                    ListRow(
                        conflict.title,
                        subtitle: conflict.summary,
                        icon: conflict.icon,
                        iconTone: .warn,
                        chips: [Chip(conflict.differenceLabel, .warn)],
                        showsChevron: true
                    ) {
                        router.open(SystemRoute.conflict(reference: conflict.reference))
                    }
                }
                SectionHeading("Resolve everything at once")
                HStack(spacing: 8) {
                    AikuButton("Keep all mine", kind: .tertiary) { resolveAll(.mine) }
                        .accessibilityIdentifier("system.keepAllMine")
                    AikuButton("Keep all Aiku", kind: .tertiary) { resolveAll(.aiku) }
                        .accessibilityIdentifier("system.keepAllAiku")
                }
            }
            if !store.resolvedConflicts.isEmpty {
                SectionHeading("Done")
                CardList(store.resolvedConflicts) { conflict in
                    ListRow(
                        conflict.title,
                        subtitle: conflict.isDiscarded ? "Discarded · kept in sync history" : conflict.choiceSummary,
                        icon: conflict.icon,
                        iconTone: conflict.isDiscarded ? .neutral : .success,
                        chips: [conflict.isDiscarded ? Chip("Discarded") : Chip("Resolved", .success)]
                    )
                }
            }
            HelperText("Like resolving a merge: open a record to choose line by line, or keep all of one side. Nothing you discard is lost: it stays in the sync history.")
        }
    }

    private var kicker: String {
        store.hasSynced ? "Back online · \(store.uploadedCount) of \(store.actions.count) uploaded" : "Offline & sync"
    }

    private func resolveAll(_ choice: SystemConflictChoice) {
        let count = store.resolveAll(keeping: choice, at: SystemSyncStore.clockTime())
        let side = choice == .mine ? "yours" : "Aiku’s"
        toasts.show(count == 1 ? "1 conflict resolved · kept \(side)" : "\(count) conflicts resolved · kept \(side)")
    }
}
