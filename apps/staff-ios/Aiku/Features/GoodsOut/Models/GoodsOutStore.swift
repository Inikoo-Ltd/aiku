import Foundation
import Observation

@MainActor
@Observable
final class GoodsOutStore {
    static let shared = GoodsOutStore()

    var notes: [GoodsOutDeliveryNote]
    var currentUser: String
    var rights: GoodsOutRights
    var trolleys: [GoodsOutTrolley]
    var pickedBays: [GoodsOutPickedBay]
    var pickers: [GoodsOutPickerProfile]
    var listQuery = GoodsOutListQuery()
    var selectedForSession: Set<String> = []
    var tradeUnits: [String: GoodsOutTradeUnit] = GoodsOutSampleData.tradeUnits
    @ObservationIgnored var now: () -> Date

    init(
        notes: [GoodsOutDeliveryNote] = GoodsOutSampleData.notes,
        currentUser: String = GoodsOutSampleData.currentUser,
        rights: GoodsOutRights = GoodsOutRights(),
        trolleys: [GoodsOutTrolley] = GoodsOutSampleData.trolleys,
        pickedBays: [GoodsOutPickedBay] = GoodsOutSampleData.pickedBays,
        pickers: [GoodsOutPickerProfile] = GoodsOutSampleData.pickers,
        now: @escaping () -> Date = Date.init
    ) {
        self.notes = notes
        self.currentUser = currentUser
        self.rights = rights
        self.trolleys = trolleys
        self.pickedBays = pickedBays
        self.pickers = pickers
        self.now = now
    }

    func contains(_ reference: String) -> Bool {
        notes.contains { $0.reference == reference }
    }

    func note(_ reference: String) -> GoodsOutDeliveryNote? {
        notes.first { $0.reference == reference } ?? notes.first
    }

    @discardableResult
    func update(_ reference: String, _ change: (inout GoodsOutDeliveryNote) -> Bool) -> GoodsOutDeliveryNote? {
        guard let index = notes.firstIndex(where: { $0.reference == reference }) else { return nil }
        let previous = notes[index]
        var updated = previous
        guard change(&updated) else { return nil }
        notes[index] = updated
        return previous
    }

    func restore(_ snapshot: GoodsOutDeliveryNote) {
        guard let index = notes.firstIndex(where: { $0.reference == snapshot.reference }) else { return }
        notes[index] = snapshot
    }

    @discardableResult
    func startPicking(_ reference: String, trolley: String?) -> GoodsOutDeliveryNote? {
        let user = currentUser, date = now()
        return update(reference) { $0.startPicking(picker: user, trolley: trolley, at: date) }
    }

    @discardableResult
    func assignPicker(_ reference: String, picker: String, packerSameAsPicker: Bool = true) -> GoodsOutDeliveryNote? {
        let date = now()
        return update(reference) { $0.assignPicker(picker, packerSameAsPicker: packerSameAsPicker, at: date) }
    }

    @discardableResult
    func removePicker(_ reference: String) -> GoodsOutDeliveryNote? {
        update(reference) { $0.removePicker() }
    }

    @discardableResult
    func takeOver(_ reference: String) -> GoodsOutDeliveryNote? {
        let user = currentUser
        return update(reference) { $0.takeOver(by: user) }
    }

    @discardableResult
    func changeTrolley(_ reference: String, to code: String?) -> GoodsOutDeliveryNote? {
        update(reference) { $0.changeTrolley(code) }
    }

    @discardableResult
    func pickAll(_ reference: String, line: GoodsOutLine.ID) -> GoodsOutDeliveryNote? {
        update(reference) { $0.pickAll(line) }
    }

    @discardableResult
    func pick(_ reference: String, line: GoodsOutLine.ID, quantity: Int, from location: String? = nil) -> GoodsOutDeliveryNote? {
        update(reference) { $0.pick(line, quantity: quantity, from: location) }
    }

    @discardableResult
    func markNotPicked(_ reference: String, line: GoodsOutLine.ID, reason: GoodsOutNotPickedReason) -> GoodsOutDeliveryNote? {
        update(reference) { $0.markNotPicked(line, reason: reason) }
    }

    @discardableResult
    func setWaiting(_ reference: String, line: GoodsOutLine.ID, reason: GoodsOutWaitingReason, note: String) -> GoodsOutDeliveryNote? {
        update(reference) { $0.setWaiting(line, reason: reason, note: note) }
    }

    @discardableResult
    func resetLine(_ reference: String, line: GoodsOutLine.ID) -> GoodsOutDeliveryNote? {
        update(reference) { $0.resetLine(line) }
    }

    func scan(_ reference: String, code: String) -> GoodsOutScanOutcome {
        var outcome = GoodsOutScanOutcome.notPicking
        update(reference) { note in
            outcome = note.registerScan(code: code)
            if case .picked = outcome { return true }
            return false
        }
        return outcome
    }

    @discardableResult
    func setAsPicked(_ reference: String, bay: String?) -> GoodsOutDeliveryNote? {
        let date = now()
        return update(reference) { $0.setAsPicked(bay: bay, at: date) }
    }

    @discardableResult
    func changePickedBay(_ reference: String, to bay: String) -> GoodsOutDeliveryNote? {
        update(reference) { $0.changePickedBay(bay) }
    }

    @discardableResult
    func backToPicking(_ reference: String) -> GoodsOutDeliveryNote? {
        guard rights.isDispatchingSupervisor else { return nil }
        return update(reference) { $0.backToPicking() }
    }

    @discardableResult
    func autoFinishWaiting(_ reference: String) -> GoodsOutDeliveryNote? {
        let date = now()
        return update(reference) { $0.autoFinishWaiting(at: date) }
    }

    @discardableResult
    func startPacking(_ reference: String) -> GoodsOutDeliveryNote? {
        let date = now()
        return update(reference) { $0.startPacking(at: date) }
    }

    @discardableResult
    func undo(_ reference: String, step: GoodsOutUndoStep) -> GoodsOutDeliveryNote? {
        update(reference) { $0.undo(step) }
    }

    func setPickingLocation(_ reference: String, line: GoodsOutLine.ID, location: String) {
        guard rights.canEditStock else { return }
        for index in notes.indices where notes[index].state == .todo || notes[index].state == .picking {
            guard notes[index].channel == note(reference)?.channel,
                  let lineIndex = notes[index].lines.firstIndex(where: { $0.id == line }),
                  notes[index].lines[lineIndex].picked == 0 || notes[index].reference == reference
            else { continue }
            notes[index].lines[lineIndex].location = location
        }
    }

    func trolleyChoices(for reference: String) -> [GoodsOutChoice] {
        GoodsOutResources.trolleyChoices(trolleys, notes: notes, for: reference)
    }

    func bayChoices(for reference: String) -> [GoodsOutChoice] {
        GoodsOutResources.bayChoices(pickedBays, notes: notes, for: reference)
    }

    func pickerChoices() -> [GoodsOutChoice] {
        GoodsOutResources.pickerChoices(pickers, notes: notes, currentUser: currentUser)
    }

    var backlogRows: [GoodsOutBacklogRow] { GoodsOutBacklog.rows(for: notes) }

    var listResults: [GoodsOutDeliveryNote] { listQuery.results(in: notes, currentUser: currentUser) }

    var listTabs: [GoodsOutDeliveryNoteState] { listQuery.tabs(in: notes, currentUser: currentUser) }

    func listCount(_ state: GoodsOutDeliveryNoteState) -> Int {
        listQuery.count(for: state, in: notes, currentUser: currentUser)
    }

    func showChannel(_ channel: GoodsOutChannel) {
        var filter = listQuery.filter
        filter.channels = [channel]
        listQuery.apply(filter)
    }

    func toggleSessionSelection(_ reference: String) {
        if selectedForSession.contains(reference) {
            selectedForSession.remove(reference)
        } else {
            selectedForSession.insert(reference)
        }
    }
}
