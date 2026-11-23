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
                    operationRow(entry, note: OfflineTexts.instruction(entry) ?? OfflineTexts.reason(entry.reason), iconTone: .warn, showsChevron: true)
                }
            }
            let settled = store.recentOperations.filter { !$0.isOpen }
            if !settled.isEmpty {
                SectionHeading("Recent")
                CardList(settled) { entry in
                    operationRow(entry, note: nil, iconTone: .neutral, showsChevron: entry.canRollback)
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

    private func operationRow(_ entry: OfflineOperationEntry, note: String?, iconTone: Tone, showsChevron: Bool) -> some View {
        let summary = ConflictDetails.summary(entry, lineWord: L10n.text("Line"))
        let itemText = summary.itemText
        return ListRow(
            summary.record ?? entry.operationLabel,
            subtitle: note ?? (itemText == nil ? entry.labels.subtitle : nil),
            detail: itemText.map { Text(verbatim: $0) },
            chips: [OfflineTexts.state(entry)].compactMap { $0 },
            showsChevron: showsChevron,
            action: { open(entry) }
        ) {
            IconTile(entry.offlineOperation?.icon ?? .sync, tone: iconTone)
        } trailing: {
            EmptyView()
        }
    }

    private func open(_ entry: OfflineOperationEntry) {
        if let key = entry.key {
            router.open(SystemRoute.conflict(reference: String(key)))
        }
    }
}
