import Foundation

enum SyncRunStatus: Sendable {
    case complete
    case retry
    case stopped
}

struct SyncRunOutcome: Sendable {
    var status: SyncRunStatus
    var newlyNeedingYou = 0
    var hasMore = false
    var changed: [OfflineRecordKey] = []
}

actor OfflineSyncEngine {
    private let queue: OfflineQueue
    private let device: OfflineDevice
    private let api: OfflineSyncAPI
    private let sessions: OfflineSessionBox
    private let clock: @Sendable () -> Date

    init(
        queue: OfflineQueue = .shared,
        device: OfflineDevice = .shared,
        api: OfflineSyncAPI = .shared,
        sessions: OfflineSessionBox = .shared,
        clock: @escaping @Sendable () -> Date = { Date() }
    ) {
        self.queue = queue
        self.device = device
        self.api = api
        self.sessions = sessions
        self.clock = clock
    }

    func run(maxBatches: Int, isDone: @Sendable () -> Bool = { false }) async -> SyncRunOutcome {
        guard let session = sessions.current else { return SyncRunOutcome(status: .stopped) }
        let userID = session.userID
        queue.purgeSettled()
        var batches = 0
        var outcome = SyncRunOutcome(status: .complete)
        var changed: [(OfflineRecordKey, Int)] = []
        var missingAnswers = Set<Int64>()

        while batches < maxBatches, !isDone() {
            guard sessions.current == session else {
                outcome.status = .stopped
                break
            }
            let batch = queue.claimBatch(userID: userID, maxSize: Self.maxBatchSize) { device.reserveSequences(userID: userID, count: $0) }
            guard let first = batch.first else { break }
            batches += 1
            let answer = await api.sync(warehouseID: first.warehouseID, actions: batch.map(Self.request))
            var missingFrom: Int64?
            if case .answered(let results, _) = answer {
                let applied = apply(batch, results: results)
                outcome.newlyNeedingYou += applied.newlyNeedingYou
                for record in applied.changed where !changed.contains(where: { $0.0 == record.0 }) {
                    changed.append(record)
                }
                missingFrom = applied.missingFrom
            }
            queue.releaseSending(userID: userID)
            switch answer {
            case .answered(_, let expected):
                if let missingFrom {
                    guard let expected, expected < missingFrom, missingAnswers.insert(expected).inserted else {
                        outcome.status = .stopped
                        break
                    }
                    fill(userID: userID, warehouseID: first.warehouseID, from: expected, upTo: missingFrom)
                }
            case .sessionExpired:
                outcome.status = .stopped
            case .unreachable:
                outcome.status = .retry
            case .failed(let statusCode):
                outcome.status = statusCode >= 500 ? .retry : .stopped
            }
            if outcome.status != .complete {
                break
            }
        }

        if outcome.status != .retry, sessions.current == session {
            for (record, warehouseID) in changed {
                _ = await api.refetch(path: OfflineRoutes.detailPath(record, warehouseID: warehouseID))
            }
            await reconcile(userID: userID)
        }
        outcome.changed = changed.map(\.0)
        outcome.hasMore = queue.entries().contains { $0.userID == userID && $0.isPendingSend }
        return outcome
    }

    private struct Applied {
        var newlyNeedingYou = 0
        var changed: [(OfflineRecordKey, Int)] = []
        var missingFrom: Int64?
    }

    private func apply(_ batch: [QueueEntry], results: [SyncResult]) -> Applied {
        let bySequence = Dictionary(results.map { ($0.sequence, $0.result) }, uniquingKeysWith: { _, last in last })
        let syncedAt = clock()
        var applied = Applied()
        for sent in batch {
            let result = sent.sequence.flatMap { bySequence[$0] }
            guard applied.missingFrom == nil, let result, result.status != OfflineOutcomeStatus.missingPrevious else {
                if result?.status == OfflineOutcomeStatus.missingPrevious, applied.missingFrom == nil {
                    applied.missingFrom = sent.sequence
                }
                continue
            }
            let state = Self.state(of: sent, status: result.status)
            queue.change(sent.localID) { current in
                var settled = current
                settled.state = state
                settled.result = result
                settled.syncedAt = syncedAt
                return settled
            }
            if state == .needsYou {
                applied.newlyNeedingYou += 1
            }
            if let record = sent.record, state != .void {
                applied.changed.append((record, sent.warehouseID))
            }
        }
        return applied
    }

    private static func state(of sent: QueueEntry, status: String) -> QueuedActionState {
        if sent.operation == nil { return .void }
        switch status {
        case OfflineOutcomeStatus.applied, OfflineOutcomeStatus.alreadyDone: return .applied
        case OfflineOutcomeStatus.void: return .void
        default: return .needsYou
        }
    }

    private func fill(userID: Int, warehouseID: Int, from expected: Int64, upTo missing: Int64) {
        let held = Dictionary(
            queue.entries().filter { $0.userID == userID && $0.sequence != nil }.map { ($0.sequence!, $0) },
            uniquingKeysWith: { first, _ in first }
        )
        for sequence in expected..<missing {
            if let entry = held[sequence] {
                queue.change(entry.localID) { current in
                    var resent = current
                    resent.state = current.state == .void || current.operation == nil ? .void : .waiting
                    resent.syncedAt = nil
                    return resent
                }
            } else {
                let now = clock()
                queue.add(QueueEntry(
                    localID: 0,
                    userID: userID,
                    warehouseID: warehouseID,
                    operation: nil,
                    record: nil,
                    payload: nil,
                    clientRequestID: UUID().uuidString.lowercased(),
                    performedAt: now,
                    timeSourceIsServer: false,
                    seenAt: nil,
                    sequence: sequence,
                    state: .void,
                    result: nil,
                    syncedAt: nil,
                    createdAt: now
                ))
            }
        }
    }

    private func reconcile(userID: Int) async {
        let open = queue.entries().filter { entry in
            entry.userID == userID && entry.result?.operationID != nil
                && (entry.state == .needsYou || entry.result?.status == OfflineOutcomeStatus.alreadyDone)
        }
        guard !open.isEmpty, let operations = try? await api.operations(.open) else { return }
        let stillOpen = Dictionary(operations.compactMap { entry in entry.key.map { ($0, entry) } }, uniquingKeysWith: { first, _ in first })
        for entry in open {
            if let operation = entry.result?.operationID.flatMap({ stillOpen[$0] }) {
                queue.change(entry.localID) { current in
                    var reopened = current
                    reopened.state = .needsYou
                    reopened.result = operation.result
                    return reopened
                }
            } else if entry.state == .needsYou {
                queue.delete(entry.localID)
            }
        }
    }

    private static func request(_ entry: QueueEntry) -> SyncActionRequest {
        SyncActionRequest(
            sequence: entry.sequence ?? 0,
            operation: entry.sendsAsVoid ? OfflineOutcomeStatus.void : (entry.operation?.rawValue ?? OfflineOutcomeStatus.void),
            performedAt: entry.performedAt,
            timeSourceIsServer: entry.timeSourceIsServer,
            seenAt: entry.seenAt,
            payload: entry.sendsAsVoid ? [:] : (entry.payload ?? [:])
        )
    }

    static let maxBatchSize = 50
}
