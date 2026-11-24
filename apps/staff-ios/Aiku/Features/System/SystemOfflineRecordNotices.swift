import SwiftUI

struct OfflineRecordNotices: View {
    let queue: OfflineRecordQueue?
    let operations: Set<OfflineOperation>
    let copyAt: Date?
    let summary: (QueueEntry) -> String
    @Environment(AppRouter.self) private var router
    private let offline = OfflineWork.shared

    var body: some View {
        if let copyAt {
            InlineAlert(
                tone: .warn,
                icon: .offline,
                title: L10n.format("Offline · saved copy from %@", copyAt.formatted(Date.FormatStyle(date: .abbreviated, time: .shortened).locale(L10n.formatLocale))),
                message: OfflineRecordText.keptOnPhone
            )
        }
        if !entries.isEmpty {
            VStack(alignment: .leading, spacing: 8) {
                SectionHeading("Saved on this phone", actionTitle: "View") {
                    router.open(SystemRoute.syncQueue)
                }
                CardList(entries) { entry in
                    let needsYou = entry.state == .needsYou
                    let open: (() -> Void)? = needsYou ? { openConflict(entry) } : nil
                    ListRow(
                        summary(entry),
                        subtitle: needsYou ? entry.result.flatMap { OfflineTexts.reason($0) } : nil,
                        chips: [needsYou ? Chip("Needs you", .warn) : Chip("Waiting to sync", .info)],
                        showsChevron: needsYou,
                        action: open
                    ) {
                        IconTile(entry.operation?.icon ?? .sync, tone: needsYou ? .warn : .neutral)
                    } trailing: {
                        if entry.canUndo {
                            AikuButton("Undo", icon: .undo, kind: .tertiary, size: .small, fullWidth: false) {
                                offline.undo(entry.localID)
                            }
                            .accessibilityIdentifier("offline.undo.\(entry.localID)")
                        }
                    }
                }
            }
        }
    }

    private var entries: [QueueEntry] {
        (queue?.pending ?? []).filter { entry in entry.operation.map { operations.contains($0) } == true }
    }

    private func openConflict(_ entry: QueueEntry) {
        if let operationID = entry.result?.operationID {
            router.open(SystemRoute.conflict(reference: String(operationID)))
        } else {
            router.open(SystemRoute.conflicts)
        }
    }
}
