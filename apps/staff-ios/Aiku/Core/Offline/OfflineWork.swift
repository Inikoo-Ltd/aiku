import Foundation
import Network
import Observation

@MainActor
@Observable
final class OfflineWork {
    static let shared = OfflineWork()

    private(set) var isOnline = true
    private(set) var offlineSince: Date?
    private(set) var queue: [QueueEntry] = []
    private(set) var waitingCount = 0
    private(set) var needsYouCount = 0
    private(set) var isSyncing = false
    private(set) var lastSyncAt: Date?
    private(set) var lastRunNeedingYou = 0
    private(set) var queueRevision = 0

    @ObservationIgnored private let actions: OfflineQueue
    @ObservationIgnored private let downloads: OfflineDownloads
    @ObservationIgnored private let engine: OfflineSyncEngine
    @ObservationIgnored private let api: OfflineSyncAPI
    @ObservationIgnored private let device: OfflineDevice
    @ObservationIgnored private let sessions: OfflineSessionBox
    @ObservationIgnored private let clock: ServerClock
    @ObservationIgnored private let monitor = NWPathMonitor()
    @ObservationIgnored private var started = false
    @ObservationIgnored private var runTask: Task<Void, Never>?
    @ObservationIgnored private var retryTask: Task<Void, Never>?
    @ObservationIgnored private var periodicTask: Task<Void, Never>?
    @ObservationIgnored private var prefetchTask: Task<Void, Never>?
    @ObservationIgnored private var noticesTask: Task<Void, Never>?
    @ObservationIgnored private var changeHandlers: [@MainActor ([OfflineRecordKey]) -> Void] = []
    @ObservationIgnored private var queueHandlers: [@MainActor () -> Void] = []

    init(
        actions: OfflineQueue = .shared,
        downloads: OfflineDownloads = .shared,
        engine: OfflineSyncEngine = OfflineSyncEngine(),
        api: OfflineSyncAPI = .shared,
        device: OfflineDevice = .shared,
        sessions: OfflineSessionBox = .shared,
        clock: ServerClock = .shared
    ) {
        self.actions = actions
        self.downloads = downloads
        self.engine = engine
        self.api = api
        self.device = device
        self.sessions = sessions
        self.clock = clock
    }

    var userID: Int? { sessions.current?.userID }

    func start() {
        guard !started else { return }
        started = true
        actions.onChange { _ in
            Task { @MainActor in OfflineWork.shared.publish() }
        }
        OfflineNetworkObserver.shared.onPrefetch { request in
            Task { @MainActor in OfflineWork.shared.prefetch(request) }
        }
        monitor.pathUpdateHandler = { path in
            let online = path.status == .satisfied
            Task { @MainActor in OfflineWork.shared.setOnline(online) }
        }
        monitor.start(queue: DispatchQueue(label: "com.inikoo.aiku.offline.path"))
        publish()
    }

    func signedIn(userID: Int, token: String) {
        let current = OfflineSessionBox.Current(userID: userID, token: token)
        guard sessions.current != current else { return }
        sessions.set(current)
        UserDefaults.standard.set(userID, forKey: Self.lastUserKey)
        downloads.keepOnly(userID: userID)
        publish()
        sendWaitingNotices()
        syncNow(.foreground)
    }

    func resume(token: String) {
        let userID = UserDefaults.standard.integer(forKey: Self.lastUserKey)
        guard userID > 0, sessions.current == nil else { return }
        signedIn(userID: userID, token: token)
    }

    func signedOut() {
        sessions.set(nil)
        UserDefaults.standard.removeObject(forKey: Self.lastUserKey)
        downloads.clear()
        runTask?.cancel()
        retryTask?.cancel()
        prefetchTask?.cancel()
        publish()
    }

    func onRecordsChanged(_ handler: @escaping @MainActor ([OfflineRecordKey]) -> Void) {
        changeHandlers.append(handler)
    }

    func onQueueChanged(_ handler: @escaping @MainActor () -> Void) {
        queueHandlers.append(handler)
    }

    func shouldQueue(_ record: OfflineRecordKey) -> Bool {
        if !isOnline { return true }
        guard let userID else { return false }
        return actions.entries().contains { $0.userID == userID && $0.record == record && $0.isUnsent }
    }

    @discardableResult
    func enqueue(
        warehouseID: Int,
        operation: OfflineOperation,
        record: OfflineRecordKey,
        payload: OfflinePayload,
        clientRequestID: String,
        seenAt: Date?
    ) -> QueueEntry? {
        guard let userID else { return nil }
        let performed = clock.now()
        let added = actions.add(QueueEntry(
            localID: 0,
            userID: userID,
            warehouseID: warehouseID,
            operation: operation,
            record: record,
            payload: payload,
            clientRequestID: clientRequestID,
            performedAt: performed.date,
            timeSourceIsServer: performed.isServerTime,
            seenAt: seenAt,
            sequence: nil,
            state: .waiting,
            result: nil,
            syncedAt: nil,
            createdAt: Date()
        ))
        publish()
        syncNow(.queued)
        return added
    }

    func replacePayload(of localID: Int64, with payload: OfflinePayload) {
        guard let userID else { return }
        actions.change(localID) { entry in
            guard entry.userID == userID, entry.state == .waiting, entry.sequence == nil else { return entry }
            var replaced = entry
            replaced.payload = payload
            return replaced
        }
        publish()
        syncNow(.queued)
    }

    func pending(for record: OfflineRecordKey) -> [QueueEntry] {
        guard let userID else { return [] }
        return actions.entries().filter { $0.userID == userID && $0.record == record && ($0.isUnsent || $0.state == .needsYou) && $0.operation != nil }
    }

    func shown(for record: OfflineRecordKey) -> [QueueEntry] {
        guard let userID else { return [] }
        return actions.entries().filter { $0.userID == userID && $0.record == record && $0.operation != nil }
    }

    func undo(_ localID: Int64) {
        guard let userID else { return }
        var mustSendVoid = false
        actions.change(localID) { entry in
            guard entry.userID == userID, entry.state == .waiting else { return entry }
            guard entry.sequence != nil else { return nil }
            mustSendVoid = true
            var voided = entry
            voided.state = .void
            return voided
        }
        publish()
        if mustSendVoid {
            syncNow(.queued)
        }
    }

    func downloaded(_ record: OfflineRecordKey) -> DownloadedRecord? {
        guard let userID else { return nil }
        return downloads.read(userID: userID, record: record)
    }

    func syncNow(_ reason: SyncReason) {
        switch reason {
        case .manual, .queued, .clockOut:
            run(maxBatches: Self.foregroundBatches)
        case .foreground, .reconnected, .periodic:
            if hasWorkToSettle {
                run(maxBatches: Self.foregroundBatches)
            }
        }
    }

    func flush(_ record: OfflineRecordKey) async -> Bool {
        guard let userID else { return false }
        if isOnline, hasPendingSend(userID: userID, record: record) {
            await runTask?.value
            let outcome = await engine.run(maxBatches: Self.flushBatches) { [actions] in
                !actions.entries().contains { $0.userID == userID && $0.record == record && $0.isPendingSend }
            }
            finish(outcome)
        }
        return !actions.entries().contains { entry in
            entry.userID == userID && entry.record == record && entry.operation != nil && (entry.isUnsent || entry.state == .needsYou)
        }
    }

    func newClientRequestID() -> String {
        UUID().uuidString.lowercased()
    }

    private var hasWorkToSettle: Bool {
        guard let userID else { return false }
        return actions.entries().contains { $0.userID == userID && ($0.isPendingSend || $0.state == .needsYou) }
    }

    private func hasPendingSend(userID: Int, record: OfflineRecordKey) -> Bool {
        actions.entries().contains { $0.userID == userID && $0.record == record && $0.isPendingSend }
    }

    private func run(maxBatches: Int) {
        guard isOnline, userID != nil, runTask == nil else { return }
        isSyncing = true
        runTask = Task { [engine] in
            let outcome = await engine.run(maxBatches: maxBatches)
            OfflineWork.shared.finish(outcome)
            OfflineWork.shared.runTask = nil
            OfflineWork.shared.isSyncing = false
            if outcome.status == .complete, outcome.hasMore {
                OfflineWork.shared.syncNow(.queued)
            }
        }
    }

    private func finish(_ outcome: SyncRunOutcome) {
        lastSyncAt = Date()
        lastRunNeedingYou = outcome.newlyNeedingYou
        publish()
        if !outcome.changed.isEmpty {
            changeHandlers.forEach { $0(outcome.changed) }
        }
        if outcome.status == .retry {
            scheduleRetry()
        }
    }

    private func scheduleRetry() {
        retryTask?.cancel()
        retryTask = Task {
            try? await Task.sleep(for: Self.retryDelay)
            guard !Task.isCancelled else { return }
            OfflineWork.shared.syncNow(.periodic)
        }
    }

    private func setOnline(_ online: Bool) {
        guard online != isOnline else { return }
        isOnline = online
        offlineSince = online ? nil : Date()
        if online {
            sendWaitingNotices()
            syncNow(.reconnected)
        }
    }

    private func publish() {
        let own = actions.entries().filter { $0.userID == userID && $0.operation != nil }
        queue = own.filter { $0.state != .void }.sorted { ($0.performedAt, $0.localID) < ($1.performedAt, $1.localID) }
        waitingCount = own.filter(\.isUnsent).count
        needsYouCount = own.filter { $0.state == .needsYou }.count
        queueRevision += 1
        queueHandlers.forEach { $0() }
        keepPeriodic(waitingCount > 0)
    }

    private func keepPeriodic(_ needed: Bool) {
        if !needed {
            periodicTask?.cancel()
            periodicTask = nil
            return
        }
        guard periodicTask == nil else { return }
        periodicTask = Task {
            while !Task.isCancelled {
                try? await Task.sleep(for: Self.periodicInterval)
                guard !Task.isCancelled else { return }
                OfflineWork.shared.syncNow(.periodic)
            }
        }
    }

    private func prefetch(_ request: DownloadPrefetch) {
        guard prefetchTask == nil else { return }
        prefetchTask = Task { [downloads, api, sessions] in
            let freshFrom = Date().addingTimeInterval(-Self.freshFor)
            for (record, warehouseID) in request.records {
                guard sessions.userID(forToken: request.token) == request.userID else { break }
                if let downloadedAt = downloads.downloadedAt(userID: request.userID, record: record), downloadedAt > freshFrom {
                    continue
                }
                guard await api.refetch(path: OfflineRoutes.detailPath(record, warehouseID: warehouseID)) else { break }
            }
            OfflineWork.shared.prefetchTask = nil
        }
    }

    private func sendWaitingNotices() {
        guard let session = sessions.current, noticesTask == nil else { return }
        let sessionKey = session.token.components(separatedBy: "|").first ?? session.token
        guard device.noticesSent(userID: session.userID) != sessionKey else { return }
        let waitingByUser = Dictionary(grouping: actions.entries().filter { $0.userID != session.userID && $0.operation != nil && $0.isUnsent }, by: \.userID)
        noticesTask = Task { [api, device] in
            var complete = true
            for (userID, waiting) in waitingByUser {
                guard let oldest = waiting.map(\.performedAt).min() else { continue }
                if await api.waitingNotice(userID: userID, count: waiting.count, oldestPerformedAt: oldest) == .unreachable {
                    complete = false
                }
            }
            if complete {
                device.markNoticesSent(userID: session.userID, sessionKey: sessionKey)
            }
            OfflineWork.shared.noticesTask = nil
        }
    }

    private static let lastUserKey = "aiku.offline.lastUserID"
    private static let foregroundBatches = 20
    private static let flushBatches = 20
    private static let retryDelay: Duration = .seconds(30)
    private static let periodicInterval: Duration = .seconds(120)
    private static let freshFor: TimeInterval = 60
}
