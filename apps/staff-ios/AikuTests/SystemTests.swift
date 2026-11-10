import Foundation
import Testing
@testable import Aiku

@MainActor
struct SystemTests {
    @Test("Conflicts stay hidden until the queue has synced")
    func conflictsHiddenBeforeSync() {
        let store = SystemSyncStore()

        #expect(store.openConflicts.isEmpty)
        #expect(store.pendingCount == 4)
        #expect(store.canSignOut == false)
    }

    @Test("Sync now uploads unchanged actions and holds changed ones for review")
    func syncUploadsUnchangedActions() {
        let store = SystemSyncStore()

        let result = store.syncNow(at: "10:40")

        #expect(result == SystemSyncResult(uploaded: 1, conflicts: 2))
        #expect(store.isOnline)
        #expect(store.waitingActions.isEmpty)
        #expect(store.reviewActions.map(\.id) == ["q1", "q2", "q3"])
        #expect(store.openConflicts.count == 2)
        #expect(store.history.first?.title == "1 action uploaded")
        #expect(store.canSignOut == false)
    }

    @Test("Both adds the quantities up but never above what the line needs")
    func bothIsCappedAtRequiredQuantity() throws {
        let conflict = try #require(SystemSyncSampleData.conflicts.first)
        let picked = conflict.lines[0]
        let notPicked = conflict.lines[1]
        let audit = try #require(SystemSyncSampleData.conflicts.last?.lines.first)

        #expect(picked.bothQuantity == 6)
        #expect(picked.bothLabel == "6 (max)")
        #expect(picked.choices == [.mine, .aiku, .both])
        #expect(notPicked.allowsBoth == false)
        #expect(audit.allowsBoth == false)
    }

    @Test("A conflict is never applied until every line has a choice")
    func applyNeedsEveryLineChosen() {
        let store = SystemSyncStore()
        store.syncNow(at: "10:40")

        store.choose(.both, line: "SKO-00733", in: "AR24811")
        #expect(store.apply("AR24811", at: "10:41") == false)

        store.choose(.aiku, line: "SKO-00953", in: "AR24811")
        #expect(store.conflict(reference: "AR24811")?.choiceSummary == "1 both, 1 Aiku")
        #expect(store.apply("AR24811", at: "10:41"))
        #expect(store.conflict(reference: "AR24811")?.isResolved == true)
        #expect(store.reviewActions.map(\.id) == ["q3"])
        #expect(store.openConflicts.map(\.reference) == ["A-03-02"])
    }

    @Test("A choice the line does not offer is ignored")
    func unavailableChoiceIsIgnored() {
        let store = SystemSyncStore()
        store.syncNow(at: "10:40")

        store.choose(.both, line: "SKO-00953", in: "AR24811")

        #expect(store.conflict(reference: "AR24811")?.lines[1].choice == nil)
    }

    @Test("Use mine for all picks mine on every differing line")
    func chooseAllSetsEveryLine() {
        let store = SystemSyncStore()
        store.syncNow(at: "10:40")

        store.chooseAll(.mine, in: "AR24811")

        #expect(store.conflict(reference: "AR24811")?.lines.allSatisfy { $0.choice == .mine } == true)
        #expect(store.conflict(reference: "AR24811")?.canApply == true)
    }

    @Test("Keep all Aiku resolves every open conflict and unblocks logout")
    func resolveAllClearsQueue() {
        let store = SystemSyncStore()
        store.syncNow(at: "10:40")

        let resolved = store.resolveAll(keeping: .aiku, at: "10:45")

        #expect(resolved == 2)
        #expect(store.openConflicts.isEmpty)
        #expect(store.pendingCount == 0)
        #expect(store.uploadedCount == 4)
        #expect(store.canSignOut)
    }

    @Test("Discarding the queue keeps a record in the sync history")
    func discardKeepsHistory() {
        let store = SystemSyncStore()

        let discarded = store.discardQueue(at: "10:50")

        #expect(discarded == 4)
        #expect(store.pendingCount == 0)
        #expect(store.canSignOut)
        #expect(store.history.first?.chip == Chip("Discarded", .neutral))
        #expect(store.history.count == 2)
    }

    @Test("Unknown conflict references fall back to the first conflict")
    func unknownReferenceFallsBack() {
        let store = SystemSyncStore()

        #expect(store.conflict(reference: "NOPE")?.reference == "AR24811")
        #expect(store.position(of: "A-03-02") == 2)
    }

    @Test("The states gallery lists every designed state once")
    func galleryListsEveryState() {
        let codes = SystemStateCatalog.sections.flatMap(\.entries).map(\.code)

        #expect(Set(codes).count == codes.count)
        #expect(codes.count == 17)
        #expect(codes.contains("S09"))
        #expect(codes.contains("105"))
    }
}
