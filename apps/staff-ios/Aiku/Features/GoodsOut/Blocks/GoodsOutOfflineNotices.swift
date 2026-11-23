import SwiftUI

struct GoodsOutOfflineNotices: View {
    let noteID: Int?
    @Environment(AppRouter.self) private var router
    private let offline = OfflineWork.shared
    private let copies = GoodsOutOfflineCopies.shared

    var body: some View {
        if let copyDate = copies.offlineCopyDate(noteID), !offline.isOnline {
            InlineAlert(
                tone: .warn,
                icon: .offline,
                title: L10n.format("Offline · saved copy from %@", copyDate.formatted(date: .omitted, time: .shortened)),
                message: "Your work on this note is kept on this phone and sent when you are back online."
            )
        }
        if !queued.isEmpty {
            VStack(alignment: .leading, spacing: 8) {
                SectionHeading("Saved on this phone", actionTitle: "View") {
                    router.open(SystemRoute.syncQueue)
                }
                CardList(queued) { entry in
                    let needsYou = entry.state == .needsYou
                    let open: (() -> Void)? = needsYou ? { openConflict(entry) } : nil
                    ListRow(
                        GoodsOutOfflineText.summary(entry, sko: sko(of: entry)),
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
                        }
                    }
                }
            }
        }
    }

    private var queued: [QueueEntry] {
        _ = offline.queueRevision
        guard let noteID else { return [] }
        return offline.pending(for: .deliveryNote(noteID))
    }

    private func sko(of entry: QueueEntry) -> String? {
        guard let noteID, let lineID = entry.payloadInt(GoodsOutOfflinePlanner.lineKey) else { return nil }
        return copies.copy(noteID)?.dto.lines?.first { $0.id == lineID }?.orgStock?.code
    }

    private func openConflict(_ entry: QueueEntry) {
        if let operationID = entry.result?.operationID {
            router.open(SystemRoute.conflict(reference: String(operationID)))
        } else {
            router.open(SystemRoute.conflicts)
        }
    }
}

@MainActor
enum GoodsOutOfflineText {
    static func summary(_ entry: QueueEntry, sko: String?) -> String {
        let quantity = OfflineTexts.quantity(entry.quantity)
        let action = switch entry.operation {
        case .pick: L10n.format("Picked %@", quantity)
        case .notPicked: L10n.format("Not picked %@", quantity)
        case .setWaitingWarehouse: L10n.format("Waiting for the warehouse %@", quantity)
        case .setWaitingCRM: L10n.format("Passed to customer services %@", quantity)
        case .pack: L10n.format("Packed %@", quantity)
        default: entry.operation?.label ?? L10n.text("Offline work")
        }
        return sko.map { "\($0) · \(action)" } ?? action
    }
}
