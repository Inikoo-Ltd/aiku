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

    private(set) var source: GoodsOutSource = .sample
    private(set) var scanMisses = 0
    private(set) var userID: Int?
    private(set) var warehouseID: Int?
    private(set) var listRows: [GoodsOutDeliveryNote] = []
    private(set) var listPage = 0
    private(set) var listLastPage = 0
    private(set) var listTotal: Int?
    private(set) var isLoadingList = false
    private(set) var listError: String?
    private(set) var canWorkInWarehouse = false
    private(set) var counts: GoodsOutCountsDTO?
    private(set) var countsError: String?
    private(set) var isLoadingCounts = false
    private(set) var noteErrors: [String: String] = [:]
    private(set) var loadingNoteIDs: Set<Int> = []
    private(set) var workingReferences: Set<String> = []
    private(set) var pendingLines: [String: [GoodsOutLine.ID: GoodsOutLine]] = [:]
    @ObservationIgnored var lineQueues: [String: Task<Void, Never>] = [:]
    private(set) var resourcesError: String?
    private(set) var isLoadingResources = false
    private(set) var pickersNow: [GoodsOutPersonActivity] = []
    private(set) var packersNow: [GoodsOutPersonActivity] = []
    private(set) var personnelError: String?
    private(set) var knownIDs: [String: Int] = [:]
    @ObservationIgnored var service: GoodsOutService
    @ObservationIgnored private var listGeneration = 0

    init(
        notes: [GoodsOutDeliveryNote] = GoodsOutSampleData.notes,
        currentUser: String = GoodsOutSampleData.currentUser,
        rights: GoodsOutRights = GoodsOutRights(),
        trolleys: [GoodsOutTrolley] = GoodsOutSampleData.trolleys,
        pickedBays: [GoodsOutPickedBay] = GoodsOutSampleData.pickedBays,
        pickers: [GoodsOutPickerProfile] = GoodsOutSampleData.pickers,
        service: GoodsOutService = .shared,
        now: @escaping () -> Date = Date.init
    ) {
        self.notes = notes
        self.currentUser = currentUser
        self.rights = rights
        self.trolleys = trolleys
        self.pickedBays = pickedBays
        self.pickers = pickers
        self.service = service
        self.now = now
    }

    var isRemote: Bool { source == .remote }

    func scanMissed() {
        scanMisses += 1
        ScanFeedback.shared.rejected()
    }

    func contains(_ reference: String) -> Bool {
        notes.contains { $0.reference == reference } || listRows.contains { $0.reference == reference }
    }

    func note(_ reference: String) -> GoodsOutDeliveryNote? {
        if isRemote {
            return notes.first { $0.reference == reference } ?? listRows.first { $0.reference == reference }
        }
        return notes.first { $0.reference == reference } ?? notes.first
    }

    func noteID(for reference: String) -> Int? {
        note(reference)?.apiID ?? knownIDs[reference]
    }

    func remember(id: Int, reference: String) {
        knownIDs[reference] = id
    }

    func isWorking(on reference: String) -> Bool {
        workingReferences.contains(reference) || !(pendingLines[reference]?.isEmpty ?? true)
    }

    func isSending(_ line: GoodsOutLine.ID, on reference: String) -> Bool {
        pendingLines[reference]?[line] != nil
    }

    func setPending(_ line: GoodsOutLine?, id: GoodsOutLine.ID, on reference: String) {
        pendingLines[reference, default: [:]][id] = line
        if pendingLines[reference]?.isEmpty == true {
            pendingLines[reference] = nil
        }
    }

    @discardableResult
    func update(_ reference: String, _ change: (inout GoodsOutDeliveryNote) -> Bool) -> GoodsOutDeliveryNote? {
        guard !isRemote, let index = notes.firstIndex(where: { $0.reference == reference }) else { return nil }
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
        if isRemote {
            return GoodsOutResources.remoteTrolleyChoices(trolleys, for: reference)
        }
        return GoodsOutResources.trolleyChoices(trolleys, notes: notes, for: reference)
    }

    func bayChoices(for reference: String) -> [GoodsOutChoice] {
        if isRemote {
            return GoodsOutResources.remoteBayChoices(pickedBays, for: reference)
        }
        return GoodsOutResources.bayChoices(pickedBays, notes: notes, for: reference)
    }

    func pickerChoices() -> [GoodsOutChoice] {
        if isRemote {
            return GoodsOutResources.remotePickerChoices(pickers, activity: pickersNow, currentUser: currentUser)
        }
        return GoodsOutResources.pickerChoices(pickers, notes: notes, currentUser: currentUser)
    }

    var backlogRows: [GoodsOutBacklogRow] {
        if isRemote {
            return counts.map(GoodsOutMapper.backlogRows) ?? []
        }
        return GoodsOutBacklog.rows(for: notes)
    }

    var listResults: [GoodsOutDeliveryNote] {
        isRemote ? listRows : listQuery.results(in: notes, currentUser: currentUser)
    }

    var listTabs: [GoodsOutDeliveryNoteState] {
        isRemote ? GoodsOutDeliveryNoteState.listTabs : listQuery.tabs(in: notes, currentUser: currentUser)
    }

    func listCount(_ state: GoodsOutDeliveryNoteState) -> Int {
        guard isRemote else { return listQuery.count(for: state, in: notes, currentUser: currentUser) }
        guard let counts, let bucket = state.apiBucket else { return 0 }
        if listQuery.filter.channels.count == 1, let channel = listQuery.filter.channels.first {
            return counts.channels.first { $0.channel == channel.apiKey }?.buckets[bucket] ?? 0
        }
        return counts.buckets[bucket] ?? 0
    }

    var hasCounts: Bool { !isRemote || counts != nil }

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

extension GoodsOutStore {
    func enterRemote(userID: Int, warehouseID: Int) {
        source = .remote
        self.userID = userID
        self.warehouseID = warehouseID
        clearRemoteState()
        notes = []
        trolleys = []
        pickedBays = []
        pickers = []
        selectedForSession = []
    }

    func leaveRemote() {
        source = .sample
        userID = nil
        warehouseID = nil
        clearRemoteState()
    }

    private func clearRemoteState() {
        listGeneration += 1
        listRows = []
        listPage = 0
        listLastPage = 0
        listTotal = nil
        isLoadingList = false
        listError = nil
        canWorkInWarehouse = false
        counts = nil
        countsError = nil
        isLoadingCounts = false
        noteErrors = [:]
        loadingNoteIDs = []
        workingReferences = []
        pendingLines = [:]
        lineQueues.values.forEach { $0.cancel() }
        lineQueues = [:]
        resourcesError = nil
        isLoadingResources = false
        pickersNow = []
        packersNow = []
        personnelError = nil
        knownIDs = [:]
    }

    func beginListLoad() -> Int {
        listGeneration += 1
        isLoadingList = true
        listError = nil
        return listGeneration
    }

    func isCurrentList(_ generation: Int) -> Bool {
        generation == listGeneration
    }

    func finishListLoad(_ generation: Int, error: String?) {
        guard generation == listGeneration else { return }
        isLoadingList = false
        listError = error
    }

    func applyListPage(_ page: GoodsOutDeliveryNotesPageDTO, replacing: Bool) {
        let date = now()
        let rows = page.data.deliveryNotes.map { GoodsOutMapper.note(from: $0, currentUserID: userID, currentUser: currentUser, now: date) }
        for row in rows {
            if let id = row.apiID {
                knownIDs[row.reference] = id
            }
        }
        if replacing {
            listRows = rows
        } else {
            let existing = Set(listRows.map(\.reference))
            listRows += rows.filter { !existing.contains($0.reference) }
        }
        listPage = page.pagination?.currentPage ?? (replacing ? 1 : listPage + 1)
        listLastPage = page.pagination?.lastPage ?? listPage
        listTotal = page.pagination?.total ?? listRows.count
        canWorkInWarehouse = page.data.canWorkInWarehouse ?? false
        selectedForSession = selectedForSession.filter { reference in listRows.contains { $0.reference == reference } }
        isLoadingList = false
        listError = nil
    }

    func setCountsLoading(_ loading: Bool) {
        isLoadingCounts = loading
    }

    func setCounts(_ loaded: GoodsOutCountsDTO?, error: String?) {
        counts = loaded
        countsError = error
        isLoadingCounts = false
    }

    func setNoteError(_ message: String?, for key: String) {
        noteErrors[key] = message
    }

    func loadNote(id: Int, errorKey: String) async -> GoodsOutDeliveryNote? {
        loadingNoteIDs.insert(id)
        defer { loadingNoteIDs.remove(id) }
        do {
            let dto = try await service.deliveryNote(id)
            noteErrors[errorKey] = nil
            return store(dto)
        } catch where error.isCancellation {
            return nil
        } catch {
            if error as? APIError == .offline, let copy = loadOfflineCopy(id: id) {
                noteErrors[errorKey] = nil
                return copy
            }
            noteErrors[errorKey] = GoodsOutMapper.message(for: error)
            return nil
        }
    }

    @discardableResult
    func store(_ dto: GoodsOutDeliveryNoteDTO) -> GoodsOutDeliveryNote {
        storeMapped(showing(dto))
    }

    @discardableResult
    func storeMapped(_ dto: GoodsOutDeliveryNoteDTO) -> GoodsOutDeliveryNote {
        let mapped = GoodsOutMapper.note(from: dto, currentUserID: userID, currentUser: currentUser, now: now())
        knownIDs[mapped.reference] = dto.id
        noteErrors[mapped.reference] = nil
        noteErrors[Self.errorKey(id: dto.id)] = nil
        if let index = notes.firstIndex(where: { $0.reference == mapped.reference }) {
            notes[index] = mapped
        } else {
            notes.append(mapped)
        }
        if let index = listRows.firstIndex(where: { $0.reference == mapped.reference }) {
            var row = listRows[index]
            row.state = mapped.state
            row.picker = mapped.picker
            row.pickerID = mapped.pickerID
            row.trolley = mapped.trolley
            row.pickedBay = mapped.pickedBay
            row.remote?.stateKey = dto.state
            row.remote?.stateLabel = dto.stateLabel ?? ""
            listRows[index] = row
        }
        return mapped
    }

    func setWorking(_ reference: String, _ working: Bool) {
        if working {
            workingReferences.insert(reference)
        } else {
            workingReferences.remove(reference)
        }
    }

    func loadResource<Value: Sendable>(
        _ fetch: @escaping @Sendable () async throws -> Value,
        apply: (GoodsOutStore, Value) -> Void
    ) async {
        isLoadingResources = true
        resourcesError = nil
        do {
            let value = try await fetch()
            apply(self, value)
            isLoadingResources = false
        } catch where error.isCancellation {
            isLoadingResources = false
        } catch {
            isLoadingResources = false
            resourcesError = GoodsOutMapper.message(for: error)
        }
    }

    func setPersonnel(pickers: [GoodsOutPersonActivity], packers: [GoodsOutPersonActivity], error: String?) {
        pickersNow = pickers
        packersNow = packers
        personnelError = error
    }
}
