import SwiftUI

struct SystemSyncQueueView: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    private let offline = OfflineWork.shared
    private let store = SystemSyncStore.shared

    var body: some View {
        AppScreen(title: "Offline & sync", kicker: "Profile") {
            banner
            if offline.needsYouCount > 0 {
                InlineAlert(
                    tone: .warn,
                    icon: .warning,
                    title: L10n.format("%lld actions need you", offline.needsYouCount),
                    message: "Aiku could not record these exactly as you did them. Open each one to see what to do."
                ) {
                    AikuButton("Resolve", icon: .next, kind: .caution, size: .regular, fullWidth: false) {
                        router.open(SystemRoute.conflicts)
                    }
                    .accessibilityIdentifier("system.openConflicts")
                }
            }
            if groups.isEmpty {
                EmptyState(icon: .checkDouble, title: "Nothing waiting", message: "Work you do offline shows here until it has synced.")
            } else {
                ForEach(groups) { group in
                    SectionHeading(group.title)
                    CardList(group.entries) { entry in
                        let needsYou = entry.state == .needsYou
                        let openEntry: (() -> Void)? = needsYou ? { open(entry) } : nil
                        ListRow(
                            row(entry),
                            subtitle: subtitle(entry),
                            chips: [OfflineTexts.queueState(entry)],
                            showsChevron: needsYou,
                            action: openEntry
                        ) {
                            IconTile(entry.operation?.icon ?? .sync, tone: needsYou ? .warn : .neutral)
                        } trailing: {
                            if entry.canUndo {
                                AikuButton("Undo", icon: .undo, kind: .tertiary, size: .small, fullWidth: false) {
                                    offline.undo(entry.localID)
                                    toasts.show("Undone", tone: .neutral)
                                }
                                .accessibilityIdentifier("system.undo.\(entry.localID)")
                            }
                        }
                    }
                }
            }
            HelperText("Line-level work (picks, packs, put-away, return puts, moves and counts) is saved on the phone while offline and checked by Aiku when it syncs. Status changes need a connection.")
        } footer: {
            if offline.isOnline && offline.waitingCount > 0 {
                AikuButton("Sync now", icon: .sync, isLoading: offline.isSyncing) {
                    offline.syncNow(.manual)
                }
                .accessibilityIdentifier("system.syncNow")
            }
        }
        .task {
            await store.load()
        }
    }

    private var groups: [SystemQueueGroup] {
        store.groups(of: offline.queue.filter { $0.state != .applied || isRecent($0) }, downloaded: offline.downloaded)
    }

    @ViewBuilder
    private var banner: some View {
        if !offline.isOnline {
            SystemStatusBanner(offlineText, tone: .warn)
        } else if offline.isSyncing {
            SystemStatusBanner(L10n.format("Sending %lld actions", offline.waitingCount), tone: .info)
        } else if offline.waitingCount > 0 {
            SystemStatusBanner(L10n.format("Online · %lld actions waiting", offline.waitingCount), tone: .info)
        } else {
            SystemStatusBanner("Online · everything is synced", tone: .success)
        }
    }

    private var offlineText: String {
        guard let since = offline.offlineSince else { return L10n.format("Offline · %lld waiting", offline.waitingCount) }
        return L10n.format("Offline since %@ · %lld waiting", since.formatted(date: .omitted, time: .shortened), offline.waitingCount)
    }

    private func row(_ entry: QueueEntry) -> String {
        let quantity = entry.payload?["quantity"] == nil ? nil : OfflineTexts.quantity(entry.quantity)
        let label = entry.operation?.label ?? L10n.text("Offline work")
        return quantity.map { "\(label) · \($0)" } ?? label
    }

    private func subtitle(_ entry: QueueEntry) -> String {
        let time = entry.performedAt.formatted(date: .abbreviated, time: .shortened)
        guard entry.state == .needsYou, let result = entry.result else { return time }
        return [OfflineTexts.reason(result), time].compactMap { $0 }.joined(separator: " · ")
    }

    private func isRecent(_ entry: QueueEntry) -> Bool {
        (entry.syncedAt ?? .distantPast) > Date().addingTimeInterval(-Self.showSyncedFor)
    }

    private func open(_ entry: QueueEntry) {
        if let operationID = entry.result?.operationID {
            router.open(SystemRoute.conflict(reference: String(operationID)))
        } else {
            router.open(SystemRoute.conflicts)
        }
    }

    private static let showSyncedFor: TimeInterval = 60 * 60
}
