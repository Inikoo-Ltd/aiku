import Foundation
import Observation

@MainActor
@Observable
final class PickingSessionStore {
    let sessionID: Int
    private(set) var session: MobilePickingSession?
    private(set) var isLoading = false
    private(set) var loadError: String?
    private(set) var working: PickingSessionWrite?
    private(set) var currentPickKey: String?
    private(set) var scanTarget: PickingSessionScanTarget?
    private(set) var scanLog: [PickingSessionScanLogEntry] = []
    private(set) var queuedScans = 0
    private(set) var trolleyChoices: [MobileSessionTrolley] = []
    private(set) var isLoadingTrolleys = false
    private(set) var trolleysError: String?
    var event: PickingSessionEvent?

    @ObservationIgnored private let service: PickingSessionService
    @ObservationIgnored private var pendingScans: [PendingScan] = []
    @ObservationIgnored private var scanTask: Task<Void, Never>?
    @ObservationIgnored private var nextLogID = 0

    init(sessionID: Int, service: PickingSessionService) {
        self.sessionID = sessionID
        self.service = service
    }

    var isWorking: Bool { working != nil }

    var currentPick: MobilePickListEntry? { session?.pick(currentPickKey) }

    var nextPick: MobilePickListEntry? { session?.nextOpenPick(after: currentPickKey) }

    func adopt(_ session: MobilePickingSession) {
        apply(session)
    }

    func load() async {
        guard queuedScans == 0, working == nil else { return }
        isLoading = true
        loadError = nil
        do {
            apply(try await service.session(sessionID))
        } catch where error.isCancellation {
        } catch {
            loadError = PickingSessionErrorText.message(error, offline: "This picking session needs internet. Check Wi-Fi and try again.")
        }
        isLoading = false
    }

    func act(_ write: PickingSessionWrite) async -> Result<String?, PickingSessionFailure> {
        guard working == nil else { return .failure(PickingSessionFailure(message: "Wait for the last change to finish.")) }
        working = write
        defer { working = nil }
        do {
            let outcome = try await service.write(sessionID, write)
            apply(outcome.session)
            loadError = nil
            let message = outcome.result.message.flatMap { $0.isEmpty ? nil : $0 }
            return .success(message)
        } catch {
            return .failure(PickingSessionFailure(message: PickingSessionErrorText.message(error)))
        }
    }

    func loadTrolleyChoices(warehouseID: Int) async {
        isLoadingTrolleys = true
        trolleysError = nil
        do {
            trolleyChoices = try await service.trolleys(warehouseID, true)
        } catch where error.isCancellation {
        } catch {
            trolleysError = PickingSessionErrorText.message(error)
        }
        isLoadingTrolleys = false
    }

    func nextLocation() {
        guard let next = session?.nextOpenPick(after: currentPickKey) else { return }
        currentPickKey = next.key
        scanTarget = nil
    }

    func closeScanPanel() {
        scanTarget = nil
    }

    func scanToPick(_ code: String) {
        let barcode = code.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !barcode.isEmpty else { return }
        enqueue(.pick(MobileSessionScanBody(barcode: barcode, quantity: 1)))
    }

    func pickAllScanned() {
        guard let target = scanTarget else { return }
        enqueue(.pick(MobileSessionScanBody(barcode: target.barcode, quantity: nil, deliveryNoteItemId: target.lineID, locationOrgStockId: target.locationOrgStockID)))
    }

    func pickScanned(amount: Int) {
        guard let target = scanTarget, amount > 0 else { return }
        enqueue(.pick(MobileSessionScanBody(barcode: target.barcode, quantity: Double(amount), deliveryNoteItemId: target.lineID, locationOrgStockId: target.locationOrgStockID)))
    }

    func scanToPack(_ code: String) {
        let barcode = code.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !barcode.isEmpty else { return }
        enqueue(.pack(MobileSessionScanBody(barcode: barcode, quantity: 1)))
    }

    func packAllScanned() {
        guard let target = scanTarget else { return }
        enqueue(.pack(MobileSessionScanBody(barcode: target.barcode, quantity: nil, deliveryNoteItemId: target.lineID)))
    }

    func packScanned(amount: Int) {
        guard let target = scanTarget, amount > 0 else { return }
        enqueue(.pack(MobileSessionScanBody(barcode: target.barcode, quantity: Double(amount), deliveryNoteItemId: target.lineID)))
    }

    func waitForScans() async {
        await scanTask?.value
    }

    private func enqueue(_ scan: PendingScan) {
        pendingScans.append(scan)
        queuedScans = pendingScans.count + (scanTask == nil ? 0 : 1)
        guard scanTask == nil else { return }
        scanTask = Task { [weak self] in
            await self?.drainScans()
        }
    }

    private func drainScans() async {
        while !pendingScans.isEmpty {
            let scan = pendingScans.removeFirst()
            queuedScans = pendingScans.count + 1
            do {
                switch scan {
                case .pick(let body):
                    onPicked(body, try await service.scanPick(sessionID, body))
                case .pack(let body):
                    onPacked(body, try await service.scanPack(sessionID, body))
                }
            } catch {
                let message = PickingSessionErrorText.message(error)
                log(scan.barcode, .failed, message)
                event = .feedback(message, .danger)
                if case APIError.unauthorized = error { pendingScans.removeAll() }
            }
        }
        queuedScans = 0
        scanTask = nil
    }

    private func onPicked(_ body: MobileSessionScanBody, _ outcome: MobilePickingSessionOutcome) {
        let previousPick = currentPick
        let updated = outcome.session
        let item = outcome.result.item
        let status = outcome.result.status
        let isPicked = status == PickingSessionScanStatus.picked
        let keepsTarget = status == PickingSessionScanStatus.notFound || status == PickingSessionScanStatus.wrongState
        let scannedPick = item.flatMap { updated.pickContaining(deliveryNoteItemID: $0.id) }
        let focusedKey = scannedPick.flatMap { $0.isDone ? nil : $0.key } ?? currentPickKey
        let nextKey = updated.openPickKey(current: focusedKey)
        let finishedLocation = previousPick.flatMap { isPicked && updated.pick($0.key)?.isDone == true ? $0 : nil }
        let toPick = item?.quantityToPick ?? 0

        session = updated
        loadError = nil
        currentPickKey = nextKey
        if !keepsTarget {
            if let item, toPick > 0 {
                scanTarget = PickingSessionScanTarget(
                    lineID: item.id,
                    barcode: body.barcode,
                    code: item.code ?? body.barcode,
                    name: item.name ?? "",
                    deliveryNoteReference: outcome.result.deliveryNote?.reference,
                    locationCode: item.locationCode,
                    locationOrgStockID: item.locationOrgStockId,
                    done: item.quantityPicked ?? 0,
                    remaining: toPick,
                    remainingLabel: item.quantityToPickLabel
                )
            } else {
                scanTarget = nil
            }
        }
        log(body.barcode, isPicked ? .done : .refused, outcome.result.message)

        if !isPicked {
            if let message = outcome.result.message, !message.isEmpty {
                event = .feedback(message, status == PickingSessionScanStatus.nothingChanged ? .info : .danger)
            }
        } else if updated.sessionState == .handlingBlocked {
            event = .nowWaiting
        } else if nextKey == nil || updated.isPickingOver {
            event = .allPicked
        } else if let finishedLocation {
            event = .locationDone(finishedLocation.title)
        } else if let item, toPick <= 0 {
            event = .lineDone(item.code ?? body.barcode)
        }
    }

    private func onPacked(_ body: MobileSessionScanBody, _ outcome: MobilePickingSessionOutcome) {
        let updated = outcome.session
        let item = outcome.result.item
        let status = outcome.result.status
        let isPacked = status == PickingSessionScanStatus.packed
        let keepsTarget = status == PickingSessionScanStatus.notFound || status == PickingSessionScanStatus.wrongState
        let toPack = item?.quantityToPack ?? 0

        session = updated
        loadError = nil
        if !keepsTarget {
            if let item, toPack > 0 {
                scanTarget = PickingSessionScanTarget(
                    lineID: item.id,
                    barcode: body.barcode,
                    code: item.code ?? body.barcode,
                    name: item.name ?? "",
                    deliveryNoteReference: outcome.result.deliveryNote?.reference,
                    locationCode: nil,
                    locationOrgStockID: nil,
                    done: item.quantityPacked ?? 0,
                    remaining: toPack,
                    remainingLabel: item.quantityToPackLabel
                )
            } else {
                scanTarget = nil
            }
        }
        log(body.barcode, isPacked ? .done : .refused, outcome.result.message)

        if !isPacked {
            if let message = outcome.result.message, !message.isEmpty {
                event = .feedback(message, status == PickingSessionScanStatus.nothingChanged ? .info : .danger)
            }
        } else if updated.sessionState == .packingFinished {
            event = .allPacked
        } else if let item, toPack <= 0 {
            event = .lineDone(item.code ?? body.barcode)
        }
    }

    private func apply(_ updated: MobilePickingSession) {
        session = updated
        currentPickKey = updated.openPickKey(current: currentPickKey)
    }

    private func log(_ barcode: String, _ result: PickingSessionScanResult, _ message: String?) {
        nextLogID += 1
        scanLog.insert(PickingSessionScanLogEntry(id: nextLogID, barcode: barcode, result: result, message: message), at: 0)
        if scanLog.count > 20 { scanLog.removeLast(scanLog.count - 20) }
    }
}

private enum PendingScan {
    case pick(MobileSessionScanBody)
    case pack(MobileSessionScanBody)

    var barcode: String {
        switch self {
        case .pick(let body), .pack(let body): body.barcode
        }
    }
}
