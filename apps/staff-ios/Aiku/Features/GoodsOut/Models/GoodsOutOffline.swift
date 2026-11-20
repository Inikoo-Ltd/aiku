import Foundation
import Observation

struct GoodsOutLineWork: Equatable, Sendable {
    enum Kind: Equatable, Sendable {
        case pick(locationOrgStockID: Int, quantity: Double)
        case pickAll(locationOrgStockID: Int?)
        case notPicked(quantity: Double?, reason: String, note: String?)
        case waiting(kind: GoodsOutWaitingKind, quantity: Double, note: String?)
        case pack(quantity: Double?)
    }

    let lineID: Int
    let kind: Kind
    let clientRequestID: String

    var isPacking: Bool {
        if case .pack = kind { return true }
        return false
    }
}

extension GoodsOutLineWork {
    init?(_ action: GoodsOutNoteAction) {
        let parts = action.path.split(separator: "/").map(String.init)
        guard action.method == .post, parts.count == 3, parts[0] == "lines", let lineID = Int(parts[1]) else { return nil }
        let body = action.body ?? [:]
        let quantity = body["quantity"]?.numberValue
        switch parts[2] {
        case "pick":
            guard let location = body["location_org_stock_id"]?.intValue, let quantity else { return nil }
            kind = .pick(locationOrgStockID: location, quantity: quantity)
        case "pick-all":
            kind = .pickAll(locationOrgStockID: body["location_org_stock_id"]?.intValue)
        case "not-picked":
            guard let reason = body["reason"]?.textValue else { return nil }
            kind = .notPicked(quantity: quantity, reason: reason, note: body["note"]?.textValue)
        case "waiting":
            guard let raw = body["kind"]?.textValue, let waitingKind = GoodsOutWaitingKind(rawValue: raw), let quantity else { return nil }
            kind = .waiting(kind: waitingKind, quantity: quantity, note: body["note"]?.textValue)
        case "pack":
            kind = .pack(quantity: quantity)
        default:
            return nil
        }
        self.lineID = lineID
        self.clientRequestID = body[GoodsOutNoteAction.clientRequestKey]?.textValue ?? GoodsOutNoteAction.newClientRequestID()
    }
}

extension GoodsOutJSONValue {
    var numberValue: Double? {
        switch self {
        case .int(let value): Double(value)
        case .double(let value): value
        case .string(let value): Double(value)
        default: nil
        }
    }

    var intValue: Int? { numberValue.map { Int($0) } }

    var textValue: String? {
        if case .string(let value) = self, !value.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty { return value }
        return nil
    }
}

enum GoodsOutOfflinePlan: Equatable {
    case queue(OfflineOperation, OfflinePayload, replaces: Int64?)
    case undo(Int64)
    case nothingToDo
    case onlineOnly
}

enum GoodsOutOfflinePlanner {
    static let workingStates: Set<String> = ["handling", "handling_blocked"]

    static func plan(note: GoodsOutDeliveryNoteDTO, work: GoodsOutLineWork, queued: [QueueEntry]) -> GoodsOutOfflinePlan {
        guard let line = note.lines?.first(where: { $0.id == work.lineID }), line.inPickingSession != true else { return .onlineOnly }
        let allowsActions = note.allowActions ?? false
        let canWork = work.isPacking ? note.state == "packing" && allowsActions : workingStates.contains(note.state) && allowsActions
        guard canWork else { return .onlineOnly }
        let settings = note.effectiveSettings

        switch work.kind {
        case .pick(let locationOrgStockID, let quantity):
            return pickAtLocation(note: note, line: line, locationOrgStockID: locationOrgStockID, quantity: quantity, work: work, queued: queued)
        case .pickAll(let requestedLocation):
            guard let location = pickLocation(line: line, requested: requestedLocation) else { return .onlineOnly }
            guard let quantity = positive(line.quantityToPick) else { return .nothingToDo }
            return .queue(.pick, payload(note, line, work, ["location_org_stock_id": .int(location), "quantity": .number(quantity)]), replaces: nil)
        case .notPicked(let requested, let reason, let text):
            guard settings?.allowPickerSetNotPicked ?? true else { return .nothingToDo }
            guard let quantity = positive(requested ?? line.quantityToPick) else { return .nothingToDo }
            return .queue(.notPicked, payload(note, line, work, ["quantity": .number(quantity), "reason": .string(reason), "note": .optional(text)]), replaces: nil)
        case .waiting(let kind, let requested, let text):
            guard settings?.allowWaiting ?? true else { return .nothingToDo }
            guard let quantity = positive(requested) else { return .nothingToDo }
            let operation: OfflineOperation = kind == .warehouse ? .setWaitingWarehouse : .setWaitingCRM
            return .queue(operation, payload(note, line, work, ["quantity": .number(quantity), "note": .optional(text)]), replaces: nil)
        case .pack(let requested):
            guard let quantity = positive(requested ?? line.quantityToPack) else { return .nothingToDo }
            return .queue(.pack, payload(note, line, work, ["quantity": .number(quantity)]), replaces: nil)
        }
    }

    static func pickLocation(line: GoodsOutLineDTO, requested: Int?) -> Int? {
        requested
            ?? line.locations?.first { ($0.stock ?? 0) > 0 && $0.locationOrgStockId != nil }?.locationOrgStockId
            ?? line.locations?.first { $0.locationOrgStockId != nil }?.locationOrgStockId
    }

    private static func pickAtLocation(
        note: GoodsOutDeliveryNoteDTO,
        line: GoodsOutLineDTO,
        locationOrgStockID: Int,
        quantity: Double,
        work: GoodsOutLineWork,
        queued: [QueueEntry]
    ) -> GoodsOutOfflinePlan {
        let current = line.locations?.first { $0.locationOrgStockId == locationOrgStockID }?.quantityPicked ?? 0
        let change = rounded(quantity - current)
        let unsent = queued.last {
            $0.operation == .pick && $0.state == .waiting && $0.sequence == nil
                && $0.payloadInt(lineKey) == line.id && $0.payloadInt("location_org_stock_id") == locationOrgStockID
        }
        let total = rounded((unsent?.quantity ?? 0) + change)
        let pickPayload = { (amount: Double) in
            payload(note, line, work, ["location_org_stock_id": .int(locationOrgStockID), "quantity": .number(amount)])
        }

        if change == 0 { return .nothingToDo }
        if let unsent, total > 0 { return .queue(.pick, pickPayload(total), replaces: unsent.localID) }
        if let unsent, total == 0 { return .undo(unsent.localID) }
        if change > 0 { return .queue(.pick, pickPayload(change), replaces: nil) }
        return .onlineOnly
    }

    private static func payload(_ note: GoodsOutDeliveryNoteDTO, _ line: GoodsOutLineDTO, _ work: GoodsOutLineWork, _ fields: OfflinePayload) -> OfflinePayload {
        var payload = fields
        payload["delivery_note_id"] = .int(note.id)
        payload[lineKey] = .int(line.id)
        payload[GoodsOutNoteAction.clientRequestKey] = .string(work.clientRequestID)
        return payload
    }

    static func positive(_ value: Double?) -> Double? {
        guard let value else { return nil }
        let amount = rounded(value)
        return amount > 0 ? amount : nil
    }

    static func rounded(_ value: Double) -> Double {
        (value * 1_000_000).rounded() / 1_000_000
    }

    static let lineKey = "delivery_note_item_id"
}

enum GoodsOutOfflineOverlay {
    static func shows(_ entry: QueueEntry, copySeenAt: Date?) -> Bool {
        switch entry.state {
        case .waiting, .sending: true
        case .applied: copySeenAt.map { entry.performedAt > $0 } ?? false
        case .needsYou, .void: false
        }
    }

    static func apply(_ entries: [QueueEntry], to dto: GoodsOutDeliveryNoteDTO, copySeenAt: Date?) -> GoodsOutDeliveryNoteDTO {
        let shown = entries.filter { entry in
            entry.operation.map { OfflineOperation.goodsOut.contains($0) } == true && shows(entry, copySeenAt: copySeenAt)
        }
        guard !shown.isEmpty, let lines = dto.lines else { return dto }
        let byLine = Dictionary(grouping: shown) { $0.payloadInt(GoodsOutOfflinePlanner.lineKey) ?? -1 }
        let updated = lines.map { line in (byLine[line.id] ?? []).reduce(line) { with($1, on: $0) } }
        let isPacking = dto.state == "packing" || dto.state == "packed"
        let change = updated.filter { isDone($0, packing: isPacking) }.count - lines.filter { isDone($0, packing: isPacking) }.count
        var overlaid = dto
        overlaid.lines = updated
        if var counts = overlaid.lineCounts {
            counts.done = max(0, (counts.done ?? 0) + change)
            counts.todo = max(0, (counts.todo ?? 0) - change)
            overlaid.lineCounts = counts
        }
        return overlaid
    }

    private static func isDone(_ line: GoodsOutLineDTO, packing: Bool) -> Bool {
        packing ? (line.quantityToPack ?? 0) <= 0 : (line.quantityToPick ?? 0) <= 0
    }

    private static func with(_ entry: QueueEntry, on line: GoodsOutLineDTO) -> GoodsOutLineDTO {
        let quantity = entry.quantity
        var updated = line
        switch entry.operation {
        case .pick:
            updated.quantityPicked = GoodsOutOfflinePlanner.rounded((line.quantityPicked ?? 0) + quantity)
            updated.quantityPickedLabel = nil
            let location = entry.payloadInt("location_org_stock_id")
            updated.locations = line.locations?.map { row in
                guard row.locationOrgStockId == location else { return row }
                var picked = row
                picked.quantityPicked = GoodsOutOfflinePlanner.rounded((row.quantityPicked ?? 0) + quantity)
                picked.stock = row.stock.map { GoodsOutOfflinePlanner.rounded($0 - quantity) }
                picked.stockLabel = nil
                return picked
            }
            recomputePick(&updated)
        case .notPicked:
            updated.quantityNotPicked = GoodsOutOfflinePlanner.rounded((line.quantityNotPicked ?? 0) + quantity)
            recomputePick(&updated)
        case .setWaitingWarehouse:
            updated.quantityWaitingWarehouse = GoodsOutOfflinePlanner.rounded(min(quantity, outstandingForWaiting(line)))
            recomputePick(&updated)
        case .setWaitingCRM:
            let moved = min(quantity, outstandingForWaiting(line))
            updated.quantityWaitingWarehouse = GoodsOutOfflinePlanner.rounded(max(0, (line.quantityWaitingWarehouse ?? 0) - moved))
            updated.quantityWaitingCrm = GoodsOutOfflinePlanner.rounded((line.quantityWaitingCrm ?? 0) + moved)
            recomputePick(&updated)
        case .pack:
            let toPack = GoodsOutOfflinePlanner.rounded(max(0, (line.quantityToPack ?? 0) - quantity))
            updated.quantityPacked = GoodsOutOfflinePlanner.rounded((line.quantityPacked ?? 0) + quantity)
            updated.quantityPackedLabel = nil
            updated.quantityToPack = toPack
            updated.quantityToPackLabel = nil
            updated.isDonePacking = (line.isDonePacking ?? false) || toPack <= 0
        default:
            break
        }
        return updated
    }

    private static func outstandingForWaiting(_ line: GoodsOutLineDTO) -> Double {
        max(0, (line.quantityRequired ?? 0) - (line.quantityPicked ?? 0) - (line.quantityWaitingCrm ?? 0))
    }

    private static func recomputePick(_ line: inout GoodsOutLineDTO) {
        let required = line.quantityRequired ?? 0
        let settled = (line.quantityPicked ?? 0) + (line.quantityNotPicked ?? 0) + (line.quantityWaitingWarehouse ?? 0) + (line.quantityWaitingCrm ?? 0)
        let toPick = GoodsOutOfflinePlanner.rounded(max(0, required - settled))
        line.quantityToPick = toPick
        line.quantityToPickLabel = nil
        line.isPicked = toPick <= 0
    }
}

@MainActor
@Observable
final class GoodsOutOfflineCopies {
    static let shared = GoodsOutOfflineCopies()

    struct Copy {
        let dto: GoodsOutDeliveryNoteDTO
        let seenAt: Date
        let isOfflineCopy: Bool
        let downloadedAt: Date?
    }

    private(set) var offlineCopyDates: [Int: Date] = [:]
    @ObservationIgnored private var copies: [Int: Copy] = [:]
    @ObservationIgnored private var overlaid: Set<Int> = []

    func keep(_ dto: GoodsOutDeliveryNoteDTO, seenAt: Date, offlineCopyFrom downloadedAt: Date? = nil) {
        copies[dto.id] = Copy(dto: dto, seenAt: seenAt, isOfflineCopy: downloadedAt != nil, downloadedAt: downloadedAt)
        offlineCopyDates[dto.id] = downloadedAt
    }

    func copy(_ noteID: Int) -> Copy? { copies[noteID] }

    var noteIDs: [Int] { Array(copies.keys) }

    func offlineCopyDate(_ noteID: Int?) -> Date? {
        noteID.flatMap { offlineCopyDates[$0] }
    }

    func markOverlaid(_ noteID: Int, _ isOverlaid: Bool) {
        if isOverlaid {
            overlaid.insert(noteID)
        } else {
            overlaid.remove(noteID)
        }
    }

    func wasOverlaid(_ noteID: Int) -> Bool { overlaid.contains(noteID) }

    func reset() {
        copies = [:]
        overlaid = []
        offlineCopyDates = [:]
    }
}

extension GoodsOutStore {
    var offlineWork: OfflineWork { OfflineWork.shared }

    func showing(_ dto: GoodsOutDeliveryNoteDTO, seenAt: Date = ServerClock.shared.now().date, offlineCopyFrom downloadedAt: Date? = nil) -> GoodsOutDeliveryNoteDTO {
        GoodsOutOfflineCopies.shared.keep(dto, seenAt: seenAt, offlineCopyFrom: downloadedAt)
        let entries = offlineWork.shown(for: .deliveryNote(dto.id))
        GoodsOutOfflineCopies.shared.markOverlaid(dto.id, !entries.isEmpty)
        return GoodsOutOfflineOverlay.apply(entries, to: dto, copySeenAt: seenAt)
    }

    func queueOffline(_ action: GoodsOutNoteAction, noteID: Int, force: Bool = false) -> GoodsOutActionOutcome? {
        guard let work = GoodsOutLineWork(action), let warehouseID else { return nil }
        let record = OfflineRecordKey.deliveryNote(noteID)
        guard force || offlineWork.shouldQueue(record), let copy = GoodsOutOfflineCopies.shared.copy(noteID) else { return nil }
        if offlineWork.pending(for: record).contains(where: { $0.state == .needsYou }) {
            return .failed(message: "Earlier offline work on this needs you first")
        }
        let shown = GoodsOutOfflineOverlay.apply(offlineWork.shown(for: record), to: copy.dto, copySeenAt: copy.seenAt)
        switch GoodsOutOfflinePlanner.plan(note: shown, work: work, queued: offlineWork.pending(for: record)) {
        case .onlineOnly:
            return force || !offlineWork.isOnline ? .failed(message: "This needs a connection") : nil
        case .nothingToDo:
            return .nothingChanged(message: nil)
        case .undo(let localID):
            offlineWork.undo(localID)
        case .queue(let operation, let payload, let replaces):
            if let replaces {
                offlineWork.replacePayload(of: replaces, with: payload)
            } else if offlineWork.enqueue(
                warehouseID: warehouseID,
                operation: operation,
                record: record,
                payload: payload,
                clientRequestID: work.clientRequestID,
                seenAt: copy.seenAt
            ) == nil {
                return nil
            }
        }
        refreshFromOfflineCopy(noteID)
        return .done(message: "Saved on this phone. It syncs when you are back online.")
    }

    func offlineScanToPick(noteID: Int, code: String, lineID: Int?) -> (outcome: GoodsOutScanOutcome, message: String?)? {
        let record = OfflineRecordKey.deliveryNote(noteID)
        guard offlineWork.shouldQueue(record), let copy = GoodsOutOfflineCopies.shared.copy(noteID) else { return nil }
        let shown = GoodsOutOfflineOverlay.apply(offlineWork.shown(for: record), to: copy.dto, copySeenAt: copy.seenAt)
        let scanned = code.trimmingCharacters(in: .whitespacesAndNewlines)
        guard GoodsOutOfflinePlanner.workingStates.contains(shown.state) else { return (.notPicking, nil) }
        guard let line = shown.lines?.first(where: { line in
            (lineID == nil || line.id == lineID) && line.scanCodes.contains { $0.caseInsensitiveCompare(scanned) == .orderedSame }
        }) else {
            return (.notOnNote(code: scanned), nil)
        }
        let sko = line.orgStock?.code ?? scanned
        guard (line.quantityToPick ?? 0) > 0 else { return (.lineComplete(sko: sko), nil) }
        guard let location = GoodsOutOfflinePlanner.pickLocation(line: line, requested: nil) else {
            return (.notOnNote(code: scanned), "This needs a connection")
        }
        let current = line.locations?.first { $0.locationOrgStockId == location }?.quantityPicked ?? 0
        switch queueOffline(.pick(line: line.id, locationOrgStockID: location, quantity: current + 1), noteID: noteID) {
        case .done:
            let left = GoodsOutMapper.quantity(max(0, (line.quantityToPick ?? 0) - 1))
            return (.picked(sko: sko, left: left), "+1 · waiting to sync")
        case .failed(let message):
            return (.notOnNote(code: scanned), message)
        case .nothingChanged, nil:
            return (.lineComplete(sko: sko), nil)
        }
    }

    func settleOfflineWork(before action: GoodsOutNoteAction, noteID: Int) async -> GoodsOutActionOutcome? {
        guard GoodsOutLineWork(action) == nil, action.method != .get else { return nil }
        let record = OfflineRecordKey.deliveryNote(noteID)
        guard !offlineWork.pending(for: record).isEmpty else { return nil }
        guard await offlineWork.flush(record) else {
            let needsYou = offlineWork.pending(for: record).contains { $0.state == .needsYou }
            return .failed(message: needsYou
                ? "Some of your work on this note needs you before this can be done."
                : "Your work on this note has not synced yet. Try again once it has.")
        }
        return nil
    }

    func refreshFromOfflineCopy(_ noteID: Int) {
        guard let copy = GoodsOutOfflineCopies.shared.copy(noteID) else { return }
        let entries = offlineWork.shown(for: .deliveryNote(noteID))
        GoodsOutOfflineCopies.shared.markOverlaid(noteID, !entries.isEmpty)
        storeMapped(GoodsOutOfflineOverlay.apply(entries, to: copy.dto, copySeenAt: copy.seenAt))
    }

    func loadOfflineCopy(id: Int) -> GoodsOutDeliveryNote? {
        guard let downloaded = offlineWork.downloaded(.deliveryNote(id)) else { return nil }
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        guard let dto = try? decoder.decode(DataEnvelope<GoodsOutDeliveryNoteDTO>.self, from: downloaded.body).value else { return nil }
        return storeMapped(showing(dto, seenAt: downloaded.seenAt, offlineCopyFrom: downloaded.downloadedAt))
    }

    func startFollowingOfflineWork() {
        guard !GoodsOutOfflineFollow.isFollowing else { return }
        GoodsOutOfflineFollow.isFollowing = true
        offlineWork.onQueueChanged {
            let store = GoodsOutStore.shared
            guard store.isRemote else { return }
            for noteID in GoodsOutOfflineCopies.shared.noteIDs {
                let hasWork = !OfflineWork.shared.shown(for: .deliveryNote(noteID)).isEmpty
                if hasWork || GoodsOutOfflineCopies.shared.wasOverlaid(noteID) {
                    store.refreshFromOfflineCopy(noteID)
                }
            }
        }
        offlineWork.onRecordsChanged { records in
            let store = GoodsOutStore.shared
            guard store.isRemote else { return }
            let held = Set(GoodsOutOfflineCopies.shared.noteIDs)
            for record in records where record.type == .deliveryNote && held.contains(record.id) {
                Task { await store.loadNote(id: record.id) }
            }
        }
    }
}

@MainActor
enum GoodsOutOfflineFollow {
    static var isFollowing = false
}
