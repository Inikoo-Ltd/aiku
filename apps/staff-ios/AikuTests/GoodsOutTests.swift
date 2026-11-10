import Foundation
import Testing
@testable import Aiku

struct GoodsOutTests {
    private let now = GoodsOutDateFormat.date(day: 10, hour: 10, minute: 0)

    private func sample(_ reference: String) -> GoodsOutDeliveryNote {
        GoodsOutSampleData.notes.first { $0.reference == reference }!
    }

    @Test func everyStateHasItsWebTitle() {
        let titles = GoodsOutDeliveryNoteState.allCases.map(\.title)
        #expect(titles == ["To do", "Picking", "Picked", "Packing", "Packed", "Finalised", "Dispatched", "Waiting", "Cancelled"])
    }

    @Test func sampleDataHasANoteInEveryState() {
        let states = Set(GoodsOutSampleData.notes.map(\.state))
        #expect(states == Set(GoodsOutDeliveryNoteState.allCases))
    }

    @Test func lineIsHandledWhenPickedNotPickedOrWaiting() {
        var line = GoodsOutLine(sko: "SKO-00733", name: "Bath bomb", location: "A-03-02", required: 6, picked: 3)
        #expect(line.isHandled == false)
        #expect(line.status == .partlyPicked)

        line.pick(10)
        #expect(line.picked == 6)
        #expect(line.isHandled)
        #expect(line.status == .picked)

        var notPicked = GoodsOutLine(sko: "SKO-00953", name: "Incense", location: "B-01-04", required: 2)
        notPicked.markNotPicked(.outOfStock)
        #expect(notPicked.isHandled)
        #expect(notPicked.status.chip == Chip("Not picked · out of stock", .danger))

        var waiting = GoodsOutLine(sko: "SKO-01187", name: "Candle", location: "A-07-01", required: 2)
        waiting.setWaiting(.warehouse, note: "  Location empty  ")
        #expect(waiting.isHandled)
        #expect(waiting.waitingNote == "Location empty")
        #expect(waiting.status.chip == Chip("Waiting · warehouse", .warn))
    }

    @MainActor
    @Test func pickingFromAnotherLocationRemembersIt() {
        var line = GoodsOutLine(sko: "SKO-01187", name: "Candle", location: "A-07-01", required: 2)
        line.pick(2, from: "C-04-02")
        #expect(line.pickLocation == "C-04-02")
        #expect(line.detailText().hasSuffix("from another location"))
    }

    @Test func setAsPickedOnlyAppearsWhenEveryLineIsHandled() {
        var note = sample("AR24811")
        #expect(note.linesStillToHandle == 1)
        #expect(note.allLinesHandled == false)
        let setAsPickedEarly = note.setAsPicked(bay: "Bay 1", at: now)
        #expect(setAsPickedEarly == false)

        note.pickAll("SKO-00733")
        #expect(note.allLinesHandled)
        let setAsPicked = note.setAsPicked(bay: "Bay 1", at: now)
        #expect(setAsPicked)
        #expect(note.state == .waiting)
        #expect(note.pickedBay == "Bay 1")
    }

    @Test func setAsPickedWithoutWaitingLinesMovesToPicked() {
        var note = sample("AR24811")
        note.pickAll("SKO-00733")
        note.markNotPicked("SKO-01187", reason: .cancelledByCustomer)
        #expect(note.hasWaitingLines == false)
        note.setAsPicked(bay: "Bay 4", at: now)
        #expect(note.state == .picked)
        #expect(note.stateDates[.picked] == now)
    }

    @Test func dropshippingSkipsPacking() {
        var note = sample("AR24816")
        note.startPicking(picker: "Alex Morgan", trolley: nil, at: now)
        for line in note.lines {
            note.pickAll(line.id)
        }
        #expect(note.finishPickingTitle == "Set as packed")
        note.setAsPicked(bay: nil, at: now)
        #expect(note.state == .packed)
    }

    @Test func startPickingAssignsPickerAndTrolley() {
        var note = sample("AR24816")
        let started = note.startPicking(picker: "Alex Morgan", trolley: "T-03", at: now)
        #expect(started)
        #expect(note.state == .picking)
        #expect(note.picker == "Alex Morgan")
        #expect(note.trolley == "T-03")
        let startedTwice = note.startPicking(picker: "Jo K.", trolley: nil, at: now)
        #expect(startedTwice == false)

        note.removePicker()
        #expect(note.state == .todo)
        #expect(note.picker == nil)
        #expect(note.trolley == nil)
    }

    @Test func lineActionsOnlyWorkWhilePicking() {
        var note = sample("AR24816")
        let pickedWhileTodo = note.pickAll("SKO-00412")
        #expect(pickedWhileTodo == false)
        #expect(note.lines.allSatisfy { $0.picked == 0 })
    }

    @Test func takeOverMakesYouThePicker() {
        var note = sample("AR24817")
        #expect(note.needsTakeOver(by: "Alex Morgan"))
        note.takeOver(by: "Alex Morgan")
        #expect(note.needsTakeOver(by: "Alex Morgan") == false)
    }

    @Test func backToPickingClearsWaitingLines() {
        var note = sample("AR24815")
        #expect(note.waitingLines.count == 2)
        let finishedWaiting = note.autoFinishWaiting(at: now)
        #expect(finishedWaiting == false)
        let sentBack = note.backToPicking()
        #expect(sentBack)
        #expect(note.state == .picking)
        #expect(note.waitingLines.isEmpty)
        #expect(note.linesStillToHandle == 2)
        #expect(note.line("SKO-01187")?.picked == 2)
    }

    @Test func pickedNoteStartsPackingAndUndoGoesBack() {
        var note = sample("AR24813")
        #expect(note.undoStep == .setAsPicked)
        let packingStarted = note.startPacking(at: now)
        #expect(packingStarted)
        #expect(note.state == .packing)
        #expect(note.undoStep == .packing)
        let undidPacking = note.undo(.packing)
        #expect(undidPacking)
        #expect(note.state == .picked)
        let undidSetAsPicked = note.undo(.setAsPicked)
        #expect(undidSetAsPicked)
        #expect(note.state == .picking)
        let unpacked = note.undo(.unpack)
        #expect(unpacked == false)
    }

    @Test func scanCountsOneAndRejectsOtherItems() {
        var note = sample("AR24811")
        let firstScan = note.registerScan(code: "sko-00733")
        #expect(firstScan == .picked(sko: "SKO-00733", left: 2))
        let completeScan = note.registerScan(code: "SKO-00412")
        #expect(completeScan == .lineComplete(sko: "SKO-00412"))
        let wrongScan = note.registerScan(code: "SKO-01402")
        #expect(wrongScan == .notOnNote(code: "SKO-01402"))
        #expect(GoodsOutScanLogEntry.wrongItemCode(for: note) == "SKO-00734")
    }

    @Test func stepperShowsWaitingInAmber() {
        let steps = GoodsOutStepper.steps(for: .waiting)
        #expect(steps.map(\.title) == ["To do", "Picking", "Waiting", "Packing", "Packed", "Finalised", "Dispatched"])
        #expect(steps[2].status == .waiting)

        let picking = GoodsOutStepper.steps(for: .picking)
        #expect(picking[0].status == .done)
        #expect(picking[1].status == .current)
        #expect(picking[2].status == .upcoming)
    }

    @Test func tabCountsIgnoreTheSelectedTab() {
        let notes = GoodsOutSampleData.notes
        var query = GoodsOutListQuery()
        #expect(query.count(for: .todo, in: notes, currentUser: "Alex Morgan") == 2)
        #expect(query.count(for: .waiting, in: notes, currentUser: "Alex Morgan") == 2)
        #expect(query.results(in: notes, currentUser: "Alex Morgan").map(\.reference) == ["AR24816", "AR24819"])
        #expect(query.tabs(in: notes, currentUser: "Alex Morgan").last == .cancelled)

        query.select(tab: .picking)
        #expect(Set(query.results(in: notes, currentUser: "Alex Morgan").map(\.reference)) == ["AR24811", "AR24817"])
    }

    @Test func filtersCombineChannelPickerAndSearch() {
        let notes = GoodsOutSampleData.notes
        var filter = GoodsOutDeliveryNoteFilter()
        filter.pickers = [.me]
        filter.states = [.picking, .picked]
        var query = GoodsOutListQuery()
        query.apply(filter)
        #expect(query.tab == nil)
        #expect(Set(query.results(in: notes, currentUser: "Alex Morgan").map(\.reference)) == ["AR24811", "AR24813"])
        #expect(filter.summary == "3 filters · sorted by date (oldest first)")

        query.search = "24813"
        #expect(query.results(in: notes, currentUser: "Alex Morgan").map(\.reference) == ["AR24813"])

        var channel = GoodsOutListQuery()
        channel.apply(GoodsOutDeliveryNoteFilter(channels: [.dropshipping]))
        #expect(channel.results(in: notes, currentUser: "Alex Morgan").map(\.reference) == ["AR24816"])
        #expect(channel.count(for: .picking, in: notes, currentUser: "Alex Morgan") == 0)
    }

    @Test func sortingByWeightPutsHeaviestFirst() {
        let sorted = GoodsOutSort.weight.sorted(GoodsOutSampleData.notes)
        #expect(sorted.first?.reference == "AR24815")
    }

    @Test func backlogCountsByChannelAndState() {
        let rows = GoodsOutBacklog.rows(for: GoodsOutSampleData.notes)
        let wholesale = rows.first { $0.channel == .wholesale }
        #expect(wholesale?.count(.todo) == 1)
        #expect(wholesale?.count(.picking) == 2)
        #expect(wholesale?.count(.waiting) == 2)
        #expect(wholesale?.count(.picked) == 1)
        #expect(wholesale?.total == 8)
        #expect(rows.map(\.channel) == [.wholesale, .dropshipping, .b2c, .marketplaces])
    }

    @Test func trolleysAndBaysShowWhoUsesThem() {
        let trolleys = GoodsOutResources.trolleyChoices(GoodsOutSampleData.trolleys, notes: GoodsOutSampleData.notes, for: "AR24816")
        #expect(trolleys.first { $0.id == "T-03" }?.subtitle == "Delivery note AR24811")
        #expect(trolleys.first { $0.id == "T-05" }?.isAvailable == true)
        #expect(trolleys.first { $0.id == "T-02" }?.isAvailable == false)

        let bays = GoodsOutResources.bayChoices(GoodsOutSampleData.pickedBays, notes: GoodsOutSampleData.notes, for: "AR24811")
        #expect(bays.map(\.subtitle) == ["1 delivery note", "2 delivery notes", "Free"])
    }

    @MainActor
    @Test func storeUndoRestoresTheSnapshot() {
        let store = GoodsOutStore(now: { GoodsOutDateFormat.date(day: 10, hour: 10, minute: 0) })
        let previous = store.pickAll("AR24811", line: "SKO-00733")
        #expect(store.note("AR24811")?.allLinesHandled == true)
        store.restore(previous!)
        #expect(store.note("AR24811")?.line("SKO-00733")?.picked == 3)
        #expect(store.note("UNKNOWN")?.reference == GoodsOutSampleData.notes.first?.reference)
    }

    @MainActor
    @Test func onlySupervisorsSendWaitingNotesBackToPicking() {
        let store = GoodsOutStore(rights: GoodsOutRights(isDispatchingSupervisor: false))
        #expect(store.backToPicking("AR24815") == nil)
        #expect(store.note("AR24815")?.state == .waiting)
    }
}
