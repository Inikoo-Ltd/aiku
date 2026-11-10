import Foundation
import Observation

struct SystemSyncResult: Equatable, Sendable {
    let uploaded: Int
    let conflicts: Int
}

@MainActor
@Observable
final class SystemSyncStore {
    static let shared = SystemSyncStore()

    private(set) var actions: [SystemQueuedAction]
    private(set) var conflicts: [SystemConflict]
    private(set) var history: [SystemSyncRecord]
    private(set) var isOnline: Bool
    private(set) var hasSynced = false
    let offlineSince: String

    init(
        actions: [SystemQueuedAction] = SystemSyncSampleData.actions,
        conflicts: [SystemConflict] = SystemSyncSampleData.conflicts,
        history: [SystemSyncRecord] = SystemSyncSampleData.history,
        isOnline: Bool = false,
        offlineSince: String = SystemSyncSampleData.offlineSince
    ) {
        self.actions = actions
        self.conflicts = conflicts
        self.history = history
        self.isOnline = isOnline
        self.offlineSince = offlineSince
    }

    var waitingActions: [SystemQueuedAction] { actions.filter { $0.status == .waiting } }

    var reviewActions: [SystemQueuedAction] { actions.filter { $0.status == .needsReview } }

    var uploadedCount: Int { actions.filter { $0.status == .uploaded }.count }

    var pendingCount: Int { waitingActions.count + reviewActions.count }

    var openConflicts: [SystemConflict] {
        guard hasSynced else { return [] }
        return conflicts.filter(\.isOpen)
    }

    var resolvedConflicts: [SystemConflict] {
        guard hasSynced else { return [] }
        return conflicts.filter { !$0.isOpen }
    }

    var canSignOut: Bool { pendingCount == 0 }

    func conflict(reference: String) -> SystemConflict? {
        conflicts.first { $0.reference == reference } ?? conflicts.first
    }

    func position(of reference: String) -> Int {
        (conflicts.firstIndex { $0.reference == reference } ?? 0) + 1
    }

    @discardableResult
    func syncNow(at time: String) -> SystemSyncResult {
        isOnline = true
        hasSynced = true
        let openReferences = Set(conflicts.filter(\.isOpen).map(\.reference))
        var uploaded = 0
        for index in actions.indices where actions[index].status == .waiting {
            if let reference = actions[index].conflictReference, openReferences.contains(reference) {
                actions[index].status = .needsReview
            } else {
                actions[index].status = .uploaded
                uploaded += 1
            }
        }
        if uploaded > 0 {
            history.insert(
                SystemSyncRecord(
                    title: uploaded == 1 ? "1 action uploaded" : "\(uploaded) actions uploaded",
                    detail: "Version check passed · \(time)",
                    chip: Chip("Synced", .success),
                    icon: .check
                ),
                at: 0
            )
        }
        let conflictCount = Set(actions.filter { $0.status == .needsReview }.compactMap(\.conflictReference)).count
        return SystemSyncResult(uploaded: uploaded, conflicts: conflictCount)
    }

    func choose(_ choice: SystemConflictChoice, line lineID: String, in reference: String) {
        guard let conflictIndex = conflicts.firstIndex(where: { $0.reference == reference }),
              conflicts[conflictIndex].isOpen,
              let lineIndex = conflicts[conflictIndex].lines.firstIndex(where: { $0.id == lineID }),
              conflicts[conflictIndex].lines[lineIndex].choices.contains(choice)
        else { return }
        conflicts[conflictIndex].lines[lineIndex].choice = choice
    }

    func chooseAll(_ choice: SystemConflictChoice, in reference: String) {
        guard let conflictIndex = conflicts.firstIndex(where: { $0.reference == reference }), conflicts[conflictIndex].isOpen else { return }
        for lineIndex in conflicts[conflictIndex].lines.indices where conflicts[conflictIndex].lines[lineIndex].choices.contains(choice) {
            conflicts[conflictIndex].lines[lineIndex].choice = choice
        }
    }

    @discardableResult
    func apply(_ reference: String, at time: String) -> Bool {
        guard let conflictIndex = conflicts.firstIndex(where: { $0.reference == reference }),
              conflicts[conflictIndex].isOpen,
              conflicts[conflictIndex].canApply
        else { return false }
        conflicts[conflictIndex].isResolved = true
        for index in actions.indices where actions[index].conflictReference == reference && actions[index].status == .needsReview {
            actions[index].status = .uploaded
        }
        let conflict = conflicts[conflictIndex]
        history.insert(
            SystemSyncRecord(
                title: "\(conflict.headerTitle) resolved",
                detail: "\(conflict.choiceSummary) · \(time)",
                chip: Chip("Synced", .success),
                icon: .check
            ),
            at: 0
        )
        return true
    }

    @discardableResult
    func resolveAll(keeping choice: SystemConflictChoice, at time: String) -> Int {
        let references = openConflicts.map(\.reference)
        var resolved = 0
        for reference in references {
            chooseAll(choice, in: reference)
            if apply(reference, at: time) {
                resolved += 1
            }
        }
        return resolved
    }

    @discardableResult
    func discardQueue(at time: String) -> Int {
        var discarded = 0
        for index in actions.indices where actions[index].status == .waiting || actions[index].status == .needsReview {
            actions[index].status = .discarded
            discarded += 1
        }
        for index in conflicts.indices where conflicts[index].isOpen {
            conflicts[index].isDiscarded = true
        }
        if discarded > 0 {
            history.insert(
                SystemSyncRecord(
                    title: discarded == 1 ? "1 action discarded" : "\(discarded) actions discarded",
                    detail: "Kept in sync history · \(time)",
                    chip: Chip("Discarded", .neutral),
                    icon: .trash
                ),
                at: 0
            )
        }
        return discarded
    }

    static func clockTime(_ date: Date = .now) -> String {
        let components = Calendar.current.dateComponents([.hour, .minute], from: date)
        return String(format: "%02d:%02d", components.hour ?? 0, components.minute ?? 0)
    }
}
