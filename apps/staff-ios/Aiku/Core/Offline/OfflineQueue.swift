import Foundation

final class OfflineQueue: @unchecked Sendable {
    static let shared = OfflineQueue()

    private let fileURL: URL
    private let clock: @Sendable () -> Date
    private let lock = NSRecursiveLock()
    private var loaded: [QueueEntry]?
    private var listeners: [@Sendable ([QueueEntry]) -> Void] = []

    init(directory: URL = OfflineFiles.directory("queue"), clock: @escaping @Sendable () -> Date = { Date() }) {
        fileURL = directory.appending(path: "actions.json")
        self.clock = clock
    }

    func entries() -> [QueueEntry] {
        lock.withLock { loaded ?? load() }
    }

    func onChange(_ listener: @escaping @Sendable ([QueueEntry]) -> Void) {
        lock.withLock { listeners.append(listener) }
    }

    @discardableResult
    func add(_ entry: QueueEntry) -> QueueEntry {
        lock.withLock {
            let current = entries()
            var added = entry
            added.localID = (current.map(\.localID).max() ?? 0) + 1
            publish(current + [added])
            return added
        }
    }

    func update(_ entry: QueueEntry) {
        lock.withLock {
            let current = entries()
            guard current.contains(where: { $0.localID == entry.localID }) else { return }
            publish(current.map { $0.localID == entry.localID ? entry : $0 })
        }
    }

    func delete(_ localID: Int64) {
        lock.withLock {
            publish(entries().filter { $0.localID != localID })
        }
    }

    @discardableResult
    func change(_ localID: Int64, _ transform: (QueueEntry) -> QueueEntry?) -> QueueEntry? {
        lock.withLock {
            guard let entry = entries().first(where: { $0.localID == localID }) else { return nil }
            let changed = transform(entry)
            if let changed {
                if changed != entry {
                    update(changed)
                }
            } else {
                delete(localID)
            }
            return changed
        }
    }

    func claimBatch(userID: Int, maxSize: Int, reserve: (Int) -> [Int64]) -> [QueueEntry] {
        lock.withLock {
            let pending = entries()
                .filter { $0.userID == userID && $0.isPendingSend }
                .sorted { ($0.sequence ?? .max, $0.localID) < ($1.sequence ?? .max, $1.localID) }
            guard let first = pending.first else { return [] }
            let batch = Array(pending.prefix { $0.warehouseID == first.warehouseID }.prefix(maxSize))
            var numbers = reserve(batch.filter { $0.sequence == nil }.count).makeIterator()
            let claimed = batch.map { entry -> QueueEntry in
                var numbered = entry
                if numbered.sequence == nil {
                    numbered.sequence = numbers.next()
                }
                if numbered.state == .waiting {
                    numbered.state = .sending
                }
                return numbered
            }
            let byID = Dictionary(uniqueKeysWithValues: claimed.map { ($0.localID, $0) })
            publish(entries().map { byID[$0.localID] ?? $0 })
            return claimed
        }
    }

    func releaseSending(userID: Int) {
        lock.withLock {
            let current = entries()
            guard current.contains(where: { $0.userID == userID && $0.state == .sending }) else { return }
            publish(current.map { entry in
                guard entry.userID == userID, entry.state == .sending else { return entry }
                var released = entry
                released.state = .waiting
                return released
            })
        }
    }

    func purgeSettled() {
        lock.withLock {
            let cutOff = clock().addingTimeInterval(-Self.keepSettledFor)
            let current = entries()
            let kept = current.filter { entry in
                guard let syncedAt = entry.syncedAt else { return true }
                return !(syncedAt < cutOff && (entry.state == .applied || entry.state == .void))
            }
            if kept.count != current.count {
                publish(kept)
            }
        }
    }

    private func publish(_ entries: [QueueEntry]) {
        loaded = entries
        if let data = try? OfflineFiles.encoder.encode(entries) {
            OfflineFiles.write(data, to: fileURL)
        }
        let listeners = self.listeners
        listeners.forEach { $0(entries) }
    }

    private func load() -> [QueueEntry] {
        let stored = (try? Data(contentsOf: fileURL)).flatMap { try? OfflineFiles.decoder.decode([QueueEntry].self, from: $0) } ?? []
        let restored = stored.map { entry -> QueueEntry in
            guard entry.state == .sending else { return entry }
            var released = entry
            released.state = .waiting
            return released
        }
        loaded = restored
        return restored
    }

    private static let keepSettledFor: TimeInterval = 24 * 60 * 60
}
