import Foundation

enum OfflineRecordText {
    static var queued: String { L10n.formatInSavedLanguage("Saved on this phone. It syncs when you are back online.") }
    static var needsConnection: String { L10n.formatInSavedLanguage("This needs a connection") }
    static var needsYouFirst: String { L10n.formatInSavedLanguage("Earlier offline work on this needs you first") }
    static var notSyncedYet: String { L10n.formatInSavedLanguage("Your work here has not synced yet. Try again once it has.") }
    static var keptOnPhone: String { L10n.formatInSavedLanguage("Your work here is kept on this phone and sent when you are back online.") }
}

struct OfflineCopy<Value: Sendable>: Sendable {
    let value: Value
    let seenAt: Date
    let downloadedAt: Date
}

@MainActor
struct OfflineRecordQueue {
    let warehouseID: Int
    let record: OfflineRecordKey

    private var work: OfflineWork { .shared }

    var isOnline: Bool { work.isOnline }

    var shouldQueue: Bool { work.shouldQueue(record) }

    var pending: [QueueEntry] {
        _ = work.queueRevision
        return work.pending(for: record)
    }

    var unsent: [QueueEntry] {
        pending.filter(\.isUnsent)
    }

    func newClientRequestID() -> String {
        work.newClientRequestID()
    }

    func enqueue(_ operation: OfflineOperation, payload: OfflinePayload) -> QueueEntry? {
        let clientRequestID = payload[OfflinePayloadKey.clientRequestID]?.stringValue ?? work.newClientRequestID()
        return work.enqueue(
            warehouseID: warehouseID,
            operation: operation,
            record: record,
            payload: payload,
            clientRequestID: clientRequestID,
            seenAt: work.downloaded(record)?.seenAt
        )
    }

    func blockingReason() async -> String? {
        guard !work.pending(for: record).isEmpty else { return nil }
        guard work.isOnline else { return OfflineRecordText.notSyncedYet }
        if await work.flush(record) { return nil }
        let needsYou = work.pending(for: record).contains { $0.state == .needsYou }
        return needsYou ? OfflineRecordText.needsYouFirst : OfflineRecordText.notSyncedYet
    }

    func copy<Value: Decodable & Sendable>(_ type: Value.Type) -> OfflineCopy<Value>? {
        guard let downloaded = work.downloaded(record) else { return nil }
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        guard let value = try? decoder.decode(DataEnvelope<Value>.self, from: downloaded.body).value else { return nil }
        return OfflineCopy(value: value, seenAt: downloaded.seenAt, downloadedAt: downloaded.downloadedAt)
    }
}

@MainActor
final class OfflineRecordFollowers {
    static let shared = OfflineRecordFollowers()

    private var followers: [OfflineRecordKey: [@MainActor () -> Bool]] = [:]
    private var isListening = false

    func follow<Owner: AnyObject>(_ record: OfflineRecordKey, by owner: Owner, reload: @escaping @MainActor (Owner) -> Void) {
        startListening()
        followers[record, default: []].append { [weak owner] in
            guard let owner else { return false }
            reload(owner)
            return true
        }
    }

    func recordsChanged(_ records: [OfflineRecordKey]) {
        for record in Set(records) {
            let alive = (followers[record] ?? []).filter { $0() }
            followers[record] = alive.isEmpty ? nil : alive
        }
    }

    private func startListening() {
        guard !isListening else { return }
        isListening = true
        OfflineWork.shared.onRecordsChanged { records in
            OfflineRecordFollowers.shared.recordsChanged(records)
        }
    }
}
