import SwiftUI

struct SystemConflictsView: View {
    @Environment(AppRouter.self) private var router
    private let store = SystemSyncStore.shared
    private let offline = OfflineWork.shared

    var body: some View {
        AppScreen(title: "Resolve conflicts", kicker: "Offline & sync") {
            if let loadError = store.loadError, !store.hasLoaded {
                InlineAlert(tone: .danger, title: "Couldn’t load the actions that need you", message: loadError) {
                    AikuButton("Try again", icon: .retry, kind: .tertiary, size: .regular, fullWidth: false) {
                        Task { await store.load() }
                    }
                }
            } else if !store.hasLoaded {
                Card {
                    SkeletonRow()
                    SkeletonRow()
                }
            } else if store.openOperations.isEmpty {
                EmptyState(icon: .checkDouble, title: "Nothing needs you", message: "Everything you did offline has been recorded.", actionTitle: "Open sync queue") {
                    router.open(SystemRoute.syncQueue)
                }
            } else {
                HelperText("These actions done offline could not be recorded exactly as you did them. Do what each one says, then mark it.")
                CardList(store.openOperations) { entry in
                    ListRow(
                        entry.labels.title.isEmpty ? entry.operationLabel : entry.labels.title,
                        subtitle: OfflineTexts.instruction(entry) ?? OfflineTexts.reason(entry.reason) ?? entry.labels.subtitle,
                        icon: entry.offlineOperation?.icon ?? .sync,
                        iconTone: .warn,
                        chips: [OfflineTexts.state(entry)].compactMap { $0 },
                        showsChevron: true
                    ) {
                        if let key = entry.key {
                            router.open(SystemRoute.conflict(reference: String(key)))
                        }
                    }
                }
            }
            let settled = store.recentOperations.filter { !$0.isOpen }
            if !settled.isEmpty {
                SectionHeading("Recent")
                CardList(settled) { entry in
                    ListRow(
                        entry.labels.title.isEmpty ? entry.operationLabel : entry.labels.title,
                        subtitle: entry.labels.subtitle,
                        icon: entry.offlineOperation?.icon ?? .sync,
                        iconTone: .neutral,
                        chips: [OfflineTexts.state(entry)].compactMap { $0 },
                        showsChevron: entry.canRollback
                    ) {
                        if let key = entry.key {
                            router.open(SystemRoute.conflict(reference: String(key)))
                        }
                    }
                }
            }
        }
        .screenRefreshable {
            await store.load()
        }
        .task(id: offline.needsYouCount) {
            await store.load()
        }
    }
}
